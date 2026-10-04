<?php

namespace App\Console\Commands;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Opening\OpeningBalanceService;
use App\Services\Operations\Exceptions\OperationException;
use App\Services\Operations\TransactionalOperationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class ImportOpeningBalancesCommand extends Command
{
    protected $signature = 'app:import-opening-balances 
                            {--file= : Boshlang\'ich qoldiqlar JSON fayli yo\'li}
                            {--dry-run : Bazaga yozmasdan tekshirish va oldindan ko\'rish (Validation & Mapping)}
                            {--generate-template : Namuna import shablonini (JSON) yaratish}';

    protected $description = 'Owner tomonidan berilgan haqiqiy boshlang\'ich hisoblar va qoldiqlarni xavfsiz va idempotent import qilish';

    public function handle(OpeningBalanceService $openingService): int
    {
        $this->info('================================================================================');
        $this->info("   AQUAOPTOM CRM — RASMIY BOSHLANG'ICH QOLDIQLARNI IMPORT QILISH (PROMPT 25)");
        $this->info('================================================================================');

        // 1. Namuna shablon generatsiyasi
        if ($this->option('generate-template')) {
            $templatePath = storage_path('app/opening_balances_template.json');
            File::ensureDirectoryExists(dirname($templatePath));

            $sample = [
                'import_id' => (string) Str::uuid(),
                'metadata' => [
                    'organization' => 'AquaOptom Do\'koni',
                    'created_at' => Carbon::now()->toIso8601String(),
                    'currency' => 'UZS',
                    'unit_rule' => 'FAAQAT BUTUN DONA',
                ],
                'cash_accounts' => [
                    ['account_type' => 'CASH', 'name' => 'Asosiy Naqd Kassa', 'amount' => 5000000],
                    ['account_type' => 'CARD', 'name' => 'Humo / Uzcard Terminal', 'amount' => 2000000],
                    ['account_type' => 'BANK', 'name' => 'Bank Hisob-Raqami', 'amount' => 15000000],
                ],
                'inventory' => [
                    [
                        'product_name' => 'Fanta',
                        'volume_ml' => 500,
                        'quantity_units' => 500,
                        'unit_cost' => 5000,
                        'sale_price' => 7000,
                    ],
                    [
                        'product_name' => 'Coca-Cola',
                        'volume_ml' => 1500,
                        'quantity_units' => 300,
                        'unit_cost' => 10000,
                        'sale_price' => 13000,
                    ],
                ],
                'customers' => [
                    [
                        'name' => 'Akmal (Bahor Market)',
                        'phone' => '+998901234567',
                        'address' => 'Chilonzor 9, Toshkent',
                        'signed_debt' => 1500000, // Musbat = do'konga qarzi bor, Manfiy = avans
                    ],
                ],
                'suppliers' => [
                    [
                        'name' => 'Coca-Cola Bottlers Uzbekistan',
                        'phone' => '+998712000000',
                        'signed_liability' => 4500000, // Musbat = do'konning qarzi bor
                    ],
                ],
            ];

            File::put($templatePath, json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->info("Namuna shablon yaratildi: {$templatePath}");
            $this->line("Ushbu faylni to'ldiring va '--file={$templatePath}' parametri bilan ishga tushiring.");

            return self::SUCCESS;
        }

        $filePath = $this->option('file');
        if (! $filePath || ! File::exists($filePath)) {
            $this->error("Fayl ko'rsatilmadi yoki topilmadi: {$filePath}");
            $this->comment("Namuna shablon olish uchun buyruqni '--generate-template' bilan chaqiring.");

            return self::FAILURE;
        }

        $content = File::get($filePath);
        $data = json_decode($content, true);
        if (! is_array($data)) {
            $this->error("JSON fayli formati noto'g'ri!");

            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $validator = Validator::make($data, [
            'import_id' => 'required|uuid',
            'cash_accounts' => 'sometimes|array', 'inventory' => 'sometimes|array',
            'customers' => 'sometimes|array', 'suppliers' => 'sometimes|array',
            'cash_accounts.*.account_type' => 'required|in:CASH,CARD,BANK|distinct',
            'cash_accounts.*.name' => 'required|string|max:255',
            'cash_accounts.*.amount' => 'required|integer|min:0|max:1000000000000000',
            'inventory.*.product_name' => 'required|string|max:255',
            'inventory.*.volume_ml' => 'required|integer|min:1|max:1000000',
            'inventory.*.quantity_units' => 'required|integer|min:1|max:2147483647',
            'inventory.*.unit_cost' => 'required|integer|min:0|max:1000000000',
            'inventory.*.sale_price' => 'required|integer|min:1|max:1000000000',
            'customers.*.name' => 'required|string|max:255', 'customers.*.phone' => 'nullable|string|max:32',
            'customers.*.signed_debt' => 'required|integer|between:-1000000000000000,1000000000000000',
            'suppliers.*.name' => 'required|string|max:255', 'suppliers.*.phone' => 'nullable|string|max:32',
            'suppliers.*.signed_liability' => 'required|integer|between:-1000000000000000,1000000000000000',
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        foreach (['inventory', 'customers', 'suppliers'] as $section) {
            $keys = [];
            foreach ($data[$section] ?? [] as $row) {
                $key = $section === 'inventory'
                    ? Product::normalizeName($row['product_name']).':'.$row['volume_ml']
                    : Product::normalizeName($row['name']).':'.($row['phone'] ?? '');
                if (isset($keys[$key])) {
                    $this->error('Importda takrorlangan qator: '.$section);

                    return self::FAILURE;
                }
                $keys[$key] = true;
            }
        }

        if ($isDryRun) {
            return $this->importRows($data, $openingService, true);
        }
        try {
            app(TransactionalOperationService::class)->execute(
                $data['import_id'], 'IMPORT_OPENING_BALANCES', $data,
                function () use ($data, $openingService) {
                    $this->importRows($data, $openingService, false);

                    return ['imported' => true];
                }
            );

            return self::SUCCESS;
        } catch (OperationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function importRows(array $data, OpeningBalanceService $openingService, bool $isDryRun): int
    {
        if ($isDryRun) {
            $this->warn(">>> DIQQAT: SINOV REJIMI (DRY-RUN) FAOL. BAZAGA HECH QANDAY O'ZGARISH YOZILMAYDI. <<<\n");
        }

        $defaultWarehouse = Warehouse::where('is_default', true)->first() ?? Warehouse::first();
        if (! $defaultWarehouse && ! $isDryRun) {
            $defaultWarehouse = Warehouse::create(['name' => 'Asosiy Ombor', 'is_default' => true]);
        }

        // 2. Kassa Hisoblari
        $cashData = $data['cash_accounts'] ?? [];
        $totalCash = 0;
        $this->line('[1/4] Kassa hisoblari tekshirilmoqda ('.count($cashData).' ta)...');
        foreach ($cashData as $c) {
            $amount = (int) ($c['amount'] ?? 0);
            $totalCash += $amount;
            $this->line("  - {$c['name']} ({$c['account_type']}): ".number_format($amount)." so'm");

            if (! $isDryRun) {
                $acc = CashAccount::firstOrCreate(
                    ['type' => $c['account_type']],
                    ['name' => $c['name'], 'balance' => 0]
                );
                $opId = (string) Uuid::uuid5($data['import_id'], 'cash:'.$c['account_type']);
                $openingService->recordCashOpening($acc->id, $amount, $opId);
            }
        }

        // 3. Ombor Qoldiqlari
        $invData = $data['inventory'] ?? [];
        $totalStockUnits = 0;
        $totalStockValue = 0;
        $this->line("\n[2/4] Ombor tovarlari tekshirilmoqda (".count($invData).' ta)...');
        foreach ($invData as $item) {
            $qty = (int) ($item['quantity_units'] ?? 0);
            $cost = (int) ($item['unit_cost'] ?? 0);
            $price = (int) ($item['sale_price'] ?? 0);
            $valMl = (int) ($item['volume_ml'] ?? 500);
            $volName = ($valMl >= 1000 ? ($valMl / 1000).' L' : ($valMl / 1000).' L');

            if ($qty < 0 || $cost < 0) {
                $this->error("  Xatolik: Miqdor yoki tannarx manfiy bo'lishi mumkin emas: {$item['product_name']}");

                return self::FAILURE;
            }

            $lineVal = $qty * $cost;
            $totalStockUnits += $qty;
            $totalStockValue += $lineVal;
            $this->line("  - {$item['product_name']} {$volName}: {$qty} dona @ ".number_format($cost)." so'm (Jami: ".number_format($lineVal)." so'm)");

            if (! $isDryRun) {
                $prod = Product::firstOrCreate(
                    ['normalized_name' => Product::normalizeName($item['product_name'])],
                    ['name' => $item['product_name'], 'code' => Product::generateCode(), 'status' => 'ACTIVE']
                );
                $vol = Volume::firstOrCreate(['value_ml' => $valMl], ['name' => $volName]);
                $variant = ProductVariant::firstOrCreate(
                    ['product_id' => $prod->id, 'volume_id' => $vol->id],
                    [
                        'sku' => ProductVariant::generateSku($prod, $vol),
                        'default_sale_price' => $price,
                        'minimum_stock' => 50,
                        'status' => 'ACTIVE',
                    ]
                );

                $opId = (string) Uuid::uuid5($data['import_id'], 'stock:'.$variant->id);
                $openingService->recordStockOpening(
                    productVariantId: $variant->id,
                    quantity: $qty,
                    unitCost: $cost,
                    operationId: $opId,
                    warehouseId: $defaultWarehouse->id
                );
            }
        }

        // 4. Xaridorlar va Qarzdorlik
        $custData = $data['customers'] ?? [];
        $totalCustDebt = 0;
        $this->line("\n[3/4] Xaridorlar tekshirilmoqda (".count($custData).' ta)...');
        foreach ($custData as $cust) {
            $debt = (int) ($cust['signed_debt'] ?? 0);
            $totalCustDebt += $debt;
            $this->line("  - {$cust['name']} ({$cust['phone']}): Balans: ".number_format($debt)." so'm");

            if (! $isDryRun) {
                $cModel = Customer::firstOrCreate(
                    ['name' => $cust['name'], 'phone' => $cust['phone'] ?? null],
                    ['name' => $cust['name'], 'address' => $cust['address'] ?? null, 'current_debt' => 0]
                );
                $opId = (string) Uuid::uuid5($data['import_id'], 'customer:'.$cModel->id);
                $openingService->recordCustomerOpening($cModel->id, $debt, $opId);
            }
        }

        // 5. Ta'minotchilar va Majburiyat
        $suppData = $data['suppliers'] ?? [];
        $totalSuppLiability = 0;
        $this->line("\n[4/4] Ta'minotchilar tekshirilmoqda (".count($suppData).' ta)...');
        foreach ($suppData as $supp) {
            $liab = (int) ($supp['signed_liability'] ?? 0);
            $totalSuppLiability += $liab;
            $this->line("  - {$supp['name']} ({$supp['phone']}): Majburiyat: ".number_format($liab)." so'm");

            if (! $isDryRun) {
                $sModel = Supplier::firstOrCreate(
                    ['name' => $supp['name']],
                    ['phone' => $supp['phone'] ?? null, 'balance' => 0]
                );
                $opId = (string) Uuid::uuid5($data['import_id'], 'supplier:'.$sModel->id);
                $openingService->recordSupplierOpening($sModel->id, $liab, $opId);
            }
        }

        // Yakuniy Xulosa
        $this->info("\n================================================================================");
        $this->info('   IMPORT XULOSASI ('.($isDryRun ? "DRY-RUN TEKSHIRUVI O'TDI" : 'MUVAFFAQIYATLI IMPORT QILINDI').')');
        $this->info('================================================================================');
        $this->table(
            ['Ko\'rsatkich', 'Miqdor / Summa'],
            [
                ['Kassa Boshlang\'ich Mablag\'i', number_format($totalCash)." so'm"],
                ['Ombor Tovarlari Donasi', number_format($totalStockUnits).' dona'],
                ['Ombor Jami Tannarx Qiymati', number_format($totalStockValue)." so'm"],
                ['Mijozlar Boshlang\'ich Qarzi', number_format($totalCustDebt)." so'm"],
                ['Ta\'minotchilar Oldidagi Majburiyat', number_format($totalSuppLiability)." so'm"],
            ]
        );

        return self::SUCCESS;
    }
}

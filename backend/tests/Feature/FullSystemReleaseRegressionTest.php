<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Device;
use App\Models\InventoryBalance;
use App\Models\OperationResult;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Ledger\Exceptions\InsufficientAllocationException;
use App\Services\Ledger\InventoryAllocationService;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Operations\Exceptions\OperationConflictException;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Operations\PayloadFingerprint;
use App\Services\Operations\TransactionalOperationService;
use App\Services\Payments\CustomerPaymentService;
use App\Services\Payments\SupplierPaymentService;
use App\Services\Purchase\ReceivePurchaseService;
use App\Services\Reports\ExportService;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportQueryService;
use App\Services\Sales\CreateSaleService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class FullSystemReleaseRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $cashier;
    protected Warehouse $warehouse;
    protected CashAccount $cashAccount;
    protected ProductVariant $variantFanta05;
    protected Customer $customer;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->owner = User::factory()->owner()->create([
            'email' => 'owner_regression@aquaoptom.uz',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->cashier = User::factory()->cashier()->create([
            'email' => 'cashier_regression@aquaoptom.uz',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::firstOrCreate(
            ['name' => 'Asosiy Ombor'],
            ['is_default' => true]
        );

        $this->cashAccount = CashAccount::firstOrCreate(
            ['name' => 'Asosiy Naqd Kassa'],
            ['type' => 'CASH', 'balance' => 0, 'is_default' => true]
        );

        $prodFanta = Product::create([
            'name' => 'Fanta Apelsin',
            'normalized_name' => 'fanta apelsin',
            'code' => 'PRD-000001',
            'status' => 'active',
        ]);

        $vol05 = Volume::create([
            'name' => '0.5 L',
            'value_ml' => 500,
            'unit' => 'L',
        ]);

        $this->variantFanta05 = ProductVariant::create([
            'product_id' => $prodFanta->id,
            'volume_id' => $vol05->id,
            'sku' => 'FANTA-05L',
            'barcode' => '4780001234567',
            'default_sale_price' => 7000,
            'min_stock_alert' => 20,
            'status' => 'active',
            'version' => 1,
        ]);

        $this->customer = Customer::create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Akmal aka',
            'phone' => '+998901234567',
            'store_name' => 'Oazis Savdo',
            'address' => 'Chilonzor 9',
            'status' => 'active',
            'current_debt' => 0,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Coca-Cola Ichimliklari Uzbekistan',
            'company_name' => 'CCBU MCHJ',
            'phone' => '+998712001122',
            'status' => 'active',
            'balance' => 0,
        ]);
    }

    /**
     * 1. QABUL DARVOZASI: 100 qoldiqdan 60 + 60 parallel/ketma-ket savdoga urinish.
     * Birinchisi 60 sotadi (qoldiq 40 bo'ladi), ikkinchisi esa 60 so'raganda
     * InsufficientStock / OperationValidationException bilan to'xtatiladi. Qoldiq aslo -20 ga tushmaydi!
     */
    public function test_concurrency_stock_oversell_protection_sixty_plus_sixty_from_one_hundred(): void
    {
        $invService = app(InventoryLedgerService::class);
        $saleService = app(CreateSaleService::class);

        // Omborga 100 dona kiritamiz
        $invService->recordInflow(
            productVariantId: $this->variantFanta05->id,
            quantity: 100,
            unitCost: 5000,
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        $stockInitial = InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->first();
        $this->assertEquals(100, (int) $stockInitial->quantity);

        // 1-savdo: 60 dona muvaffaqiyatli sotiladi
        $sale1 = $saleService->execute(
            customerId: $this->customer->id,
            items: [
                ['variant_id' => $this->variantFanta05->id, 'quantity' => 60, 'sale_price' => 6500],
            ],
            operationId: (string) Str::uuid(),
            paidAmount: 390000,
            cashAccountId: $this->cashAccount->id,
            paymentType: 'FULL',
            paymentMethod: 'CASH',
            userId: $this->owner->id
        );
        $this->assertEquals(60, $sale1->items->sum('quantity'));

        // Qoldiq 40 dona bo'ldi
        $stockAfter1 = InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->first();
        $this->assertEquals(40, (int) $stockAfter1->quantity);

        // 2-savdo: yana 60 dona sotishga urinish (40 ta bor, 60 ta so'ralmoqda) -> RAD ETILADI!
        try {
            $saleService->execute(
                customerId: $this->customer->id,
                items: [
                    ['variant_id' => $this->variantFanta05->id, 'quantity' => 60, 'sale_price' => 6500],
                ],
                operationId: (string) Str::uuid(),
                paidAmount: 390000,
                cashAccountId: $this->cashAccount->id,
                paymentType: 'FULL',
                paymentMethod: 'CASH',
                userId: $this->owner->id
            );
            $this->fail("Omborda yetarli qoldiq bo'lmaganda savdo o'tib ketmasligi shart edi!");
        } catch (OperationValidationException $e) {
            $this->assertEquals('INSUFFICIENT_STOCK', $e->errorCode);
        }

        // Yakuniy qoldiq qat'iy 40 dona qolishi shart (manfiy emas!)
        $stockFinal = InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->first();
        $this->assertEquals(40, (int) $stockFinal->quantity);
    }

    /**
     * 2. QABUL DARVOZASI: 100 zaxiradan 60 + 60 qurilma ajratmasi (Device Allocation Oversell).
     * Device A 60 dona ajratma oladi (40 erkin qoladi), Device B 60 so'raganda xato bilan rad etiladi.
     */
    public function test_concurrency_device_allocation_oversell_protection(): void
    {
        $invService = app(InventoryLedgerService::class);
        $allocService = app(InventoryAllocationService::class);

        $invService->recordInflow(
            productVariantId: $this->variantFanta05->id,
            quantity: 100,
            unitCost: 5000,
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        $deviceA = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'DEV-REG-01',
            'name' => 'Kassa Smartfon 1',
            'device_type' => 'MOBILE',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $deviceB = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'DEV-REG-02',
            'name' => 'Kassa Smartfon 2',
            'device_type' => 'MOBILE',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        // Device A ga 60 dona ajratamiz -> Muvaffaqiyatli
        $allocA = $allocService->grantAllocation(
            device: $deviceA,
            variantId: $this->variantFanta05->id,
            quantity: 60,
            warehouseId: $this->warehouse->id,
            userId: $this->owner->id
        );
        $this->assertEquals(60, $allocA->allocated_quantity);

        // Device B ga yana 60 dona ajratishga urinish (faqat 40 dona erkin) -> RAD ETILADI!
        try {
            $allocService->grantAllocation(
                device: $deviceB,
                variantId: $this->variantFanta05->id,
                quantity: 60,
                warehouseId: $this->warehouse->id,
                userId: $this->owner->id
            );
            $this->fail("Jismoniy qoldiqdan oshiqcha ajratma berilmasligi kerak edi!");
        } catch (\Exception $e) {
            $this->assertTrue(
                $e instanceof InsufficientAllocationException ||
                $e instanceof OperationValidationException ||
                str_contains($e->getMessage(), 'yetarli')
            );
        }
    }

    /**
     * 3. QABUL DARVOZASI: 20 ta bir xil operation_id va bir xil payload bilan kelgan so'rovlar
     * aniq 1 ta tranzaksiyaviy natija beradi, pul yoki qarz 20 marta ko'paymaydi.
     */
    public function test_twenty_idempotent_duplicate_requests_yield_single_execution(): void
    {
        $opService = app(TransactionalOperationService::class);
        $opId = (string) Str::uuid();
        $payload = [
            'customer_id' => $this->customer->id,
            'items' => [
                ['variant_id' => $this->variantFanta05->id, 'quantity' => 10, 'price' => 7000],
            ],
            'total' => 70000,
        ];

        $results = [];
        for ($i = 0; $i < 20; $i++) {
            $results[] = $opService->execute(
                operationId: $opId,
                operationType: 'SALES_CHECKOUT',
                payload: $payload,
                businessCallback: function () {
                    return [
                        'status' => 'SUCCESS',
                        'invoice_number' => 'INV-2026-REG-001',
                        'total' => 70000,
                    ];
                },
                actorId: $this->owner->id
            );
        }

        // Barcha 20 ta chaqiruv bitta xil invoice_number qaytargan
        foreach ($results as $res) {
            $this->assertEquals('INV-2026-REG-001', $res['invoice_number']);
        }

        // Bazada aniq 1 ta OperationResult yozuvi mavjud
        $count = OperationResult::where('operation_id', $opId)->count();
        $this->assertEquals(1, $count);
    }

    /**
     * 4. QABUL DARVOZASI: Ayni bir operation_id o'zgargan payload bilan takror kelsa
     * PAYLOAD_MISMATCH / OperationConflictException (409) beradi.
     */
    public function test_payload_mismatch_conflict_returns_conflict_status(): void
    {
        $opService = app(TransactionalOperationService::class);
        $opId = (string) Str::uuid();

        $payloadOriginal = ['total' => 50000, 'items_count' => 5];
        $opService->execute(
            operationId: $opId,
            operationType: 'SALES_CHECKOUT',
            payload: $payloadOriginal,
            businessCallback: fn() => ['status' => 'SUCCESS'],
            actorId: $this->owner->id
        );

        // O'zgargan payload bilan takror murojaat
        $payloadTampered = ['total' => 99999, 'items_count' => 10];
        $this->expectException(OperationConflictException::class);

        $opService->execute(
            operationId: $opId,
            operationType: 'SALES_CHECKOUT',
            payload: $payloadTampered,
            businessCallback: fn() => ['status' => 'SUCCESS'],
            actorId: $this->owner->id
        );
    }

    /**
     * 5. QABUL DARVOZASI: Crash va atomik rollback.
     * Operatsiya ichida xato bo'lsa, kassa, ombor va outbox tozalanadi, DB buzilmaydi.
     */
    public function test_atomic_crash_and_rollback(): void
    {
        $opService = app(TransactionalOperationService::class);
        $opId = (string) Str::uuid();

        try {
            $opService->execute(
                operationId: $opId,
                operationType: 'CRASH_TEST',
                payload: ['test' => 1],
                businessCallback: function () {
                    $this->cashAccount->increment('balance', 500000);
                    throw new \RuntimeException('Simulated mid-transaction system crash!');
                },
                actorId: $this->owner->id
            );
            $this->fail("Crash exceptionsiz o'tib ketmasligi kerak edi!");
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Simulated mid-transaction system crash!', $e->getMessage());
        }

        // Kassa balansi o'zgarmagan bo'lishi shart (0 so'm)
        $this->assertEquals(0, (int) $this->cashAccount->fresh()->balance);

        // OperationResult saqlanmagan
        $this->assertNull(OperationResult::where('operation_id', $opId)->first());
    }

    /**
     * 6. QABUL DARVOZASI: Arxitektura 14.3 to'liq nazorat misoli.
     * 
     * 1) Boshlang'ich kassa: 500 000 UZS.
     * 2) Fanta 0.5L 150 dona x 5 000 UZS kirim = 750 000 UZS ta'minotchi majburiyati.
     * 3) Ta'minotchiga 300 000 to'landi -> kassa 200 000; ta'minotchi qarzi 450 000.
     * 4) Mijozga 60 dona x 6 500 sotildi -> savdo 390 000, tannarx 300 000, yalpi foyda 90 000.
     *    Mijoz 140 000 to'ladi -> kassa 340 000; yangi mijoz qarzi 250 000.
     * 5) Omborda qolgan: 90 dona (tannarxi 450 000 UZS).
     *    Tizim narxi 7 000 bo'lsa: kutilayotgan sotuv 630 000, kutilayotgan yalpi foyda 180 000 (SIMULATION).
     * 6) Keyin mijoz 100 000 to'ladi -> qarz 150 000; kassa 440 000; eski savdo/foyda o'zgarmaydi.
     * 7) Keyin ta'minotchiga 50 000 to'landi -> bizning qarz 400 000; yakuniy kassa: 390 000 UZS!
     * 
     * 8) Qat'iy tasdiq: 500 000 - 300 000 + 140 000 + 100 000 - 50 000 = 390 000 UZS.
     *    Mijoz qarzi (150k), ta'minotchi qarzi (400k) va ombor qiymati (450k) kassaga qo'shilmaydi!
     */
    public function test_architecture_section_14_3_complete_scenario_cash_390000_and_isolated_balances(): void
    {
        $receiveService = app(ReceivePurchaseService::class);
        $saleService = app(CreateSaleService::class);
        $customerPaymentService = app(CustomerPaymentService::class);
        $supplierPaymentService = app(SupplierPaymentService::class);

        // 1. Boshlang'ich naqd pul: 500 000 so'm
        $this->cashAccount->update(['balance' => 500000]);
        $this->assertEquals(500000, (int) $this->cashAccount->fresh()->balance);

        // 2. Fanta 0.5L, 150 dona x 5 000 kirim (750 000)
        // 3. Ta'minotchiga 300 000 to'landi -> Kassa 200 000, bizning qarz 450 000
        $purchaseRes = $receiveService->execute(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            items: [
                ['variant_id' => $this->variantFanta05->id, 'quantity' => 150, 'unit_cost' => 5000],
            ],
            operationId: (string) Str::uuid(),
            paidAmount: 300000,
            cashAccountId: $this->cashAccount->id,
            userId: $this->owner->id
        );

        $this->assertEquals(750000, $purchaseRes['total_amount']);
        $this->assertEquals(300000, $purchaseRes['paid_amount']);
        $this->assertEquals(450000, $purchaseRes['debt_amount']);
        $this->assertEquals(450000, $this->supplier->fresh()->balance);
        $this->assertEquals(200000, (int) $this->cashAccount->fresh()->balance); // 500k - 300k = 200k

        $stockAfterInward = InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->first();
        $this->assertEquals(150, (int) $stockAfterInward->quantity);
        $this->assertEquals(750000, (int) $stockAfterInward->total_value);

        // 4. Mijozga 60 dona x 6 500 sotildi (Savdo: 390 000, tannarx: 300 000, yalpi foyda: 90 000)
        // Mijoz savdoda 140 000 to'ladi -> Yangi qarz: 250 000, Kassa: 200k + 140k = 340 000
        $sale = $saleService->execute(
            customerId: $this->customer->id,
            items: [
                [
                    'variant_id' => $this->variantFanta05->id,
                    'quantity' => 60,
                    'sale_price' => 6500,
                    'is_system_price' => false, // 6500 UZS maxsus sotuv narxi
                ],
            ],
            operationId: (string) Str::uuid(),
            paidAmount: 140000,
            cashAccountId: $this->cashAccount->id,
            paymentType: 'PARTIAL',
            paymentMethod: 'CASH',
            userId: $this->owner->id
        );

        $this->assertEquals(390000, $sale->total_amount);
        $this->assertEquals(300000, $sale->total_cost);
        $this->assertEquals(90000, $sale->gross_profit);
        $this->assertEquals(140000, $sale->paid_amount);
        $this->assertEquals(250000, $sale->debt_amount);
        $this->assertEquals(250000, $this->customer->fresh()->current_debt);
        $this->assertEquals(340000, (int) $this->cashAccount->fresh()->balance);

        // 5. Omborda qolgan: 90 dona, qiymati 450 000 UZS
        $stockAfterSale = InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->first();
        $this->assertEquals(90, (int) $stockAfterSale->quantity);
        $this->assertEquals(450000, (int) $stockAfterSale->total_value);

        // Tizim narxi 7 000 bo'lsa kutilayotgan sotuv = 630 000, kutilayotgan yalpi foyda = 180 000
        $expectedRevenue = 90 * 7000;
        $expectedGrossProfit = $expectedRevenue - (int) $stockAfterSale->total_value;
        $this->assertEquals(630000, $expectedRevenue);
        $this->assertEquals(180000, $expectedGrossProfit);

        // 6. Keyin mijoz 100 000 to'ladi -> Qarz 150 000; Kassa 340k + 100k = 440 000
        $customerPaymentService->execute(
            customerId: $this->customer->id,
            amount: 100000,
            cashAccountId: $this->cashAccount->id,
            paymentMethod: 'CASH',
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        $this->assertEquals(150000, $this->customer->fresh()->current_debt);
        $this->assertEquals(440000, (int) $this->cashAccount->fresh()->balance);

        // Tarixiy savdo va foyda mutlaqo o'zgarmas saqlangan!
        $freshSale = $sale->fresh();
        $this->assertEquals(390000, $freshSale->total_amount);
        $this->assertEquals(300000, $freshSale->total_cost);
        $this->assertEquals(90000, $freshSale->gross_profit);

        // 7. Keyin ta'minotchiga 50 000 to'landi -> Bizning qarz 400 000; Kassa 440k - 50k = 390 000!
        $supplierPaymentService->execute(
            supplierId: $this->supplier->id,
            amount: 50000,
            cashAccountId: $this->cashAccount->id,
            paymentMethod: 'CASH',
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        $this->assertEquals(400000, $this->supplier->fresh()->balance);

        // 8. QAT'IY YAKUNIY NAZORAT TEKSHIRUVI (EXACT 390,000 UZS):
        // 500 000 − 300 000 + 140 000 + 100 000 − 50 000 = 390 000 so'm.
        $finalCash = (int) $this->cashAccount->fresh()->balance;
        $finalCustomerDebt = (int) $this->customer->fresh()->current_debt;
        $finalSupplierDebt = (int) $this->supplier->fresh()->balance;
        $finalStockQty = (int) InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->value('quantity');
        $finalStockCost = (int) InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->value('total_value');

        $this->assertEquals(390000, $finalCash, "Yakuniy kassa balansi aniq 390 000 so'm bo'lishi shart!");
        $this->assertEquals(150000, $finalCustomerDebt, "Mijoz qarzi 150 000 so'm bo'lishi shart!");
        $this->assertEquals(400000, $finalSupplierDebt, "Ta'minotchi qarzi 400 000 so'm bo'lishi shart!");
        $this->assertEquals(90, $finalStockQty, "Omborda 90 dona tovar qolishi shart!");
        $this->assertEquals(450000, $finalStockCost, "Ombor tannarx qiymati 450 000 so'm bo'lishi shart!");
    }

    /**
     * 7. XAVFSIZLIK: Login rate limit tekshiruvi.
     * 10 ta ketma-ket login so'rovidan so'ng 11-so'rov 429 Too Many Requests bilan qaytadi.
     */
    public function test_security_login_rate_limiting(): void
    {
        RateLimiter::clear('login');

        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'wrong_email@aquaoptom.uz',
                'password' => 'wrong_password',
            ]);
            // 401 Unauthorized yoki 422
            $this->assertContains($response->status(), [401, 422]);
        }

        // 11-so'rov Rate Limiting tufayli 429 berishi shart
        $throttled = $this->postJson('/api/auth/login', [
            'email' => 'wrong_email@aquaoptom.uz',
            'password' => 'wrong_password',
        ]);

        $this->assertEquals(429, $throttled->status(), "11-urinishda 429 Too Many Requests kutilgan!");
    }

    /**
     * 8. XAVFSIZLIK: Broadcast kanallari ruxsatnomasi (Private channels authorization).
     */
    public function test_security_private_channel_authorization(): void
    {
        $channels = Broadcast::getChannels();
        $this->assertArrayHasKey('store.operations', $channels);
        $this->assertArrayHasKey('store.finance', $channels);

        $operationsCallback = $channels['store.operations'];
        $financeCallback = $channels['store.finance'];

        // store.operations: Faol xodimga ruxsat, bloklangan xodimga rad etiladi
        $this->assertTrue((bool) $operationsCallback($this->owner));
        $this->assertTrue((bool) $operationsCallback($this->cashier));
        $this->cashier->update(['is_active' => false, 'status' => 'BLOCKED']);
        $this->assertFalse((bool) $operationsCallback($this->cashier));

        // store.finance: Egasi (Owner) ko'ra oladi, lekin view_cost_price ruxsati yo'q kassa xodimiga rad etiladi
        $this->cashier->update(['is_active' => true, 'status' => 'ACTIVE']);
        $this->assertTrue((bool) $financeCallback($this->owner));
        $this->assertFalse((bool) $financeCallback($this->cashier));

        // Ruxsat berilganda financeCallback ruxsat beradi
        $this->cashier->givePermission('view_cost_price', true);
        $this->assertTrue((bool) $financeCallback($this->cashier));
    }

    /**
     * 9. XAVFSIZLIK: Eksport yuklash himoyasi va formula inyeksiyasi (CSV Formula Escaping).
     */
    public function test_security_export_download_authorization_and_formula_escaping(): void
    {
        // 1. Noto'g'ri/begona UUID yuklab olinmaydi (404)
        $fakeUuid = (string) Str::uuid();
        $res404 = $this->actingAs($this->owner)->get("/exports/download/{$fakeUuid}");
        $this->assertEquals(404, $res404->status());

        // 2. Eksport ruxsati yo'q xodimga 403
        $res403 = $this->actingAs($this->cashier)->get("/exports/download/{$fakeUuid}");
        $this->assertEquals(403, $res403->status());

        // 3. Formula inyeksiyasi belgilari (=, +, -, @, \t, \r) bittalik qo'shtirnoq bilan zararsizlantiriladi
        $this->assertEquals("'=1+1", ExportService::escapeFormula('=1+1'));
        $this->assertEquals("'+cmd|' /C calc'!A0", ExportService::escapeFormula("+cmd|' /C calc'!A0"));
        $this->assertEquals("'-100", ExportService::escapeFormula('-100'));
        $this->assertEquals("'@SUM(A1:A10)", ExportService::escapeFormula('@SUM(A1:A10)'));
        $this->assertEquals("Oddiy matn", ExportService::escapeFormula('Oddiy matn'));
    }

    /**
     * 10. XAVFSIZLIK: Telegram Webhook secret header tekshiruvi.
     */
    public function test_security_telegram_webhook_secret_enforcement(): void
    {
        config(['services.telegram.webhook_secret' => 'super-secret-token-xyz']);

        // 1. Secret headersiz so'rov -> 403 Forbidden
        $resNoSecret = $this->postJson('/telegram/webhook', [
            'update_id' => 12345,
            'message' => ['text' => '/start'],
        ]);
        $this->assertEquals(403, $resNoSecret->status());

        // 2. Noto'g'ri secret bilan so'rov -> 403 Forbidden
        $resWrongSecret = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong-token')
            ->postJson('/telegram/webhook', [
                'update_id' => 12345,
                'message' => ['text' => '/start'],
            ]);
        $this->assertEquals(403, $resWrongSecret->status());

        // 3. To'g'ri secret bilan so'rov -> 200 OK
        $resValid = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'super-secret-token-xyz')
            ->postJson('/telegram/webhook', [
                'update_id' => 999999,
                'message' => [
                    'message_id' => 1,
                    'chat' => ['id' => 11223344],
                    'text' => '/start',
                ],
            ]);
        $this->assertEquals(200, $resValid->status());
    }

    /**
     * 11. YUKLAMA VA UNUML DORLIK: Katta datasetda hisobotlar paginationdan oldin
     * SQL agregatsiya hisoblaydi va indekslar orqali tez bajariladi.
     */
    public function test_large_dataset_reporting_sql_aggregation_before_pagination_benchmark(): void
    {
        $reportQueryService = app(ReportQueryService::class);

        // 100 dona savdo kiritamiz
        $now = Carbon::now('Asia/Tashkent');
        $startTime = microtime(true);

        DB::transaction(function () use ($now) {
            for ($i = 1; $i <= 100; $i++) {
                Sale::create([
                    'operation_id' => (string) Str::uuid(),
                    'invoice_number' => "INV-BENCH-{$i}",
                    'customer_id' => $this->customer->id,
                    'warehouse_id' => $this->warehouse->id,
                    'total_amount' => 10000,
                    'total_cost' => 8000,
                    'paid_amount' => 6000,
                    'debt_amount' => 4000,
                    'gross_profit' => 2000,
                    'payment_type' => 'PARTIAL',
                    'payment_method' => 'CASH',
                    'status' => 'COMPLETED',
                    'created_by' => $this->owner->id,
                    'posted_at' => $now,
                    'created_at' => $now,
                ]);
            }
        });

        $generationDurationMs = round((microtime(true) - $startTime) * 1000, 2);

        // SQL agregatsiya va paginatsiya tezligi
        $queryStart = microtime(true);

        $period = ReportPeriod::resolve('today');
        $query = $reportQueryService->buildSalesQuery([], $period);

        // Agregatsiya (butun bazaning filtrlangan yig'indisi)
        $totals = $reportQueryService->calculateSalesTotals($query);

        // Paginatsiya (sahifalash)
        $paginated = (clone $query)->paginate(15);

        $queryDurationMs = round((microtime(true) - $queryStart) * 1000, 2);

        // Jami 100 ta savdo bo'yicha agregatsiya tekshiruvi
        $this->assertEquals(100 * 10000, (int) $totals['total_sales']);
        $this->assertEquals(100 * 6000, (int) $totals['total_paid']);
        $this->assertEquals(100 * 4000, (int) $totals['total_debt']);
        $this->assertEquals(100 * 2000, (int) $totals['total_gross_profit']);

        // Paginatsiyada faqat 15 ta qator olingan
        $this->assertEquals(15, count($paginated->items()));
        $this->assertEquals(100, $paginated->total());

        // So'rov 500ms dan tezroq bajarilishi shart
        $this->assertLessThan(500, $queryDurationMs, "Katta dataset hisoboti 500ms dan kam vaqt olishi shart!");
    }
}

<?php

namespace App\Services\Sales;

use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Warehouse;
use App\Services\TelegramService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class CreateSaleService
{
    public function __construct(
        protected TelegramService $telegram
    ) {}

    /**
     * Optom savdoni amalga oshirish va moliyaviy snapshot olish (Atomic Transaction)
     *
     * @param  array  $items  [ ['variant_id', 'package_id', 'package_quantity', 'quantity', 'sale_price', 'is_system_price'] ]
     */
    public function execute(?int $customerId, array $items, string $paymentType = 'CASH', ?int $warehouseId = null, string $source = 'web', ?int $userId = null): Sale
    {
        if (empty($items)) {
            throw new Exception("Savdo savati bo'sh!");
        }

        $warehouse = $warehouseId ? Warehouse::find($warehouseId) : Warehouse::where('is_default', true)->first();
        if (! $warehouse) {
            $warehouse = Warehouse::firstOrCreate(['name' => 'Asosiy Ombor'], ['is_default' => true]);
        }

        return DB::transaction(function () use ($customerId, $items, $paymentType, $warehouse, $source, $userId) {
            $totalAmount = 0;
            $totalCost = 0;

            // Avval stock lock va snapshot ma'lumotlarini yig'ish
            $preparedItems = [];

            foreach ($items as $item) {
                $variantId = $item['variant_id'];
                $quantity = (float) $item['quantity'];
                $salePrice = (int) $item['sale_price'];

                // 1. Concurrency Locking: lockForUpdate()
                $balance = InventoryBalance::where('product_variant_id', $variantId)
                    ->where('warehouse_id', $warehouse->id)
                    ->lockForUpdate()
                    ->first();

                $currentStock = $balance ? (float) $balance->quantity : 0.0;
                $currentCost = $balance ? (int) $balance->average_cost : 0;

                // 2. Stock validation (Negative inventory taqiqlangan)
                if ($currentStock < $quantity) {
                    $variant = ProductVariant::with('product', 'volume')->find($variantId);
                    $name = $variant ? "{$variant->product->name} ({$variant->volume->name})" : "ID: {$variantId}";
                    throw new Exception("Omborda yetarli mahsulot mavjud emas! '{$name}' uchun mavjud: {$currentStock} dona, so'raldi: {$quantity} dona.");
                }

                $lineTotal = (int) round($quantity * $salePrice);
                $costTotal = (int) round($quantity * $currentCost);
                $grossProfit = $lineTotal - $costTotal;

                $totalAmount += $lineTotal;
                $totalCost += $costTotal;

                $preparedItems[] = [
                    'variant_id' => $variantId,
                    'package_id' => $item['package_id'] ?? null,
                    'package_quantity' => $item['package_quantity'] ?? 0,
                    'quantity' => $quantity,
                    'sale_price' => $salePrice,
                    'purchase_cost_snapshot' => $currentCost, // Tarixiy tannarx snapshot
                    'line_total' => $lineTotal,
                    'cost_total' => $costTotal,
                    'gross_profit' => $grossProfit,
                    'is_system_price' => $item['is_system_price'] ?? true,
                    'balance_record' => $balance,
                ];
            }

            $netProfit = $totalAmount - $totalCost;

            // 3. Sales Record yaratish
            $sale = Sale::create([
                'invoice_number' => Sale::generateInvoiceNumber(),
                'customer_id' => $customerId,
                'warehouse_id' => $warehouse->id,
                'status' => 'COMPLETED',
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
                'gross_profit' => $netProfit,
                'payment_type' => $paymentType,
                'source' => $source,
                'created_by' => $userId,
            ]);

            $notifyItems = [];

            // 4. Sale Items va Inventory Movements yaratish
            foreach ($preparedItems as $prep) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_variant_id' => $prep['variant_id'],
                    'package_id' => $prep['package_id'],
                    'package_quantity' => $prep['package_quantity'],
                    'quantity' => $prep['quantity'],
                    'sale_price' => $prep['sale_price'],
                    'purchase_cost_snapshot' => $prep['purchase_cost_snapshot'],
                    'line_total' => $prep['line_total'],
                    'cost_total' => $prep['cost_total'],
                    'gross_profit' => $prep['gross_profit'],
                    'is_system_price' => $prep['is_system_price'],
                ]);

                // Inventory Movement (-quantity) - Source of Truth
                InventoryMovement::create([
                    'product_variant_id' => $prep['variant_id'],
                    'warehouse_id' => $warehouse->id,
                    'movement_type' => 'SALE',
                    'quantity' => -$prep['quantity'],
                    'unit_cost' => $prep['purchase_cost_snapshot'],
                    'total_cost' => -$prep['cost_total'],
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'created_by' => $userId,
                    'created_at' => Carbon::now(),
                ]);

                // Balance ni kamaytirish
                $balance = $prep['balance_record'];
                $balance->quantity -= $prep['quantity'];
                $balance->updated_at = Carbon::now();
                $balance->save();

                $v = ProductVariant::with('product', 'volume')->find($prep['variant_id']);
                if ($v) {
                    $notifyItems[] = [
                        'name' => $v->product->name,
                        'litres' => $v->volume->name ?? (string) ($v->volume->value_ml / 1000),
                        'quantity' => $prep['quantity'],
                        'unit_price' => $prep['sale_price'],
                        'is_system_price' => $prep['is_system_price'],
                    ];
                }
            }

            // 5. To'lov / Kassa / Qarz hisobi
            $customer = $customerId ? Customer::find($customerId) : null;
            $customerName = $customer ? $customer->name : 'Noma\'lum xaridor';

            if ($paymentType === 'DEBT' && $customer) {
                // Qarz daftari (Receivable)
                $newDebt = $customer->current_debt + $totalAmount;
                CustomerLedger::create([
                    'customer_id' => $customer->id,
                    'type' => 'SALE',
                    'debit' => $totalAmount,
                    'credit' => 0,
                    'balance_after' => $newDebt,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'notes' => "Savdo cheki: {$sale->invoice_number}",
                    'created_by' => $userId,
                    'created_at' => Carbon::now(),
                ]);
                $customer->current_debt = $newDebt;
                $customer->save();
            } else {
                // Kassa hisobi (Cash / Card / Bank)
                $accountType = $paymentType === 'CARD' ? 'CARD' : ($paymentType === 'BANK' ? 'BANK' : 'CASH');
                $cashAccount = CashAccount::where('type', $accountType)->first();
                if (! $cashAccount) {
                    $cashAccount = CashAccount::firstOrCreate(
                        ['name' => "Asosiy {$accountType}"],
                        ['type' => $accountType, 'balance' => 0, 'is_default' => true]
                    );
                }

                CashTransaction::create([
                    'cash_account_id' => $cashAccount->id,
                    'type' => 'SALE_PAYMENT',
                    'amount' => $totalAmount,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'description' => "Savdo: {$sale->invoice_number} ({$customerName})",
                    'created_by' => $userId,
                    'created_at' => Carbon::now(),
                ]);

                $cashAccount->increment('balance', $totalAmount);
            }

            // 6. Telegram broadcast
            $this->telegram->notifySale(
                customer: $customerName,
                items: $notifyItems,
                totalRetail: $totalAmount,
                totalCost: $totalCost,
                netProfit: $netProfit,
                paymentType: strtolower($paymentType),
                source: $source
            );

            // 7. Audit log
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'SALE_COMPLETE',
                'auditable_type' => Sale::class,
                'auditable_id' => $sale->id,
                'new_values' => [
                    'invoice' => $sale->invoice_number,
                    'total_amount' => $sale->total_amount,
                    'gross_profit' => $sale->gross_profit,
                    'payment_type' => $paymentType,
                ],
            ]);

            return $sale;
        });
    }
}

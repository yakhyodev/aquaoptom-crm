<?php

namespace App\Services\Purchase;

use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Ledger\CashAccountService;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Ledger\SupplierLedgerService;
use App\Services\Operations\DocumentNumberGenerator;
use App\Services\Operations\Exceptions\OperationPermissionException;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Operations\TransactionalOperationService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

class ReceivePurchaseService
{
    public function __construct(
        protected InventoryLedgerService $inventoryLedgerService,
        protected SupplierLedgerService $supplierLedgerService,
        protected CashAccountService $cashAccountService,
        protected TransactionalOperationService $transactionalOperationService,
        protected TelegramService $telegram
    ) {}

    /**
     * Tovar kirimini POST qilish (Atomic & Idempotent Transaction).
     *
     * @param  int  $supplierId  Majburiy ta'minotchi ID
     * @param  array  $items  [ ['variant_id' => int, 'quantity' => int, 'unit_cost' => int, 'package_id' => ?int, 'package_quantity' => ?int, 'new_sale_price' => ?int] ]
     * @param  int  $paidAmount  Haqiqatan to'langan summa (default: 0)
     * @param  int|null  $cashAccountId  Pul yechiladigan kassa hisobi (to'lov bo'lsa majburiy)
     * @param  string  $paymentMethod  CASH, CARD, BANK
     * @param  string|null  $supplierInvoiceNumber  Ta'minotchining nakladnoy raqami (ixtiyoriy)
     * @param  string|null  $notes  Kirim izohi (ixtiyoriy)
     * @param  string  $operationId  Barqaror UUID
     * @param  int|null  $warehouseId  Ombor ID (default: Asosiy Ombor)
     * @param  int|null  $userId  Mas'ul xodim ID
     * @param  string  $source  web, mobile, telegram
     */
    public function execute(
        int $supplierId,
        array $items,
        ?string $operationId = null,
        int $paidAmount = 0,
        ?int $cashAccountId = null,
        string $paymentMethod = 'CASH',
        ?string $supplierInvoiceNumber = null,
        ?string $notes = null,
        ?int $warehouseId = null,
        ?int $userId = null,
        string $source = 'web',
        ?string $invoiceNumber = null
    ): Fluent {
        $operationId = $operationId ?: (string) Str::uuid();
        $supplierInvoiceNumber = $supplierInvoiceNumber ?? $invoiceNumber;

        // 1. Validatsiyalar
        if (empty($items)) {
            throw new OperationValidationException(
                operationId: $operationId,
                message: "Kirim ro'yxatida kamida bitta tovar bo'lishi shart!",
                errorCode: 'EMPTY_PURCHASE_ITEMS'
            );
        }

        // Ta'minotchi mavjudligini tekshirish
        $supplier = Supplier::find($supplierId);
        if (! $supplier) {
            throw new OperationValidationException(
                operationId: $operationId,
                message: "Tanlangan ta'minotchi tizimda mavjud emas!",
                errorCode: 'SUPPLIER_NOT_FOUND'
            );
        }

        // Moliya ruxsati tekshiruvi: Agar to'lov summasi > 0 bo'lsa
        if ($paidAmount > 0) {
            if (! $cashAccountId) {
                throw new OperationValidationException(
                    operationId: $operationId,
                    message: "Pul to'lovi uchun kassa hisobi tanlanishi shart!",
                    errorCode: 'CASH_ACCOUNT_REQUIRED'
                );
            }

            // Foydalanuvchi huquqini tekshirish: omborchining jim qolishi naqd to'lov deb olinmasin
            if ($userId) {
                $user = User::find($userId);
                if ($user && ! $user->hasRole(['OWNER', 'ADMIN', 'CASHIER']) && ! $user->hasPermission('manage_cash_outflow') && ! $user->hasPermission('view_cash')) {
                    throw new OperationPermissionException(
                        operationId: $operationId,
                        message: "Sizda kassadan pul to'lash ruxsati mavjud emas. To'lovni faqat moliya xodimi amalga oshirishi mumkin!",
                        details: ['error_code' => 'NO_CASH_OUTFLOW_PERMISSION']
                    );
                }
            }
        }

        // Jami summa hisoblash va qatorlarni tekshirish
        $totalAmount = 0;
        foreach ($items as $index => $item) {
            $variantId = (int) ($item['variant_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);
            $unitCost = (int) ($item['unit_cost'] ?? 0);

            if ($variantId <= 0 || ! ProductVariant::where('id', $variantId)->exists()) {
                throw new OperationValidationException(
                    operationId: $operationId,
                    message: "Qatordagi tovar varianti (#{$variantId}) mavjud emas!",
                    errorCode: 'INVALID_VARIANT'
                );
            }

            if ($qty <= 0) {
                throw new OperationValidationException(
                    operationId: $operationId,
                    message: "Tovar miqdori 0 dan katta butun son bo'lishi shart! (Qator #".($index + 1).')',
                    errorCode: 'INVALID_QUANTITY'
                );
            }

            if ($unitCost < 0) {
                throw new OperationValidationException(
                    operationId: $operationId,
                    message: "Kirim narxi manfiy bo'lishi mumkin emas! (Qator #".($index + 1).')',
                    errorCode: 'INVALID_UNIT_COST'
                );
            }

            $totalAmount += ($qty * $unitCost);
        }

        if ($paidAmount > $totalAmount) {
            // Ortiqcha to'lov avansga o'tishi mumkin, lekin ogohlantirish sifatida tekshiriladi
        }

        $debtAmount = max(0, $totalAmount - $paidAmount);

        // Kanonik payload
        $payload = [
            'supplier_id' => $supplierId,
            'items' => $items,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'debt_amount' => $debtAmount,
            'cash_account_id' => $cashAccountId,
            'payment_method' => strtoupper($paymentMethod),
            'supplier_invoice_number' => $supplierInvoiceNumber,
            'warehouse_id' => $warehouseId,
            'source' => $source,
        ];

        // TransactionalOperationService orqali atomik va idempotent bajarish
        $res = $this->transactionalOperationService->execute(
            operationId: $operationId,
            operationType: 'RECEIVE_PURCHASE',
            payload: $payload,
            businessCallback: function () use (
                $supplier,
                $supplierId,
                $items,
                $totalAmount,
                $paidAmount,
                $debtAmount,
                $cashAccountId,
                $paymentMethod,
                $supplierInvoiceNumber,
                $notes,
                $operationId,
                $warehouseId,
                $userId,
                $source
            ) {
                $actualWarehouseId = $warehouseId ?: $this->getDefaultWarehouseId();
                $invoiceNumber = DocumentNumberGenerator::nextPurchaseInvoiceNumber();

                // 1. Purchase hujjati yaratish (Status: POSTED)
                $purchase = Purchase::create([
                    'operation_id' => $operationId,
                    'invoice_number' => $invoiceNumber,
                    'supplier_invoice_number' => $supplierInvoiceNumber,
                    'supplier_id' => $supplierId,
                    'warehouse_id' => $actualWarehouseId,
                    'status' => 'POSTED',
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'debt_amount' => $debtAmount,
                    'posted_at' => Carbon::now(),
                    'source' => $source,
                    'notes' => $notes,
                    'created_by' => $userId,
                ]);

                // 2. Tovarlar qatorlari va Ombor daftari (WAC yangilanishi)
                $itemsRecorded = [];
                foreach ($items as $item) {
                    $variantId = (int) $item['variant_id'];
                    $qty = (int) $item['quantity'];
                    $unitCost = (int) $item['unit_cost'];
                    $lineTotal = $qty * $unitCost;

                    $pItem = PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'product_variant_id' => $variantId,
                        'package_id' => $item['package_id'] ?? null,
                        'package_quantity' => $item['package_quantity'] ?? 0,
                        'quantity' => $qty,
                        'unit_cost' => $unitCost,
                        'total_cost' => $lineTotal,
                    ]);

                    // Ombor daftari kirimi
                    $inflow = $this->inventoryLedgerService->recordInflow(
                        productVariantId: $variantId,
                        quantity: $qty,
                        unitCost: $unitCost,
                        movementType: 'PURCHASE',
                        warehouseId: $actualWarehouseId,
                        operationId: $operationId,
                        referenceType: Purchase::class,
                        referenceId: $purchase->id,
                        userId: $userId
                    );

                    // Agar yangi sotuv narxi ko'rsatilgan bo'lsa, variant narxini yangilash
                    if (! empty($item['new_sale_price']) && (int) $item['new_sale_price'] > 0) {
                        $variant = ProductVariant::find($variantId);
                        if ($variant) {
                            $variant->update([
                                'default_sale_price' => (int) $item['new_sale_price'],
                                'version' => $variant->version + 1,
                            ]);
                        }
                    }

                    $itemsRecorded[] = [
                        'purchase_item_id' => $pItem->id,
                        'variant_id' => $variantId,
                        'quantity' => $qty,
                        'unit_cost' => $unitCost,
                        'total_cost' => $lineTotal,
                        'wac_after' => $inflow['average_cost'],
                    ];
                }

                // 3. Ta'minotchi qarz daftari (Credit: jami summa bo'yicha majburiyat oshadi)
                $supplierLedgerCredit = $this->supplierLedgerService->recordPurchaseCredit(
                    supplierId: $supplierId,
                    amount: $totalAmount,
                    type: 'PURCHASE',
                    operationId: $operationId,
                    referenceType: Purchase::class,
                    referenceId: $purchase->id,
                    notes: "Kirim hujjati #{$invoiceNumber}".($supplierInvoiceNumber ? " (Nakladnoy: {$supplierInvoiceNumber})" : ''),
                    userId: $userId
                );

                // 4. Agar to'lov summasi > 0 bo'lsa: Kassa chiqimi, Ta'minotchi qarz kamayishi, Payment hujjati
                $paymentRecord = null;
                if ($paidAmount > 0) {
                    $paymentNumber = DocumentNumberGenerator::nextPaymentNumber();
                    $paymentOperationId = Str::uuid()->toString();

                    $paymentRecord = Payment::create([
                        'payment_number' => $paymentNumber,
                        'operation_id' => $paymentOperationId,
                        'party_type' => 'SUPPLIER',
                        'party_id' => $supplierId,
                        'cash_account_id' => $cashAccountId,
                        'payment_type' => 'SUPPLIER_PAYMENT',
                        'payment_method' => strtoupper($paymentMethod),
                        'direction' => 'OUT',
                        'amount' => $paidAmount,
                        'notes' => "Kirim uchun to'lov #{$invoiceNumber}",
                        'status' => 'COMPLETED',
                        'created_by' => $userId,
                    ]);

                    // Kassadan pul chiqimi
                    $this->cashAccountService->recordOutflow(
                        cashAccountId: $cashAccountId,
                        amount: $paidAmount,
                        type: 'SUPPLIER_PAYMENT',
                        operationId: $paymentOperationId,
                        referenceType: Purchase::class,
                        referenceId: $purchase->id,
                        description: "Ta'minotchiga to'lov #{$invoiceNumber}",
                        userId: $userId
                    );

                    // Ta'minotchi qarz kamayishi (Debit)
                    $this->supplierLedgerService->recordPaymentDebit(
                        supplierId: $supplierId,
                        amount: $paidAmount,
                        type: 'PAYMENT',
                        paymentMethod: $paymentMethod,
                        operationId: $paymentOperationId,
                        referenceType: Purchase::class,
                        referenceId: $purchase->id,
                        notes: "To'lov qilindi: #{$paymentNumber} (Kirim #{$invoiceNumber})",
                        userId: $userId
                    );
                }

                // 5. Telegram orqali xabarnoma (xato bo'lsa tranzaksiyani buzmasligi uchun try/catch)
                try {
                    $supplierName = Supplier::where('id', $supplierId)->value('name') ?: 'Noma\'lum';
                    $firstItem = $items[0] ?? null;
                    if ($firstItem) {
                        $variant = ProductVariant::with(['product', 'volume'])->find($firstItem['variant_id']);
                        if ($variant) {
                            $this->telegram->notifyInward(
                                productName: $variant->product->name.' ('.$supplierName.')',
                                litres: $variant->volume->name ?? '0.5 L',
                                qty: (int) $firstItem['quantity'],
                                costPrice: (int) $firstItem['unit_cost'],
                                source: $source
                            );
                        }
                    }
                } catch (\Throwable $e) {
                    // Telegram xatosi tranzaksiyaga ta'sir qilmaydi
                }

                return [
                    'purchase_id' => $purchase->id,
                    'invoice_number' => $invoiceNumber,
                    'supplier_id' => $supplierId,
                    'supplier_name' => $supplier->name,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'debt_amount' => $debtAmount,
                    'supplier_balance_after' => $supplier->fresh()->balance,
                    'items_count' => count($itemsRecorded),
                    'items' => $itemsRecorded,
                    'payment_number' => $paymentRecord ? $paymentRecord->payment_number : null,
                ];
            },
            actorId: $userId,
            source: $source
        );

        $res['id'] = $res['purchase_id'] ?? null;
        $res['status'] = 'POSTED';

        return new Fluent($res);
    }

    /**
     * Standart omborni olish.
     */
    protected function getDefaultWarehouseId(): int
    {
        $warehouse = Warehouse::where('is_default', true)->first();
        if (! $warehouse) {
            $warehouse = Warehouse::firstOrCreate(['name' => 'Asosiy Ombor'], ['is_default' => true]);
        }

        return $warehouse->id;
    }
}

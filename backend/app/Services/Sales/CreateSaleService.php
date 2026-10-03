<?php

namespace App\Services\Sales;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Warehouse;
use App\Services\Ledger\CashAccountService;
use App\Services\Ledger\CustomerLedgerService;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Operations\DocumentNumberGenerator;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Operations\TransactionalOperationService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CreateSaleService
{
    public function __construct(
        protected InventoryLedgerService $inventoryLedgerService,
        protected CustomerLedgerService $customerLedgerService,
        protected CashAccountService $cashAccountService,
        protected TransactionalOperationService $transactionalOperationService,
        protected TelegramService $telegram
    ) {}

    /**
     * Optom savdo qilish va moliyaviy-ombor harakatlarini atomik qayd etish.
     *
     * @param  int|null  $customerId  Xaridor ID (null bo'lsa - Tezkor/Guest savdo)
     * @param  array  $items  [ ['variant_id', 'quantity', 'sale_price', 'is_system_price', 'price_version'] ]
     * @param  string|null  $operationId  Barqaror UUID
     * @param  int  $paidAmount  Hozir to'langan haqiqiy summa (so'm)
     * @param  int|null  $cashAccountId  Kassa hisobi ID
     * @param  string  $paymentType  CASH, CARD, BANK, DEBT, MIXED, FULL, PARTIAL
     * @param  string  $paymentMethod  CASH, CARD, BANK
     * @param  string|null  $notes  Izoh
     * @param  int|null  $warehouseId  Ombor ID
     * @param  int|null  $userId  Mas'ul xodim ID
     * @param  string  $source  web, mobile, pos
     * @param  bool  $useSystemPrice  Tizim narxidan foydalanish
     */
    public function execute(
        ?int $customerId,
        array $items,
        ?string $operationId = null,
        int $paidAmount = 0,
        ?int $cashAccountId = null,
        string $paymentType = 'CASH',
        string $paymentMethod = 'CASH',
        ?string $notes = null,
        ?int $warehouseId = null,
        ?int $userId = null,
        string $source = 'web',
        bool $useSystemPrice = true
    ): Sale {
        $operationId = $operationId ?: (string) Str::uuid();

        // 1. Savat bo'sh emasligini tekshirish
        if (empty($items)) {
            throw new OperationValidationException(
                operationId: $operationId,
                message: "Savdo savati bo'sh! Kamida bitta mahsulot tanlanishi shart.",
                errorCode: 'EMPTY_CART'
            );
        }

        // 2. Qatorlar validatsiyasi, tizim narxi va takrorlangan variantlar agregatsiyasi
        $totalAmount = 0;
        $validatedItems = [];
        $aggregateQtyByVariant = [];

        foreach ($items as $index => $item) {
            $variantId = (int) ($item['variant_id'] ?? 0);
            $rawQty = $item['quantity'] ?? 0;
            $isSysPrice = isset($item['is_system_price']) ? (bool) $item['is_system_price'] : $useSystemPrice;

            // Variant mavjudligini tekshirish
            $variant = ProductVariant::with(['product', 'volume'])->find($variantId);
            if (! $variant) {
                throw new OperationValidationException(
                    operationId: $operationId,
                    message: "Qatordagi tovar varianti (#{$variantId}) tizimda topilmadi!",
                    errorCode: 'INVALID_VARIANT'
                );
            }

            // Kasr dona va noldan kichik miqdor taqiqlangan (Faqat butun musbat dona!)
            if (! is_numeric($rawQty) || (int) $rawQty != $rawQty || (int) $rawQty <= 0) {
                throw new OperationValidationException(
                    operationId: $operationId,
                    message: "Tovar miqdori 0 dan katta butun dona bo'lishi shart! Kasr dona bilan savdo taqiqlanadi. (Qator #".($index + 1).')',
                    errorCode: 'INVALID_QUANTITY'
                );
            }
            $qty = (int) $rawQty;

            // Narxni aniqlash
            if ($isSysPrice) {
                // Tizim narxi majburiy, agar yo'q bo'lsa tasodifiy default yo'q!
                if ($variant->default_sale_price === null || (int) $variant->default_sale_price <= 0) {
                    throw new OperationValidationException(
                        operationId: $operationId,
                        message: "'{$variant->product->name} ({$variant->volume->name})' uchun tizim narxi belgilanmagan! Erkin narx kiriting yoki tizim narxini belgilang.",
                        errorCode: 'SYSTEM_PRICE_NOT_SET'
                    );
                }

                // Online eski narx versiyasida qayta tasdiq
                if (isset($item['price_version']) && $item['price_version'] !== null && (int) $item['price_version'] !== (int) $variant->version) {
                    throw new OperationValidationException(
                        operationId: $operationId,
                        message: "'{$variant->product->name}' mahsulotining tizim narxi o'zgargan! Yangi narx bilan qayta tasdiqlang.",
                        errorCode: 'PRICE_VERSION_MISMATCH',
                        details: [
                            'variant_id' => $variantId,
                            'current_price' => $variant->default_sale_price,
                            'current_version' => $variant->version,
                        ]
                    );
                }

                $unitPrice = (int) $variant->default_sale_price;
            } else {
                $rawPrice = $item['sale_price'] ?? $item['unit_price'] ?? 0;
                if (! is_numeric($rawPrice) || (int) $rawPrice <= 0) {
                    throw new OperationValidationException(
                        operationId: $operationId,
                        message: "Tovar narxi 0 dan katta butun so'm bo'lishi shart! Manfiy yoki nol narxda savdo taqiqlanadi. (Qator #".($index + 1).')',
                        errorCode: 'INVALID_SALE_PRICE'
                    );
                }
                $unitPrice = (int) $rawPrice;
            }

            $lineTotal = $qty * $unitPrice;
            $totalAmount += $lineTotal;

            // Takroriy qatorlar bo'yicha jami donani jamlash
            $aggregateQtyByVariant[$variantId] = ($aggregateQtyByVariant[$variantId] ?? 0) + $qty;

            $validatedItems[] = [
                'variant' => $variant,
                'variant_id' => $variantId,
                'package_id' => $item['package_id'] ?? null,
                'package_quantity' => $item['package_quantity'] ?? 0,
                'quantity' => $qty,
                'sale_price' => $unitPrice,
                'line_total' => $lineTotal,
                'is_system_price' => $isSysPrice,
                'price_version' => $variant->version,
            ];
        }

        // 3. Xaridor va To'lov qoidalari
        // Agar paidAmount berilmagan bo'lsa (0) va to'lov turi naqd/karta/bank/full bo'lsa, to'liq to'langan deb olinadi
        if ($paidAmount === 0 && in_array(strtoupper($paymentType), ['CASH', 'CARD', 'BANK', 'FULL'])) {
            $paidAmount = $totalAmount;
        }

        // "Guest tezkor savdo faqat to‘liq to‘lov; mijozsiz DEBT o‘tmaydi"
        $customer = null;
        if (! $customerId) {
            // Agar mijoz tanlanmagan bo'lsa (Tezkor savdo), to'lov to'liq bo'lishi shart!
            if ($paidAmount < $totalAmount) {
                throw new OperationValidationException(
                    operationId: $operationId,
                    message: "Noma'lum xaridorga (mijozsiz) nasiyaga savdo qilish taqiqlangan! To'liq to'lov yoki xaridorni tanlang.",
                    errorCode: 'GUEST_DEBT_NOT_ALLOWED'
                );
            }
            $paidAmount = $totalAmount;
            $debtAmount = 0;
            $resolvedPaymentType = strtoupper($paymentMethod);
        } else {
            $customer = Customer::find($customerId);
            if (! $customer) {
                throw new OperationValidationException(
                    operationId: $operationId,
                    message: "Tanlangan xaridor (#{$customerId}) tizimda mavjud emas!",
                    errorCode: 'CUSTOMER_NOT_FOUND'
                );
            }

            if ($paidAmount < 0) {
                $paidAmount = 0;
            }

            $debtAmount = max(0, $totalAmount - $paidAmount);
            if ($paidAmount >= $totalAmount) {
                $resolvedPaymentType = 'FULL';
            } elseif ($paidAmount === 0) {
                $resolvedPaymentType = 'DEBT';
            } else {
                $resolvedPaymentType = 'PARTIAL';
            }
        }

        // 4. Kassa hisobini tekshirish (agar to'lov bo'lsa)
        if ($paidAmount > 0 && ! $cashAccountId) {
            $defaultCash = CashAccount::where('type', strtoupper($paymentMethod))->where('is_default', true)->first()
                ?? CashAccount::where('type', strtoupper($paymentMethod))->first()
                ?? CashAccount::where('is_default', true)->first();

            if (! $defaultCash) {
                $defaultCash = CashAccount::firstOrCreate(
                    ['name' => 'Asosiy '.strtoupper($paymentMethod)],
                    ['type' => strtoupper($paymentMethod), 'balance' => 0, 'is_default' => true]
                );
            }

            $cashAccountId = $defaultCash->id;
        }

        // Kanonik payload (Idempotency tekshiruvi uchun)
        $payload = [
            'type' => 'SALE',
            'customer_id' => $customerId,
            'items' => array_map(fn ($it) => [
                'variant_id' => $it['variant_id'],
                'quantity' => $it['quantity'],
                'sale_price' => $it['sale_price'],
                'is_system_price' => $it['is_system_price'],
            ], $validatedItems),
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'debt_amount' => $debtAmount,
            'cash_account_id' => $cashAccountId,
            'payment_method' => strtoupper($paymentMethod),
            'warehouse_id' => $warehouseId,
            'source' => $source,
        ];

        // 5. TransactionalOperationService orqali atomik bajarish
        $resultData = $this->transactionalOperationService->execute(
            operationId: $operationId,
            operationType: 'CREATE_SALE',
            payload: $payload,
            businessCallback: function () use (
                $operationId,
                $customerId,
                $customer,
                $validatedItems,
                $aggregateQtyByVariant,
                $totalAmount,
                $paidAmount,
                $debtAmount,
                $cashAccountId,
                $resolvedPaymentType,
                $paymentMethod,
                $notes,
                $warehouseId,
                $userId,
                $source
            ) {
                $actualWarehouseId = $warehouseId ?: $this->getDefaultWarehouseId();

                // 5.1. Concurrency stock lock: Barcha kerakli variantlar bo'yicha lockForUpdate tekshirish
                // "100 qoldiqdan 60+60 o'tmaydi"
                foreach ($aggregateQtyByVariant as $vid => $totalNeeded) {
                    $balance = InventoryBalance::where('product_variant_id', $vid)
                        ->where('warehouse_id', $actualWarehouseId)
                        ->lockForUpdate()
                        ->first();

                    $available = $balance ? (int) $balance->quantity : 0;
                    if ($available < $totalNeeded) {
                        $v = ProductVariant::with(['product', 'volume'])->find($vid);
                        $vName = $v ? "{$v->product->name} ({$v->volume->name})" : "#{$vid}";
                        throw new OperationValidationException(
                            operationId: $operationId,
                            message: "Omborda yetarli mahsulot mavjud emas! '{$vName}' uchun mavjud: {$available} dona, so'ralgan jami: {$totalNeeded} dona.",
                            errorCode: 'INSUFFICIENT_STOCK',
                            details: [
                                'variant_id' => $vid,
                                'available' => $available,
                                'requested' => $totalNeeded,
                            ]
                        );
                    }
                }

                // 5.2. Hujjat raqamini olish
                $invoiceNumber = DocumentNumberGenerator::nextSaleInvoiceNumber();

                // 5.3. Sale hujjati yaratish
                $sale = Sale::create([
                    'operation_id' => $operationId,
                    'invoice_number' => $invoiceNumber,
                    'customer_id' => $customerId,
                    'warehouse_id' => $actualWarehouseId,
                    'status' => 'COMPLETED',
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'debt_amount' => $debtAmount,
                    'cash_account_id' => $cashAccountId,
                    'payment_type' => $resolvedPaymentType,
                    'payment_method' => strtoupper($paymentMethod),
                    'total_cost' => 0, // Quyida har bir qatordan jamlanadi
                    'gross_profit' => 0,
                    'source' => $source,
                    'notes' => $notes,
                    'created_by' => $userId,
                    'completed_at' => Carbon::now(),
                ]);

                // 5.4. Tovarlar qatorlari va Ombor chiqimi (WAC tannarx snapshot bilan)
                $saleTotalCost = 0;
                $itemsRecorded = [];

                foreach ($validatedItems as $item) {
                    $variantId = $item['variant_id'];
                    $qty = $item['quantity'];
                    $salePrice = $item['sale_price'];
                    $lineTotal = $item['line_total'];

                    // WAC Chiqimi (InventoryLedgerService orqali atomik)
                    $outflow = $this->inventoryLedgerService->recordOutflow(
                        productVariantId: $variantId,
                        quantity: $qty,
                        movementType: 'SALE',
                        warehouseId: $actualWarehouseId,
                        operationId: $operationId,
                        referenceType: Sale::class,
                        referenceId: $sale->id,
                        userId: $userId
                    );

                    $unitCost = (int) $outflow['unit_cost'];
                    $costTotal = (int) $outflow['total_cost'];
                    $grossProfit = $lineTotal - $costTotal;
                    $saleTotalCost += $costTotal;

                    // SaleItem yaratish
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_variant_id' => $variantId,
                        'package_id' => $item['package_id'],
                        'package_quantity' => $item['package_quantity'],
                        'quantity' => $qty,
                        'sale_price' => $salePrice,
                        'purchase_cost_snapshot' => $unitCost,
                        'line_total' => $lineTotal,
                        'cost_total' => $costTotal,
                        'gross_profit' => $grossProfit,
                        'is_system_price' => $item['is_system_price'],
                        'price_version' => $item['price_version'],
                    ]);

                    $itemsRecorded[] = [
                        'variant_id' => $variantId,
                        'product_name' => $item['variant']->product->name,
                        'volume_name' => $item['variant']->volume->name,
                        'quantity' => $qty,
                        'sale_price' => $salePrice,
                        'unit_cost' => $unitCost,
                        'line_total' => $lineTotal,
                        'cost_total' => $costTotal,
                        'gross_profit' => $grossProfit,
                    ];
                }

                $netGrossProfit = $totalAmount - $saleTotalCost;

                // Sale modelidagi tannarx va foyda snapshotini yangilash
                $sale->update([
                    'total_cost' => $saleTotalCost,
                    'gross_profit' => $netGrossProfit,
                ]);

                // 5.5. Mijoz hisobi (Customer Ledger)
                // "Mijoz daftariga jami qo‘shiladi, haqiqiy pul ayiriladi; avans ikkinchi cash emas"
                $customerBalanceAfter = 0;
                if ($customerId) {
                    // Savdo summasi to'liq qarzga qo'shiladi (Debit)
                    $this->customerLedgerService->recordSale(
                        customerId: $customerId,
                        amount: $totalAmount,
                        saleId: $sale->id,
                        operationId: $operationId,
                        userId: $userId,
                        description: "Savdo cheki #{$invoiceNumber}"
                    );

                    // Agar savdo vaqtida to'lov qilingan bo'lsa, qarz kamaytiriladi (Credit)
                    if ($paidAmount > 0) {
                        $this->customerLedgerService->recordPayment(
                            customerId: $customerId,
                            amount: $paidAmount,
                            paymentId: null,
                            operationId: $operationId,
                            userId: $userId,
                            description: "Savdo #{$invoiceNumber} uchun to'lov"
                        );
                    }

                    $customerBalanceAfter = (int) $customer->fresh()->current_debt;
                }

                // 5.6. Kassa Harakati (Cash Inflow)
                // Faqat hozir olingan haqiqiy pul kassa hisobiga kirim qilinadi (avans ikkinchi cash emas!)
                $paymentRecord = null;
                if ($paidAmount > 0 && $cashAccountId) {
                    $customerName = $customer ? $customer->name : 'Noma\'lum xaridor';

                    $this->cashAccountService->recordInflow(
                        cashAccountId: $cashAccountId,
                        amount: $paidAmount,
                        type: 'SALE_PAYMENT',
                        operationId: $operationId,
                        referenceType: Sale::class,
                        referenceId: $sale->id,
                        description: "Savdo #{$invoiceNumber} to'lovi ({$customerName})",
                        userId: $userId
                    );

                    $paymentRecord = Payment::create([
                        'operation_id' => Str::uuid()->toString(),
                        'payment_number' => DocumentNumberGenerator::nextPaymentNumber(),
                        'party_type' => $customerId ? 'CUSTOMER' : 'NONE',
                        'party_id' => $customerId,
                        'cash_account_id' => $cashAccountId,
                        'payment_type' => 'CUSTOMER_PAYMENT',
                        'payment_method' => strtoupper($paymentMethod),
                        'direction' => 'IN',
                        'amount' => $paidAmount,
                        'status' => 'CONFIRMED',
                        'created_by' => $userId,
                        'notes' => "Savdo #{$invoiceNumber} uchun to'lov",
                    ]);
                }

                // 5.7. Elektron Hujjat (Receipt Data snapshot)
                $receiptData = [
                    'invoice_number' => $invoiceNumber,
                    'date' => Carbon::now()->format('Y-m-d H:i:s'),
                    'customer_id' => $customerId,
                    'customer_name' => $customer ? $customer->name : 'Tezkor xaridor',
                    'seller_id' => $userId,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'debt_amount' => $debtAmount,
                    'total_cost' => $saleTotalCost,
                    'gross_profit' => $netGrossProfit,
                    'payment_method' => strtoupper($paymentMethod),
                    'customer_balance_after' => $customerBalanceAfter,
                    'items_count' => count($itemsRecorded),
                    'items' => $itemsRecorded,
                    'payment_number' => $paymentRecord ? $paymentRecord->payment_number : null,
                ];

                $sale->update(['receipt_data' => $receiptData]);

                // 5.8. Telegram broadcast
                try {
                    $telegramItems = array_map(fn ($it) => [
                        'name' => $it['product_name'],
                        'litres' => $it['volume_name'],
                        'quantity' => $it['quantity'],
                        'unit_price' => $it['sale_price'],
                        'is_system_price' => true,
                    ], $itemsRecorded);

                    $this->telegram->notifySale(
                        customer: $customer ? $customer->name : 'Tezkor savdo (Naqd)',
                        items: $telegramItems,
                        totalRetail: $totalAmount,
                        totalCost: $saleTotalCost,
                        netProfit: $netGrossProfit,
                        paymentType: strtolower($resolvedPaymentType),
                        source: $source
                    );
                } catch (\Throwable $e) {
                    // Telegram bildirishnomasi xatosi asosiy tranzaksiyani to'xtatmaydi
                }

                return [
                    'sale_id' => $sale->id,
                    'invoice_number' => $invoiceNumber,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'debt_amount' => $debtAmount,
                    'total_cost' => $saleTotalCost,
                    'gross_profit' => $netGrossProfit,
                    'customer_name' => $customer ? $customer->name : 'Tezkor xaridor',
                    'customer_balance_after' => $customerBalanceAfter,
                    'items_count' => count($itemsRecorded),
                    'items' => $itemsRecorded,
                    'receipt_data' => $receiptData,
                ];
            },
            actorId: $userId,
            source: $source
        );

        // Qaytarishda yangilangan Sale modelini to'liq aloqalari bilan yuklaymiz
        return Sale::with(['items.variant.product', 'items.variant.volume', 'customer', 'cashAccount'])->find($resultData['sale_id']);
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

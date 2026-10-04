<?php

namespace App\Services\Sales;

use App\Events\SaleCreatedBroadcastEvent;
use App\Models\CashAccount;
use App\Models\CreditAllocation;
use App\Models\Customer;
use App\Models\Device;
use App\Models\InventoryAllocation;
use App\Models\InventoryBalance;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Ledger\CashAccountService;
use App\Services\Ledger\CreditAllocationService;
use App\Services\Ledger\CustomerLedgerService;
use App\Services\Ledger\Exceptions\InsufficientAllocationException;
use App\Services\Ledger\Exceptions\InsufficientFreeStockException;
use App\Services\Ledger\InventoryAllocationService;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Operations\DocumentNumberGenerator;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Operations\OperationContext;
use App\Services\Operations\TransactionalOperationService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSaleService
{
    public function __construct(
        protected InventoryLedgerService $inventoryLedgerService,
        protected CustomerLedgerService $customerLedgerService,
        protected CashAccountService $cashAccountService,
        protected TransactionalOperationService $transactionalOperationService,
        protected TelegramService $telegram,
        protected InventoryAllocationService $inventoryAllocationService,
        protected CreditAllocationService $creditAllocationService
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
        bool $useSystemPrice = true,
        $goodsPickedUpAt = null,
        ?int $deviceId = null,
        ?array $rawPayload = null
    ): Sale {
        $operationId = $operationId ?: (string) Str::uuid();

        $actor = $userId ? User::find($userId) : auth()->user();
        if (! $actor || ! $actor->isActive() || ! $actor->hasRole(['OWNER', 'ADMIN', 'SALES_MANAGER', 'CASHIER'])) {
            throw new OperationValidationException($operationId, 'Savdo qilishga ruxsat yo‘q.', errorCode: 'PERMISSION_DENIED');
        }
        $userId = $actor->id;
        $operationPayload = $rawPayload ?? [
            'customer_id' => $customerId, 'items' => $items, 'paid_amount' => $paidAmount,
            'cash_account_id' => $cashAccountId, 'payment_type' => strtoupper($paymentType),
            'payment_method' => strtoupper($paymentMethod), 'notes' => $notes,
            'warehouse_id' => $warehouseId, 'device_id' => $deviceId, 'source' => $source,
            'use_system_price' => $useSystemPrice,
            'goods_picked_up_at' => $goodsPickedUpAt instanceof \DateTimeInterface ? $goodsPickedUpAt->format(DATE_ATOM) : $goodsPickedUpAt,
        ];
        $replay = $this->transactionalOperationService->replay($operationId, 'CREATE_SALE', $operationPayload, $userId);
        if ($replay !== null) {
            return Sale::with(['items.variant.product', 'items.variant.volume', 'customer', 'cashAccount'])->findOrFail($replay['sale_id']);
        }
        if ($paidAmount < 0) {
            throw new OperationValidationException($operationId, 'To‘lov manfiy bo‘lishi mumkin emas.', errorCode: 'INVALID_PAYMENT_AMOUNT');
        }

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
            if (! is_numeric($rawQty) || (int) $rawQty != $rawQty || (int) $rawQty <= 0 || $rawQty > InventoryLedgerService::MAX_QUANTITY) {
                throw new OperationValidationException(
                    operationId: $operationId,
                    message: "Tovar miqdori 0 dan katta butun dona bo'lishi shart! Kasr dona bilan savdo taqiqlanadi. (Qator #".($index + 1).')',
                    errorCode: 'INVALID_QUANTITY'
                );
            }
            $qty = (int) $rawQty;

            if (! $isSysPrice && ! $deviceId && ! $actor->hasPermission('custom_sale_price')) {
                throw new OperationValidationException($operationId, 'Kelishilgan narxda sotishga ruxsat yo‘q.', errorCode: 'PERMISSION_DENIED');
            }
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
                if (! is_numeric($rawPrice) || (int) $rawPrice != $rawPrice || (int) $rawPrice <= 0 || $rawPrice > PHP_INT_MAX) {
                    throw new OperationValidationException(
                        operationId: $operationId,
                        message: "Tovar narxi 0 dan katta butun so'm bo'lishi shart! Manfiy yoki nol narxda savdo taqiqlanadi. (Qator #".($index + 1).')',
                        errorCode: 'INVALID_SALE_PRICE'
                    );
                }
                $unitPrice = (int) $rawPrice;
            }

            if ($unitPrice > intdiv(PHP_INT_MAX - $totalAmount, $qty)) {
                throw new OperationValidationException($operationId, 'Savdo summasi ruxsat etilgan chegaradan oshdi.', errorCode: 'AMOUNT_OVERFLOW');
            }
            $lineTotal = $qty * $unitPrice;
            $totalAmount += $lineTotal;

            // Takroriy qatorlar bo'yicha jami donani jamlash
            $aggregateQtyByVariant[$variantId] = ($aggregateQtyByVariant[$variantId] ?? 0) + $qty;
            if ($aggregateQtyByVariant[$variantId] > InventoryLedgerService::MAX_QUANTITY) {
                throw new OperationValidationException($operationId, 'Jami dona ruxsat etilgan chegaradan oshdi.', errorCode: 'INVALID_QUANTITY');
            }

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
        if ($paidAmount === 0 && in_array(strtoupper($paymentType), ['CASH', 'CARD', 'BANK', 'FULL']) && strtoupper($paymentMethod) !== 'DEBT' && strtoupper($paymentType) !== 'DEBT') {
            $paidAmount = $totalAmount;
        }

        if ($paidAmount < $totalAmount && ! $actor->hasPermission('sell_on_credit')) {
            throw new OperationValidationException($operationId, 'Nasiyaga sotishga ruxsat yo‘q.', errorCode: 'PERMISSION_DENIED');
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

        ksort($aggregateQtyByVariant);

        // 5. TransactionalOperationService orqali atomik bajarish
        $resultData = $this->transactionalOperationService->execute(
            operationId: $operationId,
            operationType: 'CREATE_SALE',
            payload: $operationPayload,
            businessCallback: function (OperationContext $context) use (
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
                $source,
                $goodsPickedUpAt,
                $deviceId,
                $actor
            ) {
                $actualWarehouseId = $warehouseId ?: $this->getDefaultWarehouseId();

                // Serialize credit checks with customer sales, payments and allocation grants.
                if ($customerId) {
                    $customer = Customer::whereKey($customerId)->lockForUpdate()->firstOrFail();
                }

                // 5.1. Concurrency stock lock va Rezerv tekshiruvi:
                // "Fizik qoldiq va sotish huquqi rezervi alohida: 100 dona PC60/phone30/free10."
                // "Online savdo o‘z rezervi yoki erkin qoldiqni sarflasin."
                foreach ($aggregateQtyByVariant as $vid => $totalNeeded) {
                    $balance = InventoryBalance::where('product_variant_id', $vid)
                        ->where('warehouse_id', $actualWarehouseId)
                        ->lockForUpdate()
                        ->first();

                    $physicalOnHand = $balance ? (int) $balance->quantity : 0;

                    $deviceAlloc = null;
                    if ($deviceId) {
                        $device = Device::find($deviceId);
                        $deviceAlloc = InventoryAllocation::where('device_id', $deviceId)
                            ->where('product_variant_id', $vid)
                            ->where('warehouse_id', $actualWarehouseId)
                            ->where('status', 'ACTIVE')
                            ->lockForUpdate()
                            ->first();

                        if ($deviceAlloc) {
                            $availInReservation = $deviceAlloc->available_quantity;
                            if ($availInReservation < $totalNeeded) {
                                $v = ProductVariant::with(['product', 'volume'])->find($vid);
                                $vName = $v ? "{$v->product->name} ({$v->volume->name})" : "#{$vid}";
                                throw new InsufficientAllocationException(
                                    message: "Qurilmada (#{$device->device_code}) '{$vName}' uchun yetarli tovar ajratmasi (rezervi) mavjud emas! Ajratmada mavjud: {$availInReservation} dona, so'ralgan: {$totalNeeded} dona.",
                                    operationId: $operationId,
                                    details: [
                                        'device_id' => $deviceId,
                                        'variant_id' => $vid,
                                        'available_reservation' => $availInReservation,
                                        'requested' => $totalNeeded,
                                    ]
                                );
                            }
                        }
                    }

                    // 2. Jismoniy ombor qoldig'i tekshiruvi:
                    if ($physicalOnHand < $totalNeeded) {
                        $v = ProductVariant::with(['product', 'volume'])->find($vid);
                        $vName = $v ? "{$v->product->name} ({$v->volume->name})" : "#{$vid}";
                        throw new OperationValidationException(
                            operationId: $operationId,
                            message: "Omborda yetarli mahsulot mavjud emas! '{$vName}' uchun mavjud: {$physicalOnHand} dona, so'ralgan jami: {$totalNeeded} dona.",
                            errorCode: 'INSUFFICIENT_STOCK',
                            details: [
                                'variant_id' => $vid,
                                'available' => $physicalOnHand,
                                'requested' => $totalNeeded,
                            ]
                        );
                    }

                    // 3. Agar qurilma maxsus rezervidan emas, umumiy erkin qoldiqdan sarflanayotgan bo'lsa:
                    if (! $deviceId || ! $deviceAlloc) {
                        $sumReserved = (int) InventoryAllocation::where('product_variant_id', $vid)
                            ->where('warehouse_id', $actualWarehouseId)
                            ->where('status', 'ACTIVE')
                            ->sum(DB::raw('allocated_quantity - consumed_quantity - returned_quantity'));
                        $freeStock = max(0, $physicalOnHand - $sumReserved);
                        if ($freeStock < $totalNeeded) {
                            $v = ProductVariant::with(['product', 'volume'])->find($vid);
                            $vName = $v ? "{$v->product->name} ({$v->volume->name})" : "#{$vid}";
                            throw new InsufficientFreeStockException(
                                message: "Omborda yetarli erkin tovar qoldig'i mavjud emas! '{$vName}' uchun jami omborda {$physicalOnHand} dona bor, ammo {$sumReserved} donasi boshqa qurilmalarga rezerv qilingan. Faqat {$freeStock} dona erkin sotish mumkin, so'ralgan: {$totalNeeded} dona.",
                                operationId: $operationId,
                                details: [
                                    'variant_id' => $vid,
                                    'total_on_hand' => $physicalOnHand,
                                    'total_reserved' => $sumReserved,
                                    'free_stock' => $freeStock,
                                    'requested' => $totalNeeded,
                                ]
                            );
                        }
                    }
                }

                // 5.1.b. Qat'iy mijoz kredit limiti tekshiruvi:
                // "Credit limit online/offline rezervni hisobga oladi"
                if ($customerId && $debtAmount > 0 && $customer) {
                    $debtLimit = (int) $customer->debt_limit;
                    $isStrict = (bool) $customer->is_strict_credit_limit;

                    if ($debtLimit > 0 && $isStrict) {
                        $callingDevice = $deviceId ? Device::find($deviceId) : null;
                        $availableCredit = $this->creditAllocationService->getAvailableCreditLimit($customer, $callingDevice);

                        if ($debtAmount > $availableCredit) {
                            throw new OperationValidationException(
                                operationId: $operationId,
                                message: "Mijoz '{$customer->name}' kredit limitidan oshib ketdi! Limit: {$debtLimit} so'm, qurilma rezervlari va joriy qarz hisobga olinganda faqat {$availableCredit} so'm nasiya berish mumkin. So'ralgan nasiya: {$debtAmount} so'm.",
                                errorCode: 'CREDIT_LIMIT_EXCEEDED',
                                details: [
                                    'customer_id' => $customerId,
                                    'debt_limit' => $debtLimit,
                                    'available_credit' => $availableCredit,
                                    'requested_debt' => $debtAmount,
                                ]
                            );
                        }
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
                    'device_id' => $deviceId,
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
                    'goods_picked_up_at' => $goodsPickedUpAt ? Carbon::parse($goodsPickedUpAt, 'Asia/Tashkent')->utc() : null,
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
                    if ($lineTotal < $costTotal && ! $actor->hasPermission('sell_below_cost')) {
                        throw new OperationValidationException($operationId, 'Tannarxdan past sotishga ruxsat yo‘q.', errorCode: 'BELOW_COST_NOT_ALLOWED');
                    }
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

                    // Agar qurilmada ushbu tovar varianti bo'yicha ajratma bo'lsa, uni sarflash
                    if ($deviceId) {
                        $devModel = Device::find($deviceId);
                        $hasDevAlloc = InventoryAllocation::where('device_id', $deviceId)
                            ->where('product_variant_id', $variantId)
                            ->where('warehouse_id', $actualWarehouseId)
                            ->where('status', 'ACTIVE')
                            ->exists();

                        if ($hasDevAlloc && $devModel) {
                            $this->inventoryAllocationService->consumeAllocation(
                                device: $devModel,
                                variantId: $variantId,
                                quantity: $qty,
                                warehouseId: $actualWarehouseId,
                                operationId: (string) Str::uuid(),
                                userId: $userId
                            );
                        }
                    }

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

                    // Agar qurilmada ushbu mijoz bo'yicha kredit limiti ajratmasi bo'lsa, uni sarflash
                    if ($debtAmount > 0 && $deviceId) {
                        $devModel = Device::find($deviceId);
                        $hasCreditAlloc = CreditAllocation::where('device_id', $deviceId)
                            ->where('customer_id', $customerId)
                            ->where('status', 'ACTIVE')
                            ->exists();

                        if ($hasCreditAlloc && $devModel && $customer) {
                            $this->creditAllocationService->consumeCreditAllocation(
                                device: $devModel,
                                customer: $customer,
                                amount: $debtAmount,
                                operationId: (string) Str::uuid(),
                                isNewCustomerBudget: false,
                                userId: $userId
                            );
                        }
                    }
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
                $context->logAudit('SALE_CREATED', Sale::class, $sale->id, newValues: $receiptData);
                $context->enqueueEvent('SaleCreated', 'Sale', $sale->id, $receiptData);

                // Send only after the outermost transaction has committed.
                DB::afterCommit(function () use ($itemsRecorded, $customer, $totalAmount, $saleTotalCost, $netGrossProfit, $resolvedPaymentType, $source) {
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
                });

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
            deviceId: $deviceId ? (string) $deviceId : null,
            source: $source
        );

        // Qaytarishda yangilangan Sale modelini to'liq aloqalari bilan yuklaymiz
        $sale = Sale::with(['items.variant.product', 'items.variant.volume', 'customer', 'cashAccount'])->find($resultData['sale_id']);

        try {
            broadcast(new SaleCreatedBroadcastEvent($sale));
        } catch (\Throwable $e) {
            // Broadcasting xatosi commit bo'lgan savdoni buzmasligi kerak
        }

        return $sale;
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

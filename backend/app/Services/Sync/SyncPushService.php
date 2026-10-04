<?php

namespace App\Services\Sync;

use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Device;
use App\Models\OperationResult;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SyncConflict;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Devices\Exceptions\DeviceRevokedException;
use App\Services\Devices\Exceptions\LeaseExpiredException;
use App\Services\Devices\OfflineLeaseService;
use App\Services\Inventory\SaleReturnService;
use App\Services\Ledger\Exceptions\InsufficientAllocationException;
use App\Services\Ledger\Exceptions\InsufficientCreditAllocationException;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Operations\Exceptions\OperationException;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Operations\PayloadFingerprint;
use App\Services\Payments\CustomerPaymentService;
use App\Services\Sales\CreateSaleService;
use App\Services\Sync\Exceptions\RecoveryReconciliationRequiredException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class SyncPushService
{
    public function __construct(
        protected CreateSaleService $createSaleService,
        protected CustomerPaymentService $customerPaymentService,
        protected OfflineLeaseService $leaseService,
        protected SyncChangeLogService $changeLogService,
        protected InventoryLedgerService $inventoryLedgerService
    ) {}

    /**
     * Batch push: Offline to'plangan amallarni serverga qabul qilish va sinxronlash.
     *
     * @param  Device  $device  Sinxronlayotgan qurilma
     * @param  User  $user  Qurilmadagi xodim
     * @param  array  $operations  Amallar massivi
     * @param  string|null  $leaseToken  Qurilma ruxsat tokeni
     * @return array Har bir operatsiya uchun mustaqil natijalar massivi
     */
    public function pushBatch(Device $device, User $user, array $operations, ?string $leaseToken = null, bool $isReconciliation = false): array
    {
        abort_unless($user->isActive() && ($device->assigned_user_id === $user->id || $user->hasRole(['OWNER', 'ADMIN'])), 403);
        if (! $isReconciliation) {
            $recoveryStatus = SystemSetting::get('system_recovery_status', 'NORMAL');
            if ($recoveryStatus === 'RECONCILIATION_REQUIRED') {
                $recoveryEpoch = (int) SystemSetting::get('system_recovery_epoch', 1);
                $recoveryWatermark = SystemSetting::get('system_recovery_watermark', null);

                throw new RecoveryReconciliationRequiredException(
                    "Tizim zaxiradan tiklangan (Recovery Epoch: {$recoveryEpoch}). Oddiy sinxronizatsiya vaqtincha to'xtatildi. Iltimos, /api/sync/reconcile-recovery orqali amallarni muvofiqlashtiring.",
                    $recoveryEpoch,
                    $recoveryWatermark
                );
            }
        }

        $results = [];

        foreach ($operations as $op) {
            $opId = $op['operation_id'] ?? (string) Str::uuid();
            $type = strtoupper($op['type'] ?? $op['operation_type'] ?? '');
            if ($type === 'CANCEL_SALE') {
                $type = 'VOID_SALE';
            }
            $deviceCreatedAt = isset($op['device_created_at'])
                ? Carbon::parse($op['device_created_at'])
                : Carbon::now();
            $receivedAt = Carbon::now();
            $payload = $op['payload'] ?? [];

            // Kanonik barqaror fingerprint (Mappingdan oldingi asl xom payload asosida!)
            $canonicalFingerprint = PayloadFingerprint::compute($payload);

            // 1. Idempotentsiya tekshiruvi: timeout yoki qayta yuborish
            $existingOp = OperationResult::where('operation_id', $opId)->first();
            if ($existingOp) {
                if ($existingOp->payload_fingerprint === $canonicalFingerprint
                    && $existingOp->operation_type === $type
                    && (int) $existingOp->actor_id === $user->id
                    && (int) $existingOp->device_id === $device->id) {
                    $resPayload = $existingOp->result_payload ?: [];
                    $results[] = [
                        'operation_id' => $opId,
                        'status' => 'RETRY_SUCCESS',
                        'is_replay' => true,
                        'server_document_id' => $resPayload['server_document_id'] ?? $resPayload['sale_id'] ?? $resPayload['customer_id'] ?? $resPayload['payment_id'] ?? null,
                        'server_document_number' => $resPayload['server_document_number'] ?? $resPayload['invoice_number'] ?? $resPayload['payment_number'] ?? null,
                        'entity_type' => $resPayload['entity_type'] ?? null,
                        'entity_id' => $resPayload['entity_id'] ?? null,
                        'error_code' => null,
                        'message' => 'Operatsiya avval bajarilgan (Idempotent replay).',
                        'data' => $user->hasPermission('view_cost_price') ? $resPayload : $this->changeLogService->maskSensitiveFields($resPayload),
                    ];

                    continue;
                } else {
                    $results[] = [
                        'operation_id' => $opId,
                        'status' => 'CONFLICT',
                        'is_replay' => false,
                        'error_code' => 'PAYLOAD_MISMATCH',
                        'message' => "Operatsiya ID (#{$opId}) boshqa ma'lumotlar bilan allaqachon bajarilgan!",
                        'server_document_id' => null,
                        'server_document_number' => null,
                        'entity_type' => null,
                        'entity_id' => null,
                    ];

                    continue;
                }
            }

            // 2. Qurilma va Lease huquqlarini tekshirish
            if ($device->isRevoked() && $deviceCreatedAt->gte($device->updated_at)) {
                $conflict = SyncConflict::create([
                    'device_id' => $device->id,
                    'user_id' => $user->id,
                    'operation_id' => $opId,
                    'operation_type' => $type,
                    'status' => 'NEEDS_REVIEW',
                    'device_created_at' => $deviceCreatedAt,
                    'received_at' => $receivedAt,
                    'raw_payload' => $payload,
                    'payload_fingerprint' => $canonicalFingerprint,
                    'error_code' => 'DEVICE_REVOKED',
                    'error_message' => "Qurilma (#{$device->device_code}) bloklangan va operatsiya bloklangandan keyin yaratilgan!",
                ]);

                $results[] = [
                    'operation_id' => $opId,
                    'status' => 'NEEDS_REVIEW',
                    'conflict_id' => $conflict->id,
                    'error_code' => 'DEVICE_REVOKED',
                    'message' => 'Qurilma bloklangan! Operatsiya tekshiruv uchun saqlandi.',
                    'server_document_id' => null,
                    'server_document_number' => null,
                    'entity_type' => null,
                    'entity_id' => null,
                ];

                continue;
            }

            // 3. Har bir operatsiyani mustaqil tranzaksiyada bajarish (Per-item transaction)
            DB::beginTransaction();
            try {
                if (! Str::isUuid($opId)) {
                    throw new OperationValidationException($opId, 'Barqaror UUID talab qilinadi.', errorCode: 'INVALID_OPERATION_ID');
                }
                if (! $user->hasPermission('offline_sales')) {
                    throw new OperationValidationException($opId, 'Offline savdoga ruxsat yo‘q.', errorCode: 'PERMISSION_DENIED');
                }
                $this->leaseService->validateOperationPermitted($device, 'offline_sales', $deviceCreatedAt);
                if ($leaseToken && ! $device->offlineAuthorizations()->where('lease_token', $leaseToken)->where('user_id', $user->id)->exists()) {
                    throw new OperationValidationException($opId, 'Qurilma lease tokeni mos kelmadi.', errorCode: 'INVALID_LEASE');
                }
                if ($type === 'CREATE_CUSTOMER') {
                    $res = $this->handleCreateCustomer($device, $user, $opId, $payload, $canonicalFingerprint, $deviceCreatedAt, $receivedAt);
                } elseif ($type === 'CREATE_SALE') {
                    $res = $this->handleCreateSale($device, $user, $opId, $payload, $canonicalFingerprint, $deviceCreatedAt, $receivedAt);
                } elseif ($type === 'CUSTOMER_PAYMENT') {
                    $res = $this->handleCustomerPayment($device, $user, $opId, $payload, $canonicalFingerprint, $deviceCreatedAt, $receivedAt);
                } elseif ($type === 'VOID_SALE' || $type === 'CANCEL_SALE') {
                    $res = $this->handleVoidSale($device, $user, $opId, $payload, $canonicalFingerprint, $deviceCreatedAt, $receivedAt);
                } else {
                    throw new \InvalidArgumentException("Noma'lum operatsiya turi: '{$type}'");
                }

                DB::commit();
                $results[] = $res;
            } catch (\Throwable $e) {
                DB::rollBack();

                $errorCode = $e instanceof OperationException
                    ? $e->getErrorCode()
                    : (method_exists($e, 'getErrorCode') ? $e->getErrorCode() : ($e->errorCode ?? ($e->getCode() ?: 'OPERATION_FAILED')));
                $errorMsg = $e instanceof OperationException || $e instanceof \InvalidArgumentException
                    || $e instanceof InsufficientAllocationException || $e instanceof InsufficientCreditAllocationException
                    || $e instanceof DeviceRevokedException || $e instanceof LeaseExpiredException
                    ? $e->getMessage() : 'Operatsiyani bajarishda ichki xatolik yuz berdi.';

                // NEEDS_REVIEW toifasidagi xatolar:
                // Late closed session, lease/limit/mapping xatosi NEEDS_REVIEW; yozuv tashlab yuborilmaydi!
                $needsReviewCodes = [
                    'LATE_CLOSED_SESSION',
                    'CLOSED_SESSION_CANNOT_ACCEPT_OPERATIONS',
                    'INSUFFICIENT_ALLOCATION',
                    'INSUFFICIENT_CREDIT_ALLOCATION',
                    'CREDIT_LIMIT_EXCEEDED',
                    'CUSTOMER_NOT_FOUND',
                    'CUSTOMER_MAPPING_FAILED',
                    'LEASE_EXPIRED',
                    'INVALID_LEASE',
                    'DEVICE_REVOKED',
                ];

                $isNeedsReview = in_array($errorCode, $needsReviewCodes, true)
                    || ($e instanceof InsufficientAllocationException)
                    || ($e instanceof InsufficientCreditAllocationException)
                    || ($e instanceof DeviceRevokedException)
                    || ($e instanceof LeaseExpiredException);

                if ($isNeedsReview) {
                    $resolvedCode = $errorCode ?: 'NEEDS_REVIEW';
                    if ($e instanceof InsufficientAllocationException) {
                        $resolvedCode = 'INSUFFICIENT_ALLOCATION';
                    } elseif ($e instanceof InsufficientCreditAllocationException) {
                        $resolvedCode = 'INSUFFICIENT_CREDIT_ALLOCATION';
                    }

                    $conflict = SyncConflict::create([
                        'device_id' => $device->id,
                        'user_id' => $user->id,
                        'operation_id' => $opId,
                        'operation_type' => $type,
                        'status' => 'NEEDS_REVIEW',
                        'device_created_at' => $deviceCreatedAt,
                        'received_at' => $receivedAt,
                        'raw_payload' => $payload,
                        'payload_fingerprint' => $canonicalFingerprint,
                        'error_code' => $resolvedCode,
                        'error_message' => $errorMsg,
                    ]);

                    $results[] = [
                        'operation_id' => $opId,
                        'status' => 'NEEDS_REVIEW',
                        'conflict_id' => $conflict->id,
                        'error_code' => $resolvedCode,
                        'message' => $errorMsg,
                        'server_document_id' => null,
                        'server_document_number' => null,
                        'entity_type' => null,
                        'entity_id' => null,
                    ];
                } else {
                    $results[] = [
                        'operation_id' => $opId,
                        'status' => 'FAILED',
                        'error_code' => $errorCode ?: 'FAILED',
                        'message' => $errorMsg,
                        'server_document_id' => null,
                        'server_document_number' => null,
                        'entity_type' => null,
                        'entity_id' => null,
                    ];
                }
            }
        }

        // Heartbeat yangilash
        $device->update([
            'last_seen_at' => Carbon::now(),
            'last_ip_address' => request()->ip(),
        ]);

        return $user->hasPermission('view_cost_price') ? $results : $this->changeLogService->maskSensitiveFields($results);
    }

    /**
     * Yangi mijoz yaratish (UUID bilan, avtomatik merge yo'q).
     */
    protected function handleCreateCustomer(
        Device $device,
        User $user,
        string $operationId,
        array $payload,
        string $fingerprint,
        Carbon $deviceCreatedAt,
        Carbon $receivedAt
    ): array {
        $clientUuid = $payload['uuid'] ?? $payload['client_uuid'] ?? (string) Str::uuid();
        $name = trim($payload['name'] ?? '');
        $phone = isset($payload['phone']) ? trim($payload['phone']) : null;
        $storeName = isset($payload['store_name']) ? trim($payload['store_name']) : null;
        $address = isset($payload['address']) ? trim($payload['address']) : null;
        $debtLimit = (int) ($payload['debt_limit'] ?? 0);
        $isStrictCreditLimit = (bool) ($payload['is_strict_credit_limit'] ?? false);

        if (empty($name)) {
            throw new \InvalidArgumentException('Mijoz ismi kiritilishi shart!');
        }

        if (empty($phone) && empty($storeName) && empty($address)) {
            throw new \InvalidArgumentException("Telefon raqami bo'lmaganda do'kon nomi yoki manzil ko'rsatilishi shart!");
        }

        // Agar mijoz ushbu UUID bilan mavjud bo'lsa, mavjudini qaytarish
        $customer = Customer::where('uuid', $clientUuid)->first();
        if (! $customer) {
            // "o‘xshash telefon/nomsiz automerge yo‘q"
            $customer = Customer::create([
                'uuid' => $clientUuid,
                'name' => $name,
                'phone' => $phone,
                'store_name' => $storeName,
                'address' => $address,
                'debt_limit' => $debtLimit,
                'is_strict_credit_limit' => $isStrictCreditLimit,
                'current_debt' => 0,
                'status' => 'ACTIVE',
                'created_by' => $user->id,
            ]);

            // Change feedga yozish
            $this->changeLogService->logChange('CUSTOMER', $customer->id, 'CREATED', [
                'id' => $customer->id,
                'uuid' => $customer->uuid,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'store_name' => $customer->store_name,
                'address' => $customer->address,
                'debt_limit' => $customer->debt_limit,
                'is_strict_credit_limit' => $customer->is_strict_credit_limit,
                'current_debt' => 0,
                'status' => $customer->status,
            ]);
        }

        OperationResult::create([
            'operation_id' => $operationId,
            'operation_type' => 'CREATE_CUSTOMER',
            'payload_fingerprint' => $fingerprint,
            'actor_id' => $user->id,
            'device_id' => $device->id,
            'source' => $device->device_type ?: 'mobile',
            'status' => 'PROCESSED',
            'result_payload' => [
                'customer_id' => $customer->id,
                'uuid' => $customer->uuid,
                'name' => $customer->name,
                'server_document_id' => $customer->id,
                'server_document_number' => "CUST-{$customer->id}",
                'entity_type' => 'Customer',
                'entity_id' => $customer->id,
            ],
            'processed_at' => Carbon::now(),
        ]);

        return [
            'operation_id' => $operationId,
            'status' => 'APPLIED',
            'entity_type' => 'Customer',
            'entity_id' => $customer->id,
            'server_document_id' => $customer->id,
            'server_document_number' => "CUST-{$customer->id}",
            'data' => [
                'customer_id' => $customer->id,
                'uuid' => $customer->uuid,
                'name' => $customer->name,
            ],
            'error_code' => null,
            'message' => null,
        ];
    }

    /**
     * Savdo operatsiyasini qabul qilish.
     * Offline narx saqlanadi, server posting tartibida tannarx (WAC) hisoblanadi.
     */
    protected function handleCreateSale(
        Device $device,
        User $user,
        string $operationId,
        array $payload,
        string $fingerprint,
        Carbon $deviceCreatedAt,
        Carbon $receivedAt
    ): array {
        // 1. Mijoz bog'liqligini aniqlash (Customer Dependency)
        $customerId = null;
        if (! empty($payload['customer_client_uuid']) || ! empty($payload['customer_uuid'])) {
            $clientUuid = (string) ($payload['customer_client_uuid'] ?? $payload['customer_uuid']);
            $cust = Str::isUuid($clientUuid) ? Customer::where('uuid', $clientUuid)->first() : null;
            if (! $cust) {
                throw new OperationValidationException(
                    $operationId,
                    "Offline yaratilgan mijoz (#{$clientUuid}) topilmadi! Bog'liqlik xatosi.",
                    ['customer_client_uuid' => $clientUuid],
                    'CUSTOMER_NOT_FOUND'
                );
            }
            $customerId = $cust->id;
        } elseif (! empty($payload['customer_id'])) {
            $cust = Customer::find($payload['customer_id']);
            if (! $cust) {
                throw new OperationValidationException(
                    $operationId,
                    "Ko'rsatilgan mijoz (#{$payload['customer_id']}) topilmadi!",
                    ['customer_id' => $payload['customer_id']],
                    'CUSTOMER_NOT_FOUND'
                );
            }
            $customerId = $cust->id;
        }

        $paidAmount = (int) ($payload['paid_amount'] ?? 0);
        $cashAccountId = $payload['cash_account_id'] ?? null;
        $paymentMethod = strtoupper($payload['payment_method'] ?? 'CASH');

        // 2. Kassa smenasini tekshirish (Late closed session guard)
        if ($paidAmount > 0) {
            $cashAccount = null;
            if ($cashAccountId) {
                $cashAccount = CashAccount::find($cashAccountId);
            } else {
                $cashAccount = CashAccount::where('type', $paymentMethod)->where('is_default', true)->first()
                    ?: CashAccount::where('type', $paymentMethod)->first();
            }

            if ($cashAccount && $cashAccount->type === 'CASH') {
                $currentOpenSession = CashSession::where('cash_account_id', $cashAccount->id)
                    ->where('status', 'OPEN')
                    ->first();

                $latestClosedSession = CashSession::where('cash_account_id', $cashAccount->id)
                    ->where('status', 'CLOSED')
                    ->latest('closed_at')
                    ->first();

                // Agar hozir ochiq smena bo'lmasa
                if (! $currentOpenSession) {
                    throw new OperationValidationException(
                        $operationId,
                        'Kassa smenasi yopilgan! Naqd pul qabul qilish uchun ochiq smena mavjud emas.',
                        [
                            'cash_account_id' => $cashAccount->id,
                            'device_created_at' => $deviceCreatedAt->toIso8601String(),
                        ],
                        'LATE_CLOSED_SESSION'
                    );
                }

                // Agar savdo qilingan vaqt allaqachon yopilgan eski smena davriga to'g'ri kelsa
                if ($latestClosedSession && $latestClosedSession->closed_at && $deviceCreatedAt->lt($latestClosedSession->closed_at)) {
                    throw new OperationValidationException(
                        $operationId,
                        "Kech kelgan offline savdo: Ushbu savdo vaqti bo'yicha smena (#{$latestClosedSession->session_number}) allaqachon yopilgan!",
                        [
                            'session_id' => $latestClosedSession->id,
                            'session_number' => $latestClosedSession->session_number,
                            'closed_at' => $latestClosedSession->closed_at->toIso8601String(),
                            'device_created_at' => $deviceCreatedAt->toIso8601String(),
                        ],
                        'LATE_CLOSED_SESSION'
                    );
                }
            }
        }

        // 3. Stale narx qoidasi: Offline kelishilgan narx saqlanadi!
        $items = [];
        $staleNotes = [];

        if (empty($payload['items']) || ! is_array($payload['items'])) {
            throw new \InvalidArgumentException("Savdo qatorlari (items) bo'sh bo'lishi mumkin emas!");
        }

        foreach ($payload['items'] as $item) {
            $variantId = $item['variant_id'] ?? $item['product_variant_id'] ?? null;
            if (! $variantId) {
                throw new \InvalidArgumentException("Har bir qatorda variant_id yoki product_variant_id ko'rsatilishi shart!");
            }
            if (! ($item['is_system_price'] ?? false) && ! $user->hasPermission('custom_sale_price')) {
                throw new OperationValidationException($operationId, 'Kelishilgan narxda sotishga ruxsat yo‘q.', errorCode: 'PERMISSION_DENIED');
            }
            $qty = $item['quantity'] ?? 0;
            $variant = ProductVariant::findOrFail($variantId);

            $agreedPrice = isset($item['sale_price'])
                ? $item['sale_price']
                : ($item['unit_price'] ?? $variant->default_sale_price);

            if ((int) $variant->default_sale_price !== $agreedPrice) {
                $staleNotes[] = "Variant #{$variantId} ({$variant->sku}): offline narx {$agreedPrice} so'm saqlandi (server joriy: {$variant->default_sale_price} so'm)";
            }

            $items[] = [
                'variant_id' => $variantId,
                'quantity' => $qty,
                'sale_price' => $agreedPrice,
                'is_system_price' => false, // Kelishilgan offline narxni qat'iy saqlash
            ];
        }

        $saleNotes = $payload['notes'] ?? '';
        if (! empty($staleNotes)) {
            $saleNotes = trim($saleNotes."\n".implode('; ', $staleNotes));
        }

        // 4. CreateSaleService orqali atomik sotuvni amalga oshirish
        $paymentType = strtoupper($payload['payment_type'] ?? ($paidAmount === 0 && $customerId ? 'DEBT' : $paymentMethod));

        $sale = $this->createSaleService->execute(
            customerId: $customerId,
            items: $items,
            operationId: $operationId,
            paidAmount: $paidAmount,
            cashAccountId: $cashAccountId,
            paymentType: $paymentType,
            paymentMethod: $paymentMethod,
            notes: $saleNotes,
            warehouseId: $payload['warehouse_id'] ?? null,
            userId: $user->id,
            source: $device->device_type ?: 'pos',
            deviceId: $device->id,
            rawPayload: $payload
        );

        $postedAt = Carbon::now();

        // 5. Vaqtlarni ajratib qayd etish (device time, server received, server posted)
        $sale->update([
            'device_created_at' => $deviceCreatedAt,
            'received_at' => $receivedAt,
            'posted_at' => $postedAt,
        ]);

        // 6. Change feedga yozish
        $this->changeLogService->logChange('SALE', $sale->id, 'CREATED', [
            'id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'total_amount' => $sale->total_amount,
            'paid_amount' => $sale->paid_amount,
            'debt_amount' => $sale->debt_amount,
            'status' => $sale->status,
        ]);

        return [
            'operation_id' => $operationId,
            'status' => 'APPLIED',
            'server_document_id' => $sale->id,
            'server_document_number' => $sale->invoice_number,
            'entity_type' => 'Sale',
            'entity_id' => $sale->id,
            'data' => [
                'sale_id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'total_amount' => $sale->total_amount,
                'paid_amount' => $sale->paid_amount,
                'debt_amount' => $sale->debt_amount,
                'total_cost' => $sale->total_cost,
                'gross_profit' => $sale->gross_profit,
                'device_created_at' => $deviceCreatedAt->format('Y-m-d\TH:i:s\Z'),
                'received_at' => $receivedAt->format('Y-m-d\TH:i:s\Z'),
                'posted_at' => $postedAt->format('Y-m-d\TH:i:s\Z'),
            ],
            'error_code' => null,
            'message' => null,
        ];
    }

    /**
     * Mijoz qarz to'lovi amalini qabul qilish.
     */
    protected function handleCustomerPayment(
        Device $device,
        User $user,
        string $operationId,
        array $payload,
        string $fingerprint,
        Carbon $deviceCreatedAt,
        Carbon $receivedAt
    ): array {
        $customerId = null;
        if (! empty($payload['customer_client_uuid'])) {
            $clientUuid = (string) $payload['customer_client_uuid'];
            $cust = Str::isUuid($clientUuid) ? Customer::where('uuid', $clientUuid)->first() : null;
            if (! $cust) {
                throw new OperationValidationException(
                    $operationId,
                    "Offline yaratilgan mijoz (#{$clientUuid}) topilmadi!",
                    ['customer_client_uuid' => $clientUuid],
                    'CUSTOMER_NOT_FOUND'
                );
            }
            $customerId = $cust->id;
        } elseif (! empty($payload['customer_id'])) {
            $cust = Customer::find($payload['customer_id']);
            if (! $cust) {
                throw new OperationValidationException(
                    $operationId,
                    "Mijoz (#{$payload['customer_id']}) topilmadi!",
                    ['customer_id' => $payload['customer_id']],
                    'CUSTOMER_NOT_FOUND'
                );
            }
            $customerId = $cust->id;
        } else {
            throw new \InvalidArgumentException("To'lov uchun mijoz ko'rsatilishi shart!");
        }

        $rawAmount = $payload['amount'] ?? 0;
        if (! is_numeric($rawAmount) || $rawAmount != (int) $rawAmount || $rawAmount <= 0) {
            throw new OperationValidationException($operationId, 'To‘lov musbat butun so‘m bo‘lishi shart.', errorCode: 'INVALID_PAYMENT_AMOUNT');
        }
        $amount = (int) $rawAmount;
        $cashAccountId = $payload['cash_account_id'] ?? null;
        $paymentMethod = strtoupper($payload['payment_method'] ?? 'CASH');

        if (! $cashAccountId) {
            $defaultCash = CashAccount::where('type', $paymentMethod)->where('is_default', true)->first()
                ?: CashAccount::where('type', $paymentMethod)->first()
                ?: CashAccount::where('is_default', true)->first();
            $cashAccountId = $defaultCash ? $defaultCash->id : 1;
        }

        $res = $this->customerPaymentService->execute(
            customerId: $customerId,
            amount: $amount,
            cashAccountId: $cashAccountId,
            paymentMethod: $paymentMethod,
            operationId: $operationId,
            userId: $user->id,
            notes: $payload['notes'] ?? 'Offline mijoz to\'lovi',
            confirmExcessAsAdvance: (bool) ($payload['confirm_excess_advance'] ?? false),
            rawPayload: $payload,
            deviceId: $device->id
        );

        return [
            'operation_id' => $operationId,
            'status' => 'APPLIED',
            'server_document_id' => $res['payment_id'] ?? null,
            'server_document_number' => $res['payment_number'] ?? null,
            'entity_type' => 'Payment',
            'entity_id' => $res['payment_id'] ?? null,
            'data' => $res,
            'error_code' => null,
            'message' => null,
        ];
    }

    /**
     * Offline bekor qilingan savdoni serverda rasmiylashtirish (originalga bog'langan tuzatish).
     */
    protected function handleVoidSale(
        Device $device,
        User $user,
        string $operationId,
        array $payload,
        string $fingerprint,
        Carbon $deviceCreatedAt,
        Carbon $receivedAt
    ): array {
        $originalOpId = $payload['original_operation_id'] ?? null;
        if (! $user->hasPermission('process_refund')) {
            throw new OperationValidationException($operationId, 'Savdoni bekor qilishga ruxsat yo‘q.', errorCode: 'PERMISSION_DENIED');
        }
        $reason = $payload['reason'] ?? 'Offline bekor qilindi';

        if (! $originalOpId) {
            throw new OperationValidationException(
                $operationId,
                "Bekor qilinayotgan savdoning original_operation_id ko'rsatilishi shart!",
                ['payload' => $payload],
                'ORIGINAL_OPERATION_REQUIRED'
            );
        }

        // Asl savdoni topish
        $sale = Sale::where('operation_id', $originalOpId)->with('items')->lockForUpdate()->first();

        if ($sale && (int) $sale->created_by !== $user->id && ! $user->hasRole(['OWNER', 'ADMIN'])) {
            throw new OperationValidationException($operationId, 'Boshqa xodim savdosini bekor qilishga ruxsat yo‘q.', errorCode: 'PERMISSION_DENIED');
        }

        if (! $sale) {
            // Agar asl savdo serverda hali topilmasa:
            $conflict = SyncConflict::where('operation_id', $originalOpId)->first();
            if ($conflict) {
                $conflict->update([
                    'status' => 'CANCELLED',
                    'resolution_action' => 'CANCELLED_BY_CLIENT',
                    'resolved_at' => Carbon::now(),
                ]);
            }

            OperationResult::create([
                'operation_id' => $operationId,
                'operation_type' => 'VOID_SALE',
                'payload_fingerprint' => $fingerprint,
                'actor_id' => $user->id,
                'device_id' => $device->id,
                'source' => $device->device_type ?: 'pwa',
                'status' => 'PROCESSED',
                'result_payload' => [
                    'original_operation_id' => $originalOpId,
                    'status' => 'VOIDED_BEFORE_POSTING',
                    'reason' => $reason,
                ],
                'processed_at' => Carbon::now(),
            ]);

            return [
                'operation_id' => $operationId,
                'status' => 'APPLIED',
                'original_operation_id' => $originalOpId,
                'server_document_id' => null,
                'server_document_number' => null,
                'entity_type' => 'Sale',
                'entity_id' => null,
                'message' => 'Savdo serverga yetib kelmasdan bekor qilingan deb qayd etildi.',
                'error_code' => null,
            ];
        }

        // Agar savdo allaqachon bekor qilingan bo'lsa (idempotent replay)
        if ($sale->status === 'CANCELLED' || $sale->status === 'VOID') {
            return [
                'operation_id' => $operationId,
                'status' => 'RETRY_SUCCESS',
                'original_operation_id' => $originalOpId,
                'server_document_id' => $sale->id,
                'server_document_number' => $sale->invoice_number,
                'entity_type' => 'Sale',
                'entity_id' => $sale->id,
                'message' => 'Savdo avval bekor qilingan.',
                'error_code' => null,
            ];
        }

        // Use the same stock, debt and cash correction path as online returns.
        app(SaleReturnService::class)->createSaleReturn(
            saleId: $sale->id,
            items: $sale->items->map(fn ($item) => ['sale_item_id' => $item->id, 'quantity' => (int) $item->quantity])->all(),
            reason: $reason,
            operationId: (string) Uuid::uuid5($operationId, 'sale-return'),
            refundAmount: (int) $sale->paid_amount,
            cashAccountId: $sale->cash_account_id,
            refundPaymentMethod: $sale->payment_method,
            userId: $user->id
        );

        $sale->update([
            'status' => 'CANCELLED',
            'notes' => trim(($sale->notes ?? '')." | Bekor qilindi: {$reason}"),
        ]);

        // AuditLog
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'SALE_CANCEL',
            'auditable_type' => Sale::class,
            'auditable_id' => $sale->id,
            'old_values' => ['status' => 'COMPLETED'],
            'new_values' => ['status' => 'CANCELLED', 'reason' => $reason],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // ChangeLog
        $this->changeLogService->logChange('SALE', $sale->id, 'CANCELLED', [
            'id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'status' => 'CANCELLED',
            'reason' => $reason,
        ]);

        OperationResult::create([
            'operation_id' => $operationId,
            'operation_type' => 'VOID_SALE',
            'payload_fingerprint' => $fingerprint,
            'actor_id' => $user->id,
            'device_id' => $device->id,
            'source' => $device->device_type ?: 'pwa',
            'status' => 'PROCESSED',
            'result_payload' => [
                'original_operation_id' => $originalOpId,
                'sale_id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'status' => 'CANCELLED',
                'reason' => $reason,
            ],
            'processed_at' => Carbon::now(),
        ]);

        return [
            'operation_id' => $operationId,
            'status' => 'APPLIED',
            'original_operation_id' => $originalOpId,
            'server_document_id' => $sale->id,
            'server_document_number' => $sale->invoice_number,
            'entity_type' => 'Sale',
            'entity_id' => $sale->id,
            'message' => "Savdo #{$sale->invoice_number} muvaffaqiyatli bekor qilindi.",
            'error_code' => null,
        ];
    }
}

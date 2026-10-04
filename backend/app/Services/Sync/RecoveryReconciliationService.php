<?php

namespace App\Services\Sync;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\InventoryAllocation;
use App\Models\OperationResult;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Operations\PayloadFingerprint;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecoveryReconciliationService
{
    public function __construct(
        protected SyncPushService $pushService
    ) {}

    /**
     * Zaxiradan tiklanishdan keyin mijoz/qurilma operatsiyalarini muvofiqlashtirish (Reconciliation)
     *
     * @param Device $device
     * @param User $user
     * @param int $clientEpoch Mijoz bilgan recovery epoch
     * @param array $retainedOperations Mijoz outbox/ACK tarixida saqlangan amallar
     * @return array
     */
    public function reconcileDevice(Device $device, User $user, int $clientEpoch, array $retainedOperations): array
    {
        $serverEpoch = (int) SystemSetting::get('system_recovery_epoch', 1);
        $watermark = SystemSetting::get('system_recovery_watermark', null);
        $watermarkTime = $watermark ? Carbon::parse($watermark) : null;

        $results = [];
        $restoredCount = 0;
        $alreadyPersistedCount = 0;
        $conflictsCount = 0;

        foreach ($retainedOperations as $op) {
            $opId = $op['operation_id'] ?? (string) Str::uuid();
            $type = strtoupper($op['type'] ?? $op['operation_type'] ?? '');
            $payload = $op['payload'] ?? [];
            $deviceCreatedAt = isset($op['device_created_at'])
                ? Carbon::parse($op['device_created_at'])
                : Carbon::now();

            $canonicalFingerprint = PayloadFingerprint::compute($payload);

            // 1. Bazada mavjudligini tekshirish
            $existingOp = OperationResult::where('operation_id', $opId)->first();

            if ($existingOp) {
                if ($existingOp->payload_fingerprint === $canonicalFingerprint) {
                    $resPayload = $existingOp->result_payload ?: [];
                    $results[] = [
                        'operation_id' => $opId,
                        'status' => 'ALREADY_PERSISTED',
                        'is_replay' => true,
                        'server_document_id' => $resPayload['server_document_id'] ?? $resPayload['sale_id'] ?? $resPayload['customer_id'] ?? $resPayload['payment_id'] ?? null,
                        'server_document_number' => $resPayload['server_document_number'] ?? $resPayload['invoice_number'] ?? $resPayload['payment_number'] ?? null,
                        'message' => 'Operatsiya zaxirada mavjud (Already persisted in backup).',
                        'data' => $resPayload,
                    ];
                    $alreadyPersistedCount++;
                    continue;
                } else {
                    $results[] = [
                        'operation_id' => $opId,
                        'status' => 'CONFLICT_MISMATCH',
                        'is_replay' => false,
                        'message' => 'Operatsiya payload fingerprint mos kelmadi.',
                    ];
                    $conflictsCount++;
                    continue;
                }
            }

            // 2. Bazada yo'q bo'lsa (Zaxira olingandan keyin va tizim qulashidan oldin bajarilgan amal!)
            // Uni qayta tiklab bazaga qo'llaymiz
            DB::beginTransaction();
            try {
                $appliedResult = $this->pushService->pushBatch($device, $user, [$op], null, true);
                DB::commit();

                $firstResult = $appliedResult[0] ?? [];
                if (($firstResult['status'] ?? '') === 'APPLIED' || ($firstResult['status'] ?? '') === 'RETRY_SUCCESS') {
                    $firstResult['status'] = 'RESTORED_AND_APPLIED';
                    $firstResult['recovered_from_client'] = true;
                    $results[] = $firstResult;
                    $restoredCount++;
                } else {
                    $results[] = $firstResult;
                    $conflictsCount++;
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $results[] = [
                    'operation_id' => $opId,
                    'status' => 'RESTORE_FAILED',
                    'error' => $e->getMessage(),
                ];
                $conflictsCount++;
            }
        }

        // 3. Qurilmaning ombor ajratmalarini (Inventory Allocations) qayta hisoblash va muvofiqlashtirish
        $this->reconcileDeviceAllocations($device);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'DEVICE_RECOVERY_RECONCILED',
            'auditable_type' => Device::class,
            'auditable_id' => $device->id,
            'new_values' => [
                'device_code' => $device->device_code,
                'server_epoch' => $serverEpoch,
                'client_epoch' => $clientEpoch,
                'restored_count' => $restoredCount,
                'already_persisted_count' => $alreadyPersistedCount,
                'conflicts_count' => $conflictsCount,
            ],
            'created_at' => now(),
        ]);

        return [
            'success' => true,
            'server_recovery_epoch' => $serverEpoch,
            'watermark_at' => $watermark,
            'reconciled_total' => count($retainedOperations),
            'restored_and_applied_count' => $restoredCount,
            'already_persisted_count' => $alreadyPersistedCount,
            'conflicts_count' => $conflictsCount,
            'results' => $results,
        ];
    }

    /**
     * Qurilmaning tovar ajratmalarini (Inventory Allocations) haqiqiy sotuvlar bo'yicha qayta tekshirish
     */
    public function reconcileDeviceAllocations(Device $device): void
    {
        $allocations = InventoryAllocation::where('device_id', $device->id)
            ->where('status', 'ACTIVE')
            ->get();

        foreach ($allocations as $allocation) {
            // Ushbu qurilma va variant uchun tasdiqlangan barcha sotuvlar donasi
            $actualSold = (int) DB::table('sales')
                ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.device_id', $device->id)
                ->where('sales.status', 'COMPLETED')
                ->where('sale_items.product_variant_id', $allocation->product_variant_id)
                ->sum('sale_items.quantity');

            // Qaytarishlar donasi (sale_returns.sale_id orqali device_id ga bog'lanadi)
            $actualReturned = (int) DB::table('sale_returns')
                ->join('sale_return_items', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
                ->join('sales', 'sale_returns.sale_id', '=', 'sales.id')
                ->where('sales.device_id', $device->id)
                ->where('sale_returns.status', 'COMPLETED')
                ->where('sale_return_items.product_variant_id', $allocation->product_variant_id)
                ->sum('sale_return_items.quantity');

            $consumedNet = max(0, $actualSold - $actualReturned);

            if ($allocation->consumed_quantity !== $consumedNet) {
                $allocation->consumed_quantity = $consumedNet;
                $allocation->save();
            }
        }
    }

    /**
     * Barcha qurilmalar muvofiqlashtirilgach, tizim holatini NORMAL ga o'tkazish
     */
    public function markRecoveryCompleted(?int $userId = null): void
    {
        SystemSetting::set('system_recovery_status', 'NORMAL', $userId, 'Recovery reconciliation completed');

        AuditLog::create([
            'user_id' => $userId,
            'action' => 'SYSTEM_RECOVERY_COMPLETED',
            'auditable_type' => null,
            'auditable_id' => null,
            'new_values' => [
                'status' => 'NORMAL',
                'epoch' => (int) SystemSetting::get('system_recovery_epoch', 1),
            ],
            'created_at' => now(),
        ]);
    }
}

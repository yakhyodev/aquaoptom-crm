<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\OperationResult;
use App\Models\SyncConflict;
use App\Models\SystemSetting;
use App\Services\Sync\Exceptions\RecoveryReconciliationRequiredException;
use App\Services\Sync\RecoveryReconciliationService;
use App\Services\Sync\SyncBootstrapService;
use App\Services\Sync\SyncChangeLogService;
use App\Services\Sync\SyncConflictResolutionService;
use App\Services\Sync\SyncPushService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncApiController extends Controller
{
    public function __construct(
        protected SyncBootstrapService $bootstrapService,
        protected SyncChangeLogService $changeLogService,
        protected SyncPushService $pushService,
        protected SyncConflictResolutionService $conflictResolutionService,
        protected RecoveryReconciliationService $reconciliationService
    ) {}

    /**
     * GET /api/health
     * Ommaviy server health ping (tarmoq ulanishini tekshirish uchun)
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => 'OK',
            'server_time' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/sync/health
     * Himoyalangan sync server health tekshiruvi
     */
    public function health(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => 'OK',
            'server_time' => Carbon::now()->toIso8601String(),
            'database' => 'connected',
            'user_id' => $request->user()?->id,
            'recovery_epoch' => (int) SystemSetting::get('system_recovery_epoch', 1),
            'recovery_status' => (string) SystemSetting::get('system_recovery_status', 'NORMAL'),
            'recovery_watermark' => SystemSetting::get('system_recovery_watermark', null),
        ]);
    }

    /**
     * POST /api/sync/bootstrap
     * Qurilmani ishga tushirish, snapshot va kursor ma'lumotlarini olish.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $deviceUuid = $request->header('X-Device-UUID') ?: $request->input('device_uuid');

        if (! $deviceUuid) {
            $assigned = Device::where('assigned_user_id', $user->id)->where('is_active', true)->limit(2)->get();
            if ($assigned->count() !== 1) {
                return response()->json(['success' => false, 'message' => 'Bitta faol qurilma biriktiring yoki device_uuid yuboring.'], 422);
            }
            $deviceUuid = $assigned->first()->device_uuid;
        }

        $device = Device::where('device_uuid', $deviceUuid)->first();
        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => "Qurilma (#{$deviceUuid}) ro'yxatdan o'tmagan!",
            ], 404);
        }

        $data = $this->bootstrapService->getBootstrapData($device, $user);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * GET /api/sync/pull
     * Kursor bo'yicha serverdagi o'zgarishlarni tortib olish (Delta pull feed).
     */
    public function pull(Request $request): JsonResponse
    {
        $user = $request->user();
        $cursor = (int) $request->query('cursor', 0);
        $limit = (int) $request->query('limit', 50);

        $feed = $this->changeLogService->pullChanges($cursor, $limit, $user);

        return response()->json([
            'success' => true,
            'data' => $feed,
        ]);
    }

    /**
     * POST /api/sync/push
     * Offline to'plangan amallar batchini qabul qilish.
     */
    public function push(Request $request): JsonResponse
    {
        $user = $request->user();
        $deviceUuid = $request->header('X-Device-UUID') ?: $request->input('device_uuid');
        $leaseToken = $request->header('X-Lease-Token') ?: $request->input('lease_token');
        $operations = $request->input('operations', []);

        if (! $deviceUuid) {
            return response()->json([
                'success' => false,
                'message' => 'device_uuid talab qilinadi!',
            ], 422);
        }

        if (! is_array($operations)) {
            return response()->json([
                'success' => false,
                'message' => "operations massiv bo'lishi shart!",
            ], 422);
        }

        $device = Device::where('device_uuid', $deviceUuid)->first();
        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => "Qurilma (#{$deviceUuid}) topilmadi!",
            ], 404);
        }

        try {
            $results = $this->pushService->pushBatch($device, $user, $operations, $leaseToken);

            return response()->json([
                'success' => true,
                'results' => $results,
            ]);
        } catch (RecoveryReconciliationRequiredException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'RECOVERY_RECONCILIATION_REQUIRED',
                'message' => $e->getMessage(),
                'recovery_epoch' => $e->recoveryEpoch,
                'recovery_watermark' => $e->recoveryWatermark,
            ], 428);
        }
    }

    /**
     * POST /api/sync/reconcile-recovery
     * Zaxiradan tiklangan tizimda mijoz saqlangan amallarini (outbox/ACK history) qayta tiklash
     */
    public function reconcileRecovery(Request $request): JsonResponse
    {
        $user = $request->user();
        $deviceUuid = $request->header('X-Device-UUID') ?: $request->input('device_uuid');
        $clientEpoch = (int) $request->input('client_epoch', 1);
        $operations = $request->input('operations', []);

        if (! $deviceUuid) {
            return response()->json([
                'success' => false,
                'message' => 'device_uuid parametri yoki X-Device-UUID headeri talab qilinadi!',
            ], 422);
        }

        $device = Device::where('device_uuid', $deviceUuid)->first();
        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => "Qurilma (#{$deviceUuid}) topilmadi!",
            ], 404);
        }

        $result = $this->reconciliationService->reconcileDevice($device, $user, $clientEpoch, $operations);

        return response()->json($result);
    }

    /**
     * GET /api/sync/status/{operation_id}
     * Operatsiya holatini tekshirish.
     */
    public function status(string $operationId): JsonResponse
    {
        $op = OperationResult::where('operation_id', $operationId)->where('actor_id', auth()->id())->first();
        if ($op) {
            return response()->json([
                'success' => true,
                'operation_id' => $op->operation_id,
                'status' => $op->status,
                'operation_type' => $op->operation_type,
                'result_payload' => auth()->user()->hasPermission('view_cost_price') ? $op->result_payload : $this->changeLogService->maskSensitiveFields($op->result_payload ?? []),
                'error_code' => $op->error_code,
                'processed_at' => $op->processed_at?->format('Y-m-d\TH:i:s\Z'),
            ]);
        }

        $conflict = SyncConflict::where('operation_id', $operationId)->where('user_id', auth()->id())->first();
        if ($conflict) {
            return response()->json([
                'success' => true,
                'operation_id' => $conflict->operation_id,
                'status' => $conflict->status, // NEEDS_REVIEW, RESOLVED, REJECTED
                'operation_type' => $conflict->operation_type,
                'error_code' => $conflict->error_code,
                'error_message' => $conflict->error_message,
                'resolution_action' => $conflict->resolution_action,
                'created_at' => $conflict->created_at->format('Y-m-d\TH:i:s\Z'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Operatsiya (#{$operationId}) topilmadi!",
        ], 404);
    }

    /**
     * GET /api/sync/conflicts
     * Admin: Ko'rib chiqish kutilayotgan sinxronlash mojarolari ro'yxati.
     */
    public function conflicts(Request $request): JsonResponse
    {
        $status = $request->query('status', 'NEEDS_REVIEW');
        $deviceId = $request->query('device_id');

        $query = SyncConflict::with(['device', 'user', 'resolver'])
            ->orderBy('id', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        $conflicts = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $conflicts,
        ]);
    }

    /**
     * POST /api/sync/conflicts/{id}/resolve
     * Admin: Sinxronlash mojarosini hal qilish (APPROVED_OVERRIDE yoki REJECT).
     */
    public function resolveConflict(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $conflict = SyncConflict::findOrFail($id);

        $action = $request->input('action', 'APPROVED_OVERRIDE');
        $reason = $request->input('reason');
        $overrideData = $request->input('override_data', []);

        $resolved = $this->conflictResolutionService->resolveConflict(
            conflict: $conflict,
            admin: $admin,
            action: $action,
            overrideData: $overrideData,
            reason: $reason
        );

        return response()->json([
            'success' => true,
            'data' => $resolved,
            'message' => "Mojaro muvaffaqiyatli hal qilindi ({$resolved->status}).",
        ]);
    }
}

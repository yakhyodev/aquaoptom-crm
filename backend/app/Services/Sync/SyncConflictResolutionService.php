<?php

namespace App\Services\Sync;

use App\Models\AuditLog;
use App\Models\SyncConflict;
use App\Models\User;
use App\Services\Operations\Exceptions\OperationPermissionException;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Sales\CreateSaleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SyncConflictResolutionService
{
    public function __construct(
        protected CreateSaleService $createSaleService
    ) {}

    /**
     * Admin ko'rib hal qilish API'si (Conflict Resolution).
     *
     * @param  SyncConflict  $conflict  Hal qilinayotgan mojaro
     * @param  User  $admin  Vakolatli xodim
     * @param  string  $action  APPROVED_OVERRIDE yoki REJECT
     * @param  array|null  $overrideData  Qo'shimcha tuzatish ma'lumotlari
     * @param  string|null  $reason  Hal qilish sababi/izohi
     */
    public function resolveConflict(
        SyncConflict $conflict,
        User $admin,
        string $action,
        ?array $overrideData = null,
        ?string $reason = null
    ): SyncConflict {
        // Vakolat tekshiruvi
        $hasPermission = $admin->hasRole(['OWNER', 'ADMIN'])
            || $admin->hasPermission('manage_devices')
            || $admin->hasPermission('manage_settings')
            || $admin->hasPermission('reconcile_devices');

        if (! $hasPermission) {
            throw new OperationPermissionException(
                $conflict->operation_id,
                'Sinxronizatsiya mojarolarini hal qilish uchun vakolat yetarli emas!',
                ['admin_id' => $admin->id],
                'PERMISSION_DENIED'
            );
        }

        if ($conflict->status !== 'NEEDS_REVIEW') {
            throw new OperationValidationException(
                $conflict->operation_id,
                "Ushbu mojaro allaqachon ko'rib chiqilgan (Holati: {$conflict->status})!",
                ['conflict_id' => $conflict->id, 'status' => $conflict->status],
                'ALREADY_RESOLVED'
            );
        }

        $upperAction = strtoupper($action);

        return DB::transaction(function () use ($conflict, $admin, $upperAction, $overrideData, $reason) {
            $serverEntityType = null;
            $serverEntityId = null;

            if ($upperAction === 'APPROVED_OVERRIDE') {
                // Agar CREATE_SALE bo'lsa, admin ruxsati bilan majburiy post qilish mumkin
                if ($conflict->operation_type === 'CREATE_SALE') {
                    $rawPayload = $conflict->raw_payload;

                    // Override ma'lumotlari bilan to'ldirish (masalan boshqa kassa yoki ombor)
                    $warehouseId = $overrideData['warehouse_id'] ?? $rawPayload['warehouse_id'] ?? null;
                    $cashAccountId = $overrideData['cash_account_id'] ?? $rawPayload['cash_account_id'] ?? null;
                    $customerId = $overrideData['customer_id'] ?? $rawPayload['customer_id'] ?? null;

                    $items = [];
                    foreach ($rawPayload['items'] as $it) {
                        $items[] = [
                            'variant_id' => $it['variant_id'],
                            'quantity' => $it['quantity'],
                            'sale_price' => $it['sale_price'] ?? $it['unit_price'],
                            'is_system_price' => false,
                        ];
                    }

                    // Admin override orqali savdoni rasman o'tkazish
                    $sale = $this->createSaleService->execute(
                        customerId: $customerId,
                        items: $items,
                        operationId: $conflict->operation_id,
                        paidAmount: (int) ($rawPayload['paid_amount'] ?? 0),
                        cashAccountId: $cashAccountId,
                        warehouseId: $warehouseId,
                        userId: $admin->id,
                        source: 'admin_resolution',
                        deviceId: null, // Override orqali erkin qoldiqdan o'tkazish
                        notes: "Admin (#{$admin->name}) tomonidan konflikt yechildi. Sabab: ".($reason ?: 'Override')
                    );

                    $serverEntityType = 'Sale';
                    $serverEntityId = $sale->id;
                }

                $conflict->status = 'RESOLVED';
                $conflict->resolution_action = 'APPROVED_OVERRIDE';
            } elseif ($upperAction === 'REJECT') {
                $conflict->status = 'REJECTED';
                $conflict->resolution_action = 'REJECTED';
            } else {
                throw new \InvalidArgumentException("Noma'lum amal: '{$action}'. Faqat APPROVED_OVERRIDE yoki REJECT qabul qilinadi.");
            }

            // Asl raw_payload o'chirilmaydi va o'zgartirilmaydi!
            $conflict->resolution_notes = $reason;
            $conflict->resolved_by = $admin->id;
            $conflict->resolved_at = Carbon::now();
            $conflict->server_entity_type = $serverEntityType;
            $conflict->server_entity_id = $serverEntityId;
            $conflict->save();

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'SYNC_CONFLICT_RESOLVED',
                'auditable_type' => SyncConflict::class,
                'auditable_id' => $conflict->id,
                'old_values' => ['status' => 'NEEDS_REVIEW'],
                'new_values' => [
                    'status' => $conflict->status,
                    'action' => $conflict->resolution_action,
                    'reason' => $reason,
                    'server_entity_type' => $serverEntityType,
                    'server_entity_id' => $serverEntityId,
                ],
                'created_at' => Carbon::now(),
            ]);

            return $conflict;
        });
    }
}

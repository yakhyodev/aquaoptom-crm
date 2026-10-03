<?php

namespace App\Services\Devices;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use App\Services\Operations\DocumentNumberGenerator;
use App\Services\Operations\Exceptions\OperationValidationException;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DeviceService
{
    /**
     * Yangi qurilmani ro'yxatdan o'tkazish.
     */
    public function registerDevice(array $data, ?User $actor = null): Device
    {
        $deviceUuid = trim($data['device_uuid'] ?? (string) Str::uuid());

        if (Device::where('device_uuid', $deviceUuid)->exists()) {
            throw new OperationValidationException(
                operationId: (string) Str::uuid(),
                message: "Ushbu UUID (#{$deviceUuid}) bilan qurilma tizimda allaqachon mavjud!",
                errorCode: 'DEVICE_UUID_ALREADY_EXISTS'
            );
        }

        $deviceCode = $data['device_code'] ?? DocumentNumberGenerator::nextDeviceCode();
        if (Device::where('device_code', $deviceCode)->exists()) {
            $deviceCode = DocumentNumberGenerator::nextDeviceCode();
        }

        $name = trim($data['name'] ?? 'Yangi Qurilma');
        $deviceType = strtoupper($data['device_type'] ?? 'PC');
        if (! in_array($deviceType, ['PC', 'MOBILE', 'TABLET', 'POS_TERMINAL', 'OTHER'], true)) {
            $deviceType = 'OTHER';
        }

        $allowNewCustDebt = (bool) ($data['allow_new_offline_customer_debt'] ?? false);
        $newCustDebtBudget = max(0, (int) ($data['new_customer_debt_budget'] ?? 0));

        $device = Device::create([
            'device_uuid' => $deviceUuid,
            'device_code' => $deviceCode,
            'name' => $name,
            'device_type' => $deviceType,
            'status' => 'ACTIVE',
            'is_active' => true,
            'registered_by' => $actor?->id,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'current_lease_epoch' => 1,
            'allow_new_offline_customer_debt' => $allowNewCustDebt,
            'new_customer_debt_budget' => $newCustDebtBudget,
            'new_customer_debt_consumed' => 0,
            'app_version' => $data['app_version'] ?? '1.0.0',
            'last_seen_at' => Carbon::now(),
            'last_ip' => $data['last_ip'] ?? request()->ip(),
            'notes' => $data['notes'] ?? null,
        ]);

        AuditLog::create([
            'user_id' => $actor?->id,
            'action' => 'DEVICE_REGISTERED',
            'auditable_type' => Device::class,
            'auditable_id' => $device->id,
            'new_values' => [
                'device_uuid' => $deviceUuid,
                'device_code' => $deviceCode,
                'name' => $name,
                'device_type' => $deviceType,
            ],
            'ip_address' => request()->ip(),
            'created_at' => Carbon::now(),
        ]);

        return $device;
    }

    /**
     * Qurilmani bloklash va barcha faol leaselarni bekor qilish.
     */
    public function revokeDevice(Device $device, User $actor, string $reason): void
    {
        $device->update([
            'status' => 'REVOKED',
            'is_active' => false,
            'current_lease_epoch' => $device->current_lease_epoch + 1,
            'notes' => trim($device->notes."\n[REVOKED: {$reason}]"),
        ]);

        // Qurilmaning barcha faol leaselarini bekor qilish
        $device->authorizations()
            ->where('is_revoked', false)
            ->update([
                'is_revoked' => true,
                'revoked_at' => Carbon::now(),
                'revoked_by' => $actor->id,
                'revocation_reason' => $reason,
            ]);

        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'DEVICE_REVOKED',
            'auditable_type' => Device::class,
            'auditable_id' => $device->id,
            'new_values' => [
                'reason' => $reason,
                'new_epoch' => $device->current_lease_epoch,
            ],
            'ip_address' => request()->ip(),
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Qurilmani yo'qolgan deb belgilash.
     * DIQQAT: Avtomatik rezerv qaytarilmaydi! Manual reconcile talab etiladi.
     */
    public function markDeviceLost(Device $device, User $actor, string $reason): void
    {
        $device->update([
            'status' => 'LOST',
            'is_active' => false,
            'current_lease_epoch' => $device->current_lease_epoch + 1,
            'notes' => trim($device->notes."\n[LOST: {$reason}]"),
        ]);

        // Faol leaselarni bekor qilish
        $device->authorizations()
            ->where('is_revoked', false)
            ->update([
                'is_revoked' => true,
                'revoked_at' => Carbon::now(),
                'revoked_by' => $actor->id,
                'revocation_reason' => "Qurilma yo'qolgan: {$reason}",
            ]);

        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'DEVICE_MARKED_LOST',
            'auditable_type' => Device::class,
            'auditable_id' => $device->id,
            'new_values' => [
                'reason' => $reason,
                'requires_manual_reconcile' => true,
            ],
            'ip_address' => request()->ip(),
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Heartbeat qayd qilish (oxirgi aloqa va IP).
     */
    public function recordHeartbeat(Device $device, ?string $ip = null, ?string $appVersion = null): void
    {
        $device->touchLastSeen($ip, $appVersion);
    }
}

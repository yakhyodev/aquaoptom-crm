<?php

namespace App\Services\Devices;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\OfflineAuthorization;
use App\Models\User;
use App\Services\Devices\Exceptions\DeviceRevokedException;
use App\Services\Devices\Exceptions\InvalidLeaseException;
use App\Services\Devices\Exceptions\LeaseExpiredException;
use Carbon\Carbon;
use Illuminate\Support\Str;

class OfflineLeaseService
{
    /**
     * Qurilmaga imzolangan muddatli ruxsat guvohnomasi (Lease Snapshot) berish.
     *
     * @param  array|null  $permissions  Maxsus huquqlar ro'yxati (null bo'lsa xodimning joriy ruxsatlari olinadi)
     * @param  int  $durationHours  Amal qilish muddati (soat)
     */
    public function issueLease(
        Device $device,
        User $user,
        ?array $permissions = null,
        int $durationHours = 24,
        ?User $actor = null
    ): array {
        if (! $device->isActive()) {
            throw new DeviceRevokedException(
                message: "Qurilma (#{$device->device_code}) faol emas yoki bloklangan! Unga ruxsat berib bo'lmaydi."
            );
        }

        // Agar maxsus permissions berilmagan bo'lsa, xodimning ruxsatlarini aniqlash
        if ($permissions === null) {
            $userPerms = [];
            // Barcha offline uchun zarur ruxsatlarni tekshirish
            $checkPerms = [
                'offline_sales',
                'sell_on_credit',
                'custom_sale_price',
                'view_cost_price',
                'view_debts',
                'receive_stock',
            ];
            foreach ($checkPerms as $perm) {
                if ($user->hasPermission($perm)) {
                    $userPerms[] = $perm;
                }
            }
            $permissions = $userPerms;
        }

        sort($permissions);

        $leaseToken = (string) Str::uuid();
        $validFrom = Carbon::now()->startOfSecond();
        $expiresAt = $validFrom->copy()->addHours($durationHours);
        $epoch = (int) $device->current_lease_epoch;

        // Kanonik payload (Imzolash uchun)
        $canonicalData = [
            'device_uuid' => $device->device_uuid,
            'device_code' => $device->device_code,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'lease_token' => $leaseToken,
            'permissions' => $permissions,
            'valid_from' => $validFrom->format('Y-m-d\TH:i:s\Z'),
            'expires_at' => $expiresAt->format('Y-m-d\TH:i:s\Z'),
            'epoch' => $epoch,
        ];

        $signature = $this->signPayload($canonicalData);

        // Baza yozuvi
        $auth = OfflineAuthorization::create([
            'device_id' => $device->id,
            'user_id' => $user->id,
            'lease_token' => $leaseToken,
            'permissions' => $permissions,
            'valid_from' => $validFrom,
            'expires_at' => $expiresAt,
            'signature' => $signature,
            'epoch' => $epoch,
            'is_revoked' => false,
        ]);

        AuditLog::create([
            'user_id' => $actor?->id ?: $user->id,
            'action' => 'LEASE_ISSUED',
            'auditable_type' => OfflineAuthorization::class,
            'auditable_id' => $auth->id,
            'new_values' => [
                'device_id' => $device->id,
                'device_code' => $device->device_code,
                'user_id' => $user->id,
                'lease_token' => $leaseToken,
                'duration_hours' => $durationHours,
                'expires_at' => $expiresAt->format('Y-m-d\TH:i:s\Z'),
                'epoch' => $epoch,
            ],
            'ip_address' => request()->ip(),
            'created_at' => Carbon::now(),
        ]);

        return [
            'lease_token' => $leaseToken,
            'device_uuid' => $device->device_uuid,
            'device_code' => $device->device_code,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'permissions' => $permissions,
            'valid_from' => $validFrom->format('Y-m-d\TH:i:s\Z'),
            'expires_at' => $expiresAt->format('Y-m-d\TH:i:s\Z'),
            'epoch' => $epoch,
            'signature' => $signature,
            'authorization_id' => $auth->id,
        ];
    }

    /**
     * Imzolangan leaseni tekshirish (Signature, Expiration, Revocation).
     */
    public function verifyLease(
        Device $device,
        string $leaseToken,
        string $signature,
        ?Carbon $asOfTime = null
    ): array {
        $asOf = $asOfTime ?: Carbon::now();

        $auth = OfflineAuthorization::where('device_id', $device->id)
            ->where('lease_token', $leaseToken)
            ->first();

        if (! $auth) {
            return [
                'is_valid' => false,
                'reason' => 'LEASE_NOT_FOUND',
                'authorization' => null,
            ];
        }

        if ($auth->is_revoked) {
            return [
                'is_valid' => false,
                'reason' => 'LEASE_REVOKED',
                'authorization' => $auth,
            ];
        }

        if (! $device->isActive()) {
            return [
                'is_valid' => false,
                'reason' => 'DEVICE_INACTIVE_OR_REVOKED',
                'authorization' => $auth,
            ];
        }

        // Imzoni tekshirish
        $canonicalData = [
            'device_uuid' => $device->device_uuid,
            'device_code' => $device->device_code,
            'user_id' => $auth->user_id,
            'user_name' => $auth->user->name,
            'lease_token' => $auth->lease_token,
            'permissions' => $auth->permissions,
            'valid_from' => $auth->valid_from->format('Y-m-d\TH:i:s\Z'),
            'expires_at' => $auth->expires_at->format('Y-m-d\TH:i:s\Z'),
            'epoch' => $auth->epoch,
        ];

        $expectedSig = $this->signPayload($canonicalData);
        if (! hash_equals($expectedSig, $signature)) {
            return [
                'is_valid' => false,
                'reason' => 'SIGNATURE_MISMATCH',
                'authorization' => $auth,
            ];
        }

        // Muddat tekshiruvi
        if ($asOf->gt($auth->expires_at)) {
            return [
                'is_valid' => false,
                'reason' => 'LEASE_EXPIRED',
                'authorization' => $auth,
            ];
        }

        if ($asOf->lt($auth->valid_from)) {
            return [
                'is_valid' => false,
                'reason' => 'LEASE_NOT_YET_VALID',
                'authorization' => $auth,
            ];
        }

        return [
            'is_valid' => true,
            'reason' => null,
            'authorization' => $auth,
        ];
    }

    /**
     * Yangi operatsiya bajarish mumkinligini tekshirish.
     * DIQQAT: Revoke yoki Expiry yangi savdoni to'xtatadi,
     * ammo ushbu vaqtdan oldin lokal yaratilgan pending savdoni rad etmaydi!
     */
    public function validateOperationPermitted(
        Device $device,
        string $permission,
        ?Carbon $operationTime = null,
        ?int $userId = null
    ): void {
        $opTime = $operationTime ?: Carbon::now();

        if (! $device->isActive() && $opTime->gte($device->updated_at)) {
            throw new DeviceRevokedException(
                message: "Qurilma (#{$device->device_code}) bloklangan! Yangi operatsiyalar qabul qilinmaydi."
            );
        }

        // Qurilmaning opTime vaqtidagi eng so'nggi leaseni qidirish
        $auth = OfflineAuthorization::where('device_id', $device->id)
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->where('valid_from', '<=', $opTime)
            ->latest('id')
            ->first();

        if (! $auth) {
            throw new InvalidLeaseException(
                message: 'Qurilma uchun faol ruxsat guvohnomasi (lease) topilmadi!'
            );
        }

        // Agar lease operatsiya vaqtidan oldin bekor qilingan bo'lsa
        if ($auth->is_revoked && $auth->revoked_at && $opTime->gte($auth->revoked_at)) {
            throw new InvalidLeaseException(
                message: 'Qurilma ruxsat guvohnomasi bekor qilingan! Yangi operatsiya taqiqlanadi.'
            );
        }

        // Agar lease operatsiya vaqtidan oldin muddati tugagan bo'lsa
        if ($opTime->gt($auth->expires_at)) {
            throw new LeaseExpiredException(
                message: 'Qurilma ruxsat guvohnomasi muddati tugagan! Yangi operatsiya taqiqlanadi.'
            );
        }

        // Huquq mavjudligini tekshirish
        if (! $auth->hasPermission($permission)) {
            throw new InvalidLeaseException(
                message: "Qurilma ruxsat guvohnomasida '{$permission}' amalini bajarish huquqi mavjud emas!"
            );
        }
    }

    /**
     * Ruxsat guvohnomasini bekor qilish.
     */
    public function revokeLease(OfflineAuthorization $lease, User $actor, string $reason): void
    {
        $lease->update([
            'is_revoked' => true,
            'revoked_at' => Carbon::now(),
            'revoked_by' => $actor->id,
            'revocation_reason' => $reason,
        ]);

        $device = $lease->device;
        $device->increment('current_lease_epoch');

        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'LEASE_REVOKED',
            'auditable_type' => OfflineAuthorization::class,
            'auditable_id' => $lease->id,
            'new_values' => [
                'device_id' => $device->id,
                'device_code' => $device->device_code,
                'lease_token' => $lease->lease_token,
                'reason' => $reason,
                'new_epoch' => $device->current_lease_epoch,
            ],
            'ip_address' => request()->ip(),
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Kanonik ma'lumotni server maxfiy kaliti bilan HMAC-SHA256 orqali imzolash.
     */
    protected function signPayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $key = config('app.key') ?: 'aquaoptom-secret-signing-key';

        return hash_hmac('sha256', $json, $key);
    }
}

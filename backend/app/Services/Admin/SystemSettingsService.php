<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;

class SystemSettingsService
{
    /**
     * Barcha tizim sozlamalarini olish.
     */
    public function getAll(): array
    {
        $settings = SystemSetting::all()->pluck('value', 'key')->toArray();

        return array_merge([
            'store_name' => "AquaOptom Suv Do'koni",
            'store_phone' => '+998712000000',
            'store_address' => 'Toshkent shahri, Chilonzor tumani',
            'timezone' => 'Asia/Tashkent',
            'low_stock_threshold' => 10,
            'strict_credit_mode' => false,
            'offline_reconcile_timeout_hours' => 24,
        ], $settings);
    }

    /**
     * Bitta parametrni olish.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return SystemSetting::get($key, $default);
    }

    /**
     * Bir nechta parametrlarni yangilash va auditga yozish.
     */
    public function updateSettings(array $newSettings, User $admin): array
    {
        $updated = [];

        foreach ($newSettings as $key => $value) {
            $setting = SystemSetting::firstOrNew(['key' => $key]);
            $oldValue = $setting->exists ? $setting->value : null;

            // Tip konversiyalari
            if ($key === 'low_stock_threshold' || $key === 'offline_reconcile_timeout_hours') {
                $value = (int) $value;
            } elseif ($key === 'strict_credit_mode') {
                $value = (bool) $value;
            }

            if ($oldValue !== $value) {
                $setting->value = $value;
                $setting->updated_by = $admin->id;
                $setting->save();

                AuditLog::create([
                    'user_id' => $admin->id,
                    'action' => 'SYSTEM_SETTING_CHANGED',
                    'auditable_type' => SystemSetting::class,
                    'auditable_id' => $setting->id,
                    'old_values' => ['key' => $key, 'value' => $oldValue],
                    'new_values' => ['key' => $key, 'value' => $value],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'created_at' => now(),
                ]);
            }

            $updated[$key] = $value;
        }

        return $updated;
    }
}

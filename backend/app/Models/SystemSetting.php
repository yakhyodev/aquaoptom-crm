<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
        'updated_by',
    ];

    protected $casts = [
        'value' => 'json',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get setting value by key with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Set setting value by key, record audit log if changed.
     */
    public static function set(string $key, mixed $value, ?int $userId = null, ?string $description = null): static
    {
        $setting = static::firstOrNew(['key' => $key]);
        $oldValue = $setting->exists ? $setting->value : null;

        $setting->value = $value;
        if ($description) {
            $setting->description = $description;
        }
        $setting->updated_by = $userId;
        $setting->save();

        if ($oldValue !== $value) {
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'SYSTEM_SETTING_CHANGED',
                'auditable_type' => static::class,
                'auditable_id' => $setting->id,
                'old_values' => ['key' => $key, 'value' => $oldValue],
                'new_values' => ['key' => $key, 'value' => $value],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_at' => now(),
            ]);
        }

        return $setting;
    }
}

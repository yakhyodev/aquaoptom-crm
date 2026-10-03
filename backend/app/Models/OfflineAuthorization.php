<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineAuthorization extends Model
{
    protected $fillable = [
        'device_id',
        'user_id',
        'lease_token',
        'permissions',
        'valid_from',
        'expires_at',
        'signature',
        'epoch',
        'is_revoked',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
    ];

    protected $casts = [
        'permissions' => 'array',
        'valid_from' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'is_revoked' => 'boolean',
        'epoch' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isValid(?Carbon $at = null): bool
    {
        $at = $at ?: Carbon::now();

        if ($this->is_revoked) {
            return false;
        }

        return $at->gte($this->valid_from) && $at->lte($this->expires_at);
    }

    public function isExpired(?Carbon $at = null): bool
    {
        $at = $at ?: Carbon::now();

        return $at->gt($this->expires_at);
    }

    public function hasPermission(string $permission): bool
    {
        $perms = $this->permissions ?? [];

        return in_array($permission, $perms, true);
    }
}

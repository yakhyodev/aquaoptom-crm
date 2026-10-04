<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'device_uuid',
        'device_code',
        'name',
        'device_type',
        'status',
        'is_active',
        'registered_by',
        'assigned_user_id',
        'current_lease_epoch',
        'allow_new_offline_customer_debt',
        'new_customer_debt_budget',
        'new_customer_debt_consumed',
        'app_version',
        'last_seen_at',
        'last_sync_at',
        'last_ip',
        'notes',
        'freeze_requested_at',
        'freeze_acknowledged_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_new_offline_customer_debt' => 'boolean',
        'current_lease_epoch' => 'integer',
        'new_customer_debt_budget' => 'integer',
        'new_customer_debt_consumed' => 'integer',
        'last_seen_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'freeze_requested_at' => 'datetime',
        'freeze_acknowledged_at' => 'datetime',
    ];

    public function registrant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function authorizations(): HasMany
    {
        return $this->hasMany(OfflineAuthorization::class);
    }

    public function offlineAuthorizations(): HasMany
    {
        return $this->authorizations();
    }

    public function activeAuthorization(): HasOne
    {
        return $this->hasOne(OfflineAuthorization::class)
            ->where('is_revoked', false)
            ->where('expires_at', '>', Carbon::now())
            ->latestOfMany();
    }

    public function inventoryAllocations(): HasMany
    {
        return $this->hasMany(InventoryAllocation::class);
    }

    public function activeInventoryAllocations(): HasMany
    {
        return $this->hasMany(InventoryAllocation::class)->where('status', 'ACTIVE');
    }

    public function creditAllocations(): HasMany
    {
        return $this->hasMany(CreditAllocation::class);
    }

    public function activeCreditAllocations(): HasMany
    {
        return $this->hasMany(CreditAllocation::class)->where('status', 'ACTIVE');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function cursor(): HasOne
    {
        return $this->hasOne(DeviceCursor::class);
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(SyncConflict::class);
    }

    public function isActive(): bool
    {
        return $this->is_active && $this->status === 'ACTIVE';
    }

    public function isRevoked(): bool
    {
        return $this->status === 'REVOKED';
    }

    public function isLost(): bool
    {
        return $this->status === 'LOST';
    }

    public function isReconciled(): bool
    {
        return $this->status === 'RECONCILED';
    }

    public function touchLastSeen(?string $ip = null, ?string $version = null): void
    {
        $updateData = ['last_seen_at' => Carbon::now()];
        if ($ip) {
            $updateData['last_ip'] = $ip;
        }
        if ($version) {
            $updateData['app_version'] = $version;
        }
        $this->update($updateData);
    }
}

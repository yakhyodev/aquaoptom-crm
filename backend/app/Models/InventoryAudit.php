<?php

namespace App\Models;

use App\Services\Ledger\Exceptions\DocumentImmutableException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAudit extends Model
{
    protected $fillable = [
        'operation_id',
        'audit_number',
        'warehouse_id',
        'status', // PREPARED, COUNTING, COMPLETED, CANCELLED
        'device_freeze_status', // NOT_REQUIRED, PENDING_ACK, ACKNOWLEDGED, FORCE_CONFIRMED
        'total_expected_qty',
        'total_counted_qty',
        'total_discrepancy_qty',
        'total_discrepancy_value',
        'notes',
        'created_by',
        'completed_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_expected_qty' => 'decimal:3',
        'total_counted_qty' => 'decimal:3',
        'total_discrepancy_qty' => 'decimal:3',
        'total_discrepancy_value' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (InventoryAudit $audit) {
            if ($audit->status === 'COMPLETED') {
                throw new DocumentImmutableException(
                    "Tasdiqlangan inventarizatsiya hujjati (#{$audit->audit_number}) o'chirilishi mumkin emas! Tarixiy hujjatlar o'zgarmasdir."
                );
            }
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryAuditItem::class);
    }
}

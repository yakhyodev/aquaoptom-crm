<?php

namespace App\Models;

use App\Services\Ledger\Exceptions\DocumentImmutableException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DamageRecord extends Model
{
    protected $fillable = [
        'operation_id',
        'damage_number',
        'warehouse_id',
        'total_loss_value',
        'total_quantity',
        'status',
        'reason',
        'notes',
        'created_by',
        'posted_at',
    ];

    protected $casts = [
        'total_loss_value' => 'integer',
        'total_quantity' => 'decimal:3',
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (DamageRecord $record) {
            if ($record->status === 'POSTED') {
                throw new DocumentImmutableException(
                    "Tasdiqlangan brak / yaroqsiz tovar chiqimi hujjati (#{$record->damage_number}) o'chirilishi mumkin emas! Tarixiy hujjatlar o'zgarmasdir."
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

    public function items(): HasMany
    {
        return $this->hasMany(DamageItem::class);
    }
}

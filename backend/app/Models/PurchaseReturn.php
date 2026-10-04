<?php

namespace App\Models;

use App\Services\Ledger\Exceptions\DocumentImmutableException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends Model
{
    protected $fillable = [
        'operation_id',
        'return_number',
        'purchase_id',
        'supplier_id',
        'warehouse_id',
        'total_credit_amount',
        'total_cost_amount',
        'cost_discrepancy',
        'cash_account_id',
        'status',
        'reason',
        'notes',
        'created_by',
        'posted_at',
    ];

    protected $casts = [
        'total_credit_amount' => 'integer',
        'total_cost_amount' => 'integer',
        'cost_discrepancy' => 'integer',
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (PurchaseReturn $return) {
            if ($return->status === 'POSTED') {
                throw new DocumentImmutableException(
                    "Tasdiqlangan ta'minotchiga qaytarish hujjati (#{$return->return_number}) o'chirilishi mumkin emas! Tarixiy hujjatlar o'zgarmasdir."
                );
            }
        });
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}

<?php

namespace App\Models;

use App\Services\Ledger\Exceptions\DocumentImmutableException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    protected $fillable = [
        'operation_id',
        'return_number',
        'sale_id',
        'customer_id',
        'warehouse_id',
        'total_amount',
        'total_cost',
        'refund_amount',
        'debt_deduction_amount',
        'cash_account_id',
        'cash_session_id',
        'refund_payment_method',
        'status',
        'reason',
        'notes',
        'created_by',
        'posted_at',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'total_cost' => 'integer',
        'refund_amount' => 'integer',
        'debt_deduction_amount' => 'integer',
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (SaleReturn $return) {
            if ($return->status === 'POSTED') {
                throw new DocumentImmutableException(
                    "Tasdiqlangan sotuv qaytarish hujjati (#{$return->return_number}) o'chirilishi mumkin emas! Tarixiy hujjatlar o'zgarmasdir."
                );
            }
        });
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}

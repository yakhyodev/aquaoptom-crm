<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLedger extends Model
{
    protected $table = 'customer_ledger';

    public $timestamps = false;

    protected $fillable = [
        'operation_id',
        'customer_id',
        'type', // SALE, PAYMENT, RETURN, ADJUSTMENT, OPENING_BALANCE
        'payment_method', // CASH, CARD, BANK, OFFSET
        'debit',
        'credit',
        'balance_after',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'debit' => 'integer',
        'credit' => 'integer',
        'balance_after' => 'integer',
        'created_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

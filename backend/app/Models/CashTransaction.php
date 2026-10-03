<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cash_account_id',
        'type', // SALE_PAYMENT, CUSTOMER_PAYMENT, EXPENSE, WITHDRAWAL, DEPOSIT, REFUND
        'amount',
        'reference_type',
        'reference_id',
        'description',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'created_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }
}

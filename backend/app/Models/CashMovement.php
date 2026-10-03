<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    protected $table = 'cash_movements';

    public $timestamps = false;

    protected $fillable = [
        'operation_id',
        'cash_account_id',
        'cash_session_id',
        'type', // SALE_PAYMENT, CUSTOMER_PAYMENT, SUPPLIER_PAYMENT, EXPENSE, TRANSFER_IN, TRANSFER_OUT, OWNER_DEPOSIT, OWNER_WITHDRAWAL, REFUND, ADJUSTMENT, OPENING_BALANCE
        'direction', // IN, OUT
        'debit',
        'credit',
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'debit' => 'integer',
        'credit' => 'integer',
        'amount' => 'integer',
        'balance_after' => 'integer',
        'created_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

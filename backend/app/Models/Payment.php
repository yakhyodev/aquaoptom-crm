<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'payment_number',
        'operation_id',
        'party_type', // CUSTOMER, SUPPLIER, EXPENSE, OWNER, NONE
        'party_id',
        'cash_account_id',
        'payment_type', // CUSTOMER_PAYMENT, SUPPLIER_PAYMENT, EXPENSE, OWNER_DRAW, OWNER_DEPOSIT, REFUND, OPENING_BALANCE
        'payment_method', // CASH, CARD, BANK
        'direction', // IN, OUT
        'amount',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'party_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'party_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'party_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

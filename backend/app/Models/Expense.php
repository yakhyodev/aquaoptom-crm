<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'category',
        'cash_account_id',
        'amount',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }
}

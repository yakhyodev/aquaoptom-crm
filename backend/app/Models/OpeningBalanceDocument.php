<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalanceDocument extends Model
{
    protected $fillable = [
        'document_number',
        'operation_id',
        'type', // STOCK, CASH, CUSTOMER, SUPPLIER, BATCH
        'total_amount',
        'total_items',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'total_items' => 'integer',
        'created_by' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

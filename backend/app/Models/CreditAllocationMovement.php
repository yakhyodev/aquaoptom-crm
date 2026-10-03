<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditAllocationMovement extends Model
{
    protected $fillable = [
        'credit_allocation_id',
        'device_id',
        'customer_id',
        'operation_id',
        'movement_type',
        'amount',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(CreditAllocation::class, 'credit_allocation_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

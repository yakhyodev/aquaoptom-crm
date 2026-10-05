<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'operation_id',
        'invoice_number',
        'supplier_invoice_number',
        'supplier_id',
        'warehouse_id',
        'status', // DRAFT, POSTED, CANCELLED
        'total_amount',
        'paid_amount',
        'debt_amount',
        'posted_at',
        'source',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'paid_amount' => 'integer',
        'debt_amount' => 'integer',
        'posted_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

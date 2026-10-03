<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number',
        'customer_id',
        'warehouse_id',
        'status', // COMPLETED, CANCELLED, VOID
        'total_amount',
        'total_cost',
        'gross_profit',
        'payment_type', // CASH, CARD, BANK, DEBT, MIXED
        'source',
        'created_by',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'total_cost' => 'integer',
        'gross_profit' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public static function generateInvoiceNumber(): string
    {
        $lastId = static::max('id') ?? 0;

        return sprintf('INV-%s-%06d', date('Y'), $lastId + 1);
    }
}

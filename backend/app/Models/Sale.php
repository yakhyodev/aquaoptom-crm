<?php

namespace App\Models;

use App\Services\Operations\DocumentNumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'operation_id',
        'invoice_number',
        'customer_id',
        'warehouse_id',
        'status', // COMPLETED, CANCELLED, VOID
        'total_amount',
        'paid_amount',
        'debt_amount',
        'cash_account_id',
        'payment_type', // CASH, CARD, BANK, DEBT, MIXED
        'payment_method', // CASH, CARD, BANK
        'total_cost',
        'gross_profit',
        'source',
        'notes',
        'receipt_data',
        'created_by',
        'completed_at',
        'goods_picked_up_at',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'paid_amount' => 'integer',
        'debt_amount' => 'integer',
        'total_cost' => 'integer',
        'gross_profit' => 'integer',
        'receipt_data' => 'array',
        'completed_at' => 'datetime',
        'goods_picked_up_at' => 'datetime',
    ];

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

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getSaleIdAttribute(): int
    {
        return $this->id;
    }

    public function getCustomerNameAttribute(): string
    {
        return $this->customer ? $this->customer->name : 'Noma\'lum xaridor';
    }

    public static function generateInvoiceNumber(): string
    {
        return DocumentNumberGenerator::nextSaleInvoiceNumber();
    }
}

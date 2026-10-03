<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierLedger extends Model
{
    protected $table = 'supplier_ledger';

    public $timestamps = false;

    protected $fillable = [
        'operation_id',
        'supplier_id',
        'type', // PURCHASE, PAYMENT, RETURN, ADJUSTMENT, OPENING_BALANCE
        'payment_method', // CASH, CARD, BANK, OFFSET
        'debit', // Bizning to'lovimiz (qarz kamayishi)
        'credit', // Ta'minotchi tovari (qarz ko'payishi)
        'balance_after', // Musbat = bizning qarzimiz, manfiy = avansimiz (haqdorligimiz)
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}

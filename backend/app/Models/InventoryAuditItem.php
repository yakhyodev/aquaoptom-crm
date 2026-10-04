<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAuditItem extends Model
{
    protected $fillable = [
        'inventory_audit_id',
        'product_variant_id',
        'expected_quantity',
        'counted_quantity',
        'discrepancy_quantity',
        'unit_cost',
        'discrepancy_value',
        'reason',
        'status',
    ];

    protected $casts = [
        'expected_quantity' => 'decimal:3',
        'counted_quantity' => 'decimal:3',
        'discrepancy_quantity' => 'decimal:3',
        'unit_cost' => 'integer',
        'discrepancy_value' => 'integer',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(InventoryAudit::class, 'inventory_audit_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}

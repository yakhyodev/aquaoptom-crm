<?php

namespace App\Events;

use App\Models\ProductVariant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowStockDetected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ProductVariant $variant,
        public int $currentQuantity,
        public int $threshold,
        public ?int $warehouseId = null
    ) {}
}

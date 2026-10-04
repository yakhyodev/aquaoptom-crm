<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockChangedBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public int $productVariantId,
        public int $quantityChange,
        public string $reason
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('store.operations'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'event' => 'STOCK_CHANGED',
            'product_variant_id' => $this->productVariantId,
            'quantity_change' => $this->quantityChange,
            'reason' => $this->reason,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

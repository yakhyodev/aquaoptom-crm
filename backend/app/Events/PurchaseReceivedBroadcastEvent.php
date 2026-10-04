<?php

namespace App\Events;

use App\Models\Purchase;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseReceivedBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public Purchase $purchase
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('store.operations'),
            new PrivateChannel('store.finance'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'event' => 'PURCHASE_RECEIVED',
            'purchase_id' => $this->purchase->id,
            'invoice_number' => $this->purchase->invoice_number,
            'supplier_name' => $this->purchase->supplier?->name ?? 'Noma\'lum',
            'paid_amount' => (int) $this->purchase->paid_amount,
            'debt_amount' => (int) $this->purchase->debt_amount,
            'received_at' => $this->purchase->created_at?->toIso8601String(),
        ];
    }
}

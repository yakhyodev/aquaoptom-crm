<?php

namespace App\Events;

use App\Models\Sale;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleCreatedBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public Sale $sale
    ) {}

    /**
     * Qaysi xususiy kanallarga tarqatiladi.
     * Umumiy operatsiyalar (barcha xodimlar) va maxfiy moliya (faqat ruxsatlilar).
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('store.operations'),
            new PrivateChannel('store.finance'),
        ];
    }

    /**
     * Tarqatiladigan ma'lumotlar.
     */
    public function broadcastWith(): array
    {
        return [
            'event' => 'SALE_CREATED',
            'sale_id' => $this->sale->id,
            'invoice_number' => $this->sale->invoice_number,
            'customer_name' => $this->sale->customer?->name ?? 'Tezkor xaridor',
            'total_amount' => (int) $this->sale->total_amount,
            'paid_amount' => (int) $this->sale->paid_amount,
            'debt_amount' => (int) $this->sale->debt_amount,
            'payment_type' => $this->sale->payment_type,
            'completed_at' => $this->sale->completed_at?->toIso8601String(),
        ];
    }
}

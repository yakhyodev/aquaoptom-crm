<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentRecordedBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public Payment $payment
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
            'event' => 'PAYMENT_RECORDED',
            'payment_id' => $this->payment->id,
            'payment_number' => $this->payment->payment_number,
            'direction' => $this->payment->direction,
            'amount' => (int) $this->payment->amount,
            'payment_method' => $this->payment->payment_method,
            'created_at' => $this->payment->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Events;

use App\Models\SyncConflict;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NeedsReviewCreatedBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public SyncConflict $conflict
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
            'event' => 'NEEDS_REVIEW_CREATED',
            'conflict_id' => $this->conflict->id,
            'operation_id' => $this->conflict->operation_id,
            'operation_type' => $this->conflict->operation_type,
            'error_code' => $this->conflict->error_code,
            'created_at' => $this->conflict->created_at?->toIso8601String(),
        ];
    }
}

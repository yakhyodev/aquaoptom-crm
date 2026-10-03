<?php

namespace App\Jobs;

use App\Services\Operations\OutboxProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessOutboxJob implements ShouldQueue
{
    use Queueable;

    public function handle(OutboxProcessor $processor): void
    {
        $processor->processPending();
    }
}

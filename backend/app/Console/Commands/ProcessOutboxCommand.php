<?php

namespace App\Console\Commands;

use App\Services\Operations\OutboxProcessor;
use Illuminate\Console\Command;

class ProcessOutboxCommand extends Command
{
    protected $signature = 'app:process-outbox {--limit=50 : Batch size} {--watch : Run continuously}';

    protected $description = 'Kutilayotgan outbox hodisalarini qayta ishlash';

    public function handle(OutboxProcessor $processor): int
    {
        $limit = max(1, min(200, (int) $this->option('limit')));
        do {
            $count = $processor->processPending($limit);
            if (! $this->option('watch')) {
                $this->info("Outbox: {$count} ta hodisa muvaffaqiyatli qayta ishlandi.");
            } elseif ($count === 0) {
                sleep(2);
            }
        } while ($this->option('watch'));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Operations\OutboxProcessor;
use Illuminate\Console\Command;

class ProcessOutboxCommand extends Command
{
    protected $signature = 'app:process-outbox {--limit=50 : Qayta ishlanadigan hodisalar limiti}';

    protected $description = 'Kutilayotgan outbox hodisalarini qayta ishlash';

    public function handle(OutboxProcessor $processor): int
    {
        $limit = (int) $this->option('limit');
        $count = $processor->processPending($limit);

        $this->info("Outbox: {$count} ta hodisa muvaffaqiyatli qayta ishlandi.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Services\Operations;

use App\Models\OutboxEvent;
use App\Services\Telegram\TelegramNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

class OutboxProcessor
{
    /**
     * Kutilayotgan (PENDING) outbox hodisalarini qayta ishlash.
     * Concurrency-safe: PostgreSQL'da FOR UPDATE SKIP LOCKED ishlatiladi.
     */
    public function processPending(int $limit = 50): int
    {
        $processedCount = 0;

        return DB::transaction(function () use ($limit, &$processedCount) {
            $query = OutboxEvent::where('status', 'PENDING')
                ->whereColumn('retry_count', '<', 'max_retries')
                ->orderBy('id', 'asc')
                ->limit($limit);

            // PostgreSQL'da parallel workerlar bir-birini bloklamasligi uchun SKIP LOCKED
            if (DB::getDriverName() === 'pgsql') {
                $query->lock('FOR UPDATE SKIP LOCKED');
            }

            $events = $query->get();

            foreach ($events as $event) {
                // Allaqachon qayta ishlangan bo'lsa (Idempotent consumer)
                if ($event->status === 'PUBLISHED') {
                    continue;
                }

                try {
                    // Hodisani Laravel Event tizimi orqali tarqatamiz
                    Event::dispatch($event->event_name, [
                        'event_id' => $event->event_id,
                        'operation_id' => $event->operation_id,
                        'aggregate_type' => $event->aggregate_type,
                        'aggregate_id' => $event->aggregate_id,
                        'payload' => $event->payload,
                    ]);

                    app(TelegramNotificationService::class)->notifyOutboxEvent($event);

                    $event->update([
                        'status' => 'PUBLISHED',
                        'published_at' => now(),
                    ]);

                    $processedCount++;
                } catch (Throwable $e) {
                    $retryCount = $event->retry_count + 1;
                    $status = $retryCount >= $event->max_retries ? 'FAILED' : 'PENDING';

                    $event->update([
                        'retry_count' => $retryCount,
                        'status' => $status,
                        'last_error' => get_class($e),
                    ]);

                    Log::error('Outbox processing failed', ['event_id' => $event->event_id, 'exception_class' => get_class($e)]);
                }
            }

            return $processedCount;
        });
    }
}

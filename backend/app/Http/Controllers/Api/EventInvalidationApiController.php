<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OutboxEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventInvalidationApiController extends Controller
{
    /**
     * GET /api/events/invalidation
     * Mobil (Flutter) va PWA ilovalari uchun real-vaqt keshini yangilash (Cache Invalidation) API.
     * Reconnect bo'lganda oxirgi kursor yuboriladi va server nimalar o'zgarganini qaytaradi.
     */
    public function index(Request $request): JsonResponse
    {
        $cursor = (int) $request->query('cursor', 0);
        $limit = min(200, max(1, (int) $request->query('limit', 100)));

        $query = OutboxEvent::where('id', '>', $cursor)
            ->orderBy('id', 'asc')
            ->limit($limit);

        $events = $query->get();

        $invalidatedKeys = [];
        $eventSummaries = [];
        $nextCursor = $cursor;

        foreach ($events as $event) {
            $nextCursor = max($nextCursor, $event->id);
            $resources = $this->mapEventToResources($event->event_name, $event->aggregate_type);

            foreach ($resources as $res) {
                if (! in_array($res, $invalidatedKeys, true)) {
                    $invalidatedKeys[] = $res;
                }
            }

            $eventSummaries[] = [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'aggregate_type' => $event->aggregate_type,
                'aggregate_id' => $event->aggregate_id,
                'resources' => $resources,
                'created_at' => $event->created_at?->toIso8601String(),
            ];
        }

        // Agar hech qanday yangi hodisa bo'lmasa ham joriy maksimal ID ni kursor sifatida taqdim etish mumkin
        if ($events->isEmpty() && $cursor === 0) {
            $nextCursor = (int) (OutboxEvent::max('id') ?? 0);
        }

        return response()->json([
            'success' => true,
            'cursor' => $nextCursor,
            'has_more' => $events->count() >= $limit,
            'invalidated' => $invalidatedKeys,
            'events_count' => $events->count(),
            'events' => $eventSummaries,
            'server_time' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * Hodisa nomi va agregat turini kesh resurslariga moslash.
     *
     * @return array<string>
     */
    protected function mapEventToResources(string $eventName, string $aggregateType): array
    {
        $name = strtolower($eventName);
        $type = strtolower($aggregateType);

        $resources = [];

        if (str_contains($name, 'sale') || str_contains($type, 'sale')) {
            $resources = array_merge($resources, ['sales', 'stock', 'cash', 'debts']);
        }

        if (str_contains($name, 'purchase') || str_contains($type, 'purchase') || str_contains($name, 'inward')) {
            $resources = array_merge($resources, ['purchases', 'stock', 'suppliers', 'cash']);
        }

        if (str_contains($name, 'payment') || str_contains($type, 'payment') || str_contains($name, 'cash')) {
            $resources = array_merge($resources, ['cash', 'debts', 'suppliers']);
        }

        if (str_contains($name, 'stock') || str_contains($name, 'inventory') || str_contains($name, 'damage') || str_contains($name, 'return')) {
            $resources = array_merge($resources, ['stock']);
        }

        if (str_contains($name, 'customer') || str_contains($type, 'customer')) {
            $resources = array_merge($resources, ['customers', 'debts']);
        }

        if (str_contains($name, 'price') || str_contains($name, 'product')) {
            $resources = array_merge($resources, ['products', 'prices']);
        }

        if (str_contains($name, 'device') || str_contains($name, 'allocation')) {
            $resources = array_merge($resources, ['devices', 'allocations']);
        }

        if (empty($resources)) {
            $resources = ['general'];
        }

        return array_values(array_unique($resources));
    }
}

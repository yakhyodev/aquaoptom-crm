<?php

namespace App\Services\Operations;

use App\Models\AuditLog;
use App\Models\OutboxEvent;
use Illuminate\Support\Str;

class OperationContext
{
    /** @var array<int, array> */
    protected array $queuedEvents = [];

    /** @var array<int, array> */
    protected array $auditLogs = [];

    public function __construct(
        public readonly string $operationId,
        public readonly ?int $actorId = null,
        public readonly ?string $deviceId = null,
        public readonly string $source = 'web',
        public readonly ?int $expectedVersion = null
    ) {}

    /**
     * Tranzaksiya ichida Outbox hodisasini navbatga qo'shish
     */
    public function enqueueEvent(
        string $eventName,
        string $aggregateType,
        string|int $aggregateId,
        array $payload
    ): void {
        $this->queuedEvents[] = [
            'event_id' => (string) Str::uuid(),
            'operation_id' => $this->operationId,
            'event_name' => $eventName,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => (string) $aggregateId,
            'payload' => $payload,
            'status' => 'PENDING',
            'created_at' => now(),
        ];
    }

    /**
     * Tranzaksiya ichida AuditLog qo'shish
     */
    public function logAudit(
        string $action,
        ?string $auditableType = null,
        ?int $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $this->auditLogs[] = [
            'user_id' => $this->actorId,
            'operation_id' => $this->operationId,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'created_at' => now(),
        ];
    }

    /**
     * Barcha outbox hodisalarini bazaga bitta tranzaksiyada yozish
     */
    public function flushOutboxAndAudit(): void
    {
        foreach ($this->queuedEvents as $event) {
            OutboxEvent::create($event);
        }

        foreach ($this->auditLogs as $audit) {
            AuditLog::create($audit);
        }
    }
}

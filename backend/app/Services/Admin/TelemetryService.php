<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\OutboxEvent;
use App\Models\ReportExport;
use App\Models\SyncConflict;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TelemetryService
{
    /**
     * Tizim infratuzilmasi va monitoring holatini olish.
     */
    public function getTelemetry(): array
    {
        // 1. Outbox hodisalar navbati
        $pendingOutbox = OutboxEvent::where('status', 'PENDING')->count();
        $failedOutbox = OutboxEvent::where('status', 'FAILED')->count();
        $publishedOutbox = OutboxEvent::where('status', 'PUBLISHED')->count();
        $lastPublished = OutboxEvent::where('status', 'PUBLISHED')->latest('published_at')->first();

        $workerHealth = 'IDLE';
        if ($pendingOutbox > 0) {
            $workerHealth = 'PROCESSING';
        } elseif ($lastPublished && Carbon::parse($lastPublished->published_at)->diffInMinutes() < 10) {
            $workerHealth = 'HEALTHY';
        }

        // 2. Ma'lumotlar bazasi (PostgreSQL)
        $dbStatus = 'CONNECTED';
        $dbSize = 'Noma\'lum';
        try {
            if (DB::getDriverName() === 'pgsql') {
                $dbName = config('database.connections.pgsql.database');
                $sizeResult = DB::select('SELECT pg_size_pretty(pg_database_size(?)) as size', [$dbName]);
                $dbSize = $sizeResult[0]->size ?? 'Noma\'lum';
            }
        } catch (\Throwable $e) {
            $dbStatus = 'ERROR';
        }

        // 3. Redis / Kesh
        $redisStatus = 'UNKNOWN';
        try {
            Redis::connection()->ping();
            $redisStatus = 'CONNECTED';
        } catch (\Throwable $e) {
            $redisStatus = 'OFFLINE';
        }

        // 4. Oxirgi Eksport / Zaxira nusxasi
        $lastExport = ReportExport::latest()->first();
        $lastBackup = AuditLog::where('action', 'BACKUP_CREATED')->latest('created_at')->first();
        $lastOffsite = AuditLog::where('action', 'BACKUP_OFFSITE_COPIED')->latest('created_at')->first();
        $lastDrill = AuditLog::where('action', 'RESTORE_DRILL_PASSED')->latest('created_at')->first();
        $lastFailure = AuditLog::whereIn('action', ['BACKUP_FAILED', 'BACKUP_OFFSITE_FAILED', 'RESTORE_DRILL_FAILED'])->latest('created_at')->first();
        $latestSuccess = match ($lastFailure?->action) {
            'BACKUP_OFFSITE_FAILED' => $lastOffsite?->created_at,
            'RESTORE_DRILL_FAILED' => $lastDrill?->created_at,
            default => $lastBackup?->created_at,
        };
        $backupIssue = $lastFailure && (! $latestSuccess || $lastFailure->created_at->greaterThanOrEqualTo($latestSuccess));

        // 5. Qurilmalar holati
        $totalDevices = Device::count();
        $activeDevices = Device::where('status', 'ACTIVE')->count();
        $staleDevices = Device::where('status', 'ACTIVE')
            ->where(function ($q) {
                $q->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', now()->subHours(24));
            })->count();

        // 6. Ko'rib chiqish kutilayotgan konfliktlar
        $pendingConflicts = SyncConflict::where('status', 'NEEDS_REVIEW')->count();

        return [
            'outbox' => [
                'pending' => $pendingOutbox,
                'failed' => $failedOutbox,
                'published' => $publishedOutbox,
                'worker_health' => $workerHealth,
                'last_published_at' => $lastPublished?->published_at?->format('d.m.Y H:i:s'),
            ],
            'database' => [
                'status' => $dbStatus,
                'size' => $dbSize,
                'driver' => DB::getDriverName(),
            ],
            'redis' => [
                'status' => $redisStatus,
                'host' => config('database.redis.default.host', '127.0.0.1'),
                'port' => config('database.redis.default.port', 6379),
            ],
            'broadcasting' => [
                'driver' => config('broadcasting.default', 'log'),
                'channels' => ['store.operations', 'store.finance'],
            ],
            'backup' => [
                'last_export_at' => $lastExport?->created_at?->format('d.m.Y H:i:s') ?? 'Mavjud emas',
                'total_exports' => ReportExport::count(),
                'last_backup_at' => $lastBackup?->created_at?->timezone('Asia/Tashkent')->format('d.m.Y H:i:s') ?? 'Hali yaratilmagan',
                'last_offsite_at' => $lastOffsite?->created_at?->timezone('Asia/Tashkent')->format('d.m.Y H:i:s') ?? 'Hali ko‘chirilmagan',
                'last_drill_at' => $lastDrill?->created_at?->timezone('Asia/Tashkent')->format('d.m.Y H:i:s') ?? 'Hali tekshirilmagan',
                'offsite_configured' => (bool) config('backup.offsite_disk'),
                'needs_attention' => $backupIssue || ! config('backup.offsite_disk') || ! $lastDrill || $lastDrill->created_at->lt(now()->subDays(config('backup.drill_max_age_days', 30))) || ! $lastBackup || $lastBackup->created_at->lt(now()->subMinutes(30))
                    || (config('backup.offsite_disk') && (! $lastOffsite || $lastOffsite->created_at->lt(now()->subMinutes(30)))),
            ],
            'devices' => [
                'total' => $totalDevices,
                'active' => $activeDevices,
                'stale' => $staleDevices,
            ],
            'conflicts' => [
                'needs_review' => $pendingConflicts,
            ],
        ];
    }
}

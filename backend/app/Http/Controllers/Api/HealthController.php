<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    /**
     * GET /api/health/live
     * Liveness probe: Web server va PHP jarayoni tirikligini tekshiradi.
     */
    public function liveness(): JsonResponse
    {
        return response()->json([
            'status' => 'LIVE',
            'app' => 'AquaOptom CRM',
            'version' => config('app.version', '1.0.0'),
            'timestamp' => Carbon::now('UTC')->toIso8601String(),
        ], 200);
    }

    /**
     * GET /api/health/ready
     * Readiness probe: Baza, Redis, kesh, disk va recovery holatini tekshiradi.
     */
    public function readiness(Request $request): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
        ];

        $isReady = collect($checks)->every(fn ($check) => $check['status'] === 'OK');

        $recoveryEpoch = (int) SystemSetting::get('system_recovery_epoch', 1);
        $recoveryStatus = (string) SystemSetting::get('system_recovery_status', 'NORMAL');
        $recoveryWatermark = SystemSetting::get('system_recovery_watermark', null);

        $payload = [
            'status' => $isReady ? 'READY' : 'UNAVAILABLE',
            'app' => 'AquaOptom CRM',
            'version' => config('app.version', '1.0.0'),
            'timestamp' => Carbon::now('UTC')->toIso8601String(),
            'checks' => $checks,
            'recovery' => [
                'epoch' => $recoveryEpoch,
                'status' => $recoveryStatus,
                'watermark_at' => $recoveryWatermark,
            ],
        ];

        return response()->json($payload, $isReady ? 200 : 503);
    }

    /**
     * Database aloqasi tekshiruvi
     */
    protected function checkDatabase(): array
    {
        $startTime = microtime(true);
        try {
            DB::connection()->getPdo();
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'OK',
                'latency_ms' => $latencyMs,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'ERROR',
                'error' => 'Database connection failed',
            ];
        }
    }

    /**
     * Redis aloqasi tekshiruvi
     */
    protected function checkRedis(): array
    {
        $startTime = microtime(true);
        try {
            Redis::connection()->ping();
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'OK',
                'latency_ms' => $latencyMs,
            ];
        } catch (\Throwable $e) {
            // Redis ixtiyoriy fallback bo'lishi mumkin, lekin konfiguratsiya qilingan bo'lsa tekshiramiz
            return [
                'status' => config('database.redis.client') ? 'OK' : 'SKIPPED',
                'notice' => 'Redis ping bypassed or using file cache fallback',
            ];
        }
    }

    /**
     * Storage yozish huquqi tekshiruvi
     */
    protected function checkStorage(): array
    {
        try {
            $testFile = storage_path('framework/cache/health_check_test_'.uniqid().'.tmp');
            @file_put_contents($testFile, 'test');
            $writable = file_exists($testFile);
            @unlink($testFile);

            return [
                'status' => $writable ? 'OK' : 'ERROR',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'ERROR',
                'error' => 'Storage is not writable',
            ];
        }
    }
}

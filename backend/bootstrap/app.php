<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireRole;
use App\Models\ReportExport;
use App\Services\Telegram\TelegramNotificationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->preventRequestForgery(except: ['telegram/webhook', 'api/telegram/webhook']);
        $middleware->alias([
            'role' => RequireRole::class,
            'permission' => RequirePermission::class,
            'active' => EnsureUserIsActive::class,
        ]);

        $middleware->appendToGroup('web', [
            EnsureUserIsActive::class,
        ]);

        $middleware->appendToGroup('api', [
            EnsureUserIsActive::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('app:backup-create')->everyFifteenMinutes()->withoutOverlapping(30)->onOneServer();
        $schedule->call(function (): void {
            ReportExport::where('expires_at', '<=', now())->where('status', 'COMPLETED')
                ->chunkById(100, function ($exports): void {
                    foreach ($exports as $export) {
                        if (preg_match('/^exports\/[a-f0-9-]{36}\.(xlsx|csv|pdf)$/', $export->file_path)
                            && (! Storage::disk('local')->exists($export->file_path)
                                || Storage::disk('local')->delete($export->file_path))) {
                            $export->update(['status' => 'EXPIRED']);
                        }
                    }
                });
        })->name('reports:prune-expired')->daily()->withoutOverlapping()->onOneServer();

        $schedule->call(fn () => app(TelegramNotificationService::class)->retryFailedDeliveries())
            ->name('telegram:retry-failed-deliveries')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

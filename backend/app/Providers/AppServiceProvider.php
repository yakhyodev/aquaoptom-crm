<?php

namespace App\Providers;

use App\Livewire\Inventory\StockManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate Limiters
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
        // Prohibit destructive database commands in production
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Authorization Gates: Owner has superuser access
        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'isOwner') && $user->isOwner()) {
                return true;
            }

            return null;
        });

        // Granular permission gates
        $permissions = [
            'view_cost_price',
            'manage_prices',
            'sell_below_cost',
            'sell_on_credit',
            'custom_sale_price',
            'process_refund',
            'manage_cash_outflow',
            'offline_sales',
            'manage_users',
            'view_reports',
            'receive_stock',
            'stock_adjustment',
            'view_cash',
            'view_debts',
            'manage_settings',
        ];

        foreach ($permissions as $permission) {
            Gate::define($permission, function ($user) use ($permission) {
                return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
            });
        }

        // Livewire Component Aliases
        Livewire::component('stock-manager', StockManager::class);

        // Guard testing environment: strictly ensure test database is isolated
        if ($this->app->environment('testing')) {
            $database = config('database.connections.'.config('database.default').'.database');
            if (! empty($database) && $database !== ':memory:' && ! str_contains($database, 'test')) {
                throw new \RuntimeException(
                    "CRITICAL SAFETY VIOLATION: Testing environment must only target a database with 'test' in its name. Currently targeting: [{$database}]"
                );
            }
        }
    }
}

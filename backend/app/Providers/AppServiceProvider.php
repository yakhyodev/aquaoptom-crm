<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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

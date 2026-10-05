<?php

namespace Database\Seeders;

use App\Models\CashAccount;
use App\Models\Volume;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductionReferenceSeeder extends Seeder
{
    /**
     * Seed production baseline reference catalogs without demo/mock business data.
     */
    public function run(): void
    {
        // 1. Roles & Permissions Matrix
        $this->call(RoleAndPermissionSeeder::class);

        // 2. Primary Store Warehouse (Single Warehouse principle)
        Warehouse::firstOrCreate(
            ['name' => 'Bosh Ombor (Toshkent)'],
            ['is_default' => true]
        );

        // The shop records all money in one account, starting at zero.
        CashAccount::firstOrCreate(
            ['type' => 'CASH'],
            ['name' => 'Do‘kon kassasi', 'balance' => 0, 'is_default' => true]
        );

        // 4. Standard Beverage Volumes (Hajmlar)
        Volume::firstOrCreate(['value_ml' => 250], ['name' => '0.25 L']);
        Volume::firstOrCreate(['value_ml' => 330], ['name' => '0.33 L']);
        Volume::firstOrCreate(['value_ml' => 500], ['name' => '0.5 L']);
        Volume::firstOrCreate(['value_ml' => 1000], ['name' => '1.0 L']);
        Volume::firstOrCreate(['value_ml' => 1500], ['name' => '1.5 L']);
        Volume::firstOrCreate(['value_ml' => 2000], ['name' => '2.0 L']);
        Volume::firstOrCreate(['value_ml' => 5000], ['name' => '5.0 L']);
        Volume::firstOrCreate(['value_ml' => 18900], ['name' => '18.9 L']);

        // 5. System Recovery Settings (Production Baseline)
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'system_recovery_epoch'],
            ['value' => json_encode(1), 'updated_at' => Carbon::now()]
        );
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'system_recovery_status'],
            ['value' => json_encode('NORMAL'), 'updated_at' => Carbon::now()]
        );
    }
}

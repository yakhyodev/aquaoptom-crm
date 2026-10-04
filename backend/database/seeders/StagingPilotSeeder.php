<?php

namespace Database\Seeders;

use App\Models\CashAccount;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Device;
use App\Models\InventoryAllocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StagingPilotSeeder extends Seeder
{
    /**
     * Seed staging database with realistic pilot dataset and test accounts.
     */
    public function run(): void
    {
        // 1. Roles and Base Catalog
        $this->call(RoleAndPermissionSeeder::class);
        $this->call(DatabaseSeeder::class);

        // 2. Staging Users with distinct roles
        $users = [
            [
                'name' => 'Do\'kon Egasi (Staging Owner)',
                'email' => 'owner@aquaoptom.test',
                'password' => Hash::make('OwnerStaging123!'),
                'role' => 'OWNER',
                'status' => 'ACTIVE',
                'is_active' => true,
                'phone' => '+998901111111',
                'telegram_chat_id' => '123456789',
                'telegram_username' => 'aquaoptom_owner',
            ],
            [
                'name' => 'Tizim Administratori (Staging Admin)',
                'email' => 'admin@aquaoptom.test',
                'password' => Hash::make('AdminStaging123!'),
                'role' => 'ADMIN',
                'status' => 'ACTIVE',
                'is_active' => true,
                'phone' => '+998902222222',
            ],
            [
                'name' => 'Kassir Sardor (Staging Cashier)',
                'email' => 'cashier@aquaoptom.test',
                'password' => Hash::make('CashierStaging123!'),
                'role' => 'CASHIER',
                'status' => 'ACTIVE',
                'is_active' => true,
                'phone' => '+998903333333',
            ],
            [
                'name' => 'Sotuvchi Botir (Staging Seller)',
                'email' => 'seller@aquaoptom.test',
                'password' => Hash::make('SellerStaging123!'),
                'role' => 'SALES_MANAGER',
                'status' => 'ACTIVE',
                'is_active' => true,
                'phone' => '+998904444444',
            ],
            [
                'name' => 'Omborchi Jamshid (Staging Warehouse)',
                'email' => 'warehouse@aquaoptom.test',
                'password' => Hash::make('WarehouseStaging123!'),
                'role' => 'WAREHOUSE_MANAGER',
                'status' => 'ACTIVE',
                'is_active' => true,
                'phone' => '+998905555555',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        // 3. Additional Staging Customers & Suppliers
        Customer::firstOrCreate(
            ['phone' => '+998909876543'],
            [
                'name' => 'Akmal (Bahor Market)',
                'address' => 'Chilonzor 19-mavze, Toshkent',
                'current_debt' => 0,
            ]
        );

        Customer::firstOrCreate(
            ['phone' => '+998935551234'],
            [
                'name' => 'Dilshod (Chilonzor Savdo)',
                'address' => 'Bunyodkor shoh ko\'chasi, Toshkent',
                'current_debt' => 0,
            ]
        );

        // 4. Staging POS Devices (PC PWA and Mobile Device)
        $cashierUser = User::where('email', 'cashier@aquaoptom.test')->first();
        $sellerUser = User::where('email', 'seller@aquaoptom.test')->first();
        $defaultWarehouse = Warehouse::first();

        $pcDevice = Device::updateOrCreate(
            ['device_uuid' => 'PC-PWA-POS-STAGING-01'],
            [
                'device_code' => 'PC-01',
                'name' => 'Kassa Kompyuteri (PC PWA)',
                'device_type' => 'desktop',
                'status' => 'ACTIVE',
                'is_active' => true,
                'assigned_user_id' => $cashierUser?->id,
                'registered_by' => $cashierUser?->id,
                'last_seen_at' => Carbon::now(),
            ]
        );

        $mobileDevice = Device::updateOrCreate(
            ['device_uuid' => 'ANDROID-MOBILE-POS-STAGING-02'],
            [
                'device_code' => 'MOB-02',
                'name' => 'Sotuvchi Smartfoni (Flutter Android)',
                'device_type' => 'mobile',
                'status' => 'ACTIVE',
                'is_active' => true,
                'assigned_user_id' => $sellerUser?->id,
                'registered_by' => $sellerUser?->id,
                'last_seen_at' => Carbon::now(),
            ]
        );

        // 5. Initial Device Inventory Allocations for offline testing
        $fanta05 = ProductVariant::whereHas('product', fn($q) => $q->where('name', 'Fanta'))
            ->whereHas('volume', fn($q) => $q->where('value_ml', 500))
            ->first();

        if ($fanta05 && $defaultWarehouse) {
            InventoryAllocation::updateOrCreate(
                [
                    'device_id' => $pcDevice->id,
                    'product_variant_id' => $fanta05->id,
                ],
                [
                    'warehouse_id' => $defaultWarehouse->id,
                    'allocated_quantity' => 50,
                    'consumed_quantity' => 0,
                    'returned_quantity' => 0,
                    'epoch' => 1,
                    'status' => 'ACTIVE',
                ]
            );

            InventoryAllocation::updateOrCreate(
                [
                    'device_id' => $mobileDevice->id,
                    'product_variant_id' => $fanta05->id,
                ],
                [
                    'warehouse_id' => $defaultWarehouse->id,
                    'allocated_quantity' => 50,
                    'consumed_quantity' => 0,
                    'returned_quantity' => 0,
                    'epoch' => 1,
                    'status' => 'ACTIVE',
                ]
            );
        }

        // 6. Active Cash Session for Cashier
        $mainCashAccount = CashAccount::where('type', 'CASH')->first();
        if ($cashierUser && $mainCashAccount) {
            CashSession::firstOrCreate(
                [
                    'opened_by' => $cashierUser->id,
                    'status' => 'OPEN',
                ],
                [
                    'session_number' => 'CS-' . Carbon::now()->format('Ymd') . '-001',
                    'cash_account_id' => $mainCashAccount->id,
                    'opening_balance' => 500000,
                    'opened_at' => Carbon::now(),
                ]
            );
        }

        // 7. System Settings Initialization
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

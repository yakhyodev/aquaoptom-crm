<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Cost & Pricing
            [
                'name' => 'view_cost_price',
                'display_name' => 'Tannarx va foydani ko\'rish',
                'category' => 'pricing',
                'description' => 'Tovar kirim narxi, o\'rtacha tannarx va umumiy foydani ko\'rish huquqi',
            ],
            [
                'name' => 'manage_prices',
                'display_name' => 'Sotuv narxlarini belgilash',
                'category' => 'pricing',
                'description' => 'Mahsulotlar katalogida standart tizim sotuv narxini o\'zgartirish',
            ],
            [
                'name' => 'sell_below_cost',
                'display_name' => 'Tannarxdan past narxda sotish',
                'category' => 'pricing',
                'description' => 'Zararga savdo qilishga maxsus ruxsat',
            ],
            [
                'name' => 'custom_sale_price',
                'display_name' => 'Erkin narx kiritish',
                'category' => 'pricing',
                'description' => 'Chekda standart narx o\'rniga ixtiyoriy kelishilgan narx belgilash',
            ],

            // Sales & Debt
            [
                'name' => 'sell_on_credit',
                'display_name' => 'Nasiyaga sotish',
                'category' => 'sales',
                'description' => 'To\'liq to\'lanmagan, qarzga mahsulot chiqarish',
            ],
            [
                'name' => 'process_refund',
                'display_name' => 'Qaytarish va refund qilish',
                'category' => 'sales',
                'description' => 'Mijozdan tovar qaytarish va pulni qaytarib berish',
            ],
            [
                'name' => 'offline_sales',
                'display_name' => 'Offline rejimda sotish',
                'category' => 'sales',
                'description' => 'Internet uzilgan paytda qurilma rezervi hisobidan sotish',
            ],
            [
                'name' => 'view_debts',
                'display_name' => 'Qarzdorliklarni ko\'rish va to\'lov qabul qilish',
                'category' => 'sales',
                'description' => 'Mijoz va ta\'minotchi signed qarz daftari, qarz to\'lovlarini qabul qilish',
            ],

            // Inventory & Warehouse
            [
                'name' => 'receive_stock',
                'display_name' => 'Tovar kirimi qilish',
                'category' => 'inventory',
                'description' => 'Yangi partiya ichimliklarni omborga qabul qilish',
            ],
            [
                'name' => 'stock_adjustment',
                'display_name' => 'Inventarizatsiya va tuzatish',
                'category' => 'inventory',
                'description' => 'Ombor qoldig\'ini sanash, brak va yo\'qotishlarni hisobdan chiqarish',
            ],

            // Cash & Finance
            [
                'name' => 'view_cash',
                'display_name' => 'Kassa qoldig\'ini ko\'rish',
                'category' => 'finance',
                'description' => 'Naqd pul, karta va hisob raqamdagi qoldiqlarni ko\'rish',
            ],
            [
                'name' => 'manage_cash_outflow',
                'display_name' => 'Kassadan pul chiqimi qilish',
                'category' => 'finance',
                'description' => 'Operatsion xarajatlar, inkassatsiya va ta\'minotchi to\'lovlarini tasdiqlash',
            ],
            [
                'name' => 'manage_cash_sessions',
                'display_name' => 'Kassa smenalarini boshqarish',
                'category' => 'finance',
                'description' => 'Smena ochish, kutilgan va sanalgan naqdni kiritish, smenani yopish',
            ],
            [
                'name' => 'approve_cash_discrepancy',
                'display_name' => 'Kassa farqini tasdiqlash',
                'category' => 'finance',
                'description' => 'Kassa smenasi yopilgandagi farqni (ortiqcha/kamomad) tasdiqlash va qonuniy tuzatish',
            ],

            // Reporting & Admin
            [
                'name' => 'view_reports',
                'display_name' => 'Hisobotlarni ko\'rish',
                'category' => 'reports',
                'description' => 'Savdo dinamikasi, rentabellik va davriy hisobotlarni ko\'rish',
            ],
            [
                'name' => 'manage_users',
                'display_name' => 'Xodimlarni boshqarish',
                'category' => 'admin',
                'description' => 'Foydalanuvchilarni qo\'shish, rollarni va huquqlarni belgilash',
            ],
            [
                'name' => 'manage_settings',
                'display_name' => 'Tizim sozlamalari',
                'category' => 'admin',
                'description' => 'Do\'kon rekvizitlari, audit, backup va tizim parametrlarini boshqarish',
            ],
        ];

        $now = now();
        $permissionIds = [];

        foreach ($permissions as $p) {
            $id = DB::table('permissions')->updateOrInsert(
                ['name' => $p['name']],
                [
                    'display_name' => $p['display_name'],
                    'category' => $p['category'],
                    'description' => $p['description'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $permissionIds[$p['name']] = DB::table('permissions')->where('name', $p['name'])->value('id');
        }

        // Roles definition
        $roles = [
            'OWNER' => [
                'display_name' => 'Do\'kon Egasi',
                'description' => 'Barcha modullar, moliyaviy ma\'lumotlar va tizimga cheksiz kirish huquqi',
                'permissions' => array_keys($permissionIds), // All permissions
            ],
            'ADMIN' => [
                'display_name' => 'Administrator',
                'description' => 'Kundalik boshqaruv, foydalanuvchilar va ombor boshqaruvi (tannarx va moliya egasi tomonidan cheklanishi mumkin)',
                'permissions' => [
                    'manage_prices',
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
                    'manage_cash_sessions',
                    'approve_cash_discrepancy',
                ],
            ],
            'SALES_MANAGER' => [
                'display_name' => 'Sotuvchi (Kassir)',
                'description' => 'Tezkor va mijozli sotuv, offline savdo, savdo tarixi. Tannarx va umumiy foydani ko\'rmaydi.',
                'permissions' => [
                    'sell_on_credit',
                    'offline_sales',
                    'view_debts',
                ],
            ],
            'WAREHOUSE_MANAGER' => [
                'display_name' => 'Omborchi',
                'description' => 'Tovar qabul qilish (kirim), ombor harakatlari va inventarizatsiya. Tannarx va pul mablag\'larini ko\'rmaydi.',
                'permissions' => [
                    'receive_stock',
                    'stock_adjustment',
                ],
            ],
            'CASHIER' => [
                'display_name' => 'Moliya xodimi / Kassa',
                'description' => 'Pul hisoblari, xarajatlar, qarz to\'lovlari va moliyaviy hisobotlar',
                'permissions' => [
                    'view_cash',
                    'manage_cash_outflow',
                    'manage_cash_sessions',
                    'view_debts',
                    'process_refund',
                    'view_reports',
                ],
            ],
        ];

        foreach ($roles as $roleName => $roleData) {
            DB::table('roles')->updateOrInsert(
                ['name' => $roleName],
                [
                    'display_name' => $roleData['display_name'],
                    'description' => $roleData['description'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $roleId = DB::table('roles')->where('name', $roleName)->value('id');

            // Attach permissions
            DB::table('role_permissions')->where('role_id', $roleId)->delete();
            $rolePermRecords = [];
            foreach ($roleData['permissions'] as $permName) {
                if (isset($permissionIds[$permName])) {
                    $rolePermRecords[] = [
                        'role_id' => $roleId,
                        'permission_id' => $permissionIds[$permName],
                    ];
                }
            }
            if (! empty($rolePermRecords)) {
                DB::table('role_permissions')->insert($rolePermRecords);
            }
        }
    }
}

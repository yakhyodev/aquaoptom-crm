<?php

namespace Database\Seeders;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductPackage;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\Volume;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Asosiy Ombor
        $warehouse = Warehouse::firstOrCreate(['name' => 'Bosh Ombor (Toshkent)'], ['is_default' => true]);

        // 2. Kassa Hisoblari
        CashAccount::firstOrCreate(['type' => 'CASH'], ['name' => 'Asosiy Naqd Kassa', 'balance' => 0, 'is_default' => true]);
        CashAccount::firstOrCreate(['type' => 'CARD'], ['name' => 'Humo / Uzcard Terminal', 'balance' => 0]);
        CashAccount::firstOrCreate(['type' => 'BANK'], ['name' => 'Bank Hisob-Raqami', 'balance' => 0]);

        // 3. Hajmlar (Volumes)
        $v025 = Volume::firstOrCreate(['value_ml' => 250], ['name' => '0.25 L']);
        $v033 = Volume::firstOrCreate(['value_ml' => 330], ['name' => '0.33 L']);
        $v05 = Volume::firstOrCreate(['value_ml' => 500], ['name' => '0.5 L']);
        $v10 = Volume::firstOrCreate(['value_ml' => 1000], ['name' => '1.0 L']);
        $v15 = Volume::firstOrCreate(['value_ml' => 1500], ['name' => '1.5 L']);
        $v20 = Volume::firstOrCreate(['value_ml' => 2000], ['name' => '2.0 L']);
        $v50 = Volume::firstOrCreate(['value_ml' => 5000], ['name' => '5.0 L']);
        $v189 = Volume::firstOrCreate(['value_ml' => 18900], ['name' => '18.9 L']);

        // 4. Ta'minotchilar va Xaridorlar
        Supplier::firstOrCreate(['name' => 'Coca-Cola Bottlers Uzbekistan'], ['phone' => '+998 71 200 00 00', 'balance' => 0]);
        Supplier::firstOrCreate(['name' => 'Chortoq Mineral Suv Zavodi'], ['phone' => '+998 69 412 11 22', 'balance' => 0]);
        Customer::firstOrCreate(['name' => 'Farhod aka (Chorsu Bozor)'], ['phone' => '+998 90 123 45 67', 'current_debt' => 0]);
        Customer::firstOrCreate(['name' => 'Oloy Express Minimarket'], ['phone' => '+998 97 765 43 21', 'current_debt' => 0]);

        // 5. Mahsulotlar va Variantlar (Master Prompt misollari)
        $catalogData = [
            [
                'name' => 'Fanta',
                'variants' => [
                    ['volume' => $v05,  'stock' => 2400, 'cost' => 5000, 'retail' => 7000, 'yashik_units' => 12],
                    ['volume' => $v10,  'stock' => 6000, 'cost' => 7500, 'retail' => 9500, 'yashik_units' => 12],
                    ['volume' => $v15,  'stock' => 12000, 'cost' => 10000, 'retail' => 12500, 'yashik_units' => 6],
                    ['volume' => $v20,  'stock' => 8000, 'cost' => 11000, 'retail' => 14000, 'yashik_units' => 6],
                ],
            ],
            [
                'name' => 'Coca-Cola',
                'variants' => [
                    ['volume' => $v05,  'stock' => 3000, 'cost' => 5200, 'retail' => 7000, 'yashik_units' => 12],
                    ['volume' => $v15,  'stock' => 9000, 'cost' => 10500, 'retail' => 13000, 'yashik_units' => 6],
                ],
            ],
            [
                'name' => 'Chortoq',
                'variants' => [
                    ['volume' => $v05,  'stock' => 1500, 'cost' => 4000, 'retail' => 5500, 'yashik_units' => 20],
                    ['volume' => $v10,  'stock' => 800,  'cost' => 6000, 'retail' => 8000, 'yashik_units' => 12],
                ],
            ],
            [
                'name' => 'Nestle Pure Life',
                'variants' => [
                    ['volume' => $v50,  'stock' => 400,  'cost' => 9000,  'retail' => 12000, 'yashik_units' => 2],
                    ['volume' => $v189, 'stock' => 150,  'cost' => 14000, 'retail' => 20000, 'yashik_units' => 1],
                ],
            ],
        ];

        foreach ($catalogData as $pData) {
            $normalized = Product::normalizeName($pData['name']);
            $product = Product::firstOrCreate(
                ['normalized_name' => $normalized],
                [
                    'name' => $pData['name'],
                    'code' => Product::generateCode(),
                    'status' => 'active',
                ]
            );

            foreach ($pData['variants'] as $vData) {
                $vol = $vData['volume'];
                $sku = ProductVariant::generateSku($product, $vol);

                $variant = ProductVariant::firstOrCreate(
                    ['product_id' => $product->id, 'volume_id' => $vol->id],
                    [
                        'sku' => $sku,
                        'default_sale_price' => $vData['retail'],
                        'minimum_stock' => 50,
                        'status' => 'active',
                    ]
                );

                // Qadoqlash (Packages)
                ProductPackage::firstOrCreate(['product_variant_id' => $variant->id, 'name' => 'dona'], ['units_per_package' => 1]);
                ProductPackage::firstOrCreate(['product_variant_id' => $variant->id, 'name' => 'blok'], ['units_per_package' => 6]);
                ProductPackage::firstOrCreate(['product_variant_id' => $variant->id, 'name' => 'yashik'], ['units_per_package' => $vData['yashik_units']]);

                // Initial Inventory Movement & Balance (Source of Truth)
                $qty = $vData['stock'];
                $cost = $vData['cost'];

                InventoryMovement::create([
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => $warehouse->id,
                    'movement_type' => 'INITIAL_STOCK',
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'total_cost' => $qty * $cost,
                    'created_at' => Carbon::now(),
                ]);

                InventoryBalance::updateOrCreate(
                    ['product_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id],
                    [
                        'quantity' => $qty,
                        'average_cost' => $cost,
                        'total_value' => $qty * $cost,
                        'updated_at' => Carbon::now(),
                    ]
                );
            }
        }
    }
}

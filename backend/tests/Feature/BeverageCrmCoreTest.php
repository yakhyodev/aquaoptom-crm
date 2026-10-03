<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Catalog\CreateProductService;
use App\Services\Catalog\CreateVariantService;
use App\Services\Inventory\CalculateAverageCostService;
use App\Services\Inventory\InventoryCalculatorService;
use App\Services\Purchase\ReceivePurchaseService;
use App\Services\Sales\CreateSaleService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BeverageCrmCoreTest extends TestCase
{
    use RefreshDatabase;

    protected Warehouse $warehouse;

    protected Volume $v05;

    protected Volume $v10;

    protected Product $fanta;

    protected ProductVariant $fanta05;

    protected ProductVariant $fanta10;

    protected Customer $customer;

    protected Supplier $supplier;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        Sanctum::actingAs($this->owner);

        $this->warehouse = Warehouse::create(['name' => 'Asosiy Ombor', 'is_default' => true]);
        $this->v05 = Volume::create(['name' => '0.5 L', 'value_ml' => 500]);
        $this->v10 = Volume::create(['name' => '1.0 L', 'value_ml' => 1000]);

        $this->fanta = Product::create([
            'name' => 'Fanta',
            'normalized_name' => 'fanta',
            'code' => 'PRD-000001',
            'status' => 'active',
        ]);

        $this->fanta05 = ProductVariant::create([
            'product_id' => $this->fanta->id,
            'volume_id' => $this->v05->id,
            'sku' => 'FANTA-500',
            'default_sale_price' => 7000,
            'status' => 'active',
        ]);

        $this->fanta10 = ProductVariant::create([
            'product_id' => $this->fanta->id,
            'volume_id' => $this->v10->id,
            'sku' => 'FANTA-1000',
            'default_sale_price' => 10000,
            'status' => 'active',
        ]);

        $this->customer = Customer::create(['name' => 'Farhod aka (Chorsu)']);
        $this->supplier = Supplier::create(['name' => 'Coca-Cola Zavodi']);
    }

    /**
     * TEST 1: Weighted Average Cost formulasi to'g'ri ishlashi
     */
    public function test_weighted_average_cost_calculation(): void
    {
        $wacService = app(CalculateAverageCostService::class);

        // Omborda: 100 dona x 5 000 = 500 000
        // Yangi kirim: 100 dona x 6 000 = 600 000
        // Jami: 200 dona. Yangi tannarx: 1 100 000 / 200 = 5 500
        $newCost = $wacService->execute(
            currentStock: 100,
            currentAverageCost: 5000,
            incomingQuantity: 100,
            incomingUnitCost: 6000
        );

        $this->assertEquals(5500, $newCost);
    }

    /**
     * TEST 2: Purchase posting, inventory movement va stock increase
     */
    public function test_purchase_posting_creates_movement_and_updates_balance(): void
    {
        $purchaseService = app(ReceivePurchaseService::class);

        // Kirim: Fanta 0.5L 150 dona x 5000 so'm
        $purchase = $purchaseService->execute(
            supplierId: $this->supplier->id,
            items: [
                [
                    'variant_id' => $this->fanta05->id,
                    'package_quantity' => 12,
                    'quantity' => 150,
                    'unit_cost' => 5000,
                ],
            ],
            source: 'test'
        );

        $this->assertEquals('POSTED', $purchase->status);
        $this->assertEquals(750000, $purchase->total_amount);

        // Balance tekshirish
        $balance = $this->fanta05->balance()->first();
        $this->assertNotNull($balance);
        $this->assertEquals(150, (float) $balance->quantity);
        $this->assertEquals(5000, $balance->average_cost);

        // Movement tekshirish
        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $this->fanta05->id,
            'movement_type' => 'PURCHASE',
            'quantity' => 150,
            'unit_cost' => 5000,
        ]);
    }

    /**
     * TEST 3: Optom savdo, stock kamayishi va Realized Gross Profit snapshot
     */
    public function test_sale_decreases_stock_and_calculates_profit_snapshot(): void
    {
        $purchaseService = app(ReceivePurchaseService::class);
        $saleService = app(CreateSaleService::class);

        // 1. Kirim: 100 dona x 5000 so'm
        $purchaseService->execute(
            supplierId: $this->supplier->id,
            items: [
                ['variant_id' => $this->fanta05->id, 'quantity' => 100, 'unit_cost' => 5000],
            ]
        );

        // 2. Sotuv: 50 dona x 7000 so'm (Tizim narxida)
        $sale = $saleService->execute(
            customerId: $this->customer->id,
            items: [
                [
                    'variant_id' => $this->fanta05->id,
                    'quantity' => 50,
                    'sale_price' => 7000,
                    'is_system_price' => true,
                ],
            ],
            paymentType: 'CASH',
            source: 'test'
        );

        $this->assertEquals(350000, $sale->total_amount); // 50 * 7000
        $this->assertEquals(250000, $sale->total_cost);   // 50 * 5000
        $this->assertEquals(100000, $sale->gross_profit); // 350000 - 250000 = 100000

        // Omborda 50 dona qolishi kerak (100 - 50)
        $balance = $this->fanta05->balance()->first();
        $this->assertEquals(50, (float) $balance->quantity);

        // Sale item tekshirish
        $item = $sale->items()->first();
        $this->assertEquals(5000, $item->purchase_cost_snapshot);
        $this->assertEquals(100000, $item->gross_profit);
    }

    /**
     * TEST 4: Omborda yetarli tovar bo'lmaganda savdo bloklanishi (Insufficient stock)
     */
    public function test_cannot_sell_more_than_current_stock(): void
    {
        $purchaseService = app(ReceivePurchaseService::class);
        $saleService = app(CreateSaleService::class);

        // Omborda faqat 50 dona bor
        $purchaseService->execute(
            supplierId: $this->supplier->id,
            items: [
                ['variant_id' => $this->fanta05->id, 'quantity' => 50, 'unit_cost' => 5000],
            ]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Omborda yetarli mahsulot mavjud emas');

        // 60 dona sotishga urinish
        $saleService->execute(
            customerId: $this->customer->id,
            items: [
                ['variant_id' => $this->fanta05->id, 'quantity' => 60, 'sale_price' => 7000],
            ]
        );
    }

    /**
     * TEST 5: Master Prompt 53-modda bo'yicha Ombor Kalkulyatori testi:
     * Fanta:
     * 0.5L: 100 dona. Average cost: 5000. Sale price: 7000.
     * 1L: 200 dona. Average cost: 8000. Sale price: 10000.
     * Select: 0.5L only -> Cost: 500 000, Sale Value: 700 000, Profit: 200 000.
     * Select all -> Cost: 2 100 000, Sale Value: 2 700 000, Profit: 600 000.
     */
    public function test_inventory_calculator_exact_specification(): void
    {
        $purchaseService = app(ReceivePurchaseService::class);
        $calcService = app(InventoryCalculatorService::class);

        // Fanta 0.5L: 100 dona x 5 000
        $purchaseService->execute(
            supplierId: $this->supplier->id,
            items: [['variant_id' => $this->fanta05->id, 'quantity' => 100, 'unit_cost' => 5000]]
        );

        // Fanta 1.0L: 200 dona x 8 000
        $purchaseService->execute(
            supplierId: $this->supplier->id,
            items: [['variant_id' => $this->fanta10->id, 'quantity' => 200, 'unit_cost' => 8000]]
        );

        // 1. Faqat 0.5L tanlanganda:
        $res05 = $calcService->calculate([$this->fanta05->id]);
        $this->assertEquals(500000, $res05['total_cost_value']);       // 100 * 5000
        $this->assertEquals(700000, $res05['total_potential_sale_value']); // 100 * 7000
        $this->assertEquals(200000, $res05['potential_gross_profit']); // 700000 - 500000

        // 2. Hammasi (0.5L va 1.0L) tanlanganda:
        $resAll = $calcService->calculate([$this->fanta05->id, $this->fanta10->id]);
        // Cost: (100 * 5000) + (200 * 8000) = 500 000 + 1 600 000 = 2 100 000
        $this->assertEquals(2100000, $resAll['total_cost_value']);
        // Sale: (100 * 7000) + (200 * 10000) = 700 000 + 2 000 000 = 2 700 000
        $this->assertEquals(2700000, $resAll['total_potential_sale_value']);
        // Profit: 2 700 000 - 2 100 000 = 600 000
        $this->assertEquals(600000, $resAll['potential_gross_profit']);
    }

    /**
     * TEST 6: Duplicate Mahsulot va Hajm himoyasi
     */
    public function test_product_and_volume_duplicate_protection(): void
    {
        $prodService = app(CreateProductService::class);
        $varService = app(CreateVariantService::class);

        // "fanta", "  FANTA  ", "Fanta" barchasi bitta bo'lishi kerak
        $p1 = $prodService->execute('Fanta');
        $p2 = $prodService->execute('  FANTA  ');
        $p3 = $prodService->execute('fanta');

        $this->assertEquals($p1->id, $p2->id);
        $this->assertEquals($p1->id, $p3->id);

        // 500 ml hajm ikki marta yaratilmasligi kerak
        $vol1 = $varService->findOrCreateVolume('0.5 L', 500);
        $vol2 = $varService->findOrCreateVolume('500 ml', 500);

        $this->assertEquals($vol1->id, $vol2->id);
    }

    /**
     * TEST 7: API orqali mahsulotlarni olish
     */
    public function test_api_get_products_returns_formatted_list(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id', 'name', 'code', 'variants' => [
                            '*' => ['id', 'product_id', 'sku', 'litres', 'volume_ml', 'stock_qty', 'cost_price', 'retail_price', 'packages'],
                        ],
                    ],
                ],
            ]);
    }

    /**
     * TEST 8: API orqali kirim va sotuv qilish
     */
    public function test_api_store_inward_and_sale(): void
    {
        // 1. API orqali kirim: 120 dona x 4500 so'm
        $inwardRes = $this->postJson('/api/inward', [
            'product_name' => 'Sprite',
            'litres' => 0.5,
            'quantity' => 120,
            'cost_price' => 4500,
            'supplier_name' => 'Sprite zavodi',
        ]);

        $inwardRes->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $sprite = Product::where('name', 'Sprite')->first();
        $this->assertNotNull($sprite);
        $variant = $sprite->variants()->first();
        $this->assertNotNull($variant);
        $this->assertEquals(120, (float) $variant->balance->quantity);

        // Standart sotuv narxini belgilash
        $variant->default_sale_price = 6000;
        $variant->save();

        // 2. API orqali optom savdo: 20 dona
        $saleRes = $this->postJson('/api/sales', [
            'customer_name' => 'Akmal aka',
            'payment_type' => 'CASH',
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 20,
                    'sale_price' => 6000,
                ],
            ],
        ]);

        $saleRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_amount', 120000)
            ->assertJsonPath('data.gross_profit', 30000); // 120000 - (20 * 4500) = 30000

        // Qoldiq 100 dona qolishi kerak
        $variant->refresh();
        $this->assertEquals(100, (float) $variant->balance->quantity);

        // 3. API orqali kalkulyator
        $calcRes = $this->postJson('/api/calculator', [
            'variant_ids' => [$variant->id],
        ]);

        $calcRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_cost_value', 450000) // 100 * 4500
            ->assertJsonPath('data.total_potential_sale_value', 600000); // 100 * 6000
    }
}

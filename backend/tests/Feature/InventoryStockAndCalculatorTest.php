<?php

namespace Tests\Feature;

use App\Events\LowStockDetected;
use App\Livewire\Inventory\StockManager;
use App\Models\Customer;
use App\Models\Device;
use App\Models\InventoryAllocation;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryCalculatorService;
use App\Services\Inventory\InventoryStockService;
use App\Services\Operations\Exceptions\OperationPermissionException;
use App\Services\Purchase\ReceivePurchaseService;
use App\Services\Sales\CreateSaleService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryStockAndCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected Warehouse $warehouse;

    protected User $owner;

    protected User $cashier;

    protected Volume $v05;

    protected Volume $v10;

    protected Volume $v15;

    protected Supplier $supplier;

    protected Customer $customer;

    protected InventoryStockService $stockService;

    protected InventoryCalculatorService $calculatorService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->warehouse = Warehouse::create([
            'name' => 'Asosiy Ombor',
            'code' => 'WH-MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'role' => 'OWNER',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->cashier = User::factory()->create([
            'role' => 'CASHIER',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->v05 = Volume::firstOrCreate(['value_ml' => 500], ['name' => '0.5 L']);
        $this->v10 = Volume::firstOrCreate(['value_ml' => 1000], ['name' => '1.0 L']);
        $this->v15 = Volume::firstOrCreate(['value_ml' => 1500], ['name' => '1.5 L']);

        $this->supplier = Supplier::create([
            'name' => 'Coca-Cola Zavod',
            'balance' => 0,
        ]);

        $this->customer = Customer::create([
            'name' => 'Olim aka',
            'current_debt' => 0,
        ]);

        $this->stockService = app(InventoryStockService::class);
        $this->calculatorService = app(InventoryCalculatorService::class);
    }

    /**
     * Test 1: Ombor ro'yxati va server qidiruvi (Paginationdan oldin).
     */
    public function test_stock_list_server_search_before_pagination(): void
    {
        $fanta = Product::create(['name' => 'Fanta Apelsin', 'code' => 'PRD-001', 'status' => 'active']);
        $cola = Product::create(['name' => 'Coca-Cola Classic', 'code' => 'PRD-002', 'status' => 'active']);

        $vFanta = ProductVariant::create([
            'product_id' => $fanta->id,
            'volume_id' => $this->v05->id,
            'sku' => 'FANTA-500',
            'default_sale_price' => 7000,
            'status' => 'active',
        ]);

        $vCola = ProductVariant::create([
            'product_id' => $cola->id,
            'volume_id' => $this->v05->id,
            'sku' => 'COLA-500',
            'default_sale_price' => 7500,
            'status' => 'active',
        ]);

        // Qidiruv 'fanta' bo'yicha
        $paginated = $this->stockService->getPaginatedStock(['search' => 'Fanta'], 10, 1);
        $this->assertCount(1, $paginated->items());
        $this->assertEquals($vFanta->id, $paginated->items()[0]->id);

        // Qidiruv SKU bo'yicha
        $paginatedSku = $this->stockService->getPaginatedStock(['search' => 'COLA-500'], 10, 1);
        $this->assertCount(1, $paginatedSku->items());
        $this->assertEquals($vCola->id, $paginatedSku->items()[0]->id);
    }

    /**
     * Test 2: Barcha filtrlar (threshold, zero, narx yo'q, arxiv, sekin sotiladigan).
     */
    public function test_stock_list_filters_threshold_zero_unpriced_archived_slow_moving(): void
    {
        $product = Product::create(['name' => 'Chortoq', 'code' => 'PRD-003', 'status' => 'active']);

        // 1. Kam qoldiqdagi variant (stock 10 <= minimum_stock 20)
        $vLow = ProductVariant::create([
            'product_id' => $product->id,
            'volume_id' => $this->v05->id,
            'sku' => 'CHORTOQ-500',
            'minimum_stock' => 20,
            'default_sale_price' => 5000,
            'status' => 'active',
        ]);
        InventoryBalance::create([
            'product_variant_id' => $vLow->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10,
            'average_cost' => 3500,
            'total_value' => 35000,
        ]);

        // 2. Nol qoldiq va narxsiz variant
        $vZeroUnpriced = ProductVariant::create([
            'product_id' => $product->id,
            'volume_id' => $this->v10->id,
            'sku' => 'CHORTOQ-1000',
            'minimum_stock' => 0,
            'default_sale_price' => null, // Narx yo'q
            'status' => 'active',
        ]);
        InventoryBalance::create([
            'product_variant_id' => $vZeroUnpriced->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 0,
            'average_cost' => 0,
            'total_value' => 0,
        ]);

        // 3. Arxivlangan variant
        $vArchived = ProductVariant::create([
            'product_id' => $product->id,
            'volume_id' => $this->v15->id,
            'sku' => 'CHORTOQ-1500',
            'default_sale_price' => 8000,
            'status' => 'inactive',
        ]);

        // Threshold filter
        $lowRes = $this->stockService->getPaginatedStock(['threshold_filter' => 'below_threshold']);
        $this->assertTrue($lowRes->contains('id', $vLow->id));
        $this->assertFalse($lowRes->contains('id', $vZeroUnpriced->id)); // zero is below threshold if min_stock > 0

        // Zero filter
        $zeroRes = $this->stockService->getPaginatedStock(['zero_stock_filter' => 'zero_only']);
        $this->assertTrue($zeroRes->contains('id', $vZeroUnpriced->id));
        $this->assertFalse($zeroRes->contains('id', $vLow->id));

        // Price filter (no_price)
        $unpricedRes = $this->stockService->getPaginatedStock(['price_filter' => 'no_price']);
        $this->assertTrue($unpricedRes->contains('id', $vZeroUnpriced->id));
        $this->assertFalse($unpricedRes->contains('id', $vLow->id));

        // Archived filter
        $archivedRes = $this->stockService->getPaginatedStock(['status_filter' => 'archived']);
        $this->assertTrue($archivedRes->contains('id', $vArchived->id));
        $this->assertFalse($archivedRes->contains('id', $vLow->id));

        // Slow moving filter (hech qanday sotuv bo'lmagan)
        $slowRes = $this->stockService->getPaginatedStock(['slow_moving' => true]);
        $this->assertTrue($slowRes->contains('id', $vLow->id));
    }

    /**
     * Test 3: Narrow filterdagi yig'indi butun bazaning filtrlangan natijasi (faqat bitta sahifa emas).
     */
    public function test_narrow_filter_aggregates_across_whole_filtered_database_not_just_current_page(): void
    {
        $p = Product::create(['name' => 'Nestle Water', 'code' => 'PRD-004', 'status' => 'active']);

        // 25 ta variant yaratamiz, har birida 10 dona (jami 250 dona)
        for ($i = 1; $i <= 25; $i++) {
            $vol = Volume::firstOrCreate(['value_ml' => 3000 + ($i * 50)], ['name' => "Vol-{$i}"]);
            $v = ProductVariant::create([
                'product_id' => $p->id,
                'volume_id' => $vol->id,
                'sku' => "NST-{$i}",
                'default_sale_price' => 5000,
                'status' => 'active',
            ]);
            InventoryBalance::create([
                'product_variant_id' => $v->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 10,
                'average_cost' => 3000,
                'total_value' => 30000,
            ]);
        }

        // Sahifada 10 tadan chiqarsak ham, umumiy agregat butun bazaning 250 donasini hisoblashi shart!
        $paginated = $this->stockService->getPaginatedStock(['search' => 'Nestle'], 10, 1);
        $this->assertCount(10, $paginated->items()); // sahifada 10 ta

        $totals = $this->stockService->getAggregatedTotals(['search' => 'Nestle']);
        $this->assertEquals(25, $totals['total_variants_count']);
        $this->assertEquals(250, $totals['total_physical_units']); // Butun bazadagi 250 dona!
        $this->assertEquals(250 * 5000, $totals['total_sale_value']);
        $this->assertEquals(250 * 3000, $totals['total_cost_value']);
    }

    /**
     * Test 4: Tannarx va qiymat rol bo'yicha (view_cost_price ruxsatisiz xodimga yashiriladi).
     */
    public function test_role_based_cost_price_and_value_visibility(): void
    {
        $p = Product::create(['name' => 'Pepsi', 'code' => 'PRD-005', 'status' => 'active']);
        $v = ProductVariant::create([
            'product_id' => $p->id,
            'volume_id' => $this->v05->id,
            'sku' => 'PEP-500',
            'default_sale_price' => 6000,
            'status' => 'active',
        ]);
        InventoryBalance::create([
            'product_variant_id' => $v->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'average_cost' => 4000,
            'total_value' => 400000,
        ]);

        // 1. Owner (ruxsati bor)
        $this->assertTrue($this->owner->hasPermission('view_cost_price'));
        $ownerTotals = $this->stockService->getAggregatedTotals(['search' => 'Pepsi'], null, true);
        $this->assertEquals(400000, $ownerTotals['total_cost_value']);

        $ownerList = $this->stockService->getPaginatedStock(['search' => 'Pepsi'], 10, 1, null, true);
        $this->assertEquals(4000, $ownerList->items()[0]->wac_cost);
        $this->assertEquals(400000, $ownerList->items()[0]->total_cost_value);

        // 2. Cashier (tannarx ruxsati yo'q)
        $this->assertFalse($this->cashier->hasPermission('view_cost_price'));
        $cashierTotals = $this->stockService->getAggregatedTotals(['search' => 'Pepsi'], null, false);
        $this->assertNull($cashierTotals['total_cost_value']);

        $cashierList = $this->stockService->getPaginatedStock(['search' => 'Pepsi'], 10, 1, null, false);
        $this->assertNull($cashierList->items()[0]->wac_cost);
        $this->assertNull($cashierList->items()[0]->total_cost_value);
    }

    /**
     * Test 5: Fanta barcha hajm vs 1L vs ikki hajm summalari haqiqiy rowsdan.
     * Qabul mezoni:
     * - Fanta 0.5L: 150 dona x 5000 (cost), 7000 (sale)
     * - Fanta 1.0L: 200 dona x 7500 (cost), 9500 (sale)
     * - Fanta 1.5L: 100 dona x 10000 (cost), 12500 (sale)
     */
    public function test_calculator_fanta_all_vs_one_liter_vs_two_volumes_from_real_rows(): void
    {
        $fanta = Product::create(['name' => 'Fanta Apelsin', 'code' => 'PRD-006', 'status' => 'active']);

        // 0.5 L
        $v05 = ProductVariant::create([
            'product_id' => $fanta->id,
            'volume_id' => $this->v05->id,
            'sku' => 'FNT-05',
            'default_sale_price' => 7000,
            'status' => 'active',
        ]);
        InventoryBalance::create([
            'product_variant_id' => $v05->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 150,
            'average_cost' => 5000,
            'total_value' => 150 * 5000, // 750 000
        ]);

        // 1.0 L
        $v10 = ProductVariant::create([
            'product_id' => $fanta->id,
            'volume_id' => $this->v10->id,
            'sku' => 'FNT-10',
            'default_sale_price' => 9500,
            'status' => 'active',
        ]);
        InventoryBalance::create([
            'product_variant_id' => $v10->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 200,
            'average_cost' => 7500,
            'total_value' => 200 * 7500, // 1 500 000
        ]);

        // 1.5 L
        $v15 = ProductVariant::create([
            'product_id' => $fanta->id,
            'volume_id' => $this->v15->id,
            'sku' => 'FNT-15',
            'default_sale_price' => 12500,
            'status' => 'active',
        ]);
        InventoryBalance::create([
            'product_variant_id' => $v15->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'average_cost' => 10000,
            'total_value' => 100 * 10000, // 1 000 000
        ]);

        // A. Faqat 1.0L tanlanganda:
        $calc1L = $this->calculatorService->calculate([$v10->id]);
        $this->assertEquals(200, $calc1L['total_quantity_units']);
        $this->assertEquals(1500000, $calc1L['total_cost_value']); // 200 * 7500
        $this->assertEquals(1900000, $calc1L['total_potential_sale_value']); // 200 * 9500
        $this->assertEquals(400000, $calc1L['expected_gross_profit']); // 1900000 - 1500000
        $this->assertTrue($calc1L['is_fully_priced']);

        // B. Ikki hajm (0.5L va 1.0L) tanlanganda:
        $calcTwo = $this->calculatorService->calculate([$v05->id, $v10->id]);
        $this->assertEquals(350, $calcTwo['total_quantity_units']); // 150 + 200
        $this->assertEquals(2250000, $calcTwo['total_cost_value']); // 750000 + 1500000
        $this->assertEquals(2950000, $calcTwo['total_potential_sale_value']); // 1050000 + 1900000
        $this->assertEquals(700000, $calcTwo['expected_gross_profit']); // 2950000 - 2250000

        // C. Barcha hajm (0.5L, 1.0L, 1.5L) tanlanganda:
        $calcAll = $this->calculatorService->calculate([$v05->id, $v10->id, $v15->id]);
        $this->assertEquals(450, $calcAll['total_quantity_units']); // 150 + 200 + 100
        $this->assertEquals(3250000, $calcAll['total_cost_value']); // 750k + 1.5m + 1.0m
        $this->assertEquals(4200000, $calcAll['total_potential_sale_value']); // 1.05m + 1.9m + 1.25m
        $this->assertEquals(950000, $calcAll['expected_gross_profit']); // 4.2m - 3.25m
    }

    /**
     * Test 6: Narxsiz variantlar nol narxga tenglashtirilmasligi va to'liq foyda aniqlanmagani ko'rsatilishi.
     */
    public function test_unpriced_variants_are_isolated_and_not_forced_to_zero_price(): void
    {
        $p = Product::create(['name' => 'Moxito', 'code' => 'PRD-007', 'status' => 'active']);

        // Variant A (Narxi bor: 100 dona x 4000 cost, 6000 sale)
        $vPriced = ProductVariant::create([
            'product_id' => $p->id,
            'volume_id' => $this->v05->id,
            'sku' => 'MOX-05',
            'default_sale_price' => 6000,
            'status' => 'active',
        ]);
        InventoryBalance::create([
            'product_variant_id' => $vPriced->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'average_cost' => 4000,
            'total_value' => 400000,
        ]);

        // Variant B (Narxi yo'q: 50 dona x 5000 cost, sale_price NULL)
        $vUnpriced = ProductVariant::create([
            'product_id' => $p->id,
            'volume_id' => $this->v10->id,
            'sku' => 'MOX-10',
            'default_sale_price' => null, // Narx yo'q
            'status' => 'active',
        ]);
        InventoryBalance::create([
            'product_variant_id' => $vUnpriced->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50,
            'average_cost' => 5000,
            'total_value' => 250000,
        ]);

        $calc = $this->calculatorService->calculate([$vPriced->id, $vUnpriced->id]);

        $this->assertFalse($calc['is_fully_priced']);
        $this->assertEquals(1, $calc['missing_price_count']);
        $this->assertEquals(50, $calc['missing_price_units']);
        $this->assertEquals(250000, $calc['missing_price_cost_value']);

        // Sotuv qiymati faqat narxi bor variantdan olinadi
        $this->assertEquals(600000, $calc['total_potential_sale_value']); // 100 * 6000

        // Kutilayotgan yalpi foyda narxsiz variantni 0 ga tenglashtirib foydani sun'iy minusga tushirmaydi!
        // Narxi bor qism bo'yicha: 600 000 - 400 000 = 200 000
        $this->assertEquals(200000, $calc['expected_gross_profit']);
    }

    /**
     * Test 7: Taxminiy narx faqat simulation, katalogga saqlash faqat manage_prices ruxsati bilan.
     */
    public function test_simulation_prices_recalculate_without_modifying_catalog_and_saving_requires_permission(): void
    {
        $p = Product::create(['name' => 'Sprite', 'code' => 'PRD-008', 'status' => 'active']);
        $v = ProductVariant::create([
            'product_id' => $p->id,
            'volume_id' => $this->v05->id,
            'sku' => 'SPR-05',
            'default_sale_price' => null, // Hozircha narxi yo'q
            'status' => 'active',
            'version' => 1,
        ]);
        InventoryBalance::create([
            'product_variant_id' => $v->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'average_cost' => 4500,
            'total_value' => 450000,
        ]);

        // 1. Simulyatsiya narxi kiritilganda kalkulyatsiya yangilanadi
        $calcSim = $this->calculatorService->calculate(
            selectedVariantIds: [$v->id],
            simulationPrices: [$v->id => 6500]
        );
        $this->assertEquals(650000, $calcSim['total_potential_sale_value']);
        $this->assertEquals(200000, $calcSim['expected_gross_profit']);
        $this->assertTrue($calcSim['is_fully_priced']); // Simulyatsiya bilan to'liq narxlangan

        // Bazadagi default_sale_price o'zgarmasdan qolgan!
        $this->assertNull($v->fresh()->default_sale_price);

        // 2. manage_prices ruxsatisiz xodim katalogga saqlay olmaydi
        $this->assertFalse($this->cashier->hasPermission('manage_prices'));
        $this->expectException(OperationPermissionException::class);
        $this->calculatorService->applySimulationPricesToCatalog([$v->id => 6500], $this->cashier);
    }

    /**
     * Test 7.1: manage_prices ruxsati bilan katalogga saqlash PriceHistory yaratadi.
     */
    public function test_owner_can_save_simulation_prices_to_catalog(): void
    {
        $p = Product::create(['name' => 'Sprite 2', 'code' => 'PRD-009', 'status' => 'active']);
        $v = ProductVariant::create([
            'product_id' => $p->id,
            'volume_id' => $this->v05->id,
            'sku' => 'SPR-05B',
            'default_sale_price' => null,
            'status' => 'active',
            'version' => 1,
        ]);

        $this->assertTrue($this->owner->hasPermission('manage_prices'));
        $count = $this->calculatorService->applySimulationPricesToCatalog([$v->id => 6500], $this->owner);

        $this->assertEquals(1, $count);
        $this->assertEquals(6500, $v->fresh()->default_sale_price);
        $this->assertEquals(2, $v->fresh()->version);

        $this->assertDatabaseHas('price_history', [
            'product_variant_id' => $v->id,
            'new_price' => 6500,
            'changed_by' => $this->owner->id,
        ]);
    }

    /**
     * Test 8: Serverdagi fizik qoldiq, erkin qoldiq, qurilmaga ajratilgan miqdor va eskirgan offline snapshot farqlanishi.
     */
    public function test_physical_free_allocated_and_stale_allocation_separation(): void
    {
        $p = Product::create(['name' => 'Red Bull', 'code' => 'PRD-010', 'status' => 'active']);
        $v = ProductVariant::create([
            'product_id' => $p->id,
            'volume_id' => $this->v05->id,
            'sku' => 'RB-05',
            'default_sale_price' => 18000,
            'status' => 'active',
        ]);
        InventoryBalance::create([
            'product_variant_id' => $v->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'average_cost' => 12000,
            'total_value' => 1200000,
        ]);

        $device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'DEV-9901',
            'name' => 'Kassir Plansheti',
            'device_type' => 'TABLET',
            'status' => 'ACTIVE',
            'is_active' => true,
            'registered_by' => $this->owner->id,
            'last_sync_at' => now()->subDays(2), // 2 kundan beri aloqa yo'q!
        ]);

        // 30 dona ajratma beramiz
        InventoryAllocation::create([
            'device_id' => $device->id,
            'product_variant_id' => $v->id,
            'warehouse_id' => $this->warehouse->id,
            'allocated_quantity' => 30,
            'consumed_quantity' => 0,
            'returned_quantity' => 0,
            'epoch' => 1,
            'status' => 'ACTIVE',
        ]);

        $detail = $this->stockService->getVariantDetail($v->id);
        $this->assertEquals(100, $detail['physical_quantity']);
        $this->assertEquals(30, $detail['allocated_quantity']);
        $this->assertEquals(70, $detail['free_quantity']); // 100 - 30 = 70
        $this->assertTrue($detail['has_stale_allocation']); // 2 kundan beri aloqa yo'q -> stale!
    }

    /**
     * Test 9: Kam qoldiqda LowStockDetected hodisasi qayd etilishi.
     */
    public function test_low_stock_detected_event_dispatched(): void
    {
        Event::fake([LowStockDetected::class]);

        $p = Product::create(['name' => 'Fuse Tea', 'code' => 'PRD-011', 'status' => 'active']);
        $v = ProductVariant::create([
            'product_id' => $p->id,
            'volume_id' => $this->v05->id,
            'sku' => 'FT-05',
            'minimum_stock' => 50,
            'default_sale_price' => 7000,
            'status' => 'active',
        ]);
        InventoryBalance::create([
            'product_variant_id' => $v->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 20, // 20 <= 50 (Kam qoldiq)
            'average_cost' => 4500,
            'total_value' => 90000,
        ]);

        $lowStockItems = $this->stockService->checkLowStockAlerts();
        $this->assertCount(1, $lowStockItems);

        Event::assertDispatched(LowStockDetected::class, function ($event) use ($v) {
            return $event->variant->id === $v->id
                && $event->currentQuantity === 20
                && $event->threshold === 50;
        });
    }

    /**
     * Test 10: Variant kartasi va source hujjatga bog'langan harakat tarixi to'liq ko'rinishi.
     */
    public function test_variant_movements_history_with_source_documents_and_tashkent_time(): void
    {
        $p = Product::create(['name' => 'Bonaqua', 'code' => 'PRD-012', 'status' => 'active']);
        $v = ProductVariant::create([
            'product_id' => $p->id,
            'volume_id' => $this->v05->id,
            'sku' => 'BON-05',
            'default_sale_price' => 4000,
            'status' => 'active',
        ]);

        // 1. Kirim hujjati
        $purchaseService = app(ReceivePurchaseService::class);
        $purchase = $purchaseService->execute(
            supplierId: $this->supplier->id,
            items: [
                ['variant_id' => $v->id, 'quantity' => 100, 'unit_cost' => 2500],
            ],
            supplierInvoiceNumber: 'INV-BONA-01',
            userId: $this->owner->id
        );

        // 2. Sotuv hujjati
        $saleService = app(CreateSaleService::class);
        $sale = $saleService->execute(
            customerId: $this->customer->id,
            items: [
                ['variant_id' => $v->id, 'quantity' => 30, 'sale_price' => 4000],
            ],
            userId: $this->owner->id
        );

        $movements = $this->stockService->getVariantMovements($v->id, 10, 1);
        $this->assertCount(2, $movements->items());

        // Eng so'nggi harakat: Sotuv
        $mSale = $movements->items()[0];
        $this->assertEquals('SALE', $mSale['movement_type']);
        $this->assertEquals('Sotuv', $mSale['type_label']);
        $this->assertEquals(-30, $mSale['quantity']);
        $this->assertEquals(70, $mSale['balance_after_quantity']);
        $this->assertStringContainsString('Sotuv cheki', $mSale['reference_title']);
        $this->assertNotNull($mSale['created_at']);

        // Birinchi harakat: Kirim
        $mPurchase = $movements->items()[1];
        $this->assertEquals('PURCHASE', $mPurchase['movement_type']);
        $this->assertEquals('Kirim', $mPurchase['type_label']);
        $this->assertEquals(100, $mPurchase['quantity']);
        $this->assertEquals(100, $mPurchase['balance_after_quantity']);
        $this->assertStringContainsString('INV-BONA-01', $mPurchase['reference_title']);
    }

    /**
     * Test 11: Livewire StockManager component mounts, renders balances and switches tabs.
     */
    public function test_livewire_stock_manager_renders_and_switches_tabs(): void
    {
        $p = Product::create(['name' => 'Asu', 'code' => 'PRD-013', 'status' => 'active']);
        $v = ProductVariant::create([
            'product_id' => $p->id,
            'volume_id' => $this->v05->id,
            'sku' => 'ASU-05',
            'default_sale_price' => 3500,
            'status' => 'active',
        ]);
        InventoryBalance::create([
            'product_variant_id' => $v->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 120,
            'average_cost' => 2200,
            'total_value' => 264000,
        ]);

        $test = Livewire::actingAs($this->owner)
            ->test(StockManager::class)
            ->assertStatus(200)
            ->assertSee('Zaxiralar Boshqaruvi')
            ->assertSee('Asu')
            ->assertSee('ASU-05');

        // Switch to calculator tab
        $test->set('activeTab', 'calculator')
            ->assertSee('Kutilayotgan Foyda')
            ->assertSee('Narx Simulyatsiyasi');

        // Switch to inward tab
        $test->set('activeTab', 'inward')
            ->assertSeeHtml('wire:id');
    }

    /**
     * Test 12: Livewire StockManager calculator simulation and modal opening.
     */
    public function test_livewire_stock_manager_calculator_and_modal_actions(): void
    {
        $p = Product::create(['name' => 'Dena', 'code' => 'PRD-014', 'status' => 'active']);
        $v = ProductVariant::create([
            'product_id' => $p->id,
            'volume_id' => $this->v10->id,
            'sku' => 'DENA-10',
            'default_sale_price' => 11000,
            'status' => 'active',
            'version' => 1,
        ]);
        InventoryBalance::create([
            'product_variant_id' => $v->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50,
            'average_cost' => 8000,
            'total_value' => 400000,
        ]);

        $test = Livewire::actingAs($this->owner)
            ->test(StockManager::class)
            // Open variant detail modal
            ->call('openVariantDetail', $v->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('Tovar kartasi, qurilmalar ajratmasi')
            ->call('closeVariantDetail')
            ->assertSet('showDetailModal', false);

        // Calculator simulation and apply
        $test->set('activeTab', 'calculator')
            ->call('setSimulationPrice', $v->id, 12000)
            ->call('applySimulationPricesToCatalog')
            ->assertSee('Muvaffaqiyatli: 1 ta mahsulot varianti uchun yangi tizim narxi saqlandi');

        $this->assertEquals(12000, $v->fresh()->default_sale_price);
    }
}

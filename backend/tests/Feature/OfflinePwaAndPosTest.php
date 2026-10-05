<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Device;
use App\Models\InventoryAllocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflinePwaAndPosTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected Device $device;

    protected Warehouse $warehouse;

    protected Product $product;

    protected Volume $volume;

    protected ProductVariant $variant;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->cashier = User::factory()->create([
            'role' => 'CASHIER',
            'is_active' => true,
        ]);
        $this->cashier->givePermission('offline_sales');

        $this->warehouse = Warehouse::firstOrCreate(
            ['name' => 'Asosiy Ombor'],
            ['is_default' => true]
        );

        $this->device = Device::create([
            'device_code' => 'DEV-0001',
            'device_uuid' => (string) Str::uuid(),
            'name' => 'Kassa 1 Kompyuter',
            'device_type' => 'desktop',
            'status' => 'ACTIVE',
            'is_active' => true,
            'assigned_warehouse_id' => $this->warehouse->id,
            'assigned_user_id' => $this->cashier->id,
            'current_lease_epoch' => 1,
            'allow_new_offline_customer_debt' => true,
            'new_customer_debt_budget' => 500000,
        ]);

        $this->product = Product::create([
            'name' => 'Fanta',
            'normalized_name' => 'fanta',
            'code' => 'PRD-FANTA-01',
            'status' => 'ACTIVE',
        ]);
        $this->volume = Volume::create([
            'name' => '0.5 L',
            'value_ml' => 500,
            'unit' => 'L',
        ]);
        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'volume_id' => $this->volume->id,
            'sku' => 'FANTA-05',
            'barcode' => '4780001112223',
            'default_sale_price' => 6500,
            'status' => 'ACTIVE',
            'version' => 1,
        ]);

        $this->customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Farhod Do\'koni',
            'phone' => '+998901234567',
            'store_name' => 'Farhod Savdo',
            'current_debt' => 0,
            'debt_limit' => 200000,
            'is_strict_credit_limit' => true,
        ]);
    }

    /**
     * Test 1: PWA Web App Manifest tekshiruvi
     */
    public function test_pwa_manifest_exists_and_is_valid_json(): void
    {
        $manifestPath = public_path('manifest.json');
        $this->assertFileExists($manifestPath, 'manifest.json fayli public katalogida mavjud bo\'lishi shart!');

        $content = file_get_contents($manifestPath);
        $json = json_decode($content, true);

        $this->assertIsArray($json, 'manifest.json to\'g\'ri JSON formatida bo\'lishi shart!');
        $this->assertEquals('AquaOptom CRM — Ulgurji Ichimliklar', $json['name']);
        $this->assertEquals('AquaOptom', $json['short_name']);
        $this->assertEquals('standalone', $json['display']);
        $this->assertStringContainsString('/pos', $json['start_url']);
        $this->assertEquals('#0284c7', $json['theme_color']);
        $this->assertEquals('#020617', $json['background_color']);

        $this->assertNotEmpty($json['icons']);
        foreach ($json['icons'] as $icon) {
            $iconPath = public_path($icon['src']);
            $this->assertFileExists($iconPath, "Belgilangan icon fayli topilmadi: {$icon['src']}");
        }
    }

    /**
     * Test 2: Service Worker (sw.js) mavjudligi va kesh qoidalari
     */
    public function test_service_worker_file_exists_and_implements_offline_app_shell_caching(): void
    {
        $swPath = public_path('sw.js');
        $this->assertFileExists($swPath, 'sw.js Service Worker fayli public katalogida mavjud bo\'lishi shart!');

        $swCode = file_get_contents($swPath);
        $this->assertStringContainsString('CACHE_NAME', $swCode);
        $this->assertStringContainsString('/pos', $swCode);
        $this->assertStringContainsString('/offline.html', $swCode);
        $this->assertStringContainsString('/manifest.json', $swCode);
        $this->assertStringContainsString('NETWORK_DISCONNECTED', $swCode);
    }

    /**
     * Test 3: Offline fallback sahifasi (offline.html)
     */
    public function test_offline_fallback_html_file_exists_and_links_to_pos(): void
    {
        $offlinePath = public_path('offline.html');
        $this->assertFileExists($offlinePath, 'offline.html fayli mavjud bo\'lishi shart!');

        $html = file_get_contents($offlinePath);
        $this->assertStringContainsString('/pos', $html);
        $this->assertStringContainsString('Offline', $html);
    }

    /**
     * Test 4: /pos marshruti PWA Offline App Shell'ni yuklaydi
     */
    public function test_pos_pwa_route_requires_auth_and_renders_offline_pos_shell(): void
    {
        // 1. Mehmon foydalanuvchi loginga yo'naltiriladi
        $guestResponse = $this->get('/pos');
        $guestResponse->assertRedirect(route('login'));

        // 2. Avtorizatsiyalangan kassir /pos ga kirganda 200 OK va App Shell oladi
        $response = $this->actingAs($this->cashier)->get('/pos');
        $response->assertStatus(200);

        $response->assertSee('manifest.json', false);
        $response->assertSee('aquaPos()', false);
        $response->assertSee('AquaOptom POS', false);
        $response->assertSee('PWA', false);
        $response->assertSee('Savdoni Yakunlash', false);
    }

    /**
     * Test 5: /sotuv (Livewire) sahifasida PWA Offline POS ga o'tish tugmasi bor
     */
    public function test_sales_pos_page_has_offline_pos_switcher_banner(): void
    {
        $response = $this->actingAs($this->cashier)->get('/sotuv');
        $response->assertStatus(200);
        $response->assertSee(route('pos.pwa'), false);
        $response->assertSee('Internetsiz sotuv oynasi', false);
    }

    /**
     * Test 6: Sync bootstrap API offline POS uchun katalog va mijozlarni beradi
     */
    public function test_bootstrap_api_supplies_catalog_and_customers_to_offline_pos(): void
    {
        // Tovar ajratmasi beramiz
        InventoryAllocation::create([
            'device_id' => $this->device->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'allocated_quantity' => 60,
            'consumed_quantity' => 0,
            'returned_quantity' => 0,
            'epoch' => 1,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/sync/bootstrap', [
                'device_uuid' => $this->device->device_uuid,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Katalog tekshiruvi
        $catalog = $response->json('data.catalog');
        $this->assertIsArray($catalog);
        $this->assertNotEmpty($catalog);
        $this->assertEquals($this->variant->id, $catalog[0]['id']);
        $this->assertEquals('Fanta', $catalog[0]['product_name']);
        $this->assertEquals(6500, $catalog[0]['default_sale_price']);

        // Sezgir tannarxlar (cost_price / average_cost) kassir qurilmasiga aslo uzatilmasligi kerak!
        $this->assertArrayNotHasKey('cost_price', $catalog[0]);
        $this->assertArrayNotHasKey('average_cost', $catalog[0]);
        $this->assertArrayNotHasKey('unit_cost', $catalog[0]);

        // Mijozlar tekshiruvi
        $customers = $response->json('data.customers');
        $this->assertIsArray($customers);
        $this->assertNotEmpty($customers);
        $this->assertEquals($this->customer->id, $customers[0]['id']);
        $this->assertEquals('Farhod Do\'koni', $customers[0]['name']);
        $this->assertEquals(200000, $customers[0]['debt_limit']);

        // Ajratmalar tekshiruvi
        $allocs = $response->json('data.stock_allocations');
        $this->assertIsArray($allocs);
        $this->assertEquals(60, $allocs[0]['allocated_quantity']);
    }

    /**
     * Test 7: Node.js orqali AquaDB IndexedDB barcha 7 ta testini avtomatik tekshirish
     */
    public function test_javascript_indexeddb_test_suite_passes(): void
    {
        $testScript = base_path('tests/pwa-indexeddb-test.cjs');
        $this->assertFileExists($testScript);

        $output = [];
        $exitCode = 0;
        exec("node \"{$testScript}\"", $output, $exitCode);

        $outputText = implode("\n", $output);
        $this->assertEquals(0, $exitCode, "IndexedDB testlari muvaffaqiyatsiz bo'ldi:\n{$outputText}");
        $this->assertStringContainsString('ALL AQUADB TESTS PASSED', $outputText);
    }

    public function test_keyboard_quantity_input_keeps_draft_and_accepts_wholesale_quantities(): void
    {
        $output = [];
        $exitCode = 0;
        exec('node '.escapeshellarg(base_path('tests/pwa-quantity-input-test.cjs')), $output, $exitCode);
        $this->assertSame(0, $exitCode, implode("\n", $output));
        $this->assertStringContainsString('PWA KEYBOARD QUANTITY TESTS PASSED', implode("\n", $output));
    }
}

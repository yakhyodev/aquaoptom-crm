<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Device;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Devices\OfflineLeaseService;
use App\Services\Sales\CreateSaleService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PwaAutoSyncAndDisruptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected Device $device;

    protected Warehouse $warehouse;

    protected CashAccount $cashAccount;

    protected ProductVariant $variant;

    protected Customer $customer;

    protected string $leaseToken;

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

        $this->cashier = User::factory()->create([
            'role' => 'CASHIER',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);
        $this->cashier->givePermission('offline_sales');

        $this->cashAccount = CashAccount::create([
            'name' => 'Asosiy Naqd Kassa',
            'type' => 'CASH',
            'balance' => 500000,
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Fanta Apelsin',
            'normalized_name' => 'fanta apelsin',
            'code' => 'PRD-000101',
            'status' => 'ACTIVE',
        ]);

        $volume = Volume::create([
            'name' => '0.5 L',
            'value_ml' => 500,
            'unit' => 'L',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'volume_id' => $volume->id,
            'sku' => 'FAN-05L',
            'default_sale_price' => 6000,
            'cost_price' => 4500,
            'status' => 'ACTIVE',
        ]);

        InventoryBalance::create([
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'total_value' => 450000,
            'average_cost' => 4500,
        ]);

        $this->customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Alisher Savdogar',
            'phone' => '+998901234567',
            'store_name' => 'Baraka Do\'kon',
            'current_debt' => 0,
            'debt_limit' => 500000,
            'is_strict_credit_limit' => true,
            'status' => 'ACTIVE',
        ]);

        $this->device = Device::create([
            'device_code' => 'DEV-0001',
            'device_uuid' => (string) Str::uuid(),
            'name' => 'Kassa Kompyuteri 1',
            'device_type' => 'desktop',
            'status' => 'ACTIVE',
            'is_active' => true,
            'assigned_warehouse_id' => $this->warehouse->id,
            'assigned_user_id' => $this->cashier->id,
        ]);

        $leaseService = app(OfflineLeaseService::class);
        $lease = $leaseService->issueLease($this->device, $this->cashier, null, 24);
        $this->leaseToken = $lease['lease_token'];
    }

    public function test_public_health_ping_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'OK',
            ])
            ->assertJsonStructure(['success', 'status', 'server_time']);
    }

    public function test_authenticated_sync_health_returns_database_connected(): void
    {
        $response = $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/sync/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'OK',
                'database' => 'connected',
                'user_id' => $this->cashier->id,
            ]);
    }

    public function test_server_void_sale_reverses_inventory_customer_debt_and_cash(): void
    {
        // 1. Dastlab haqiqiy savdo o'tkazamiz (10 dona x 6000 = 60,000 so'm; 20,000 naqd, 40,000 qarz)
        $saleOpId = (string) Str::uuid();
        $saleService = app(CreateSaleService::class);
        $sale = $saleService->execute(
            customerId: $this->customer->id,
            items: [
                ['variant_id' => $this->variant->id, 'quantity' => 10, 'sale_price' => 6000],
            ],
            operationId: $saleOpId,
            paidAmount: 20000,
            cashAccountId: $this->cashAccount->id,
            paymentType: 'CASH',
            paymentMethod: 'CASH',
            warehouseId: $this->warehouse->id,
            userId: $this->cashier->id,
            useSystemPrice: false
        );

        $this->assertEquals(90, InventoryBalance::where('product_variant_id', $this->variant->id)->value('quantity'));
        $this->assertEquals(40000, $this->customer->fresh()->current_debt);
        $this->assertEquals(520000, $this->cashAccount->fresh()->balance);
        $this->assertEquals('COMPLETED', $sale->fresh()->status);

        // 2. Endi VOID_SALE operatsiyasini batch push orqali jo'natamiz
        $voidOpId = (string) Str::uuid();
        $pushPayload = [
            'device_uuid' => $this->device->device_uuid,
            'lease_token' => $this->leaseToken,
            'operations' => [
                [
                    'operation_id' => $voidOpId,
                    'type' => 'VOID_SALE',
                    'device_created_at' => now()->toIso8601String(),
                    'payload' => [
                        'original_operation_id' => $saleOpId,
                        'reason' => 'Xaridor shartnomani bekor qildi',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/sync/push', $pushPayload);

        $response->assertStatus(200)
            ->assertJsonPath('results.0.status', 'APPLIED')
            ->assertJsonPath('results.0.entity_type', 'Sale')
            ->assertJsonPath('results.0.entity_id', $sale->id);

        // 3. Ombor, kassa va mijoz qarzining to'liq qaytarilganini (reversal) tekshiramiz
        $this->assertEquals('CANCELLED', $sale->fresh()->status);
        $this->assertEquals(100, InventoryBalance::where('product_variant_id', $this->variant->id)->value('quantity'));
        $this->assertEquals(0, $this->customer->fresh()->current_debt);
        $this->assertEquals(500000, $this->cashAccount->fresh()->balance);

        // 4. AuditLog mavjudligi
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'SALE_CANCEL',
            'auditable_id' => $sale->id,
        ]);
    }

    public function test_server_void_sale_idempotent_replay(): void
    {
        // 1. Savdo yaratamiz
        $saleOpId = (string) Str::uuid();
        $saleService = app(CreateSaleService::class);
        $sale = $saleService->execute(
            customerId: $this->customer->id,
            items: [
                ['variant_id' => $this->variant->id, 'quantity' => 5, 'sale_price' => 6000],
            ],
            operationId: $saleOpId,
            paidAmount: 30000,
            cashAccountId: $this->cashAccount->id,
            warehouseId: $this->warehouse->id,
            userId: $this->cashier->id,
            useSystemPrice: false
        );

        $voidOpId = (string) Str::uuid();
        $pushPayload = [
            'device_uuid' => $this->device->device_uuid,
            'lease_token' => $this->leaseToken,
            'operations' => [
                [
                    'operation_id' => $voidOpId,
                    'type' => 'VOID_SALE',
                    'device_created_at' => now()->toIso8601String(),
                    'payload' => [
                        'original_operation_id' => $saleOpId,
                        'reason' => 'Xatolik tuzatildi',
                    ],
                ],
            ],
        ];

        // 1-chi push: APPLIED
        $res1 = $this->actingAs($this->cashier, 'sanctum')->postJson('/api/sync/push', $pushPayload);
        $res1->assertStatus(200)->assertJsonPath('results.0.status', 'APPLIED');

        // 2-chi push (replay): RETRY_SUCCESS qaytishi kerak, ombor ikkinchi marta oshib ketmaydi
        $res2 = $this->actingAs($this->cashier, 'sanctum')->postJson('/api/sync/push', $pushPayload);
        $res2->assertStatus(200)->assertJsonPath('results.0.status', 'RETRY_SUCCESS');

        $this->assertEquals(100, InventoryBalance::where('product_variant_id', $this->variant->id)->value('quantity'));
    }

    public function test_void_sale_before_posted_records_safely(): void
    {
        $nonExistentSaleOpId = (string) Str::uuid();
        $voidOpId = (string) Str::uuid();

        $pushPayload = [
            'device_uuid' => $this->device->device_uuid,
            'lease_token' => $this->leaseToken,
            'operations' => [
                [
                    'operation_id' => $voidOpId,
                    'type' => 'VOID_SALE',
                    'device_created_at' => now()->toIso8601String(),
                    'payload' => [
                        'original_operation_id' => $nonExistentSaleOpId,
                        'reason' => 'Serverga bormasdan bekor qilindi',
                    ],
                ],
            ],
        ];

        $res = $this->actingAs($this->cashier, 'sanctum')->postJson('/api/sync/push', $pushPayload);
        $res->assertStatus(200)->assertJsonPath('results.0.status', 'APPLIED');
    }

    public function test_node_js_sync_protocol_tests_pass(): void
    {
        $cmd = 'node tests/pwa-sync-protocol-test.cjs';
        exec($cmd, $output, $exitCode);

        $this->assertSame(0, $exitCode, "Node.js sync tests failed: \n".implode("\n", $output));
    }
}

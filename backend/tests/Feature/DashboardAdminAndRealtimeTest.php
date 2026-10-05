<?php

namespace Tests\Feature;

use App\Events\SaleCreatedBroadcastEvent;
use App\Livewire\Dashboard\DashboardManager;
use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Device;
use App\Models\InventoryBalance;
use App\Models\OutboxEvent;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\SyncConflict;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Admin\SystemSettingsService;
use App\Services\Admin\UserManagementService;
use App\Services\Dashboard\DashboardQueryService;
use App\Services\Purchase\ReceivePurchaseService;
use App\Services\Sales\CreateSaleService;
use App\Services\Sync\SyncConflictResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardAdminAndRealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $salesUser;

    protected Warehouse $warehouse;

    protected CashAccount $cashAccount;

    protected Customer $customer;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles & Permissions setup
        $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);

        // 2. Owner User
        $this->owner = User::create([
            'name' => 'Boshqaruvchi Egasi',
            'email' => 'owner@aquaoptom.uz',
            'role' => 'OWNER',
            'status' => 'ACTIVE',
            'is_active' => true,
            'password' => bcrypt('password123'),
        ]);

        // 3. Sales Manager User (no view_cost_price permission by default)
        $this->salesUser = User::create([
            'name' => 'Sotuvchi Ali',
            'email' => 'ali@aquaoptom.uz',
            'role' => 'SALES_MANAGER',
            'status' => 'ACTIVE',
            'is_active' => true,
            'password' => bcrypt('password123'),
        ]);

        // 4. Warehouse & Cash Account
        $this->warehouse = Warehouse::firstOrCreate(
            ['name' => 'Asosiy Ombor'],
            ['is_default' => true]
        );

        $this->cashAccount = CashAccount::firstOrCreate(
            ['name' => 'Asosiy Kassa (Naqd)'],
            [
                'type' => 'CASH',
                'balance' => 1000000,
            ]
        );

        // 5. Product & Variant
        $product = Product::firstOrCreate(['name' => 'Hydrolife'], ['category' => 'Suv']);
        $volume = Volume::firstOrCreate(['value_ml' => 1500], ['name' => '1.5 L']);
        $this->variant = ProductVariant::firstOrCreate(
            ['sku' => 'HYDRO-1.5L'],
            [
                'product_id' => $product->id,
                'volume_id' => $volume->id,
                'default_sale_price' => 5000,
            ]
        );

        // Set initial stock (100 units at 3000 cost = 300 000 value)
        InventoryBalance::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_variant_id' => $this->variant->id],
            ['quantity' => 100, 'total_value' => 300000, 'average_cost' => 3000, 'updated_at' => now()]
        );

        // 6. Customer
        $this->customer = Customer::create([
            'name' => 'Akbar Savdo',
            'phone' => '+998901112233',
            'current_debt' => 0,
            'debt_limit' => 500000,
        ]);
    }

    /**
     * Test 1: Role-tailored dashboard: Owner sees cost & gross profit, Sales Manager sees masked data.
     */
    public function test_dashboard_metrics_are_tailored_by_role_and_cost_price_is_masked(): void
    {
        // Make a sale: 20 units * 5000 = 100 000, Cost = 20 * 3000 = 60 000, Gross Profit = 40 000
        $saleService = app(CreateSaleService::class);
        $saleService->execute(
            customerId: $this->customer->id,
            items: [
                ['variant_id' => $this->variant->id, 'quantity' => 20, 'sale_price' => 5000, 'is_system_price' => true],
            ],
            operationId: (string) Str::uuid(),
            paidAmount: 60000,
            cashAccountId: $this->cashAccount->id,
            warehouseId: $this->warehouse->id,
            userId: $this->owner->id
        );

        $dashboardService = app(DashboardQueryService::class);

        // 1. Owner query
        $ownerData = $dashboardService->getDashboardData($this->owner, 'today');
        $this->assertTrue($ownerData['can_view_cost']);
        $this->assertEquals(100000, $ownerData['flow']['total_sales']);
        $this->assertEquals(60000, $ownerData['flow']['total_cost']);
        $this->assertEquals(40000, $ownerData['flow']['gross_profit']);
        $this->assertEquals(240000, $ownerData['balances']['stock_cost_valuation']); // 80 remaining * 3000
        $this->assertNotNull($ownerData['balances']['potential_gross_profit']);

        // 2. Sales User query (No view_cost_price permission)
        $salesData = $dashboardService->getDashboardData($this->salesUser, 'today');
        $this->assertFalse($salesData['can_view_cost']);
        $this->assertEquals(100000, $salesData['flow']['total_sales']);
        $this->assertNull($salesData['flow']['total_cost']);
        $this->assertNull($salesData['flow']['gross_profit']);
        $this->assertNull($salesData['balances']['stock_cost_valuation']);
        $this->assertNull($salesData['balances']['potential_gross_profit']);
    }

    /**
     * Test 2: Flow vs As-Of Balances separation: Customer debts and advances are NEVER netted across customers.
     */
    public function test_customer_debts_and_advances_are_never_netted_together(): void
    {
        // Customer 1 has debt of +200 000
        $c1 = Customer::create(['name' => 'Qarzdor Bobur', 'phone' => '+998901234501', 'current_debt' => 200000]);

        // Customer 2 has advance of -50 000
        $c2 = Customer::create(['name' => 'Haqdor Sardor', 'phone' => '+998901234502', 'current_debt' => -50000]);

        // Supplier 1 has payable of +300 000
        Supplier::create(['name' => 'Zavod Suv', 'balance' => 300000]);

        // Supplier 2 has advance of -80 000
        Supplier::create(['name' => 'Kompaniya Shisha', 'balance' => -80000]);

        $dashboardService = app(DashboardQueryService::class);
        $data = $dashboardService->getDashboardData($this->owner, 'today');

        // Customer debts must be 200 000 (NOT netted 150 000)
        $this->assertEquals(200000, $data['balances']['customer_debts']);
        // Customer advances must be 50 000
        $this->assertEquals(50000, $data['balances']['customer_advances']);

        // Supplier payables must be 300 000
        $this->assertEquals(300000, $data['balances']['supplier_payables']);
        // Supplier advances must be 80 000
        $this->assertEquals(80000, $data['balances']['supplier_advances']);
    }

    /**
     * Test 3: Completeness Indicator: Active devices and stale devices (>24h) calculate data completeness.
     */
    public function test_completeness_indicator_reflects_offline_device_staleness(): void
    {
        // 1 active device synced 1 hour ago
        $dev1 = Device::create([
            'device_code' => 'DEV-001',
            'device_uuid' => (string) Str::uuid(),
            'name' => 'PC Kassa 1',
            'device_type' => 'PC',
            'status' => 'ACTIVE',
            'last_seen_at' => now()->subHour(),
        ]);

        // 1 active device stale (>24 hours ago)
        $dev2 = Device::create([
            'device_code' => 'DEV-002',
            'device_uuid' => (string) Str::uuid(),
            'name' => 'Telefon Kuryer',
            'device_type' => 'SMARTPHONE',
            'status' => 'ACTIVE',
            'last_seen_at' => now()->subHours(30),
        ]);

        $dashboardService = app(DashboardQueryService::class);
        $data = $dashboardService->getDashboardData($this->owner, 'today');

        // 1 out of 2 devices is stale -> 50% completeness
        $this->assertEquals(50, $data['warnings']['completeness_percent']);
        $this->assertEquals(1, $data['warnings']['stale_devices_count']);
        $this->assertStringContainsString('offline qurilma', $data['warnings']['completeness_note']);
    }

    /**
     * Test 4: Admin settings updates record AuditLog entries.
     */
    public function test_admin_settings_update_creates_audit_log(): void
    {
        $settingsService = app(SystemSettingsService::class);

        $settingsService->updateSettings([
            'store_name' => 'AquaOptom Yangi Nomi',
            'low_stock_threshold' => 15,
            'strict_credit_mode' => true,
        ], $this->owner);

        $this->assertEquals('AquaOptom Yangi Nomi', SystemSetting::get('store_name'));
        $this->assertEquals(15, SystemSetting::get('low_stock_threshold'));
        $this->assertTrue(SystemSetting::get('strict_credit_mode'));

        $audit = AuditLog::where('action', 'SYSTEM_SETTING_CHANGED')
            ->where('user_id', $this->owner->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals(SystemSetting::class, $audit->auditable_type);
    }

    /**
     * Test 5: Admin user status toggle and direct permission override record AuditLog entries.
     */
    public function test_admin_user_status_and_permission_override_record_audit_log(): void
    {
        $userService = app(UserManagementService::class);

        // 1. Toggle status
        $userService->toggleUserStatus($this->salesUser, $this->owner);
        $this->assertFalse($this->salesUser->fresh()->isActive());
        $this->assertEquals('BLOCKED', $this->salesUser->fresh()->status);

        $statusAudit = AuditLog::where('action', 'USER_STATUS_TOGGLED')->first();
        $this->assertNotNull($statusAudit);
        $this->assertEquals($this->salesUser->id, $statusAudit->auditable_id);

        // Reactivate
        $userService->toggleUserStatus($this->salesUser, $this->owner);

        // 2. Direct permission override: grant 'view_cost_price'
        $userService->overridePermission($this->salesUser, 'view_cost_price', true, $this->owner);
        $this->assertTrue($this->salesUser->fresh()->hasPermission('view_cost_price'));

        $permAudit = AuditLog::where('action', 'USER_PERMISSION_OVERRIDDEN')->first();
        $this->assertNotNull($permAudit);
    }

    /**
     * Test 6: Resolving NEEDS_REVIEW conflict updates stock, customer ledger, and writes AuditLog.
     */
    public function test_needs_review_resolution_updates_ledger_and_stock(): void
    {
        $device = Device::create([
            'device_code' => 'DEV-003',
            'device_uuid' => (string) Str::uuid(),
            'name' => 'Offline POS 1',
            'device_type' => 'PC',
            'status' => 'ACTIVE',
        ]);

        $opId = (string) Str::uuid();

        // Conflict representing an offline sale of 10 units at 5000 = 50 000, 20 000 paid, 30 000 debt
        $conflict = SyncConflict::create([
            'device_id' => $device->id,
            'user_id' => $this->salesUser->id,
            'operation_id' => $opId,
            'operation_type' => 'CREATE_SALE',
            'status' => 'NEEDS_REVIEW',
            'error_code' => 'LATE_CLOSED_SESSION',
            'error_message' => 'Smena allaqachon yopilgan edi.',
            'raw_payload' => [
                'customer_id' => $this->customer->id,
                'items' => [
                    ['variant_id' => $this->variant->id, 'quantity' => 10, 'sale_price' => 5000, 'unit_price' => 5000],
                ],
                'paid_amount' => 20000,
                'cash_account_id' => $this->cashAccount->id,
                'warehouse_id' => $this->warehouse->id,
            ],
        ]);

        $resolutionService = app(SyncConflictResolutionService::class);

        // Resolve conflict with APPROVED_OVERRIDE and reason
        $resolved = $resolutionService->resolveConflict(
            conflict: $conflict,
            admin: $this->owner,
            action: 'APPROVED_OVERRIDE',
            overrideData: [],
            reason: 'Admin tekshirdi, tovar berilgan, rasman qabul qilindi.'
        );

        $this->assertEquals('RESOLVED', $resolved->status);
        $this->assertEquals('APPROVED_OVERRIDE', $resolved->resolution_action);
        $this->assertEquals($this->owner->id, $resolved->resolved_by);

        // Stock decreased by 10 (100 - 10 = 90)
        $balance = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(90, $balance->quantity);

        // Customer debt increased by 30 000
        $this->assertEquals(30000, $this->customer->fresh()->current_debt);

        // AuditLog recorded
        $audit = AuditLog::where('action', 'SYNC_CONFLICT_RESOLVED')->first();
        $this->assertNotNull($audit);
        $this->assertEquals($conflict->id, $audit->auditable_id);
    }

    /**
     * Test 7: Private channel authorization: Active users can access store.operations; only cost-authorized can access store.finance.
     */
    public function test_broadcast_private_channel_authorization(): void
    {
        // 1. Channel callbacks authorization verification
        $channels = Broadcast::getChannels();
        $this->assertArrayHasKey('store.operations', $channels);
        $this->assertArrayHasKey('store.finance', $channels);

        $operationsCallback = $channels['store.operations'];
        $financeCallback = $channels['store.finance'];

        // store.operations: Active user allowed, blocked user denied
        $this->assertTrue((bool) $operationsCallback($this->owner));
        $this->assertTrue((bool) $operationsCallback($this->salesUser));
        $this->salesUser->update(['is_active' => false, 'status' => 'BLOCKED']);
        $this->assertFalse((bool) $operationsCallback($this->salesUser));

        // store.finance: Owner allowed, sales user without view_cost_price denied
        $this->salesUser->update(['is_active' => true, 'status' => 'ACTIVE']);
        $this->assertTrue((bool) $financeCallback($this->owner));
        $this->assertFalse((bool) $financeCallback($this->salesUser));

        // When permission is explicitly given to user, financeCallback allows access
        $this->salesUser->givePermission('view_cost_price', true);
        $this->assertTrue((bool) $financeCallback($this->salesUser));

        // 2. HTTP Endpoint test: blocked user receives 403 via EnsureUserIsActive middleware
        $this->salesUser->update(['is_active' => false, 'status' => 'BLOCKED']);
        $this->actingAs($this->salesUser);
        $responseBlocked = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-store.operations',
        ]);
        $responseBlocked->assertStatus(403);
    }

    /**
     * Test 8: Commit guarantee: Transaction commit dispatches broadcast event, rollback NEVER emits event.
     */
    public function test_rollback_never_emits_broadcast_events(): void
    {
        Event::fake([SaleCreatedBroadcastEvent::class]);

        $saleService = app(CreateSaleService::class);

        // A. Successful transaction
        $saleService->execute(
            customerId: $this->customer->id,
            items: [
                ['variant_id' => $this->variant->id, 'quantity' => 5, 'sale_price' => 5000, 'is_system_price' => true],
            ],
            operationId: (string) Str::uuid(),
            paidAmount: 25000,
            cashAccountId: $this->cashAccount->id,
            warehouseId: $this->warehouse->id,
            userId: $this->owner->id
        );

        Event::assertDispatched(SaleCreatedBroadcastEvent::class, 1);

        // B. Failed transaction (Insufficient stock: requesting 500 when only 95 left)
        try {
            $saleService->execute(
                customerId: $this->customer->id,
                items: [
                    ['variant_id' => $this->variant->id, 'quantity' => 500, 'sale_price' => 5000, 'is_system_price' => true],
                ],
                operationId: (string) Str::uuid(),
                paidAmount: 25000,
                cashAccountId: $this->cashAccount->id,
                warehouseId: $this->warehouse->id,
                userId: $this->owner->id
            );
        } catch (\Throwable $e) {
            // Expected failure
        }

        // Event count must still be strictly 1 (rollback did NOT emit event!)
        Event::assertDispatched(SaleCreatedBroadcastEvent::class, 1);
    }

    /**
     * Test 9: Flutter Invalidation API returns invalidated resources and advances cursor.
     */
    public function test_flutter_event_invalidation_api_cursor_catch_up(): void
    {
        // Create an Outbox event representing a Sale
        $outbox1 = OutboxEvent::create([
            'event_id' => (string) Str::uuid(),
            'operation_id' => (string) Str::uuid(),
            'event_name' => 'SaleCreated',
            'aggregate_type' => 'Sale',
            'aggregate_id' => '101',
            'payload' => ['invoice_number' => 'INV-001'],
            'status' => 'PUBLISHED',
        ]);

        $this->actingAs($this->owner);

        // 1. Initial poll from cursor 0
        $response = $this->getJson('/api/events/invalidation?cursor=0');
        $response->assertStatus(200);
        $json = $response->json();

        $this->assertTrue($json['success']);
        $this->assertEquals($outbox1->id, $json['cursor']);
        $this->assertContains('sales', $json['invalidated']);
        $this->assertContains('stock', $json['invalidated']);
        $this->assertContains('cash', $json['invalidated']);

        $nextCursor = $json['cursor'];

        // 2. Poll with updated cursor -> returns empty invalidated array
        $poll2 = $this->getJson("/api/events/invalidation?cursor={$nextCursor}");
        $poll2->assertStatus(200);
        $json2 = $poll2->json();

        $this->assertEquals($nextCursor, $json2['cursor']);
        $this->assertEmpty($json2['invalidated']);
    }

    /**
     * Test 10: Livewire open draft state retention during background refresh.
     */
    public function test_livewire_dashboard_preserves_uncommitted_draft_state(): void
    {
        $this->actingAs($this->owner);

        $testComponent = Livewire::test(DashboardManager::class)
            ->set('draftNote', 'Xaridorga 10 blok suv yetkazish rejalashtirildi')
            ->set('showDraftModal', true)
            ->call('refreshDashboard')
            ->assertSet('draftNote', 'Xaridorga 10 blok suv yetkazish rejalashtirildi')
            ->assertSet('showDraftModal', true)
            ->assertSee('Xaridorga 10 blok suv yetkazish rejalashtirildi');
    }

    public function test_dashboard_keeps_working_after_real_purchase_is_received(): void
    {
        $this->actingAs($this->owner);
        $supplier = Supplier::create(['name' => 'Water Supplier', 'balance' => 0, 'status' => 'active']);
        app(ReceivePurchaseService::class)->execute(
            supplierId: $supplier->id,
            items: [['variant_id' => $this->variant->id, 'quantity' => 10, 'unit_cost' => 3000]],
            operationId: (string) Str::uuid(),
            paidAmount: 0,
            userId: $this->owner->id
        );
        $data = app(DashboardQueryService::class)->getDashboardData($this->owner);
        $this->assertSame(110, $data['balances']['stock_units']);
        $this->assertSame(30000, $data['balances']['supplier_payables']);
        $purchase = collect($data['recent_activities'])->firstWhere('type', 'PURCHASE');
        $this->assertSame($this->owner->name, $purchase['actor']);
        $this->get('/dashboard')->assertOk()->assertSee('Water Supplier');
    }
}

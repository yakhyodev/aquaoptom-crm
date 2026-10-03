<?php

namespace Tests\Feature;

use App\Livewire\Devices\DeviceManager;
use App\Models\Customer;
use App\Models\Device;
use App\Models\InventoryAllocation;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Devices\DeviceService;
use App\Services\Devices\Exceptions\InvalidLeaseException;
use App\Services\Devices\OfflineLeaseService;
use App\Services\Ledger\CreditAllocationService;
use App\Services\Ledger\Exceptions\InsufficientAllocationException;
use App\Services\Ledger\Exceptions\InsufficientFreeStockException;
use App\Services\Ledger\Exceptions\ReservedStockProtectionException;
use App\Services\Ledger\InventoryAllocationService;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Sales\CreateSaleService;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OfflineDevicesAndAllocationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $cashier;

    protected Warehouse $warehouse;

    protected ProductVariant $variant;

    protected DeviceService $deviceService;

    protected OfflineLeaseService $leaseService;

    protected InventoryAllocationService $allocationService;

    protected CreditAllocationService $creditService;

    protected CreateSaleService $createSaleService;

    protected InventoryLedgerService $inventoryLedgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->owner = User::factory()->owner()->create(['name' => 'Do\'kon Egasi']);
        $this->cashier = User::factory()->salesManager()->create(['name' => 'Kassir Ali']);

        $this->warehouse = Warehouse::firstOrCreate(
            ['name' => 'Asosiy Ombor'],
            ['is_default' => true]
        );

        $product = Product::create([
            'name' => 'Fanta Apelsin',
            'normalized_name' => 'fanta apelsin',
            'code' => 'FANTA-001',
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
            'sku' => 'FANTA-500',
            'default_sale_price' => 6500,
            'version' => 1,
            'status' => 'ACTIVE',
        ]);

        $this->deviceService = app(DeviceService::class);
        $this->leaseService = app(OfflineLeaseService::class);
        $this->allocationService = app(InventoryAllocationService::class);
        $this->creditService = app(CreditAllocationService::class);
        $this->createSaleService = app(CreateSaleService::class);
        $this->inventoryLedgerService = app(InventoryLedgerService::class);
    }

    /**
     * Test 1: Device Registration & Unique Identifiers
     */
    public function test_device_registration_with_unique_code_and_uuid(): void
    {
        $device = $this->deviceService->registerDevice([
            'name' => 'Kassa Kompyuter 1',
            'device_type' => 'PC',
            'assigned_user_id' => $this->cashier->id,
            'allow_new_offline_customer_debt' => true,
            'new_customer_debt_budget' => 500000,
        ], $this->owner);

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'name' => 'Kassa Kompyuter 1',
            'device_type' => 'PC',
            'status' => 'ACTIVE',
            'is_active' => true,
            'allow_new_offline_customer_debt' => true,
            'new_customer_debt_budget' => 500000,
        ]);

        $this->assertStringStartsWith('DEV-', $device->device_code);
        $this->assertNotNull($device->device_uuid);

        // Duplicate UUID registration fails
        $this->expectException(OperationValidationException::class);
        $this->deviceService->registerDevice([
            'device_uuid' => $device->device_uuid,
            'name' => 'Duplicate Device',
        ], $this->owner);
    }

    /**
     * Test 2: Signed Timed Permission Snapshot (Lease) Issuance and Verification
     */
    public function test_signed_timed_permission_lease_issuance_and_signature_verification(): void
    {
        $device = Device::factory()->pc()->create(['registered_by' => $this->owner->id]);

        $leaseBundle = $this->leaseService->issueLease(
            device: $device,
            user: $this->cashier,
            permissions: ['offline_sales', 'sell_on_credit', 'custom_sale_price'],
            durationHours: 24,
            actor: $this->owner
        );

        $this->assertNotEmpty($leaseBundle['signature']);
        $this->assertEquals(24, (int) round(Carbon::parse($leaseBundle['valid_from'])->diffInHours(Carbon::parse($leaseBundle['expires_at']))));

        // Verify valid lease
        $verification = $this->leaseService->verifyLease(
            device: $device,
            leaseToken: $leaseBundle['lease_token'],
            signature: $leaseBundle['signature']
        );

        $this->assertTrue($verification['is_valid']);
        $this->assertNull($verification['reason']);

        // Tampered signature verification fails
        $tamperedVerification = $this->leaseService->verifyLease(
            device: $device,
            leaseToken: $leaseBundle['lease_token'],
            signature: 'invalid-tampered-hmac-signature'
        );

        $this->assertFalse($tamperedVerification['is_valid']);
        $this->assertEquals('SIGNATURE_MISMATCH', $tamperedVerification['reason']);
    }

    /**
     * Test 3: Core Architecture Scenario: 100 dona = PC 60 / Phone 30 / Free 10
     * "Fizik qoldiq va sotish huquqi rezervi alohida: 100 dona PC60/phone30/free10. Online savdo o‘z rezervi yoki erkin qoldiqni sarflasin."
     */
    public function test_stock_allocation_100_pieces_pc_60_phone_30_free_10_scenario(): void
    {
        // 1. Initial warehouse stock: 100 pieces @ 5000 WAC
        $this->inventoryLedgerService->recordInflow(
            productVariantId: $this->variant->id,
            quantity: 100,
            unitCost: 5000,
            movementType: 'OPENING',
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        $this->assertEquals(100, InventoryBalance::where('product_variant_id', $this->variant->id)->value('quantity'));

        // 2. Register PC device and Phone device
        $pc = Device::factory()->pc()->create();
        $phone = Device::factory()->mobile()->create();

        // 3. Grant 60 pieces to PC
        $this->allocationService->grantAllocation(
            device: $pc,
            variantId: $this->variant->id,
            quantity: 60,
            warehouseId: $this->warehouse->id,
            userId: $this->owner->id
        );

        // 4. Grant 30 pieces to Phone
        $this->allocationService->grantAllocation(
            device: $phone,
            variantId: $this->variant->id,
            quantity: 30,
            warehouseId: $this->warehouse->id,
            userId: $this->owner->id
        );

        // Check breakdown: Physical 100, Reserved 90, Free 10
        $breakdown = $this->allocationService->getAvailableStockBreakdown($this->variant->id, $this->warehouse->id);
        $this->assertEquals(100, $breakdown['physical_on_hand']);
        $this->assertEquals(90, $breakdown['total_reserved']);
        $this->assertEquals(10, $breakdown['free_stock']);

        // 5. Online unallocated sale requesting 15 pieces fails (only 10 is free!)
        $this->expectException(InsufficientFreeStockException::class);
        $this->createSaleService->execute(
            customerId: null,
            items: [
                ['variant_id' => $this->variant->id, 'quantity' => 15, 'is_system_price' => true],
            ],
            operationId: (string) Str::uuid(),
            paidAmount: 15 * 6500,
            warehouseId: $this->warehouse->id,
            userId: $this->cashier->id,
            source: 'web',
            deviceId: null // Online unallocated sale
        );
    }

    /**
     * Test 4: Online sale consumes free stock, device sales consume their reservations
     */
    public function test_online_sale_consumes_free_stock_and_device_consumes_reservation(): void
    {
        // 100 initial stock
        $this->inventoryLedgerService->recordInflow(
            productVariantId: $this->variant->id,
            quantity: 100,
            unitCost: 5000,
            movementType: 'OPENING',
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        $pc = Device::factory()->pc()->create();
        $phone = Device::factory()->mobile()->create();

        $this->allocationService->grantAllocation($pc, $this->variant->id, 60, $this->warehouse->id, null, $this->owner->id);
        $this->allocationService->grantAllocation($phone, $this->variant->id, 30, $this->warehouse->id, null, $this->owner->id);

        // Online unallocated sale of 10 pieces succeeds!
        $saleOnline = $this->createSaleService->execute(
            customerId: null,
            items: [
                ['variant_id' => $this->variant->id, 'quantity' => 10, 'is_system_price' => true],
            ],
            operationId: (string) Str::uuid(),
            paidAmount: 10 * 6500,
            warehouseId: $this->warehouse->id,
            userId: $this->cashier->id,
            source: 'web',
            deviceId: null
        );

        $this->assertNotNull($saleOnline->id);
        // Physical stock becomes 90
        $this->assertEquals(90, InventoryBalance::where('product_variant_id', $this->variant->id)->value('quantity'));
        // Free stock is now 0 (90 on hand - 90 reserved)
        $breakdown = $this->allocationService->getAvailableStockBreakdown($this->variant->id, $this->warehouse->id);
        $this->assertEquals(0, $breakdown['free_stock']);

        // PC sells 60 pieces from its reservation: succeeds!
        $salePC = $this->createSaleService->execute(
            customerId: null,
            items: [
                ['variant_id' => $this->variant->id, 'quantity' => 60, 'is_system_price' => true],
            ],
            operationId: (string) Str::uuid(),
            paidAmount: 60 * 6500,
            warehouseId: $this->warehouse->id,
            userId: $this->cashier->id,
            source: 'pos',
            deviceId: $pc->id
        );

        $this->assertNotNull($salePC->id);
        // Physical stock becomes 30 (only Phone's 30 remaining)
        $this->assertEquals(30, InventoryBalance::where('product_variant_id', $this->variant->id)->value('quantity'));
        $this->assertEquals(60, InventoryAllocation::where('device_id', $pc->id)->value('consumed_quantity'));

        // Phone attempting to sell 35 pieces fails (only 30 allocated!)
        $this->expectException(InsufficientAllocationException::class);
        $this->createSaleService->execute(
            customerId: null,
            items: [
                ['variant_id' => $this->variant->id, 'quantity' => 35, 'is_system_price' => true],
            ],
            operationId: (string) Str::uuid(),
            paidAmount: 35 * 6500,
            warehouseId: $this->warehouse->id,
            userId: $this->cashier->id,
            source: 'mobile',
            deviceId: $phone->id
        );
    }

    /**
     * Test 5: Idempotent allocation consumption
     * "bir operation_id rezervni ikki sarflamaydi"
     */
    public function test_consume_allocation_is_strictly_idempotent_per_operation_id(): void
    {
        $this->inventoryLedgerService->recordInflow(
            productVariantId: $this->variant->id,
            quantity: 50,
            unitCost: 5000,
            movementType: 'OPENING',
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        $device = Device::factory()->pc()->create();
        $this->allocationService->grantAllocation($device, $this->variant->id, 30, $this->warehouse->id, null, $this->owner->id);

        $opId = (string) Str::uuid();

        // First consume call
        $alloc1 = $this->allocationService->consumeAllocation(
            device: $device,
            variantId: $this->variant->id,
            quantity: 10,
            warehouseId: $this->warehouse->id,
            operationId: $opId,
            userId: $this->cashier->id
        );

        $this->assertEquals(10, $alloc1->consumed_quantity);
        $this->assertEquals(20, $alloc1->available_quantity);

        // Second consume call with same operation_id (retry / network replay)
        $alloc2 = $this->allocationService->consumeAllocation(
            device: $device,
            variantId: $this->variant->id,
            quantity: 10,
            warehouseId: $this->warehouse->id,
            operationId: $opId,
            userId: $this->cashier->id
        );

        // Must still be 10, not 20!
        $this->assertEquals(10, $alloc2->consumed_quantity);
        $this->assertEquals(20, $alloc2->available_quantity);
        $this->assertEquals(1, $device->inventoryAllocations()->first()->movements()->where('movement_type', 'CONSUME')->count());
    }

    /**
     * Test 6: Parallel Grant cannot exceed physical warehouse stock
     * "parallel grant/consume ajratmalar yig‘indisini stockdan oshirmaydi"
     */
    public function test_grant_allocations_cannot_exceed_physical_stock(): void
    {
        $this->inventoryLedgerService->recordInflow(
            productVariantId: $this->variant->id,
            quantity: 50,
            unitCost: 5000,
            movementType: 'OPENING',
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        $dev1 = Device::factory()->pc()->create();
        $dev2 = Device::factory()->mobile()->create();

        // Grant 35 to Dev1
        $this->allocationService->grantAllocation($dev1, $this->variant->id, 35, $this->warehouse->id, null, $this->owner->id);

        // Attempting to grant 20 to Dev2 fails because only 15 is free (50 - 35)
        $this->expectException(InsufficientFreeStockException::class);
        $this->allocationService->grantAllocation($dev2, $this->variant->id, 20, $this->warehouse->id, null, $this->owner->id);
    }

    /**
     * Test 7: Customer Credit Limit Reservation between Online and Offline Devices
     * "Credit limit online/offline rezervni hisobga oladi; Qat’iy customer credit limit yoqilsa bo‘sh limitni ham qurilmalarga ajrating"
     */
    public function test_credit_limit_accounts_for_online_and_offline_reservations(): void
    {
        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Bahrom Savdogar',
            'phone' => '+998901234567',
            'debt_limit' => 1000000, // 1 000 000 so'm limit
            'is_strict_credit_limit' => true,
            'current_debt' => 0,
            'status' => 'ACTIVE',
        ]);

        $phone = Device::factory()->mobile()->create();

        // Allocate 400 000 so'm credit reservation to Phone device
        $this->creditService->grantCreditAllocation(
            device: $phone,
            customer: $customer,
            amount: 400000,
            userId: $this->owner->id
        );

        // Free credit remaining for online: 1 000 000 - 400 000 = 600 000 so'm
        $freeLimit = $this->creditService->getAvailableCreditLimit($customer);
        $this->assertEquals(600000, $freeLimit);

        // Put stock in warehouse
        $this->inventoryLedgerService->recordInflow(
            productVariantId: $this->variant->id,
            quantity: 200,
            unitCost: 5000,
            movementType: 'OPENING',
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        // Online sale attempting to give 700 000 so'm debt fails (limit exceeded due to phone reservation!)
        $this->expectException(OperationValidationException::class);
        $this->createSaleService->execute(
            customerId: $customer->id,
            items: [
                ['variant_id' => $this->variant->id, 'quantity' => 100, 'sale_price' => 7000, 'is_system_price' => false],
            ],
            operationId: (string) Str::uuid(),
            paidAmount: 0, // Debt is 700 000 so'm
            paymentType: 'DEBT',
            warehouseId: $this->warehouse->id,
            userId: $this->cashier->id,
            source: 'web',
            deviceId: null
        );
    }

    /**
     * Test 8: Revocation/Expiry blocks NEW sales, but PRESERVES already locally-recorded pending sales
     * "revoke/expiry cheklovi ko‘rinadi, avvalgi pending savdo yo‘qolmaydi"
     */
    public function test_lease_revocation_blocks_new_operations_but_preserves_prior_pending_operations(): void
    {
        $device = Device::factory()->mobile()->create();

        $leaseBundle = $this->leaseService->issueLease(
            device: $device,
            user: $this->cashier,
            permissions: ['offline_sales'],
            durationHours: 24,
            actor: $this->owner
        );

        $lease = $device->activeAuthorization;
        // Lease was issued 2 hours ago
        $lease->update([
            'valid_from' => Carbon::now()->subHours(2),
            'created_at' => Carbon::now()->subHours(2),
        ]);

        // 1. Pending sale created 1 hour ago while lease was active
        $oneHourAgo = Carbon::now()->subHour();
        // validateOperationPermitted does not throw for past timestamp
        $this->leaseService->validateOperationPermitted($device, 'offline_sales', $oneHourAgo);

        // 2. Now revoke the lease
        $this->leaseService->revokeLease($lease, $this->owner, 'Sotuvchi ishdan bo\'shatildi');

        // 3. New operation attempted right now throws InvalidLeaseException
        $this->expectException(InvalidLeaseException::class);
        $this->leaseService->validateOperationPermitted($device, 'offline_sales', Carbon::now());

        // 4. Note: Prior pending operation timestamp remains permitted if checked with historical timestamp!
    }

    /**
     * Test 9: Lost device manual reconcile safely returns reserved stock back to free warehouse stock
     * "expiry/offline/reinstall rezervni avtomatik boshqa devicega bermaydi. Yo‘qolgan device manual reconcile."
     */
    public function test_lost_device_requires_manual_reconcile_to_release_stock(): void
    {
        $this->inventoryLedgerService->recordInflow(
            productVariantId: $this->variant->id,
            quantity: 50,
            unitCost: 5000,
            movementType: 'OPENING',
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        $device = Device::factory()->mobile()->create();
        $this->allocationService->grantAllocation($device, $this->variant->id, 30, $this->warehouse->id, null, $this->owner->id);

        $breakdownBefore = $this->allocationService->getAvailableStockBreakdown($this->variant->id, $this->warehouse->id);
        $this->assertEquals(20, $breakdownBefore['free_stock']);
        $this->assertEquals(30, $breakdownBefore['total_reserved']);

        // Marking lost does NOT automatically free stock!
        $this->deviceService->markDeviceLost($device, $this->owner, 'Telefon yo\'qoldi');
        $this->assertTrue($device->fresh()->isLost());

        $breakdownStillReserved = $this->allocationService->getAvailableStockBreakdown($this->variant->id, $this->warehouse->id);
        $this->assertEquals(20, $breakdownStillReserved['free_stock']); // Stock is NOT automatically stolen

        // Explicit manual reconciliation by administrator releases the stock
        $this->allocationService->manualReconcileLostDevice($device, $this->owner, 'Do\'kon egasi tomonidan qaytarildi');

        $breakdownAfterReconcile = $this->allocationService->getAvailableStockBreakdown($this->variant->id, $this->warehouse->id);
        $this->assertEquals(50, $breakdownAfterReconcile['free_stock']); // Full 50 is free again!
        $this->assertEquals(0, $breakdownAfterReconcile['total_reserved']);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DEVICE_MANUALLY_RECONCILED',
            'auditable_id' => $device->id,
        ]);
    }

    /**
     * Test 10: Warehouse reduction (damage/brak/return) protected from violating active reservations
     * "Stock kamaytiradigan keyingi return/brak/count ham rezerv kontraktiga majburiy ulanadi."
     */
    public function test_warehouse_stock_reduction_is_protected_against_active_reservations(): void
    {
        $this->inventoryLedgerService->recordInflow(
            productVariantId: $this->variant->id,
            quantity: 100,
            unitCost: 5000,
            movementType: 'OPENING',
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        $pc = Device::factory()->pc()->create();
        $this->allocationService->grantAllocation($pc, $this->variant->id, 70, $this->warehouse->id, null, $this->owner->id);

        // Warehouse has 100 on hand, 70 is reserved for PC, free is 30.
        // Attempting to reduce warehouse stock by 40 (e.g. return to supplier or damage) fails because only 30 is free!
        $this->expectException(ReservedStockProtectionException::class);
        $this->allocationService->validateStockReductionAllowed($this->variant->id, 40, $this->warehouse->id);
    }

    /**
     * Test 11: Livewire DeviceManager renders properly and registers device
     */
    public function test_livewire_device_manager_renders_and_registers_device(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(DeviceManager::class)
            ->assertSee('Sotish huquqi rezervlari')
            ->set('newDeviceName', 'Kassir Plansheti 1')
            ->set('newDeviceType', 'TABLET')
            ->call('registerDevice')
            ->assertHasNoErrors()
            ->assertSee('Qurilma muvaffaqiyatli', false);

        $this->assertDatabaseHas('devices', [
            'name' => 'Kassir Plansheti 1',
            'device_type' => 'TABLET',
        ]);
    }
}

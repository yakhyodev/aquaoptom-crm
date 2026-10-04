<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Device;
use App\Models\InventoryAllocation;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Devices\OfflineLeaseService;
use App\Services\Inventory\InventoryCalculatorService;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Payments\CustomerPaymentService;
use App\Services\Purchase\ReceivePurchaseService;
use App\Services\Sales\CreateSaleService;
use App\Services\Sync\Exceptions\RecoveryReconciliationRequiredException;
use App\Services\Sync\RecoveryReconciliationService;
use App\Services\Sync\SyncPushService;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StagingDeploymentAndAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $cashier;

    protected User $seller;

    protected Warehouse $warehouse;

    protected CashAccount $cashAccount;

    protected ProductVariant $variantFanta05;

    protected Supplier $supplier;

    protected Customer $customer;

    protected Device $pcDevice;

    protected Device $mobDevice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $ownerRole = Role::firstOrCreate(['name' => 'OWNER'], ['display_name' => 'Owner', 'permissions' => ['*']]);
        $cashierRole = Role::firstOrCreate(['name' => 'CASHIER'], ['display_name' => 'Cashier', 'permissions' => ['view_cash', 'manage_cash_sessions', 'view_debts', 'offline_sales', 'sell_on_credit']]);
        $sellerRole = Role::firstOrCreate(['name' => 'SALES_MANAGER'], ['display_name' => 'Seller', 'permissions' => ['sell_on_credit', 'offline_sales']]);

        $this->owner = User::create([
            'name' => 'Staging Owner',
            'email' => 'owner_stage@aquaoptom.test',
            'is_active' => true,
            'password' => Hash::make('Secret123!'),
            'role' => 'OWNER',
            'role_id' => $ownerRole->id,
            'status' => 'ACTIVE',
            'telegram_chat_id' => '123456789',
        ]);

        $this->cashier = User::create([
            'name' => 'Staging Cashier',
            'email' => 'cashier_stage@aquaoptom.test',
            'is_active' => true,
            'password' => Hash::make('Secret123!'),
            'role' => 'CASHIER',
            'role_id' => $cashierRole->id,
            'status' => 'ACTIVE',
        ]);
        $this->cashier->givePermission('offline_sales');

        $this->seller = User::create([
            'name' => 'Staging Seller',
            'email' => 'seller_stage@aquaoptom.test',
            'is_active' => true,
            'password' => Hash::make('Secret123!'),
            'role' => 'SALES_MANAGER',
            'role_id' => $sellerRole->id,
            'status' => 'ACTIVE',
        ]);

        $this->warehouse = Warehouse::firstOrCreate(['name' => 'Staging Ombor'], ['is_default' => true]);
        $this->cashAccount = CashAccount::firstOrCreate(['name' => 'Staging Naqd Kassa'], ['type' => 'CASH', 'balance' => 0, 'is_default' => true]);

        $product = Product::create(['name' => 'Fanta Staging', 'normalized_name' => 'fanta staging', 'code' => 'STG-001', 'status' => 'active']);
        $volume = Volume::create(['name' => '0.5 L', 'value_ml' => 500, 'unit' => 'L']);
        $this->variantFanta05 = ProductVariant::create([
            'product_id' => $product->id,
            'volume_id' => $volume->id,
            'sku' => 'STG-FANTA-05',
            'default_sale_price' => 7000,
            'min_stock_alert' => 20,
            'status' => 'active',
            'version' => 1,
        ]);

        $this->supplier = Supplier::create(['name' => 'Coca-Cola Staging', 'phone' => '+998711111111', 'balance' => 0]);
        $this->customer = Customer::create(['name' => 'Akmal Staging', 'phone' => '+998901112233', 'current_debt' => 0]);

        $this->pcDevice = Device::create([
            'device_uuid' => 'PC-STAGING-01',
            'device_code' => 'PC-01',
            'is_active' => true,
            'name' => 'PC POS',
            'device_type' => 'desktop',
            'status' => 'ACTIVE',
            'assigned_user_id' => $this->cashier->id,
            'registered_by' => $this->owner->id,
        ]);

        $this->mobDevice = Device::create([
            'device_uuid' => 'MOB-STAGING-02',
            'device_code' => 'MOB-02',
            'is_active' => true,
            'name' => 'Mobile POS',
            'device_type' => 'mobile',
            'status' => 'ACTIVE',
            'assigned_user_id' => $this->seller->id,
            'registered_by' => $this->owner->id,
        ]);
        app(OfflineLeaseService::class)->issueLease($this->pcDevice, $this->cashier);
        app(OfflineLeaseService::class)->issueLease($this->mobDevice, $this->seller);

        CashSession::create([
            'session_number' => 'CS-STAGE-001',
            'cash_account_id' => $this->cashAccount->id,
            'opened_by' => $this->cashier->id,
            'status' => 'OPEN',
            'opening_balance' => 0,
            'opened_at' => Carbon::now(),
        ]);

        $mobileCashAccount = CashAccount::create(['name' => 'Mobil Kassa', 'type' => 'CASH', 'balance' => 0]);
        CashSession::create([
            'session_number' => 'CS-STAGE-002',
            'cash_account_id' => $mobileCashAccount->id,
            'opened_by' => $this->seller->id,
            'status' => 'OPEN',
            'opening_balance' => 0,
            'opened_at' => Carbon::now(),
        ]);

        SystemSetting::set('system_recovery_epoch', 1);
        SystemSetting::set('system_recovery_status', 'NORMAL');
    }

    /**
     * Test Staging Health probes and version.
     */
    public function test_staging_environment_and_health_probes(): void
    {
        $response = $this->getJson('/api/health/live');
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'LIVE',
                'app' => 'AquaOptom CRM',
            ]);

        $readyResponse = $this->getJson('/api/health/ready');
        $readyResponse->assertStatus(200)
            ->assertJson([
                'status' => 'READY',
            ]);
    }

    /**
     * Test Pilot Workflow: Kirim -> Tezkor Savdo -> Nasiya -> Qarz To'lovi -> Kalkulyator.
     */
    public function test_staging_pilot_business_lifecycle(): void
    {
        $purchaseService = app(ReceivePurchaseService::class);
        $saleService = app(CreateSaleService::class);
        $paymentService = app(CustomerPaymentService::class);
        $calculatorService = app(InventoryCalculatorService::class);

        // 1. Kirim: 200 dona @ 5000 so'm = 1 000 000 so'm majburiyat
        $purchase = $purchaseService->execute(
            supplierId: $this->supplier->id,
            items: [['variant_id' => $this->variantFanta05->id, 'quantity' => 200, 'unit_cost' => 5000, 'new_sale_price' => 7000]],
            operationId: (string) Str::uuid(),
            paidAmount: 0,
            warehouseId: $this->warehouse->id,
            userId: $this->owner->id
        );
        $this->assertEquals(1000000, $purchase->total_amount);
        $this->assertEquals(200, InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->value('quantity'));

        // 2. Tezkor savdo: 10 dona x 7000 = 70 000 so'm naqd
        $quickSale = $saleService->execute(
            customerId: null,
            items: [['variant_id' => $this->variantFanta05->id, 'quantity' => 10, 'sale_price' => 7000, 'is_system_price' => true]],
            operationId: (string) Str::uuid(),
            paidAmount: 70000,
            cashAccountId: $this->cashAccount->id,
            paymentType: 'FULL',
            paymentMethod: 'CASH',
            userId: $this->cashier->id
        );
        $this->assertEquals(70000, $quickSale->total_amount);
        $this->assertEquals(190, InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->value('quantity'));

        // 3. Nasiya savdo: 20 dona x 7000 = 140 000 so'm (40 000 naqd, 100 000 nasiya)
        $creditSale = $saleService->execute(
            customerId: $this->customer->id,
            items: [['variant_id' => $this->variantFanta05->id, 'quantity' => 20, 'sale_price' => 7000, 'is_system_price' => true]],
            operationId: (string) Str::uuid(),
            paidAmount: 40000,
            cashAccountId: $this->cashAccount->id,
            paymentType: 'PARTIAL',
            paymentMethod: 'CASH',
            userId: $this->cashier->id
        );
        $this->assertEquals(100000, $creditSale->debt_amount);
        $this->assertEquals(100000, $this->customer->fresh()->current_debt);
        $this->assertEquals(170, InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->value('quantity'));

        // 4. Qarz to'lovi: 50 000 so'm
        $debtPayment = $paymentService->execute(
            customerId: $this->customer->id,
            amount: 50000,
            cashAccountId: $this->cashAccount->id,
            paymentMethod: 'CASH',
            operationId: (string) Str::uuid(),
            userId: $this->cashier->id
        );
        $this->assertEquals(50000, $this->customer->fresh()->current_debt);
        // Cash total: 70000 (quick) + 40000 (partial) + 50000 (debt pay) = 160000
        $this->assertEquals(160000, $this->cashAccount->fresh()->balance);

        // 5. Kalkulyator
        $calc = $calculatorService->calculate(
            selectedVariantIds: [$this->variantFanta05->id],
            warehouseId: $this->warehouse->id,
            canViewCost: true
        );
        $this->assertEquals(170, $calc['total_quantity_units']);
        $this->assertEquals(850000, $calc['total_cost_value']); // 170 * 5000
        $this->assertEquals(1190000, $calc['total_potential_sale_value']); // 170 * 7000
        $this->assertEquals(340000, $calc['expected_gross_profit']); // 1190000 - 850000
    }

    /**
     * Test Offline Multi-Device synchronization and Overdraft protection.
     */
    public function test_staging_offline_multi_device_and_overdraft_protection(): void
    {
        $syncPushService = app(SyncPushService::class);
        $invService = app(InventoryLedgerService::class);

        // Seed initial 100 stock
        $invService->recordInflow(
            productVariantId: $this->variantFanta05->id,
            quantity: 100,
            unitCost: 5000,
            movementType: 'OPENING_BALANCE',
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        // Allocate 50 to PC and 50 to Mobile
        InventoryAllocation::create([
            'device_id' => $this->pcDevice->id,
            'product_variant_id' => $this->variantFanta05->id,
            'warehouse_id' => $this->warehouse->id,
            'allocated_quantity' => 50,
            'consumed_quantity' => 0,
            'status' => 'ACTIVE',
            'epoch' => 1,
        ]);
        InventoryAllocation::create([
            'device_id' => $this->mobDevice->id,
            'product_variant_id' => $this->variantFanta05->id,
            'warehouse_id' => $this->warehouse->id,
            'allocated_quantity' => 50,
            'consumed_quantity' => 0,
            'status' => 'ACTIVE',
            'epoch' => 1,
        ]);

        $pcOpId = (string) Str::uuid();
        $pcOps = [
            [
                'operation_id' => $pcOpId,
                'operation_type' => 'CREATE_SALE',
                'client_sequence' => 1,
                'local_timestamp' => Carbon::now()->subMinutes(5)->toIso8601String(),
                'payload' => [
                    'customer_id' => null,
                    'items' => [['variant_id' => $this->variantFanta05->id, 'quantity' => 30, 'sale_price' => 7000]],
                    'paid_amount' => 210000,
                    'cash_account_id' => $this->cashAccount->id,
                    'payment_type' => 'FULL',
                    'payment_method' => 'CASH',
                ],
            ],
        ];

        $mobOpId = (string) Str::uuid();
        $mobOps = [
            [
                'operation_id' => $mobOpId,
                'operation_type' => 'CREATE_SALE',
                'client_sequence' => 1,
                'local_timestamp' => Carbon::now()->subMinutes(4)->toIso8601String(),
                'payload' => [
                    'customer_id' => null,
                    'items' => [['variant_id' => $this->variantFanta05->id, 'quantity' => 40, 'sale_price' => 7000]],
                    'paid_amount' => 280000,
                    'cash_account_id' => $this->cashAccount->id,
                    'payment_type' => 'FULL',
                    'payment_method' => 'CASH',
                ],
            ],
        ];

        // Push PC sale
        $resPc = $syncPushService->pushBatch($this->pcDevice, $this->cashier, $pcOps);
        $this->assertContains($resPc[0]['status'], ['ACK', 'APPLIED']);

        // Push Mobile sale
        $resMob = $syncPushService->pushBatch($this->mobDevice, $this->seller, $mobOps);
        $this->assertContains($resMob[0]['status'], ['ACK', 'APPLIED']);

        // Attempt overdraft on Mobile device: trying to sell 20 more when only 10 left in allocation
        $overdraftOps = [
            [
                'operation_id' => (string) Str::uuid(),
                'operation_type' => 'CREATE_SALE',
                'client_sequence' => 2,
                'local_timestamp' => Carbon::now()->toIso8601String(),
                'payload' => [
                    'customer_id' => null,
                    'items' => [['variant_id' => $this->variantFanta05->id, 'quantity' => 20, 'sale_price' => 7000]],
                    'paid_amount' => 140000,
                    'cash_account_id' => $this->cashAccount->id,
                    'payment_type' => 'FULL',
                    'payment_method' => 'CASH',
                ],
            ],
        ];
        $resOverdraft = $syncPushService->pushBatch($this->mobDevice, $this->seller, $overdraftOps);
        $this->assertNotEquals('ACK', $resOverdraft[0]['status']);
        $this->assertNotEquals('APPLIED', $resOverdraft[0]['status']);

        // Idempotency check: 10 repeated pushes
        for ($i = 0; $i < 10; $i++) {
            $replay = $syncPushService->pushBatch($this->pcDevice, $this->cashier, $pcOps);
            $this->assertContains($replay[0]['status'], ['ACK', 'APPLIED', 'ALREADY_PROCESSED', 'ALREADY_APPLIED', 'RETRY_SUCCESS']);
        }
        $this->assertEquals(1, Sale::where('operation_id', $pcOpId)->count());

        // Final balance: 100 - 30 - 40 = 30
        $this->assertEquals(30, InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->value('quantity'));
    }

    /**
     * Test Recovery Epoch and Offline Client Reconciliation.
     */
    public function test_staging_recovery_epoch_and_reconciliation(): void
    {
        $syncPushService = app(SyncPushService::class);
        $recoveryService = app(RecoveryReconciliationService::class);

        SystemSetting::set('system_recovery_epoch', 2);
        SystemSetting::set('system_recovery_status', 'RECONCILIATION_REQUIRED');

        $op = [
            [
                'operation_id' => (string) Str::uuid(),
                'operation_type' => 'CREATE_SALE',
                'client_sequence' => 1,
                'local_timestamp' => Carbon::now()->toIso8601String(),
                'payload' => [
                    'items' => [['variant_id' => $this->variantFanta05->id, 'quantity' => 5, 'sale_price' => 7000]],
                    'paid_amount' => 35000,
                ],
            ],
        ];

        // Normal push should be blocked with HTTP 428
        $this->expectException(RecoveryReconciliationRequiredException::class);
        $syncPushService->pushBatch($this->pcDevice, $this->cashier, $op);
    }
}

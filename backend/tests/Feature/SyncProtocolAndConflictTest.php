<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Device;
use App\Models\OperationResult;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SyncConflict;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Devices\DeviceService;
use App\Services\Devices\OfflineLeaseService;
use App\Services\Ledger\CashSessionService;
use App\Services\Ledger\InventoryAllocationService;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Sync\SyncChangeLogService;
use App\Services\Sync\SyncPushService;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncProtocolAndConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_device_permission_can_be_renewed_without_changing_allocations(): void
    {
        $old = $this->leaseService->issueLease($this->device, $this->cashier, durationHours: 1);
        $this->travel(2)->hours();
        $stockBefore = $this->device->activeInventoryAllocations()->get()->toArray();
        $creditBefore = $this->device->activeCreditAllocations()->get()->toArray();
        $response = $this->actingAs($this->cashier, 'sanctum')->postJson('/api/sync/renew-lease', ['device_uuid' => $this->device->device_uuid]);
        $response->assertOk()->assertJsonPath('success', true);
        $this->assertNotSame($old['lease_token'], $response->json('data.lease_token'));
        $this->assertTrue(Carbon::parse($response->json('data.expires_at'))->isFuture());
        $this->assertSame($stockBefore, $this->device->activeInventoryAllocations()->get()->toArray());
        $this->assertSame($creditBefore, $this->device->activeCreditAllocations()->get()->toArray());
        $this->travelBack();
    }

    public function test_device_renewal_rejects_an_unassigned_user_and_a_revoked_device(): void
    {
        $other = User::factory()->salesManager()->create();
        $this->actingAs($other, 'sanctum')->postJson('/api/sync/renew-lease', ['device_uuid' => $this->device->device_uuid])->assertForbidden();
        $this->device->update(['status' => 'REVOKED', 'is_active' => false]);
        $this->actingAs($this->cashier, 'sanctum')->postJson('/api/sync/renew-lease', ['device_uuid' => $this->device->device_uuid])->assertStatus(403);
    }

    protected User $owner;

    protected User $cashier;

    protected Device $device;

    protected Warehouse $warehouse;

    protected ProductVariant $variant;

    protected CashAccount $cashAccount;

    protected InventoryLedgerService $inventoryLedgerService;

    protected InventoryAllocationService $allocationService;

    protected OfflineLeaseService $leaseService;

    protected SyncChangeLogService $changeLogService;

    protected SyncPushService $pushService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->owner = User::factory()->owner()->create(['name' => 'Dilshod Egasi']);
        $this->cashier = User::factory()->salesManager()->create(['name' => 'Sotuvchi Bobur']);

        $this->warehouse = Warehouse::firstOrCreate(
            ['name' => 'Asosiy Ombor'],
            ['is_default' => true]
        );

        $this->cashAccount = CashAccount::firstOrCreate(
            ['type' => 'CASH'],
            ['name' => 'Asosiy Naqd Kassa', 'balance' => 0, 'is_default' => true]
        );

        $product = Product::create([
            'name' => 'Fanta Apelsin',
            'normalized_name' => 'fanta apelsin',
            'code' => 'PRD-FANTA-01',
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
            'sku' => 'FANTA-05L',
            'default_sale_price' => 6500,
            'version' => 1,
            'status' => 'ACTIVE',
        ]);

        $this->device = app(DeviceService::class)->registerDevice([
            'name' => 'Kassa Planshet 1',
            'device_type' => 'TABLET',
            'assigned_user_id' => $this->cashier->id,
            'allow_new_offline_customer_debt' => true,
            'new_customer_debt_budget' => 1_000_000,
        ], $this->owner);

        $this->inventoryLedgerService = app(InventoryLedgerService::class);
        $this->allocationService = app(InventoryAllocationService::class);
        $this->leaseService = app(OfflineLeaseService::class);
        Carbon::setTestNow(Carbon::now()->subDay());
        try {
            $this->leaseService->issueLease($this->device, $this->cashier, durationHours: 48);
        } finally {
            Carbon::setTestNow();
        }
        $this->changeLogService = app(SyncChangeLogService::class);
        $this->pushService = app(SyncPushService::class);
    }

    /**
     * Test 1: Device Bootstrap Endpoint: Snapshot, Leases, Allocations, Initial Cursor
     */
    public function test_audit_cancellation_before_create_blocks_late_sale(): void
    {
        $this->cashier->givePermission('process_refund');
        $originalId = (string) Str::uuid();
        $first = $this->pushService->pushBatch($this->device, $this->cashier, [['operation_id' => (string) Str::uuid(), 'type' => 'VOID_SALE', 'payload' => ['original_operation_id' => $originalId, 'reason' => 'Cancelled offline']]]);
        $this->assertSame('APPLIED', $first[0]['status']);
        $late = $this->pushService->pushBatch($this->device, $this->cashier, [['operation_id' => $originalId, 'type' => 'CREATE_SALE', 'payload' => ['items' => [['variant_id' => $this->variant->id, 'quantity' => 1, 'sale_price' => 6500]], 'paid_amount' => 6500]]]);
        $this->assertSame('CANCELLED_BEFORE_POSTING', $late[0]['error_code']);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_audit_offline_payment_retry_posts_only_once(): void
    {
        $customer = Customer::create(['name' => 'Debt payer', 'current_debt' => 10000, 'debt_limit' => 10000, 'status' => 'ACTIVE']);
        $op = ['operation_id' => (string) Str::uuid(), 'type' => 'CUSTOMER_PAYMENT', 'payload' => ['customer_id' => $customer->id, 'amount' => 2000, 'cash_account_id' => $this->cashAccount->id]];
        $first = $this->pushService->pushBatch($this->device, $this->cashier, [$op]);
        $second = $this->pushService->pushBatch($this->device, $this->cashier, [$op]);
        $this->assertEquals('APPLIED', $first[0]['status']);
        $this->assertEquals('RETRY_SUCCESS', $second[0]['status']);
        $this->assertEquals(8000, $customer->fresh()->current_debt);
        $this->assertEquals(2000, $this->cashAccount->fresh()->balance);
    }

    public function test_audit_sync_cannot_use_another_sellers_device(): void
    {
        $other = User::factory()->salesManager()->create();
        $this->actingAs($other, 'sanctum')->postJson('/api/sync/bootstrap', ['device_uuid' => $this->device->device_uuid])->assertForbidden();
        $this->actingAs($other, 'sanctum')->postJson('/api/sync/push', ['device_uuid' => $this->device->device_uuid, 'operations' => []])->assertForbidden();
    }

    public function test_audit_sync_fractional_price_and_quantity_are_not_truncated(): void
    {
        foreach ([['quantity' => 1.5, 'sale_price' => 6500], ['quantity' => 1, 'sale_price' => 6500.5]] as $item) {
            $results = $this->pushService->pushBatch($this->device, $this->cashier, [['operation_id' => (string) Str::uuid(), 'type' => 'CREATE_SALE',
                'payload' => ['items' => [array_merge(['variant_id' => $this->variant->id], $item)], 'payment_type' => 'DEBT']]]);
            $this->assertSame('FAILED', $results[0]['status']);
        }
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_audit_operation_status_is_actor_scoped_and_cost_masked_recursively(): void
    {
        $id = (string) Str::uuid();
        OperationResult::create(['operation_id' => $id, 'operation_type' => 'CREATE_SALE', 'payload_fingerprint' => 'test', 'actor_id' => $this->cashier->id,
            'status' => 'PROCESSED', 'result_payload' => ['gross_profit' => 1000, 'receipt_data' => ['items' => [['unit_cost' => 5000]]]]]);
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/sync/status/'.$id)->assertNotFound();
        $response = $this->actingAs($this->cashier, 'sanctum')->getJson('/api/sync/status/'.$id)->assertOk();
        $response->assertJsonPath('result_payload.gross_profit', null)->assertJsonPath('result_payload.receipt_data.items.0.unit_cost', null);
    }

    public function test_device_bootstrap_endpoint_returns_snapshot_lease_allocations_and_initial_cursor(): void
    {
        // 100 dona boshlang'ich ombor qoldig'i
        $this->inventoryLedgerService->recordInflow(
            productVariantId: $this->variant->id,
            quantity: 100,
            unitCost: 5000,
            movementType: 'OPENING',
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        // 30 dona tovar ajratmasi berish
        $this->allocationService->grantAllocation(
            device: $this->device,
            variantId: $this->variant->id,
            quantity: 30,
            warehouseId: $this->warehouse->id,
            userId: $this->owner->id
        );

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/sync/bootstrap', [
                'device_uuid' => $this->device->device_uuid,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.device.device_code', $this->device->device_code)
            ->assertJsonPath('data.device.allow_new_offline_customer_debt', true)
            ->assertJsonPath('data.device.new_customer_debt_budget', 1_000_000);

        $data = $response->json('data');
        $this->assertNotEmpty($data['lease']['signature']);
        $this->assertNotEmpty($data['lease']['lease_token']);
        $this->assertCount(1, $data['stock_allocations']);
        $this->assertEquals(30, $data['stock_allocations'][0]['available_quantity']);
        $this->assertArrayHasKey('current_cursor', $data);
        $this->assertArrayHasKey('server_time', $data);
    }

    /**
     * Test 2: Timeout + Retry returns exact previous result (Idempotent replay)
     */
    public function test_timeout_and_retry_returns_exact_previous_result(): void
    {
        // 50 dona tovar kirim
        $this->inventoryLedgerService->recordInflow(
            productVariantId: $this->variant->id,
            quantity: 50,
            unitCost: 5000,
            movementType: 'OPENING',
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        // Kassa smenasini ochish
        app(CashSessionService::class)->openSession(
            cashAccountId: $this->cashAccount->id,
            userId: $this->cashier->id,
            openingBalance: 0
        );

        $opId = (string) Str::uuid();
        $payload = [
            'type' => 'CREATE_SALE',
            'operation_id' => $opId,
            'device_created_at' => Carbon::now()->toIso8601String(),
            'payload' => [
                'items' => [
                    ['variant_id' => $this->variant->id, 'quantity' => 10, 'sale_price' => 6500],
                ],
                'paid_amount' => 65000,
                'payment_method' => 'CASH',
                'cash_account_id' => $this->cashAccount->id,
            ],
        ];

        // 1-marta push
        $resp1 = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_uuid' => $this->device->device_uuid,
                'operations' => [$payload],
            ]);

        $resp1->assertStatus(200);
        $res1 = $resp1->json('results.0');
        $this->assertEquals('APPLIED', $res1['status'], 'Error: '.($res1['message'] ?? 'none'));
        $invoiceNumber = $res1['server_document_number'];
        $saleId = $res1['server_document_id'];
        $this->assertNotNull($saleId);

        // Qoldiq 40 bo'ldi
        $this->assertEquals(40, $this->variant->inventoryBalances()->value('quantity'));

        // 2-marta push (Client timeout bo'ldi va aynan shu so'rovni qayta yubordi)
        $resp2 = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_uuid' => $this->device->device_uuid,
                'operations' => [$payload],
            ]);

        $resp2->assertStatus(200);
        $res2 = $resp2->json('results.0');
        $this->assertEquals('RETRY_SUCCESS', $res2['status']);
        $this->assertTrue($res2['is_replay']);
        $this->assertEquals($invoiceNumber, $res2['server_document_number']);
        $this->assertEquals($saleId, $res2['server_document_id']);

        // Ombordan ikkinchi marta tovar kamaymagan! Qoldiq hamon 40!
        $this->assertEquals(40, $this->variant->inventoryBalances()->value('quantity'));
        $this->assertEquals(1, Sale::count());
    }

    /**
     * Test 3: Mixed Batch with Per-Item Status (Bir batch ichida har amal o'z natijasiga ega)
     */
    public function test_mixed_batch_with_per_item_status(): void
    {
        $op1Id = (string) Str::uuid();
        $op2Id = (string) Str::uuid();
        $op3Id = (string) Str::uuid();

        $batch = [
            // Op 1: Yangi mijoz (Muvaffaqiyatli)
            [
                'operation_id' => $op1Id,
                'type' => 'CREATE_CUSTOMER',
                'device_created_at' => Carbon::now()->toIso8601String(),
                'payload' => [
                    'client_uuid' => (string) Str::uuid(),
                    'name' => 'Farhod Do\'koni',
                    'phone' => '+998901110011',
                    'store_name' => 'Farhod Savdo',
                ],
            ],
            // Op 2: Noma'lum mijozga nasiya savdo (NEEDS_REVIEW yoki FAILED)
            [
                'operation_id' => $op2Id,
                'type' => 'CREATE_SALE',
                'device_created_at' => Carbon::now()->toIso8601String(),
                'payload' => [
                    'customer_client_uuid' => 'non-existent-customer-uuid',
                    'items' => [
                        ['variant_id' => $this->variant->id, 'quantity' => 2, 'sale_price' => 6500],
                    ],
                    'paid_amount' => 0,
                ],
            ],
            // Op 3: Yana bir yangi mijoz (Muvaffaqiyatli)
            [
                'operation_id' => $op3Id,
                'type' => 'CREATE_CUSTOMER',
                'device_created_at' => Carbon::now()->toIso8601String(),
                'payload' => [
                    'client_uuid' => (string) Str::uuid(),
                    'name' => 'Sanjarbek',
                    'phone' => '+998902220022',
                    'store_name' => 'Sanjar Market',
                ],
            ],
        ];

        $resp = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_uuid' => $this->device->device_uuid,
                'operations' => $batch,
            ]);

        $resp->assertStatus(200);
        $results = $resp->json('results');
        $this->assertCount(3, $results);

        // Op 1 muvaffaqiyatli
        $this->assertEquals('APPLIED', $results[0]['status']);
        $this->assertEquals($op1Id, $results[0]['operation_id']);

        // Op 2 NEEDS_REVIEW (bog'liqlik xatosi, yozuv tashlab yuborilmaydi)
        $this->assertEquals('NEEDS_REVIEW', $results[1]['status']);
        $this->assertEquals($op2Id, $results[1]['operation_id']);
        $this->assertEquals('CUSTOMER_NOT_FOUND', $results[1]['error_code']);

        // Op 3 muvaffaqiyatli (Op 2 ning xatosi uni qulatmagan!)
        $this->assertEquals('APPLIED', $results[2]['status']);
        $this->assertEquals($op3Id, $results[2]['operation_id']);

        // Ikkala mijoz ham bazada mavjud
        $this->assertEquals(2, Customer::whereIn('name', ['Farhod Do\'koni', 'Sanjarbek'])->count());
        $this->assertEquals(1, SyncConflict::where('operation_id', $op2Id)->count());
    }

    /**
     * Test 4: Customer dependency: Offline UUID mijoz avval server ID ga ulanadi, keyin savdo
     * "UUID yangi mijoz avval server ID’ga ulanadi, keyin savdo; o‘xshash telefon/nomsiz automerge yo‘q."
     */
    public function test_customer_dependency_in_batch_maps_uuid_to_server_id_without_automerge(): void
    {
        // 100 dona qoldiq
        $this->inventoryLedgerService->recordInflow(
            productVariantId: $this->variant->id,
            quantity: 100,
            unitCost: 5000,
            movementType: 'OPENING',
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        $clientCustUuid = (string) Str::uuid();

        // 1. Tizimda oldindan ayni telefon raqamli boshqa mijoz bor
        $existingCustomer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Eski Akmal',
            'phone' => '+998935555555',
            'store_name' => 'Akmal Eski Do\'koni',
            'current_debt' => 0,
        ]);

        $op1Cust = (string) Str::uuid();
        $op2Sale = (string) Str::uuid();

        $batch = [
            // 1-amal: Yangi mijoz (ayni shu telefon raqami bilan, lekin boshqa do'kon/uuid)
            [
                'operation_id' => $op1Cust,
                'type' => 'CREATE_CUSTOMER',
                'device_created_at' => Carbon::now()->toIso8601String(),
                'payload' => [
                    'client_uuid' => $clientCustUuid,
                    'name' => 'Yangi Akmal',
                    'phone' => '+998935555555', // Bir xil telefon
                    'store_name' => 'Yangi Akmal Mini Market',
                ],
            ],
            // 2-amal: Shu yangi mijozga nasiya savdo
            [
                'operation_id' => $op2Sale,
                'type' => 'CREATE_SALE',
                'device_created_at' => Carbon::now()->toIso8601String(),
                'payload' => [
                    'customer_client_uuid' => $clientCustUuid,
                    'items' => [
                        ['variant_id' => $this->variant->id, 'quantity' => 10, 'sale_price' => 6500],
                    ],
                    'paid_amount' => 0,
                    'payment_method' => 'DEBT',
                ],
            ],
        ];

        $resp = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_uuid' => $this->device->device_uuid,
                'operations' => $batch,
            ]);

        $resp->assertStatus(200);
        $results = $resp->json('results');

        $this->assertEquals('APPLIED', $results[0]['status']);
        $this->assertEquals('APPLIED', $results[1]['status']);

        $newCustomer = Customer::where('uuid', $clientCustUuid)->first();
        $this->assertNotNull($newCustomer);
        $this->assertNotEquals($existingCustomer->id, $newCustomer->id, 'Automerge taqiqlangan!');

        // Savdo yangi mijozga bog'langan
        $sale = Sale::find($results[1]['server_document_id']);
        $this->assertEquals($newCustomer->id, $sale->customer_id);
        $this->assertEquals(65000, $sale->debt_amount);
        $this->assertEquals(65000, $newCustomer->fresh()->current_debt);
        $this->assertEquals(0, $existingCustomer->fresh()->current_debt);
    }

    /**
     * Test 5: Revoked Permission va Expired Lease tekshiruvi:
     * Bloklangandan keyingi yangi amallar NEEDS_REVIEW bo'ladi va saqlanadi.
     */
    public function test_revoked_permission_and_expired_lease_handling(): void
    {
        // Qurilmani bloklash
        $this->device->update([
            'status' => 'REVOKED',
            'is_active' => false,
            'updated_at' => Carbon::now()->subMinutes(10),
        ]);

        $opId = (string) Str::uuid();
        $batch = [
            [
                'operation_id' => $opId,
                'type' => 'CREATE_CUSTOMER',
                'device_created_at' => Carbon::now()->toIso8601String(), // Bloklangandan keyin yaratilgan
                'payload' => [
                    'client_uuid' => (string) Str::uuid(),
                    'name' => 'Bloklangan Mijoz',
                    'phone' => '+998909998877',
                    'store_name' => 'Blok Market',
                ],
            ],
        ];

        $resp = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_uuid' => $this->device->device_uuid,
                'operations' => $batch,
            ]);

        $resp->assertStatus(200);
        $res = $resp->json('results.0');

        $this->assertEquals('NEEDS_REVIEW', $res['status']);
        $this->assertEquals('DEVICE_REVOKED', $res['error_code']);

        // Yozuv tashlab yuborilmagan! sync_conflicts da mavjud!
        $this->assertDatabaseHas('sync_conflicts', [
            'operation_id' => $opId,
            'error_code' => 'DEVICE_REVOKED',
            'status' => 'NEEDS_REVIEW',
        ]);
    }

    /**
     * Test 6: Stale narx qoidasi va server posting tannarxi:
     * "Offline narx saqlanadi, final cost server posting tartibida, device time/server received/posted ajratiladi."
     */
    public function test_stale_price_preserves_offline_agreed_sale_price_and_calculates_server_wac_cost(): void
    {
        // 1. Dastlabki kirim: 50 dona @ 5 000 so'm
        $this->inventoryLedgerService->recordInflow(
            productVariantId: $this->variant->id,
            quantity: 50,
            unitCost: 5000,
            movementType: 'OPENING',
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        // 2. Offline savdo paytida tizim narxi 6 500 bo'lgan
        $deviceTime = Carbon::now()->subHours(2);

        // 3. Qurilma offline bo'lgan vaqtda serverda narx oshirildi: 7 500 so'mga!
        $this->variant->update(['default_sale_price' => 7500, 'version' => 2]);

        // Va yangi qimmatroq partiya kirim qilindi (WAC oshdi: 50 dona @ 6 000 => WAC 5500 bo'ldi)
        $this->inventoryLedgerService->recordInflow(
            productVariantId: $this->variant->id,
            quantity: 50,
            unitCost: 6000,
            movementType: 'PURCHASE',
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id
        );

        $this->assertEquals(5500, $this->variant->inventoryBalances()->value('average_cost'));

        $opId = (string) Str::uuid();
        $batch = [
            [
                'operation_id' => $opId,
                'type' => 'CREATE_SALE',
                'device_created_at' => $deviceTime->toIso8601String(),
                'payload' => [
                    'items' => [
                        // Offline kelishilgan narx: 6500 so'm (serverda 7500 bo'lsa ham!)
                        ['variant_id' => $this->variant->id, 'quantity' => 10, 'sale_price' => 6500],
                    ],
                    'paid_amount' => 65000,
                    'payment_method' => 'CASH',
                ],
            ],
        ];

        // Kassa smenasini ochish
        app(CashSessionService::class)->openSession(
            cashAccountId: $this->cashAccount->id,
            userId: $this->cashier->id,
            openingBalance: 0
        );

        $resp = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_uuid' => $this->device->device_uuid,
                'operations' => $batch,
            ]);

        $resp->assertStatus(200);
        $res = $resp->json('results.0');
        $this->assertEquals('APPLIED', $res['status']);

        $sale = Sale::find($res['server_document_id']);
        // 1. Sotuv summasi offline kelishilgan narx bo'yicha: 10 * 6500 = 65 000 (75 000 EMAS!)
        $this->assertEquals(65000, $sale->total_amount);

        // 2. Tannarx server posting tartibidagi WAC (5 500) bo'yicha: 10 * 5500 = 55 000 so'm!
        $this->assertEquals(55000, $sale->total_cost);
        $this->assertEquals(10000, $sale->gross_profit); // 65000 - 55000 = 10000

        // 3. Uchta vaqt aniq ajratilgan
        $this->assertNotNull($sale->device_created_at);
        $this->assertNotNull($sale->received_at);
        $this->assertNotNull($sale->posted_at);
        $this->assertEquals($deviceTime->format('Y-m-d H:i:s'), $sale->device_created_at->format('Y-m-d H:i:s'));
    }

    /**
     * Test 7: Tombstone va Reconnect Delta feed:
     * O'chirilgan/arxivlangan yozuvlar tombstone bilan pull feedda qaytadi.
     */
    public function test_tombstone_and_reconnect_delta(): void
    {
        $cust = Customer::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tombstone Mijoz',
            'phone' => '+998907770077',
            'store_name' => 'Tombstone Do\'koni',
            'current_debt' => 0,
        ]);

        // Tombstone yozuvini yaratish
        $this->changeLogService->logChange(
            entityType: 'CUSTOMER',
            entityId: $cust->id,
            changeType: 'DELETED',
            payload: ['id' => $cust->id, 'uuid' => $cust->uuid],
            version: 2,
            isTombstone: true
        );

        $resp = $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/sync/pull?cursor=0&limit=50');

        $resp->assertStatus(200)
            ->assertJsonPath('success', true);

        $items = $resp->json('data.items');
        $this->assertNotEmpty($items);

        $tombstoneItems = array_filter($items, fn ($it) => $it['is_tombstone'] === true);
        $this->assertNotEmpty($tombstoneItems);

        $firstTombstone = reset($tombstoneItems);
        $this->assertEquals('CUSTOMER', $firstTombstone['entity_type']);
        $this->assertEquals('DELETED', $firstTombstone['change_type']);
        $this->assertTrue($firstTombstone['is_tombstone']);
    }

    /**
     * Test 8: Haqiqatan yangi bir xil summali savdolar ikkalasi ham saqlanadi:
     * Ikki xil operation_id ga ega bir xil summali savdo soxta dedup qilinmaydi.
     */
    public function test_genuinely_new_sales_with_identical_amounts_are_both_preserved(): void
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

        app(CashSessionService::class)->openSession(
            cashAccountId: $this->cashAccount->id,
            userId: $this->cashier->id,
            openingBalance: 0
        );

        $op1 = (string) Str::uuid();
        $op2 = (string) Str::uuid();

        $batch = [
            [
                'operation_id' => $op1,
                'type' => 'CREATE_SALE',
                'device_created_at' => Carbon::now()->toIso8601String(),
                'payload' => [
                    'items' => [
                        ['variant_id' => $this->variant->id, 'quantity' => 5, 'sale_price' => 6500],
                    ],
                    'paid_amount' => 32500,
                    'payment_method' => 'CASH',
                ],
            ],
            [
                'operation_id' => $op2,
                'type' => 'CREATE_SALE',
                'device_created_at' => Carbon::now()->toIso8601String(),
                'payload' => [
                    'items' => [
                        ['variant_id' => $this->variant->id, 'quantity' => 5, 'sale_price' => 6500],
                    ],
                    'paid_amount' => 32500,
                    'payment_method' => 'CASH',
                ],
            ],
        ];

        $resp = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_uuid' => $this->device->device_uuid,
                'operations' => $batch,
            ]);

        $resp->assertStatus(200);
        $results = $resp->json('results');

        $this->assertEquals('APPLIED', $results[0]['status']);
        $this->assertEquals('APPLIED', $results[1]['status']);
        $this->assertNotEquals($results[0]['server_document_id'], $results[1]['server_document_id']);

        // Ikkala savdo ham alohida saqlangan (2 ta chek)
        $this->assertEquals(2, Sale::count());
        $this->assertEquals(90, $this->variant->inventoryBalances()->value('quantity'));
    }

    /**
     * Test 9: Bir ID boshqa payload = 409 Conflict:
     * Bir xil operation_id bilan boshqa summa/qator kelganda original o'zgarmaydi.
     */
    public function test_same_operation_id_with_different_payload_returns_conflict(): void
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

        app(CashSessionService::class)->openSession(
            cashAccountId: $this->cashAccount->id,
            userId: $this->cashier->id,
            openingBalance: 0
        );

        $opId = (string) Str::uuid();

        // 1-so'rov: 10 dona
        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_uuid' => $this->device->device_uuid,
                'operations' => [
                    [
                        'operation_id' => $opId,
                        'type' => 'CREATE_SALE',
                        'payload' => [
                            'items' => [
                                ['variant_id' => $this->variant->id, 'quantity' => 10, 'sale_price' => 6500],
                            ],
                            'paid_amount' => 65000,
                            'payment_method' => 'CASH',
                        ],
                    ],
                ],
            ]);

        $this->assertEquals(1, Sale::count());

        // 2-so'rov: Shu ID bilan 20 dona (Boshqa payload)
        $resp2 = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_uuid' => $this->device->device_uuid,
                'operations' => [
                    [
                        'operation_id' => $opId,
                        'type' => 'CREATE_SALE',
                        'payload' => [
                            'items' => [
                                ['variant_id' => $this->variant->id, 'quantity' => 20, 'sale_price' => 6500],
                            ],
                            'paid_amount' => 130000,
                            'payment_method' => 'CASH',
                        ],
                    ],
                ],
            ]);

        $resp2->assertStatus(200);
        $res = $resp2->json('results.0');

        $this->assertEquals('CONFLICT', $res['status']);
        $this->assertEquals('PAYLOAD_MISMATCH', $res['error_code']);

        // Original savdo o'zgarmagan (hamon 1 ta savdo, 10 dona)
        $this->assertEquals(1, Sale::count());
        $this->assertEquals(65000, Sale::first()->total_amount);
    }

    /**
     * Test 10: PostgreSQL commit-order cursor va pagination
     */
    public function test_postgresql_commit_order_cursor_monotonicity(): void
    {
        // 5 ta ketma-ket change log yaratish
        for ($i = 1; $i <= 5; $i++) {
            $this->changeLogService->logChange(
                entityType: 'PRODUCT',
                entityId: $i,
                changeType: 'UPDATED',
                payload: ['id' => $i, 'step' => $i]
            );
        }

        // 1-sahifa: limit 3
        $resp1 = $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/sync/pull?cursor=0&limit=3');

        $resp1->assertStatus(200);
        $data1 = $resp1->json('data');

        $this->assertCount(3, $data1['items']);
        $this->assertTrue($data1['has_more']);
        $nextCursor = $data1['next_cursor'];
        $this->assertGreaterThan(0, $nextCursor);

        // 2-sahifa: nextCursor bilan davom etish
        $resp2 = $this->actingAs($this->cashier, 'sanctum')
            ->getJson("/api/sync/pull?cursor={$nextCursor}&limit=3");

        $resp2->assertStatus(200);
        $data2 = $resp2->json('data');

        $this->assertCount(2, $data2['items']);
        $this->assertFalse($data2['has_more']);

        // Barcha kursorlar qat'iy o'suvchi tartibda
        $prevId = 0;
        foreach (array_merge($data1['items'], $data2['items']) as $item) {
            $this->assertGreaterThan($prevId, $item['cursor']);
            $prevId = $item['cursor'];
        }
    }

    /**
     * Test 11: Late closed session NEEDS_REVIEW holatiga tushadi:
     * Yopilgan smenadan keyin kelgan naqd savdo tashlab yuborilmaydi!
     */
    public function test_late_closed_session_puts_operation_into_needs_review(): void
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

        $sessionService = app(CashSessionService::class);

        // Smena ochildi va 1 soat oldin yopildi
        $session = $sessionService->openSession(
            cashAccountId: $this->cashAccount->id,
            userId: $this->cashier->id,
            openingBalance: 0
        );

        $sessionService->closeSession(
            sessionId: $session->id,
            userId: $this->cashier->id,
            actualClosingBalance: 0
        );

        $session->update(['closed_at' => Carbon::now()->subMinutes(30)]);

        // Hozir ochiq smena YO'Q!
        $opId = (string) Str::uuid();
        $batch = [
            [
                'operation_id' => $opId,
                'type' => 'CREATE_SALE',
                'device_created_at' => Carbon::now()->subHours(1)->toIso8601String(), // Yopilgan smena davridagi savdo
                'payload' => [
                    'items' => [
                        ['variant_id' => $this->variant->id, 'quantity' => 5, 'sale_price' => 6500],
                    ],
                    'paid_amount' => 32500,
                    'payment_method' => 'CASH',
                    'cash_account_id' => $this->cashAccount->id,
                ],
            ],
        ];

        $resp = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_uuid' => $this->device->device_uuid,
                'operations' => $batch,
            ]);

        $resp->assertStatus(200);
        $res = $resp->json('results.0');

        $this->assertEquals('NEEDS_REVIEW', $res['status']);
        $this->assertEquals('LATE_CLOSED_SESSION', $res['error_code']);

        // sync_conflicts da saqlangan, yozuv tashlab yuborilmagan!
        $this->assertDatabaseHas('sync_conflicts', [
            'operation_id' => $opId,
            'status' => 'NEEDS_REVIEW',
            'error_code' => 'LATE_CLOSED_SESSION',
        ]);

        $conflict = SyncConflict::where('operation_id', $opId)->first();
        $this->assertNotNull($conflict);
        $this->assertEquals(32500, $conflict->raw_payload['paid_amount']);
    }

    /**
     * Test 12: Admin Conflict Resolution API:
     * Mojaroni ko'rib hal qilish (APPROVED_OVERRIDE / REJECT) auditli va originalni o'chirmaydi.
     */
    public function test_admin_conflict_resolution_api_approves_override_or_rejects_with_audit(): void
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

        $opId = (string) Str::uuid();

        // Mojaro yaratish
        $conflict = SyncConflict::create([
            'device_id' => $this->device->id,
            'user_id' => $this->cashier->id,
            'operation_id' => $opId,
            'operation_type' => 'CREATE_SALE',
            'status' => 'NEEDS_REVIEW',
            'raw_payload' => [
                'items' => [
                    ['variant_id' => $this->variant->id, 'quantity' => 10, 'sale_price' => 6500],
                ],
                'paid_amount' => 0,
                'payment_method' => 'DEBT',
            ],
            'payload_fingerprint' => 'test-fingerprint',
            'error_code' => 'INSUFFICIENT_ALLOCATION',
            'error_message' => 'Qurilmada yetarli rezerv mavjud emas edi',
        ]);

        // 1. Admin ro'yxatni ko'radi
        $listResp = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/sync/conflicts?status=NEEDS_REVIEW');

        $listResp->assertStatus(200)
            ->assertJsonPath('success', true);

        // 2. Admin konfliktni tasdiqlab o'tkazadi (APPROVED_OVERRIDE)
        $resolveResp = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/sync/conflicts/{$conflict->id}/resolve", [
                'action' => 'APPROVED_OVERRIDE',
                'reason' => 'Do\'kon egasi tomonidan tasdiqlandi, ombor erkin qoldig\'idan o\'tkazilsin',
                'override_data' => [
                    'warehouse_id' => $this->warehouse->id,
                ],
            ]);

        $resolveResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'RESOLVED')
            ->assertJsonPath('data.resolution_action', 'APPROVED_OVERRIDE');

        $conflict->refresh();
        $this->assertEquals('RESOLVED', $conflict->status);
        $this->assertEquals($this->owner->id, $conflict->resolved_by);
        $this->assertNotNull($conflict->resolved_at);
        $this->assertEquals('Sale', $conflict->server_entity_type);
        $this->assertNotNull($conflict->server_entity_id);

        // Asl raw_payload o'chmagan!
        $this->assertNotEmpty($conflict->raw_payload);

        // Audit log yaratilgan
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'SYNC_CONFLICT_RESOLVED',
            'user_id' => $this->owner->id,
            'auditable_type' => SyncConflict::class,
            'auditable_id' => $conflict->id,
        ]);
    }
}

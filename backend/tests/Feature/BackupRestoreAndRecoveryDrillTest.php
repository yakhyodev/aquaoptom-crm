<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Device;
use App\Models\InventoryAllocation;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Backup\BackupService;
use App\Services\Devices\OfflineLeaseService;
use App\Services\Sync\RecoveryReconciliationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;
use ZipArchive;

class BackupRestoreAndRecoveryDrillTest extends TestCase
{
    protected User $owner;

    protected Device $device;

    protected Warehouse $warehouse;

    protected ProductVariant $variant;

    protected Customer $customer;

    protected Supplier $supplier;

    protected CashAccount $cashAccount;

    public function test_audit_restore_rejects_path_traversal_before_database_restore(): void
    {
        $path = storage_path('app/unsafe-audit-'.Str::uuid().'.zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('../escape.txt', 'unsafe');
        $zip->close();
        $this->expectExceptionMessage('Unsafe backup archive path.');
        try {
            app(BackupService::class)->restoreBackup($path, ['target_db' => 'aquaoptom_test']);
        } finally {
            File::delete($path);
        }
    }

    public function test_audit_recovery_cannot_resume_without_all_device_review(): void
    {
        SystemSetting::set('system_recovery_status', 'RECONCILIATION_REQUIRED');
        try {
            app(RecoveryReconciliationService::class)->markRecoveryCompleted($this->owner->id);
            $this->fail('Unreviewed device must block recovery completion');
        } catch (\RuntimeException $error) {
            $this->assertSame('RECONCILIATION_REQUIRED', SystemSetting::get('system_recovery_status'));
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $ownerRole = Role::firstOrCreate(['name' => 'OWNER'], [
            'display_name' => 'Do\'kon egasi',
            'permissions' => ['*'],
        ]);

        $this->owner = User::create([
            'name' => 'Drill Owner',
            'email' => 'drill_owner@aquaoptom.uz',
            'phone' => '+998909998877',
            'role' => 'OWNER',
            'role_id' => $ownerRole->id,
            'password' => Hash::make('Password123!'),
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Asosiy Ombor',
            'code' => 'MAIN-01',
            'address' => 'Toshkent sh.',
        ]);

        $this->cashAccount = CashAccount::create([
            'name' => 'Asosiy Kassa',
            'type' => 'CASH',
            'currency' => 'UZS',
            'balance' => 500000,
            'is_default' => true,
            'status' => 'ACTIVE',
        ]);

        CashSession::create([
            'session_number' => 'CS-DRILL-01',
            'cash_account_id' => $this->cashAccount->id,
            'opened_by' => $this->owner->id,
            'opening_balance' => 500000,
            'opened_at' => now(),
            'status' => 'OPEN',
        ]);

        $product = Product::create([
            'name' => 'Nestle Pure Life',
            'brand' => 'Nestle',
            'category' => 'Suv',
            'base_unit' => 'dona',
            'status' => 'ACTIVE',
        ]);

        $volume = Volume::create([
            'value_ml' => 1500,
            'name' => '1.5L',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'volume_id' => $volume->id,
            'sku' => 'NESTLE-1.5L',
            'price' => 5000,
            'cost_price' => 3500,
            'status' => 'ACTIVE',
        ]);

        $this->customer = Customer::create([
            'name' => 'Akbar Savdo',
            'phone' => '+998911112233',
            'address' => 'Chilonzor',
            'status' => 'ACTIVE',
            'balance' => 0,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Nestle Uzbekistan',
            'phone' => '+998712003040',
            'contact_person' => 'Dilshod',
            'status' => 'ACTIVE',
            'balance' => 0,
        ]);

        $this->device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'DEV-DRILL-01',
            'name' => 'Drill Test Device',
            'device_type' => 'MOBILE',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);
    }

    public function test_backup_succeeds_without_user_files(): void
    {
        $originalStoragePath = storage_path();
        $isolatedStoragePath = storage_path('framework/testing/empty-backup-'.Str::uuid());
        File::ensureDirectoryExists($isolatedStoragePath.'/app/private');
        File::ensureDirectoryExists($isolatedStoragePath.'/app/public');
        $this->app->useStoragePath($isolatedStoragePath);

        try {
            $backup = app(BackupService::class)->createBackup(['encrypt' => false]);

            $this->assertTrue($backup['success']);
            $this->assertSame(0, $backup['manifest']['files_count']);

            $archive = new ZipArchive;
            $this->assertTrue($archive->open($backup['file_path']));
            $this->assertIsString($archive->getFromName('files.zip'));
            $archive->close();
        } finally {
            $this->app->useStoragePath($originalStoragePath);
            File::deleteDirectory($isolatedStoragePath);
        }
    }

    /**
     * 1. Backup Creation & Disaster Recovery Drill into Isolated Database
     */
    public function test_full_encrypted_backup_creation_and_isolated_database_restore_drill(): void
    {
        // 1. Dastlabki biznes ma'lumotlarini kiritish
        InventoryBalance::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 100,
            'total_value' => 350000,
            'wac' => 3500,
        ]);

        CustomerLedger::create([
            'operation_id' => (string) Str::uuid(),
            'customer_id' => $this->customer->id,
            'type' => 'SALE',
            'payment_method' => 'CASH',
            'debit' => 150000,
            'credit' => 50000,
            'balance_after' => 100000,
            'notes' => 'Qisman to\'lovli savdo',
            'created_by' => $this->owner->id,
            'created_at' => now(),
        ]);

        SupplierLedger::create([
            'operation_id' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'type' => 'PURCHASE',
            'payment_method' => 'CASH',
            'debit' => 200000,
            'credit' => 600000,
            'balance_after' => 400000,
            'notes' => 'Yangi partiya kirimi',
            'created_by' => $this->owner->id,
            'created_at' => now(),
        ]);

        CashMovement::create([
            'operation_id' => (string) Str::uuid(),
            'cash_account_id' => $this->cashAccount->id,
            'type' => 'SALE_PAYMENT',
            'direction' => 'IN',
            'debit' => 50000,
            'credit' => 0,
            'amount' => 50000,
            'balance_after' => 550000,
            'description' => 'Savdo tushumi',
            'created_by' => $this->owner->id,
            'created_at' => now(),
        ]);

        $backupService = app(BackupService::class);

        // 2. To'liq shifrlangan zaxira nusxa yaratish
        $backup = $backupService->createBackup(['encrypt' => true]);

        $this->assertTrue($backup['success']);
        $this->assertTrue($backup['is_encrypted']);
        $this->assertFileExists($backup['file_path']);
        $this->assertFileExists($backup['file_path'].'.sha256');
        $this->assertEquals(hash_file('sha256', $backup['file_path']), $backup['checksum']);
        $this->assertEquals(BackupService::RPO_TARGET, $backup['manifest']['rpo_target']);
        $this->assertEquals(BackupService::RTO_TARGET, $backup['manifest']['rto_target']);

        // 3. Izolyatsiya qilingan alohida yangi test bazasiga tiklash (Restore Drill)
        $isolatedDb = 'aquaoptom_restore_test';
        $restore = $backupService->restoreBackup($backup['file_path'], [
            'target_db' => $isolatedDb,
            'restore_files' => false,
        ]);

        $this->assertTrue($restore['success']);
        $this->assertEquals($isolatedDb, $restore['target_database']);

        // 4. Tiklangan bazaga PDO orqali ulanib ledger va moliyaviy tenglikni tekshirish
        $config = config('database.connections.pgsql');
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '5432';
        $username = $config['username'] ?? 'postgres';
        $password = $config['password'] ?? '';

        $pdo = new PDO("pgsql:host={$host};port={$port};dbname={$isolatedDb}", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Jadvallar soni
        $tablesCnt = (int) $pdo->query("SELECT count(*) as cnt FROM pg_tables WHERE schemaname = 'public'")->fetch(PDO::FETCH_OBJ)->cnt;
        $this->assertGreaterThan(50, $tablesCnt);

        // Mahsulotlar mavjudligi
        $prodCnt = (int) $pdo->query('SELECT count(*) as cnt FROM products')->fetch(PDO::FETCH_OBJ)->cnt;
        $this->assertEquals(1, $prodCnt);

        // Ombor qiymati (350,000 so'm)
        $stockVal = (int) $pdo->query('SELECT COALESCE(SUM(total_value), 0) as val FROM inventory_balances')->fetch(PDO::FETCH_OBJ)->val;
        $this->assertEquals(350000, $stockVal);

        // Mijoz sof qarzi: debit - credit = 150000 - 50000 = 100000 so'm
        $custDebt = (int) $pdo->query('SELECT COALESCE(SUM(debit - credit), 0) as val FROM customer_ledger')->fetch(PDO::FETCH_OBJ)->val;
        $this->assertEquals(100000, $custDebt);

        // Ta'minotchi sof qarzi: credit - debit = 600000 - 200000 = 400000 so'm
        $suppDebt = (int) $pdo->query('SELECT COALESCE(SUM(credit - debit), 0) as val FROM supplier_ledger')->fetch(PDO::FETCH_OBJ)->val;
        $this->assertEquals(400000, $suppDebt);

        // Kassa harakatlari (50,000 so'm)
        $cashVal = (int) $pdo->query('SELECT COALESCE(SUM(amount), 0) as val FROM cash_movements')->fetch(PDO::FETCH_OBJ)->val;
        $this->assertEquals(50000, $cashVal);

        // 5. Tozalash (cleanup)
        unset($pdo);
        DB::statement("DROP DATABASE IF EXISTS \"{$isolatedDb}\";");
        if (File::exists($backup['file_path'])) {
            File::delete($backup['file_path']);
            File::delete($backup['file_path'].'.sha256');
        }
    }

    /**
     * 2. System Recovery Epoch, Sync Hold, and Offline Client Reconciliation
     */
    public function test_recovery_epoch_pauses_normal_push_and_reconciles_retained_client_operations(): void
    {
        app(OfflineLeaseService::class)->issueLease($this->device, $this->owner);
        // Allocation yaratamiz
        $allocation = InventoryAllocation::create([
            'warehouse_id' => $this->warehouse->id,
            'device_id' => $this->device->id,
            'product_variant_id' => $this->variant->id,
            'allocated_quantity' => 50,
            'consumed_quantity' => 0,
            'returned_quantity' => 0,
            'status' => 'ACTIVE',
            'created_by' => $this->owner->id,
        ]);

        // Omborda qoldiq yaratamiz
        InventoryBalance::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 100,
            'total_value' => 350000,
            'wac' => 3500,
        ]);

        // Tizimda zaxiradan tiklanish holatini simulyatsiya qilamiz
        $backupWatermark = Carbon::now('UTC')->subMinutes(30)->toIso8601String();
        SystemSetting::set('system_recovery_epoch', 2);
        SystemSetting::set('system_recovery_watermark', $backupWatermark);
        SystemSetting::set('system_recovery_status', 'RECONCILIATION_REQUIRED');

        $token = $this->owner->createToken('test-device')->plainTextToken;

        // 1. Mijoz oddiy push yuborganda 428 Precondition Required bilan rad etilishi
        $missingOpId = (string) Str::uuid();
        $normalPushResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Device-UUID', $this->device->device_uuid)
            ->postJson('/api/sync/push', [
                'operations' => [
                    [
                        'operation_id' => $missingOpId,
                        'type' => 'CREATE_SALE',
                        'device_created_at' => Carbon::now('UTC')->toIso8601String(),
                        'payload' => [
                            'warehouse_id' => $this->warehouse->id,
                            'total_amount' => 20000,
                            'paid_amount' => 20000,
                            'debt_amount' => 0,
                            'payment_method' => 'CASH',
                            'items' => [
                                [
                                    'product_variant_id' => $this->variant->id,
                                    'quantity' => 4,
                                    'unit_price' => 5000,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $normalPushResponse->assertStatus(428)
            ->assertJson([
                'success' => false,
                'error_code' => 'RECOVERY_RECONCILIATION_REQUIRED',
                'recovery_epoch' => 2,
            ]);

        // 2. Mijoz reconciliation endpointi orqali saqlangan amallarini taqdim etishi
        $reconcileResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Device-UUID', $this->device->device_uuid)
            ->postJson('/api/sync/reconcile-recovery', [
                'client_epoch' => 1,
                'operations' => [
                    [
                        'operation_id' => $missingOpId,
                        'type' => 'CREATE_SALE',
                        'device_created_at' => Carbon::now('UTC')->toIso8601String(),
                        'payload' => [
                            'warehouse_id' => $this->warehouse->id,
                            'total_amount' => 20000,
                            'paid_amount' => 20000,
                            'debt_amount' => 0,
                            'payment_method' => 'CASH',
                            'items' => [
                                [
                                    'product_variant_id' => $this->variant->id,
                                    'quantity' => 4,
                                    'unit_price' => 5000,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $reconcileResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'server_recovery_epoch' => 2,
                'restored_and_applied_count' => 1,
                'conflicts_count' => 0,
            ]);

        // Operatsiya bazaga muvaffaqiyatli tiklanganini tasdiqlash
        $this->assertDatabaseHas('sales', [
            'operation_id' => $missingOpId,
            'total_amount' => 20000,
        ]);

        // Qurilma tovar ajratmasi (allocation) sarfi 4 donaga yangilangan bo'lishi shart
        $allocation->refresh();
        $this->assertEquals(4, $allocation->consumed_quantity);

        // 3. Muvofiqlashtirish yakunlanib, tizim NORMAL holatga o'tishi
        $reconciliationService = app(RecoveryReconciliationService::class);
        $reconciliationService->markRecoveryCompleted($this->owner->id, [$this->device->id]);

        $this->assertEquals('NORMAL', SystemSetting::get('system_recovery_status'));

        // 4. Tizim NORMAL holatda bo'lganda, oddiy push qayta ishlashi
        $newOpId = (string) Str::uuid();
        $resumePushResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Device-UUID', $this->device->device_uuid)
            ->postJson('/api/sync/push', [
                'operations' => [
                    [
                        'operation_id' => $newOpId,
                        'type' => 'CREATE_SALE',
                        'device_created_at' => Carbon::now('UTC')->toIso8601String(),
                        'payload' => [
                            'warehouse_id' => $this->warehouse->id,
                            'total_amount' => 10000,
                            'paid_amount' => 10000,
                            'debt_amount' => 0,
                            'payment_method' => 'CASH',
                            'items' => [
                                [
                                    'product_variant_id' => $this->variant->id,
                                    'quantity' => 2,
                                    'unit_price' => 5000,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $resumePushResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('sales', [
            'operation_id' => $newOpId,
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    protected function cleanDatabase(): void
    {
        DB::statement('DROP DATABASE IF EXISTS "aquaoptom_restore_test";');

        // Tozalash: child jadvallar birinchi o'chiriladi
        DB::table('sync_conflicts')->delete();
        DB::table('offline_authorizations')->delete();
        DB::table('device_cursors')->delete();
        DB::table('payments')->delete();
        DB::table('sale_return_items')->delete();
        DB::table('sale_returns')->delete();
        DB::table('purchase_return_items')->delete();
        DB::table('purchase_returns')->delete();
        DB::table('damage_items')->delete();
        DB::table('damage_records')->delete();
        DB::table('inventory_audit_items')->delete();
        DB::table('inventory_audits')->delete();
        DB::table('sale_items')->delete();
        DB::table('sales')->delete();
        DB::table('purchase_items')->delete();
        DB::table('purchases')->delete();
        DB::table('inventory_movements')->delete();
        DB::table('inventory_allocation_movements')->delete();
        DB::table('inventory_allocations')->delete();
        DB::table('credit_allocation_movements')->delete();
        DB::table('credit_allocations')->delete();
        DB::table('inventory_balances')->delete();
        DB::table('customer_ledger')->delete();
        DB::table('supplier_ledger')->delete();
        DB::table('cash_movements')->delete();
        DB::table('cash_sessions')->delete();
        DB::table('cash_transactions')->delete();
        DB::table('operation_results')->delete();
        DB::table('outbox_events')->delete();
        DB::table('price_history')->delete();
        DB::table('product_variants')->delete();
        DB::table('products')->delete();
        DB::table('volumes')->delete();
        DB::table('customers')->delete();
        DB::table('suppliers')->delete();
        DB::table('cash_accounts')->delete();
        DB::table('devices')->delete();
        DB::table('warehouses')->delete();
        DB::table('users')->delete();
    }
}

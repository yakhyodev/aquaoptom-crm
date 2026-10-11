<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\DashboardManager;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Sale;
use App\Models\SyncChangeLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Catalog\CatalogService;
use App\Services\Reports\ExportService;
use App\Services\Reports\ReportPeriod;
use App\Services\Sync\SyncBootstrapService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->owner = User::factory()->create(['role' => 'OWNER', 'status' => 'ACTIVE', 'is_active' => true]);
        Warehouse::firstOrCreate(['id' => 1], ['name' => 'Asosiy ombor', 'is_default' => true]);
    }

    public function test_dashboard_dates_match_presets_and_respect_tashkent_and_month_end(): void
    {
        $this->travelTo(Carbon::parse('2026-03-31 12:00:00', 'UTC'));
        $component = Livewire::actingAs($this->owner)->test(DashboardManager::class)
            ->assertSet('customStart', '2026-03-31')->assertSet('customEnd', '2026-03-31')
            ->call('setPeriod', 'last_month')
            ->assertSet('customStart', '2026-02-01')->assertSet('customEnd', '2026-02-28');
        $range = ReportPeriod::resolve('last_month');
        $this->assertSame('2026-02-01', $range['from_date']);
        $this->assertSame('2026-02-28', $range['to_date']);
        $component->set('customStart', '2026-04-02')->set('customEnd', '2026-04-01')
            ->call('applyCustomDates')->assertHasErrors(['customEnd' => 'after_or_equal'])
            ->assertSet('period', 'last_month')
            ->set('customStart', '2026-04-01')->set('customEnd', '2026-04-02')
            ->call('applyCustomDates')->assertHasNoErrors()->assertSet('period', 'custom');
        $this->travelTo(Carbon::parse('2026-03-31 22:00:00', 'UTC'));
        Livewire::actingAs($this->owner)->test(DashboardManager::class)
            ->assertSet('customStart', '2026-04-01')->assertSet('customEnd', '2026-04-01');
    }

    public function test_real_catalog_writes_reach_bootstrap_and_delta_with_exact_volume(): void
    {
        $catalog = app(CatalogService::class);
        $variant = $catalog->createVariant('Sinov ichimlik', '2.5', 7000, createdBy: $this->owner->id);
        $device = Device::create(['device_uuid' => (string) Str::uuid(), 'device_code' => 'DEV-AUDIT', 'name' => 'Telefon', 'device_type' => 'MOBILE', 'status' => 'ACTIVE', 'is_active' => true, 'assigned_user_id' => $this->owner->id]);
        $bootstrap = app(SyncBootstrapService::class)->getBootstrapData($device, $this->owner);
        $this->assertSame($variant->id, $bootstrap['catalog'][0]['id']);
        $this->assertSame(2500, (int) $bootstrap['catalog'][0]['volume_ml']);
        $this->assertSame('ACTIVE', $bootstrap['catalog'][0]['status']);
        $cursor = (int) SyncChangeLog::max('id');
        $catalog->updatePrice($variant, 8000, userId: $this->owner->id);
        $variant->product->update(['name' => 'Yangi nom']);
        $changes = SyncChangeLog::where('id', '>', $cursor)->where('entity_type', 'PRODUCT_VARIANT')->get();
        $this->assertSame(8000, $changes->first()->payload['default_sale_price']);
        $this->assertSame('Yangi nom', $changes->last()->payload['product_name']);
        $this->assertSame(2500, $changes->last()->payload['volume_ml']);
        $variant->update(['status' => 'archived']);
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/products')->assertOk()->assertJsonCount(0, 'data.0.variants');
    }

    public function test_customer_balance_and_archive_changes_are_published(): void
    {
        $customer = Customer::create(['name' => 'Mijoz', 'status' => 'active']);
        $customer->increment('current_debt', 10000);
        $this->assertSame(10000, SyncChangeLog::where('entity_type', 'CUSTOMER')->latest('id')->first()->payload['current_debt']);
        $customer->delete();
        $this->assertTrue(SyncChangeLog::where('entity_type', 'CUSTOMER')->latest('id')->first()->is_tombstone);
    }

    public function test_cash_is_hidden_in_dashboard_reports_and_export_for_unauthorized_roles(): void
    {
        CashAccount::create(['name' => 'Kassa', 'type' => 'CASH', 'balance' => 950000]);
        $user = User::factory()->create(['role' => 'WAREHOUSE_MANAGER', 'status' => 'ACTIVE', 'is_active' => true]);
        $user->givePermission('view_reports');
        $user->givePermission('export_reports');
        $this->actingAs($user, 'sanctum')->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.can_view_cash', false)->assertJsonPath('data.balances.total_cash', null)->assertJsonPath('data.balances.cash_accounts', [])->assertJsonPath('data.flow.cash_in', null);
        $this->getJson('/api/reports')->assertOk()->assertJsonPath('data.cash', null);
        $this->postJson('/api/reports/export', ['type' => 'cash', 'period' => 'today'])->assertForbidden();
    }

    public function test_cancelled_sales_stay_in_export_history_but_do_not_change_totals(): void
    {
        Storage::fake('local');
        foreach (['COMPLETED', 'CANCELLED'] as $status) {
            Sale::create(['operation_id' => (string) Str::uuid(), 'invoice_number' => 'INV-'.$status, 'warehouse_id' => Warehouse::first()->id, 'status' => $status, 'total_amount' => 10000, 'total_cost' => 7000, 'gross_profit' => 3000, 'paid_amount' => 6000, 'debt_amount' => 4000, 'payment_type' => 'PARTIAL', 'payment_method' => 'CASH', 'created_by' => $this->owner->id, 'posted_at' => now(), 'completed_at' => now()]);
        }
        $export = app(ExportService::class)->exportSalesReport($this->owner, ['period' => 'today']);
        $csv = Storage::disk('local')->get($export->file_path);
        $this->assertStringContainsString('INV-CANCELLED', $csv);
        $totals = array_values(array_filter(array_map('str_getcsv', explode("\n", trim($csv))), fn ($row) => ($row[0] ?? '') === 'JAMI'))[0];
        $this->assertSame('10000', $totals[5]);
        $this->assertSame('6000', $totals[6]);
        $this->assertSame('4000', $totals[7]);
    }

    public function test_history_can_reach_older_sales_and_filter_before_pagination(): void
    {
        for ($i = 0; $i < 55; $i++) {
            Sale::create(['operation_id' => (string) Str::uuid(), 'invoice_number' => 'CHEK-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'warehouse_id' => Warehouse::first()->id, 'status' => 'COMPLETED', 'total_amount' => 10000, 'total_cost' => 7000, 'gross_profit' => 3000, 'paid_amount' => 10000, 'debt_amount' => 0, 'payment_type' => 'CASH', 'payment_method' => 'CASH', 'created_by' => $this->owner->id]);
        }
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/sales/history?page=2')->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('meta.total', 55);
        $this->getJson('/api/sales/history?search=CHEK-000')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.invoice_number', 'CHEK-000');
    }

    public function test_web_login_is_rate_limited_and_android_offer_uses_permanent_release_url(): void
    {
        $this->get('/login')->assertOk()->assertSee('releases/download/android-test/aquaoptom-android-test.apk', false);
        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.201'])->post('/login', ['email' => 'bad@example.test', 'password' => 'invalid']);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.201'])->post('/login', ['email' => 'bad@example.test', 'password' => 'invalid'])->assertStatus(429);
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Reports\ReportDashboard;
use App\Livewire\Sales\SalesHistory;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Device;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductPackage;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\ReportExport;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Parties\CustomerService;
use App\Services\Payments\CustomerPaymentService;
use App\Services\Reports\Exceptions\UnauthorizedExportException;
use App\Services\Reports\ExportService;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportQueryService;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SalesHistoryAndReportsTest extends TestCase
{
    use DatabaseMigrations;

    protected User $owner;

    protected User $cashier;

    protected Customer $customer;

    protected Supplier $supplier;

    protected ProductVariant $variantFanta;

    protected CashAccount $cashDrawer;

    protected Device $posDevice;

    protected ReportQueryService $reportQueryService;

    protected ExportService $exportService;

    public function test_every_report_tab_downloads_its_own_real_excel_workbook(): void
    {
        Purchase::create([
            'operation_id' => (string) Str::uuid(), 'invoice_number' => 'KIRIM-XLSX-42',
            'supplier_id' => $this->supplier->id, 'warehouse_id' => 1, 'status' => 'POSTED',
            'total_amount' => 123456, 'paid_amount' => 23456, 'debt_amount' => 100000,
            'posted_at' => now(), 'source' => 'WEB', 'created_by' => $this->owner->id,
        ]);
        $this->supplier->update(['name' => '=HYPERLINK("https://example.invalid")']);

        foreach (['sales', 'profit_loss', 'purchases', 'inventory', 'statements', 'cash', 'staff', 'sync'] as $type) {
            Livewire::actingAs($this->owner)->test(ReportDashboard::class)
                ->call('setTab', $type)->call('export', 'xlsx')->assertHasNoErrors();
            $export = ReportExport::latest('id')->firstOrFail();
            $this->assertSame($type === 'statements' ? 'statement' : $type, $export->report_type);
            $this->assertSame('xlsx', $export->format);
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open(Storage::disk('local')->path($export->file_path)));
            $this->assertNotFalse($zip->getFromName('xl/workbook.xml'));
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertNotFalse($xml);
            $this->assertDoesNotMatchRegularExpression('/<f(?:\s|>)/', $xml, 'User-entered names must never become Excel formulas');
            if ($type === 'purchases') {
                $this->assertStringContainsString('KIRIM-XLSX-42', $xml);
                $this->assertStringContainsString('<v>123456</v>', $xml, 'Amounts must be numeric Excel cells');
                $this->assertStringContainsString('HYPERLINK', $xml);
            }
            $zip->close();
            $this->actingAs($this->owner)->get(route('exports.download', $export->uuid))->assertOk()
                ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
    }

    public function test_customer_search_combines_name_store_and_formatted_phone_in_alphabetical_order(): void
    {
        $this->customer->update(['name' => 'akmal', 'store_name' => 'Bahor Market', 'phone' => '+998 (90) 111-22-33']);
        Customer::create(['name' => 'Zafar', 'store_name' => 'Bahor Market', 'phone' => '+998901112233']);
        $service = app(CustomerService::class);
        $this->assertSame([$this->customer->id], $service->search('akmal bahor 901112233')->pluck('id')->all());
        $this->assertSame(['akmal', 'Zafar'], $service->search('bahor')->pluck('name')->all());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->reportQueryService = app(ReportQueryService::class);
        $this->exportService = app(ExportService::class);

        // Do'kon egasi
        $this->owner = User::factory()->create([
            'role' => 'OWNER',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        // Sotuvchi / Kassir (Eksport va tannarx huquqi yo'q)
        $this->cashier = User::factory()->create([
            'role' => 'SALES_MANAGER',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        // Ombor
        Warehouse::firstOrCreate(['id' => 1], ['name' => 'Asosiy Ombor', 'is_default' => true]);

        // Kassa hisobi
        $this->cashDrawer = CashAccount::create([
            'name' => 'Asosiy Naqd Kassa',
            'type' => 'CASH',
            'currency' => 'UZS',
            'is_active' => true,
        ]);

        // Qurilma
        $this->posDevice = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'DEV-TEST-01',
            'name' => 'Kassa Kompyuteri',
            'device_type' => 'DESKTOP_POS',
            'status' => 'ACTIVE',
            'current_lease_epoch' => 1,
            'is_active' => true,
        ]);

        // Mijoz va Ta'minotchi
        $this->customer = Customer::create([
            'name' => 'Akmal Aka Optom',
            'phone' => '+998901112233',
            'credit_limit' => 5000000,
            'strict_credit_limit' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Coca-Cola Zavod',
            'phone' => '+998712001122',
        ]);

        // Mahsulot va Variant
        $product = Product::create([
            'name' => 'Fanta Apelsin',
            'normalized_name' => 'fanta apelsin',
            'code' => 'PRD-FANTA',
            'status' => 'active',
        ]);

        $volume = Volume::create([
            'name' => '1.5 Litr',
            'value_ml' => 1500,
            'unit' => 'L',
        ]);

        $this->variantFanta = ProductVariant::create([
            'product_id' => $product->id,
            'volume_id' => $volume->id,
            'sku' => 'FANTA-1500',
            'default_sale_price' => 10000,
            'status' => 'active',
        ]);

        ProductPackage::create([
            'product_variant_id' => $this->variantFanta->id,
            'name' => 'Dona',
            'multiplier' => 1,
            'is_default' => true,
        ]);
    }

    /**
     * Test 1: Sana chegaralari (ReportPeriod) Asia/Tashkent bo'yicha to'g'ri hisoblanadi va UTC ga o'giriladi.
     */
    public function test_report_period_resolves_asia_tashkent_boundaries_correctly(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 12, 0, 0, 'Asia/Tashkent'));

        // Bugun
        $today = ReportPeriod::resolve('today');
        $this->assertEquals('2026-10-04', $today['from_date']);
        $this->assertEquals('2026-10-04', $today['to_date']);
        // 2026-10-04 00:00:00 +05:00 == 2026-10-03 19:00:00 UTC
        $this->assertEquals('2026-10-03 19:00:00', $today['start_utc']->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-04 18:59:59', $today['end_utc']->format('Y-m-d H:i:s'));

        // Sentabr 2026 oyi
        $september = ReportPeriod::resolve('month', null, null, '2026-09');
        $this->assertEquals('2026-09-01', $september['from_date']);
        $this->assertEquals('2026-09-30', $september['to_date']);
        // 2026-09-01 00:00:00 +05:00 == 2026-08-31 19:00:00 UTC
        $this->assertEquals('2026-08-31 19:00:00', $september['start_utc']->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-09-30 18:59:59', $september['end_utc']->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    /**
     * Test 2: QABUL MEZONI — Sentabr savdosi va Oktabr qarz to'lovi ikki davrga to'g'ri bo'linadi,
     * savdo va pul summalar double-count bo'lmaydi, invoice paid statusi to'qilmaydi.
     */
    public function test_september_sale_and_october_debt_payment_do_not_double_count(): void
    {
        // 1. Sentabrda savdo rasmiylashtiriladi (15-sentabr 2026):
        // 10 dona x 10 000 = 100 000 so'm, dastlabki to'langan = 0, nasiya = 100 000 so'm.
        $septemberDateUtc = Carbon::create(2026, 9, 15, 10, 0, 0, 'Asia/Tashkent')->setTimezone('UTC');

        $sepOperationId = (string) Str::uuid();
        $sale = Sale::create([
            'operation_id' => $sepOperationId,
            'invoice_number' => 'INV-SEP-001',
            'customer_id' => $this->customer->id,
            'warehouse_id' => 1,
            'device_id' => $this->posDevice->id,
            'status' => 'COMPLETED',
            'total_amount' => 100000,
            'paid_amount' => 0,
            'debt_amount' => 100000,
            'cash_account_id' => $this->cashDrawer->id,
            'payment_type' => 'DEBT',
            'payment_method' => 'DEBT',
            'total_cost' => 70000,
            'gross_profit' => 30000,
            'created_by' => $this->owner->id,
            'created_at' => $septemberDateUtc,
            'posted_at' => $septemberDateUtc,
        ]);
        $sale->created_at = $septemberDateUtc;
        $sale->save();

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $this->variantFanta->id,
            'quantity' => 10,
            'sale_price' => 10000,
            'purchase_cost_snapshot' => 7000,
            'line_total' => 100000,
            'cost_total' => 70000,
            'gross_profit' => 30000,
            'is_system_price' => true,
            'price_version' => 1,
            'created_at' => $septemberDateUtc,
        ]);

        // Mijoz qarz daftari (customer_ledger)
        CustomerLedger::create([
            'operation_id' => $sepOperationId,
            'customer_id' => $this->customer->id,
            'type' => 'SALE',
            'payment_method' => 'DEBT',
            'debit' => 100000,
            'credit' => 0,
            'balance_after' => 100000,
            'reference_type' => 'SALE',
            'reference_id' => $sale->id,
            'created_at' => $septemberDateUtc,
            'notes' => 'Savdo #INV-SEP-001',
        ]);
        $this->customer->update(['current_debt' => 100000]);

        // 2. Oktabrda mijoz qarzini to'laydi (2-oktabr 2026):
        // 100 000 so'm naqd to'lov
        $octoberDateUtc = Carbon::create(2026, 10, 2, 11, 0, 0, 'Asia/Tashkent')->setTimezone('UTC');

        $paymentService = app(CustomerPaymentService::class);
        $paymentService->execute(
            customerId: $this->customer->id,
            amount: 100000,
            cashAccountId: $this->cashDrawer->id,
            userId: $this->owner->id,
            notes: 'Sentabr qarzini yopish',
            operationId: (string) Str::uuid(),
            happenedAt: $octoberDateUtc
        );

        // --- SENTABR HISOBOTINI TEKSHIRISH ---
        $septReport = $this->reportQueryService->getSalesSummary([
            'period' => 'month',
            'month' => '2026-09',
        ]);

        $this->assertEquals(100000, $septReport['kpi']['total_gross_sales'], 'Sentabrda jami savdo 100 000 bo\'lishi kerak');
        $this->assertEquals(0, $septReport['kpi']['total_initial_paid'], 'Sentabrda dastlabki to\'langan naqd 0 bo\'lishi kerak');
        $this->assertEquals(100000, $septReport['kpi']['total_initial_debt'], 'Sentabrda dastlabki nasiya 100 000 bo\'lishi kerak');
        $this->assertEquals(1, $septReport['kpi']['total_orders_count']);

        // --- OKTABR HISOBOTINI TEKSHIRISH ---
        $octReport = $this->reportQueryService->getSalesSummary([
            'period' => 'month',
            'month' => '2026-10',
        ]);

        $this->assertEquals(0, $octReport['kpi']['total_gross_sales'], 'Oktabrda yangi savdo yaratilmagan, savdo 0 bo\'lishi kerak');
        $this->assertEquals(0, $octReport['kpi']['total_orders_count'], 'Oktabrda cheklar soni 0 bo\'lishi kerak');

        // Oktabr kassa hisoboti (Cash Summary)
        $octCashReport = $this->reportQueryService->getCashSummary([
            'period' => 'month',
            'month' => '2026-10',
        ]);

        // Oktabrda mijozdan qarz to'lovi sifatida kassa kirimi bo'ldi
        $this->assertEquals(100000, $octCashReport['summary']['total_inflows'], 'Oktabrda kassa kirimi 100 000 bo\'lishi kerak');

        // Sentabr sotuv hujjati tekshiriladi: invoice_status soxta o'zgartirilmagan
        $reloadedSale = Sale::find($sale->id);
        $this->assertEquals(0, $reloadedSale->paid_amount, 'Asl savdo chekidagi snapshot o\'zgarmagan bo\'lishi shart');
        $this->assertEquals(100000, $reloadedSale->debt_amount, 'Asl savdo chekidagi nasiya snapshot saqlangan');
    }

    /**
     * Test 3: QABUL MEZONI — Sahifa (pagination) va Eksport bir filtr uchun bir xil jami qiymat beradi,
     * ko'p sahifali data yo'qolmaydi.
     */
    public function test_pagination_and_export_produce_identical_totals_across_all_pages(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 12, 0, 0, 'Asia/Tashkent'));

        // 35 ta savdo yaratamiz (har biri 10 000 so'mdan, jami 350 000 so'm)
        for ($i = 1; $i <= 35; $i++) {
            Sale::create([
                'operation_id' => (string) Str::uuid(),
                'invoice_number' => "INV-PAG-{$i}",
                'customer_id' => $this->customer->id,
                'warehouse_id' => 1,
                'status' => 'COMPLETED',
                'total_amount' => 10000,
                'paid_amount' => 10000,
                'debt_amount' => 0,
                'cash_account_id' => $this->cashDrawer->id,
                'payment_method' => 'CASH',
                'payment_type' => 'CASH',
                'total_cost' => 7000,
                'gross_profit' => 3000,
                'created_by' => $this->owner->id,
                'created_at' => now()->subMinutes(35 - $i),
                'posted_at' => now()->subMinutes(35 - $i),
            ]);
        }

        // Livewire orqali sahifa 1 tekshiriladi (sahifada 20 ta element bo'lsa ham)
        $component = Livewire::actingAs($this->owner)
            ->test(SalesHistory::class)
            ->set('period', 'today');

        // Summary jami butun 35 ta savdoni jamlashi shart (350 000 so'm)
        $summary = $component->viewData('summary');
        $salesPaginator = $component->viewData('sales');

        $this->assertEquals(350000, $summary['kpi']['total_gross_sales'], 'KPI kartalar butun 35 ta qatorni jamlashi shart');
        $this->assertEquals(35, $summary['kpi']['total_orders_count']);
        $this->assertEquals(35, $salesPaginator->total(), 'Paginator jami 35 ta qatorni ko\'rsatishi kerak');
        $this->assertCount(20, $salesPaginator->items(), '1-sahifada faqat 20 ta ko\'rinadi');

        // Eksport qilinganda ham barcha 35 ta yozuv to'liq chiqishi kerak
        $export = $this->exportService->exportSalesReport($this->owner, ['period' => 'today'], 'csv');
        $this->assertTrue(Storage::disk('local')->exists($export->file_path));

        $csvContent = Storage::disk('local')->get($export->file_path);
        // Faylda 35 ta INV-PAG bo'lishi kerak
        $this->assertEquals(35, substr_count($csvContent, 'INV-PAG-'), 'Eksport barcha 35 ta yozuvni o\'z ichiga olishi shart');
        $this->assertStringContainsString('350000', $csvContent, 'Eksport jami 350 000 so\'mni ko\'rsatishi shart');

        Carbon::setTestNow();
    }

    /**
     * Test 4: QABUL MEZONI — Spreadsheet formulasi in'ektsiyasi (CSV Injection) dan himoyalash.
     */
    public function test_formula_injection_escaping_protects_spreadsheets(): void
    {
        // Xavfli simvollar bilan boshlanuvchi mijoz va izoh
        $dangerousCustomer = Customer::create([
            'name' => '=SUM(A1:A10)',
            'phone' => '+998909999999',
        ]);

        Sale::create([
            'operation_id' => (string) Str::uuid(),
            'invoice_number' => '+CMD_INVOICE',
            'customer_id' => $dangerousCustomer->id,
            'warehouse_id' => 1,
            'status' => 'COMPLETED',
            'total_amount' => 50000,
            'paid_amount' => 50000,
            'debt_amount' => 0,
            'total_cost' => 30000,
            'gross_profit' => 20000,
            'cash_account_id' => $this->cashDrawer->id,
            'payment_method' => 'CASH',
            'payment_type' => 'CASH',
            'notes' => '@CALC_EXPRESSION',
            'created_by' => $this->owner->id,
            'created_at' => now(),
            'posted_at' => now(),
        ]);

        $export = $this->exportService->exportSalesReport($this->owner, ['period' => 'today'], 'csv');
        $csvContent = Storage::disk('local')->get($export->file_path);

        // Barcha formula belgilarining oldiga bitta tirnoq (') qo'yilgan bo'lishi shart
        $this->assertStringContainsString("'+CMD_INVOICE", $csvContent, '+ bilan boshlangan matn eskaping qilingan');
        $this->assertStringContainsString("'=SUM(A1:A10)", $csvContent, '= bilan boshlangan matn eskaping qilingan');
        $this->assertStringContainsString("'@CALC_EXPRESSION", $csvContent, '@ bilan boshlangan matn eskaping qilingan');
    }

    /**
     * Test 5: QABUL MEZONI — Ruxsatsiz foydalanuvchining eksport so'rovi rad etiladi (403 Forbidden).
     */
    public function test_unauthorized_user_cannot_export_reports(): void
    {
        // Kassirda export_reports huquqi yo'q
        $this->expectException(UnauthorizedExportException::class);

        $this->exportService->exportSalesReport($this->cashier, ['period' => 'today'], 'csv');
    }

    /**
     * Test 6: QABUL MEZONI — Ruxsatsiz foydalanuvchining download endpointi 403 bilan rad etiladi.
     */
    public function test_unauthorized_download_endpoint_returns_403(): void
    {
        // Egasi eksport yaratadi
        $export = $this->exportService->exportSalesReport($this->owner, ['period' => 'today'], 'csv');

        // Kassir yuklab olishga urinadi
        $response = $this->actingAs($this->cashier)
            ->get(route('exports.download', $export->uuid));

        $response->assertStatus(403);
    }

    /**
     * Test 7: QABUL MEZONI — Vakolatli egasi eksportni yuklab oladi va fayl to'g'ri headerlar bilan keladi.
     */
    public function test_authorized_owner_can_download_export_file(): void
    {
        $export = $this->exportService->exportSalesReport($this->owner, ['period' => 'today'], 'csv');

        $response = $this->actingAs($this->owner)
            ->get(route('exports.download', $export->uuid));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Pragma', 'no-cache');
    }

    /**
     * Test 8: QABUL MEZONI — PDF eksport yaratiladi, %PDF belgisi mavjud va ichidagi ma'lumotlar to'g'ri.
     */
    public function test_pdf_export_is_generated_and_contains_valid_pdf_stream(): void
    {
        Sale::create([
            'operation_id' => (string) Str::uuid(),
            'invoice_number' => 'INV-PDF-777',
            'customer_id' => $this->customer->id,
            'warehouse_id' => 1,
            'status' => 'COMPLETED',
            'total_amount' => 88000,
            'paid_amount' => 88000,
            'debt_amount' => 0,
            'total_cost' => 60000,
            'gross_profit' => 28000,
            'cash_account_id' => $this->cashDrawer->id,
            'payment_method' => 'CASH',
            'payment_type' => 'CASH',
            'created_by' => $this->owner->id,
            'created_at' => now(),
            'posted_at' => now(),
        ]);

        $export = $this->exportService->exportSalesReport($this->owner, ['period' => 'today'], 'pdf');

        $this->assertEquals('pdf', $export->format);
        $this->assertTrue(Storage::disk('local')->exists($export->file_path));

        $content = Storage::disk('local')->get($export->file_path);
        // PDF fayl har doim %PDF bilan boshlanadi
        $this->assertStringStartsWith('%PDF-', $content, 'PDF fayli haqiqiy PDF signaturasi bilan boshlanishi kerak');
        $this->assertGreaterThan(500, strlen($content), 'PDF fayli bo\'sh bo\'lmasligi kerak');
    }

    /**
     * Test 9: QABUL MEZONI — Tarixiy ombor qoldig'i (Valuation) joriy snapshotdan emas, daftardan hisoblanadi.
     */
    public function test_inventory_valuation_report_uses_historical_ledger_movements(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 12, 0, 0, 'Asia/Tashkent'));

        $pastDateUtc = Carbon::create(2026, 8, 10, 10, 0, 0, 'Asia/Tashkent')->setTimezone('UTC');
        $lastMonthDateUtc = Carbon::create(2026, 9, 15, 10, 0, 0, 'Asia/Tashkent')->setTimezone('UTC');

        // 2 oy oldin omborga 100 dona kiritilgan
        InventoryMovement::create([
            'operation_id' => (string) Str::uuid(),
            'product_variant_id' => $this->variantFanta->id,
            'warehouse_id' => 1,
            'movement_type' => 'INWARD',
            'quantity' => 100,
            'unit_cost' => 5000,
            'total_cost' => 500000,
            'balance_after_quantity' => 100,
            'balance_after_value' => 500000,
            'created_at' => $pastDateUtc,
        ]);

        // 1 oy oldin 40 dona sotilgan
        InventoryMovement::create([
            'operation_id' => (string) Str::uuid(),
            'product_variant_id' => $this->variantFanta->id,
            'warehouse_id' => 1,
            'movement_type' => 'SALE',
            'quantity' => -40,
            'unit_cost' => 5000,
            'total_cost' => 200000,
            'balance_after_quantity' => 60,
            'balance_after_value' => 300000,
            'created_at' => $lastMonthDateUtc,
        ]);

        // Bugun esa joriy balans 60 dona
        // O'tgan oy hisoboti so'ralganda:
        // Davr boshi = 100 dona bo'lishi kerak (chunki 2 oy oldin 100 dona kiritilgan)
        // Davr chiqimi = 40 dona bo'lishi kerak
        // Davr yakuni = 60 dona bo'lishi kerak
        $report = $this->reportQueryService->getInventoryValuationReport([
            'period' => 'last_month',
        ]);

        $this->assertEquals(100, $report['summary']['total_opening_units'], 'O\'tgan oy boshida qoldiq 100 dona bo\'lgan');
        $this->assertEquals(40, $report['summary']['total_outward_units'], 'O\'tgan oyda chiqim 40 dona bo\'lgan');
        $this->assertEquals(60, $report['summary']['total_closing_units'], 'O\'tgan oy oxirida qoldiq 60 dona bo\'lgan');

        Carbon::setTestNow();
    }

    /**
     * Test 10: Yalpi va Operatsion Foyda (P&L): Egasi pul yechishi (Owner Draw) operatsion xarajat hisoblanmaydi!
     */
    public function test_profit_and_loss_excludes_owner_draw_from_operating_expenses(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 12, 0, 0, 'Asia/Tashkent'));

        // Savdo: 100 000 so'm, tannarx 60 000 so'm (yalpi foyda = 40 000 so'm)
        $sale = Sale::create([
            'operation_id' => (string) Str::uuid(),
            'invoice_number' => 'INV-PNL-01',
            'customer_id' => $this->customer->id,
            'warehouse_id' => 1,
            'status' => 'COMPLETED',
            'total_amount' => 100000,
            'paid_amount' => 100000,
            'debt_amount' => 0,
            'cash_account_id' => $this->cashDrawer->id,
            'payment_method' => 'CASH',
            'payment_type' => 'CASH',
            'total_cost' => 60000,
            'gross_profit' => 40000,
            'created_by' => $this->owner->id,
            'created_at' => now(),
            'posted_at' => now(),
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $this->variantFanta->id,
            'quantity' => 10,
            'sale_price' => 10000,
            'purchase_cost_snapshot' => 6000,
            'line_total' => 100000,
            'cost_total' => 60000,
            'gross_profit' => 40000,
            'is_system_price' => true,
            'price_version' => 1,
            'created_at' => now(),
        ]);

        // Operatsion xarajat (Do'kon arendasi): 10 000 so'm
        CashMovement::create([
            'operation_id' => (string) Str::uuid(),
            'cash_account_id' => $this->cashDrawer->id,
            'direction' => 'OUT',
            'amount' => 10000,
            'debit' => 0,
            'credit' => 10000,
            'balance_after' => 90000,
            'type' => 'EXPENSE',
            'created_at' => now(),
            'description' => 'Arenda to\'lovi',
        ]);

        // Egasi shaxsiy ehtiyojiga pul yechdi (Owner Draw / Withdrawal): 15 000 so'm
        CashMovement::create([
            'operation_id' => (string) Str::uuid(),
            'cash_account_id' => $this->cashDrawer->id,
            'direction' => 'OUT',
            'amount' => 15000,
            'debit' => 0,
            'credit' => 15000,
            'balance_after' => 75000,
            'type' => 'OWNER_WITHDRAWAL',
            'created_at' => now(),
            'description' => 'Egasi pul yechdi',
        ]);

        $pnl = $this->reportQueryService->getProfitAndLoss(['period' => 'today'], true);

        $this->assertEquals(100000, $pnl['net_sales']);
        $this->assertEquals(60000, $pnl['cogs']);
        $this->assertEquals(40000, $pnl['gross_profit']);
        // Operatsion xarajat faqat 10 000 bo'lishi shart! 15 000 egasi pul yechishi qo'shilmaydi!
        $this->assertEquals(10000, $pnl['operating_expenses'], 'Egasi pul yechishi (OWNER_DRAW) operatsion xarajatga kiritilmaydi');
        $this->assertEquals(30000, $pnl['operating_profit'], 'Sof operatsion foyda 40 000 - 10 000 = 30 000 bo\'lishi kerak');

        Carbon::setTestNow();
    }

    /**
     * Test 11: Tannarx huquqi yo'q xodim uchun P&L ma'lumotlari yashiriladi.
     */
    public function test_pnl_is_masked_when_view_cost_price_is_denied(): void
    {
        $pnl = $this->reportQueryService->getProfitAndLoss(['period' => 'today'], false);

        $this->assertFalse($pnl['can_view_cost']);
        $this->assertNull($pnl['cogs']);
        $this->assertNull($pnl['gross_profit']);
        $this->assertNull($pnl['operating_profit']);
    }

    /**
     * Test 12: Livewire ReportDashboard komponenti barcha tablarni xatosiz render qiladi.
     */
    public function test_livewire_report_dashboard_renders_and_switches_tabs(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ReportDashboard::class)
            ->assertSee('Do‘kon hisobotlari')
            ->set('activeTab', 'sales')
            ->assertSee('Mahsulotlar va Hajmlar Kesimida Savdo Aylanmasi')
            ->set('activeTab', 'profit_loss')
            ->assertSee('Moliyaviy Natijalar Tuzilishi')
            ->set('activeTab', 'purchases')
            ->assertSee('Jami Tovar Kirimi')
            ->set('activeTab', 'inventory')
            ->assertSee('Qoldiqlar va Harakatlar Daftari')
            ->set('activeTab', 'statements')
            ->assertSee('Davriy Hisob-Kitob')
            ->set('activeTab', 'cash')
            ->assertSee('Kassa Hisoblari')
            ->set('activeTab', 'staff')
            ->assertSee('Xodimlar Kesimida Savdo')
            ->set('activeTab', 'sync')
            ->assertSee('Offline Qurilmalar');
    }
}

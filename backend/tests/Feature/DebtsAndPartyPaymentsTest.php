<?php

namespace Tests\Feature;

use App\Livewire\Debts\DebtsManager;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\InventoryBalance;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Accounting\StatementService;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Operations\Exceptions\OperationPermissionException;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Payments\CustomerPaymentService;
use App\Services\Payments\SupplierPaymentService;
use App\Services\Purchase\ReceivePurchaseService;
use App\Services\Sales\CreateSaleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DebtsAndPartyPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $cashier;

    protected User $warehouseUser;

    protected Customer $customerAkmal;

    protected Supplier $supplierNavoiy;

    protected Warehouse $warehouse;

    protected CashAccount $cashAccount;

    protected ProductVariant $variantFanta05;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create([
            'email' => 'owner_debt_test@aquaoptom.uz',
        ]);

        $this->cashier = User::factory()->cashier()->create([
            'email' => 'cashier_debt_test@aquaoptom.uz',
        ]);

        $this->warehouseUser = User::factory()->warehouseManager()->create([
            'email' => 'wh_debt_test@aquaoptom.uz',
        ]);

        $this->warehouse = Warehouse::firstOrCreate(
            ['name' => 'Asosiy Ombor'],
            ['is_default' => true]
        );

        $this->cashAccount = CashAccount::firstOrCreate(
            ['name' => 'Asosiy Naqd Kassa'],
            ['type' => 'CASH', 'balance' => 1000000, 'is_default' => true]
        );

        $this->customerAkmal = Customer::create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Akmal aka',
            'phone' => '+998901234567',
            'store_name' => 'Oazis Savdo',
            'address' => 'Chilonzor 9',
            'debt_limit' => 5000000,
            'current_debt' => 0,
            'status' => 'active',
        ]);

        $this->supplierNavoiy = Supplier::create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Navoiy Suvlari MCHJ',
            'phone' => '+998791234567',
            'company_name' => 'Navoiy Distribution',
            'balance' => 0,
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'Fanta Apelsin',
            'normalized_name' => 'fanta apelsin',
            'code' => 'PRD-000001',
            'status' => 'active',
        ]);

        $volume = Volume::create([
            'name' => '0.5 L',
            'value_ml' => 500,
            'unit' => 'L',
        ]);

        $this->variantFanta05 = ProductVariant::create([
            'product_id' => $product->id,
            'volume_id' => $volume->id,
            'sku' => 'FANTA-05L',
            'barcode' => '4780001234567',
            'default_sale_price' => 6500,
            'min_stock_alert' => 20,
            'status' => 'active',
            'version' => 1,
        ]);
    }

    /**
     * QABUL MEZONI 1:
     * Keyingi 100 000 UZS mijoz to'lovi qarzni kamaytiradi;
     * oldingi savdo summasi, tannarxi va yalpi foydasi (gross profit) aslo o'zgarmaydi!
     */
    public function test_subsequent_customer_payment_reduces_debt_without_altering_previous_sale_or_gross_profit(): void
    {
        // 1. Dastlabki ombor kirimi: 100 dona @ 5000 = 500 000
        $inventoryService = app(InventoryLedgerService::class);
        $inventoryService->recordInflow(
            productVariantId: $this->variantFanta05->id,
            quantity: 100,
            unitCost: 5000,
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid()
        );

        // 2. Savdo: 60 dona × 6500 = 390 000 jami, cost 300 000, gross profit 90 000
        // To'langan: 140 000, qarz: 250 000
        $saleService = app(CreateSaleService::class);
        $sale = $saleService->execute(
            customerId: $this->customerAkmal->id,
            items: [
                ['variant_id' => $this->variantFanta05->id, 'quantity' => 60, 'is_system_price' => true],
            ],
            operationId: (string) Str::uuid(),
            paidAmount: 140000,
            cashAccountId: $this->cashAccount->id,
            paymentType: 'PARTIAL',
            paymentMethod: 'CASH',
            userId: $this->owner->id
        );

        $this->assertEquals(390000, $sale->total_amount);
        $this->assertEquals(300000, $sale->total_cost);
        $this->assertEquals(90000, $sale->gross_profit);
        $this->assertEquals(250000, $this->customerAkmal->fresh()->current_debt);

        $initialCashBalance = (int) $this->cashAccount->fresh()->balance;

        // 3. Keyingi qarz to'lovi: Akmal kelib 100 000 so'm to'laydi
        $paymentService = app(CustomerPaymentService::class);
        $paymentOpId = (string) Str::uuid();

        $result = $paymentService->execute(
            customerId: $this->customerAkmal->id,
            amount: 100000,
            cashAccountId: $this->cashAccount->id,
            paymentMethod: 'CASH',
            operationId: $paymentOpId,
            userId: $this->owner->id,
            notes: 'Akmal qarzning 100k qismini to\'ladi'
        );

        // Mijoz qarzi 250 000 dan 150 000 ga kamaygan bo'lishi shart!
        $this->assertEquals(150000, $this->customerAkmal->fresh()->current_debt);
        $this->assertEquals(150000, $result['new_debt']);
        $this->assertFalse($result['is_advance']);

        // Kassa balansi 100 000 ga ko'paygan bo'lishi shart
        $this->assertEquals($initialCashBalance + 100000, (int) $this->cashAccount->fresh()->balance);

        // QAT'IY QOIDA: Oldingi savdoning summasi, tannarxi va yalpi foydasi 1 tiyin ham o'zgarmasligi shart!
        $freshSale = $sale->fresh();
        $this->assertEquals(390000, $freshSale->total_amount);
        $this->assertEquals(300000, $freshSale->total_cost);
        $this->assertEquals(90000, $freshSale->gross_profit);
    }

    /**
     * QABUL MEZONI 2:
     * Ta'minotchi to'lovi (supplier payment) ombor tovar qoldig'iga (stock) aslo tegmaydi!
     */
    public function test_supplier_payment_reduces_payable_and_never_touches_inventory_stock(): void
    {
        // 1. Tovar kirimi: 150 dona @ 5000 = 750 000 so'm
        // To'lov: 300 000, qarzimiz: 450 000
        $receiveService = app(ReceivePurchaseService::class);
        $purchase = $receiveService->execute(
            supplierId: $this->supplierNavoiy->id,
            warehouseId: $this->warehouse->id,
            items: [
                ['variant_id' => $this->variantFanta05->id, 'quantity' => 150, 'unit_cost' => 5000],
            ],
            operationId: (string) Str::uuid(),
            paidAmount: 300000,
            cashAccountId: $this->cashAccount->id,
            userId: $this->owner->id
        );

        $this->assertEquals(450000, $this->supplierNavoiy->fresh()->balance);

        $stockBefore = InventoryBalance::where('product_variant_id', $this->variantFanta05->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(150, (int) $stockBefore->quantity);
        $this->assertEquals(750000, (int) $stockBefore->total_value);

        // 2. Ta'minotchiga keyingi to'lov: 200 000 so'm
        $supplierPaymentService = app(SupplierPaymentService::class);
        $result = $supplierPaymentService->execute(
            supplierId: $this->supplierNavoiy->id,
            amount: 200000,
            cashAccountId: $this->cashAccount->id,
            paymentMethod: 'CASH',
            operationId: (string) Str::uuid(),
            userId: $this->owner->id,
            notes: 'Navoiy Suvlariga keyingi to\'lov'
        );

        // Ta'minotchi oldidagi qarzimiz 450 000 dan 250 000 ga tushdi
        $this->assertEquals(250000, $this->supplierNavoiy->fresh()->balance);
        $this->assertEquals(250000, $result['new_payable']);

        // QAT'IY QOIDA: Stock miqdori va qiymati zarracha o'zgarmasligi shart!
        $stockAfter = InventoryBalance::where('product_variant_id', $this->variantFanta05->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(150, (int) $stockAfter->quantity);
        $this->assertEquals(750000, (int) $stockAfter->total_value);
    }

    /**
     * QABUL MEZONI 3:
     * Qarzdan ortiq to'lov tasdiqlanganda manfiy signed avans hosil qiladi, max(0) qo'llanmaydi!
     */
    public function test_excess_payment_becomes_signed_advance_and_max_zero_is_never_applied(): void
    {
        // Akmalning qarzini 50 000 qilib belgilaymiz
        $this->customerAkmal->update(['current_debt' => 50000]);

        $paymentService = app(CustomerPaymentService::class);

        // 1. Agar qarzdan ortiq summa tasdiqlanmasa, validation xato berishi shart
        try {
            $paymentService->execute(
                customerId: $this->customerAkmal->id,
                amount: 80000,
                cashAccountId: $this->cashAccount->id,
                operationId: (string) Str::uuid(),
                userId: $this->owner->id,
                confirmExcessAsAdvance: false
            );
            $this->fail('Ortiqcha to\'lov tasdiqsiz qabul qilinmasligi kerak edi.');
        } catch (OperationValidationException $e) {
            $this->assertEquals('EXCESS_PAYMENT_REQUIRES_ADVANCE_CONFIRMATION', $e->errorCode);
        }

        // 2. Tasdiqlangan holda to'lash: 80 000 so'm (50k qarz yopilib, -30k avans hosil bo'ladi)
        $result = $paymentService->execute(
            customerId: $this->customerAkmal->id,
            amount: 80000,
            cashAccountId: $this->cashAccount->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id,
            confirmExcessAsAdvance: true
        );

        $this->assertEquals(-30000, $this->customerAkmal->fresh()->current_debt);
        $this->assertTrue($result['is_advance']);
        $this->assertEquals(30000, $result['advance_amount']);
    }

    /**
     * QABUL MEZONI 4:
     * Bitta mijozning avansi boshqa mijozning qarzini yashirmaydi!
     */
    public function test_customer_advance_does_not_hide_another_customer_debt_in_aggregates(): void
    {
        // Customer 1: Avansda (-50 000)
        $this->customerAkmal->update(['current_debt' => -50000]);

        // Customer 2: Qarzdor (+150 000)
        $customerDilshod = Customer::create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Dilshod market',
            'phone' => '+998909998877',
            'current_debt' => 150000,
            'status' => 'active',
        ]);

        $livewire = Livewire::test(DebtsManager::class);

        $stats = $livewire->viewData('customerStats');

        // Jami qarz 150 000 bo'lishi shart (avans uni 100 000 qilib yashirmasligi kerak!)
        $this->assertEquals(150000, $stats['total_debt']);

        // Jami avans 50 000 bo'lishi shart
        $this->assertEquals(50000, $stats['total_advance']);

        // Sof signed balans esa 100 000
        $this->assertEquals(100000, $stats['net_balance']);
    }

    /**
     * QABUL MEZONI 5:
     * Hisob ko'chirmasi: boshlang'ich_qoldiq + harakatlar = yakuniy_qoldiq
     * Matematik tenglik 100% bajariladi.
     */
    public function test_account_statement_reconciliation_opening_plus_movements_equals_closing(): void
    {
        $statementService = app(StatementService::class);
        $customerPaymentService = app(CustomerPaymentService::class);

        // Boshlang'ich qarz: 100 000 (o'tgan oyda)
        $pastDate = Carbon::now('Asia/Tashkent')->subMonths(2);
        CustomerLedger::create([
            'customer_id' => $this->customerAkmal->id,
            'type' => 'OPENING_BALANCE',
            'debit' => 100000,
            'credit' => 0,
            'balance_after' => 100000,
            'created_at' => $pastDate,
        ]);
        $this->customerAkmal->update(['current_debt' => 100000]);

        // Ushbu oydagi harakat: to'lov 40 000 so'm
        $customerPaymentService->execute(
            customerId: $this->customerAkmal->id,
            amount: 40000,
            cashAccountId: $this->cashAccount->id,
            operationId: (string) Str::uuid(),
            userId: $this->owner->id,
            notes: 'Ushbu oydagi to\'lov'
        );

        // Ko'chirmani olish (shu oy boshidan)
        $startDate = Carbon::now('Asia/Tashkent')->startOfMonth()->format('Y-m-d');
        $endDate = Carbon::now('Asia/Tashkent')->format('Y-m-d');

        $statement = $statementService->getCustomerStatement(
            customerId: $this->customerAkmal->id,
            startDate: $startDate,
            endDate: $endDate
        );

        $this->assertEquals(100000, $statement['opening_balance']);
        $this->assertEquals(0, $statement['total_debit']);
        $this->assertEquals(40000, $statement['total_credit']);
        $this->assertEquals(60000, $statement['closing_balance']);
        $this->assertTrue($statement['is_reconciled']);
        $this->assertEquals(60000, $statement['current_balance']);
    }

    /**
     * QABUL MEZONI 6:
     * Kamera uchun vaqtlar sekund aniqligida Asia/Tashkent formatida saqlanadi va
     * ixtiyoriy tovar olib ketilgan vaqt alohida aks etadi.
     */
    public function test_statement_reflects_optional_goods_picked_up_at_timestamp_with_second_precision(): void
    {
        $inventoryService = app(InventoryLedgerService::class);
        $inventoryService->recordInflow(
            productVariantId: $this->variantFanta05->id,
            quantity: 50,
            unitCost: 5000,
            warehouseId: $this->warehouse->id,
            operationId: (string) Str::uuid()
        );

        $saleService = app(CreateSaleService::class);
        $pickedUpTime = '2026-10-03 16:45:22';

        $sale = $saleService->execute(
            customerId: $this->customerAkmal->id,
            items: [
                ['variant_id' => $this->variantFanta05->id, 'quantity' => 10, 'is_system_price' => true],
            ],
            operationId: (string) Str::uuid(),
            paidAmount: 0,
            cashAccountId: $this->cashAccount->id,
            paymentType: 'DEBT',
            userId: $this->owner->id,
            goodsPickedUpAt: $pickedUpTime
        );

        $this->assertNotNull($sale->goods_picked_up_at);
        $this->assertEquals($pickedUpTime, $sale->goods_picked_up_at->timezone('Asia/Tashkent')->format('Y-m-d H:i:s'));

        $statementService = app(StatementService::class);
        $statement = $statementService->getCustomerStatement(
            customerId: $this->customerAkmal->id,
            startDate: '2026-10-01',
            endDate: '2026-10-31'
        );

        $saleRow = collect($statement['movements'])->firstWhere('type', 'SALE');
        $this->assertNotNull($saleRow);
        $this->assertEquals($pickedUpTime, $saleRow['goods_picked_up_at']);
    }

    /**
     * QABUL MEZONI 7:
     * Idempotency: bir xil operation_id bilan qayta yuborilgan to'lov faqat bitta yozuv qoldiradi!
     */
    public function test_idempotency_retry_with_same_operation_id_returns_identical_payment_and_creates_single_record(): void
    {
        $this->customerAkmal->update(['current_debt' => 200000]);

        $paymentService = app(CustomerPaymentService::class);
        $fixedOpId = (string) Str::uuid();

        // 1-marta to'lash
        $result1 = $paymentService->execute(
            customerId: $this->customerAkmal->id,
            amount: 50000,
            cashAccountId: $this->cashAccount->id,
            operationId: $fixedOpId,
            userId: $this->owner->id
        );

        // 2-marta ayni operation_id bilan qayta yuborish (masalan tarmoq retry)
        $result2 = $paymentService->execute(
            customerId: $this->customerAkmal->id,
            amount: 50000,
            cashAccountId: $this->cashAccount->id,
            operationId: $fixedOpId,
            userId: $this->owner->id
        );

        // Natija bir xil bo'lishi shart
        $this->assertEquals($result1['payment_id'], $result2['payment_id']);
        $this->assertEquals($result1['payment_number'], $result2['payment_number']);

        // Bazada faqat 1 dona Payment bo'lishi shart!
        $this->assertEquals(1, Payment::where('operation_id', $fixedOpId)->count());

        // Mijoz qarzi 2 marta emas, aniq 1 marta (150 000) kamaygan bo'lishi shart!
        $this->assertEquals(150000, $this->customerAkmal->fresh()->current_debt);
    }

    /**
     * QABUL MEZONI 8:
     * Kassada pul yetarli bo'lmaganda ta'minotchi to'lovi to'liq rollback bo'ladi!
     */
    public function test_supplier_payment_rolls_back_when_cash_is_insufficient(): void
    {
        $this->supplierNavoiy->update(['balance' => 500000]);
        // Kassada faqat 50 000 so'm bor
        $this->cashAccount->update(['balance' => 50000]);

        $supplierPaymentService = app(SupplierPaymentService::class);

        try {
            $supplierPaymentService->execute(
                supplierId: $this->supplierNavoiy->id,
                amount: 100000, // 50k dan ko'p
                cashAccountId: $this->cashAccount->id,
                operationId: (string) Str::uuid(),
                userId: $this->owner->id
            );
            $this->fail('Kassada pul yetarli bo\'lmaganda to\'lov o\'tmasligi kerak edi.');
        } catch (OperationValidationException $e) {
            $this->assertEquals('INSUFFICIENT_CASH', $e->errorCode);
        }

        // Ta'minotchi qarzimiz va kassa balansi o'zgarmagan bo'lishi shart
        $this->assertEquals(500000, $this->supplierNavoiy->fresh()->balance);
        $this->assertEquals(50000, (int) $this->cashAccount->fresh()->balance);
    }

    /**
     * QABUL MEZONI 9:
     * Ruxsatlar nazorati: kassadan pul chiqimi ruxsati bo'lmagan xodim ta'minotchiga to'lov qila olmaydi.
     */
    public function test_permissions_prevent_unauthorized_user_from_making_supplier_payment(): void
    {
        $this->supplierNavoiy->update(['balance' => 500000]);

        $supplierPaymentService = app(SupplierPaymentService::class);

        $this->expectException(OperationPermissionException::class);

        // Omborchi (warehouseUser) da manage_cash_outflow ruxsati yo'q!
        $supplierPaymentService->execute(
            supplierId: $this->supplierNavoiy->id,
            amount: 100000,
            cashAccountId: $this->cashAccount->id,
            operationId: (string) Str::uuid(),
            userId: $this->warehouseUser->id
        );
    }

    /**
     * QABUL MEZONI 10:
     * Livewire DebtsManager komponenti to'liq ishlaydi (Tablar, to'lov, sozlamalar).
     */
    public function test_livewire_debts_manager_component_renders_tabs_and_manages_limits(): void
    {
        $this->customerAkmal->update(['current_debt' => 200000]);

        Livewire::actingAs($this->owner)
            ->test(DebtsManager::class)
            ->assertSee('Mijozlarning bizga qarzi')
            ->assertSee('Bizning yetkazuvchilarga qarzimiz')
            ->assertSee('Akmal aka')
            ->set('activeTab', 'suppliers')
            ->assertSee('Navoiy Suvlari MCHJ')
            // Sozlamalarni yangilash (kredit limiti va to'lov sanasi)
            ->call('openSettingsModal', 'CUSTOMER', $this->customerAkmal->id)
            ->set('settingsDebtLimit', '10000000')
            ->set('settingsPaymentDueDate', '2026-11-01')
            ->call('saveSettings');

        $this->assertEquals(10000000, $this->customerAkmal->fresh()->debt_limit);
        $this->assertEquals('2026-11-01', $this->customerAkmal->fresh()->payment_due_date->format('Y-m-d'));
    }
}

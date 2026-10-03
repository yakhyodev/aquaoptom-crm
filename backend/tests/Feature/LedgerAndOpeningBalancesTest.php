<?php

namespace Tests\Feature;

use App\Livewire\Admin\OpeningBalancesManager;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Ledger\CashAccountService;
use App\Services\Ledger\CustomerLedgerService;
use App\Services\Ledger\Exceptions\InsufficientCashException;
use App\Services\Ledger\Exceptions\InsufficientStockException;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Ledger\PaymentService;
use App\Services\Ledger\SupplierLedgerService;
use App\Services\Opening\OpeningBalanceService;
use App\Services\Operations\Exceptions\OperationConflictException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LedgerAndOpeningBalancesTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $cashier;

    protected Warehouse $warehouse;

    protected ProductVariant $variantFanta;

    protected CashAccount $cashAccount;

    protected CashAccount $bankAccount;

    protected Customer $customer;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->cashier = User::factory()->cashier()->create();

        $this->warehouse = Warehouse::create(['name' => 'Asosiy Ombor', 'is_default' => true]);

        $vol05 = Volume::create(['name' => '0.5 L', 'value_ml' => 500]);
        $prodFanta = Product::create([
            'name' => 'Fanta',
            'normalized_name' => 'fanta',
            'code' => 'PRD-000001',
            'status' => 'active',
        ]);

        $this->variantFanta = ProductVariant::create([
            'product_id' => $prodFanta->id,
            'volume_id' => $vol05->id,
            'sku' => 'FANTA-500',
            'default_sale_price' => 7000,
            'status' => 'active',
        ]);

        $cashService = app(CashAccountService::class);
        $this->cashAccount = $cashService->getOrCreateAccount('CASH', 'Asosiy Kassa', true);
        $this->bankAccount = $cashService->getOrCreateAccount('BANK', 'Bank Hisobi', false);

        $this->customer = Customer::create([
            'name' => 'Olim aka (Do\'kon)',
            'phone' => '+998901234567',
            'store_name' => 'Olim Savdo',
            'current_debt' => 0,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Coca-Cola Zavodi',
            'phone' => '+998712000000',
            'balance' => 0,
        ]);
    }

    /**
     * TEST 1: WAC Qoidasi: 100 x 5000 + 100 x 6000 = 200 dona / 1 100 000 qiymat / WAC 5500.
     * Ledger va cached hisoblar tengligi.
     */
    public function test_weighted_average_cost_and_inventory_ledger_inflows(): void
    {
        $service = app(InventoryLedgerService::class);

        // 1-kirim: 100 dona x 5 000 = 500 000
        $inflow1 = $service->recordInflow(
            productVariantId: $this->variantFanta->id,
            quantity: 100,
            unitCost: 5000,
            movementType: 'PURCHASE',
            warehouseId: $this->warehouse->id,
            operationId: Str::uuid()->toString(),
            userId: $this->owner->id
        );

        $this->assertEquals(100, $inflow1['balance_quantity']);
        $this->assertEquals(500000, $inflow1['balance_total_value']);
        $this->assertEquals(5000, $inflow1['average_cost']);

        // 2-kirim: 100 dona x 6 000 = 600 000
        $inflow2 = $service->recordInflow(
            productVariantId: $this->variantFanta->id,
            quantity: 100,
            unitCost: 6000,
            movementType: 'PURCHASE',
            warehouseId: $this->warehouse->id,
            operationId: Str::uuid()->toString(),
            userId: $this->owner->id
        );

        $this->assertEquals(200, $inflow2['balance_quantity']);
        $this->assertEquals(1100000, $inflow2['balance_total_value']);
        $this->assertEquals(5500, $inflow2['average_cost']);

        // Cached balansi tekshirish
        $balance = InventoryBalance::where('product_variant_id', $this->variantFanta->id)->first();
        $this->assertNotNull($balance);
        $this->assertEquals(200, (int) $balance->quantity);
        $this->assertEquals(1100000, (int) $balance->total_value);
        $this->assertEquals(5500, (int) $balance->average_cost);

        // Audit: ledger yig'indisi cached balansga 100% teng
        $audit = $service->auditBalanceAgainstLedger($this->variantFanta->id, $this->warehouse->id);
        $this->assertTrue($audit['is_consistent']);
        $this->assertEquals(200, $audit['cached_quantity']);
        $this->assertEquals(200, $audit['ledger_quantity']);
    }

    /**
     * TEST 2: Chiqim va "Oxirgi dona sotilganda qiymat ham 0" qat'iy invarianti.
     */
    public function test_inventory_outflow_and_last_item_zero_value_invariant(): void
    {
        $service = app(InventoryLedgerService::class);

        // Kirim: 100 x 5000 + 100 x 6000 = 200 dona / 1 100 000 so'm / WAC 5500
        $service->recordInflow($this->variantFanta->id, 100, 5000, 'PURCHASE');
        $service->recordInflow($this->variantFanta->id, 100, 6000, 'PURCHASE');

        // Chiqim 1: 60 dona sotildi (60 x 5500 = 330 000)
        $outflow1 = $service->recordOutflow(
            productVariantId: $this->variantFanta->id,
            quantity: 60,
            movementType: 'SALE',
            operationId: Str::uuid()->toString(),
            userId: $this->owner->id
        );

        $this->assertEquals(60, $outflow1['quantity']);
        $this->assertEquals(330000, $outflow1['total_cost']);
        $this->assertEquals(140, $outflow1['remaining_quantity']);
        $this->assertEquals(770000, $outflow1['remaining_total_value']);
        $this->assertEquals(5500, $outflow1['average_cost']);

        // Chiqim 2: Qolgan barcha 140 dona sotildi -> Qoldiq 0 bo'lganda ombor qiymati ham 0 bo'lishi shart!
        $outflow2 = $service->recordOutflow(
            productVariantId: $this->variantFanta->id,
            quantity: 140,
            movementType: 'SALE',
            operationId: Str::uuid()->toString(),
            userId: $this->owner->id
        );

        $this->assertEquals(140, $outflow2['quantity']);
        $this->assertEquals(770000, $outflow2['total_cost']);
        $this->assertEquals(0, $outflow2['remaining_quantity']);
        $this->assertEquals(0, $outflow2['remaining_total_value'], 'Qoldiq 0 bo\'lganda qiymat ham 0 bo\'lishi shart!');
        $this->assertEquals(0, $outflow2['average_cost']);

        $balance = InventoryBalance::where('product_variant_id', $this->variantFanta->id)->first();
        $this->assertEquals(0, (int) $balance->quantity);
        $this->assertEquals(0, (int) $balance->total_value);
        $this->assertEquals(0, (int) $balance->average_cost);

        // Qoldiq yetarli bo'lmaganda chiqim xato berishi
        $this->expectException(InsufficientStockException::class);
        $service->recordOutflow($this->variantFanta->id, 1, 'SALE');
    }

    /**
     * TEST 3: Mijoz signed daftari: debit (qarz), credit (avans).
     * max(0) orqali avans yo'qolib ketmasligi kafolati.
     */
    public function test_customer_signed_ledger_debt_and_advance(): void
    {
        $service = app(CustomerLedgerService::class);

        // 1. Nasiya savdo: 200 000 so'm (Debit -> qarz oshadi)
        $ledger1 = $service->recordDebit(
            customerId: $this->customer->id,
            amount: 200000,
            type: 'SALE',
            operationId: Str::uuid()->toString(),
            userId: $this->owner->id
        );

        $this->assertEquals(200000, $ledger1->balance_after);
        $this->assertEquals(200000, $this->customer->fresh()->current_debt);

        $bal1 = $service->getSignedBalance($this->customer->id);
        $this->assertTrue($bal1['is_debt']);
        $this->assertFalse($bal1['is_advance']);
        $this->assertEquals(200000, $bal1['debt_amount']);

        // 2. Mijoz ortiqcha to'ladi: 250 000 so'm (Credit -> 200 000 - 250 000 = -50 000 so'm AVANS)
        $ledger2 = $service->recordCredit(
            customerId: $this->customer->id,
            amount: 250000,
            type: 'PAYMENT',
            paymentMethod: 'CASH',
            operationId: Str::uuid()->toString(),
            userId: $this->owner->id
        );

        $this->assertEquals(-50000, $ledger2->balance_after, 'Balans manfiy avans bo\'lishi kerak!');
        $this->assertEquals(-50000, $this->customer->fresh()->current_debt, 'current_debt max(0) bilan 0 qilinmasligi shart!');

        $bal2 = $service->getSignedBalance($this->customer->id);
        $this->assertFalse($bal2['is_debt']);
        $this->assertTrue($bal2['is_advance'], 'Manfiy balans avans sifatida tan olinadi');
        $this->assertEquals(50000, $bal2['advance_amount']);
        $this->assertEquals(0, $bal2['debt_amount']);

        // Audit tekshiruvi: sum(debit) - sum(credit) == -50 000
        $audit = $service->auditBalanceAgainstLedger($this->customer->id);
        $this->assertTrue($audit['is_consistent']);
        $this->assertEquals(-50000, $audit['cached_debt']);
        $this->assertEquals(-50000, $audit['ledger_debt']);
    }

    /**
     * TEST 4: Ta'minotchi signed daftari: credit (qarzimiz), debit (avansimiz).
     * max(0) orqali ta'minotchi oldindan to'lovi yo'qolib ketmasligi kafolati.
     */
    public function test_supplier_signed_ledger_payable_and_prepayment(): void
    {
        $service = app(SupplierLedgerService::class);

        // 1. Tovar kirimi: 500 000 so'm (Credit -> bizning qarzimiz oshadi)
        $ledger1 = $service->recordPurchaseCredit(
            supplierId: $this->supplier->id,
            amount: 500000,
            type: 'PURCHASE',
            operationId: Str::uuid()->toString(),
            userId: $this->owner->id
        );

        $this->assertEquals(500000, $ledger1->balance_after);
        $this->assertEquals(500000, $this->supplier->fresh()->balance);

        $bal1 = $service->getSignedBalance($this->supplier->id);
        $this->assertTrue($bal1['is_payable']);
        $this->assertFalse($bal1['is_prepaid']);

        // 2. Ta'minotchiga ortiqcha to'lov: 600 000 so'm (Debit -> 500 000 - 600 000 = -100 000 so'm AVANS)
        $ledger2 = $service->recordPaymentDebit(
            supplierId: $this->supplier->id,
            amount: 600000,
            type: 'PAYMENT',
            paymentMethod: 'CASH',
            operationId: Str::uuid()->toString(),
            userId: $this->owner->id
        );

        $this->assertEquals(-100000, $ledger2->balance_after);
        $this->assertEquals(-100000, $this->supplier->fresh()->balance);

        $bal2 = $service->getSignedBalance($this->supplier->id);
        $this->assertFalse($bal2['is_payable']);
        $this->assertTrue($bal2['is_prepaid'], 'Manfiy balans bizning avansimiz sifatida tan olinadi');
        $this->assertEquals(100000, $bal2['prepaid_amount']);

        // Audit tekshiruvi: sum(credit) - sum(debit) == -100 000
        $audit = $service->auditBalanceAgainstLedger($this->supplier->id);
        $this->assertTrue($audit['is_consistent']);
        $this->assertEquals(-100000, $audit['cached_balance']);
        $this->assertEquals(-100000, $audit['ledger_balance']);
    }

    /**
     * TEST 5: Kassa hisoblari, kirim/chiqim, overdraft blokirovkasi va o'tkazma (transfer).
     */
    public function test_cash_accounts_movements_and_transfers(): void
    {
        $cashService = app(CashAccountService::class);

        // 1. Kassaga pul kirimi: 500 000 so'm
        $m1 = $cashService->recordInflow(
            cashAccountId: $this->cashAccount->id,
            amount: 500000,
            type: 'OWNER_DEPOSIT',
            operationId: Str::uuid()->toString(),
            userId: $this->owner->id
        );

        $this->assertEquals(500000, $m1->balance_after);
        $this->assertEquals(500000, $this->cashAccount->fresh()->balance);

        // 2. Kassadan xarajat: 200 000 so'm
        $m2 = $cashService->recordOutflow(
            cashAccountId: $this->cashAccount->id,
            amount: 200000,
            type: 'EXPENSE',
            operationId: Str::uuid()->toString(),
            userId: $this->owner->id
        );

        $this->assertEquals(300000, $m2->balance_after);
        $this->assertEquals(300000, $this->cashAccount->fresh()->balance);

        // 3. Ortiqcha pul chiqimiga urinish (350 000 so'm) -> Xatolik
        try {
            $cashService->recordOutflow(
                cashAccountId: $this->cashAccount->id,
                amount: 350000,
                type: 'EXPENSE'
            );
            $this->fail('Ortiqcha pul chiqimi bloklanishi kerak edi.');
        } catch (InsufficientCashException $e) {
            $this->assertStringContainsString('yetarli pul mavjud emas', $e->getMessage());
        }

        // 4. Kassa hisoblari o'rtasida transfer: 100 000 so'm (CASH -> BANK)
        $transfer = $cashService->transfer(
            fromAccountId: $this->cashAccount->id,
            toAccountId: $this->bankAccount->id,
            amount: 100000,
            operationId: Str::uuid()->toString(),
            userId: $this->owner->id
        );

        $this->assertEquals(200000, $this->cashAccount->fresh()->balance);
        $this->assertEquals(100000, $this->bankAccount->fresh()->balance);

        // Jami pul massasi saqlanganligi (200 000 + 100 000 = 300 000)
        $totalMoney = CashAccount::sum('balance');
        $this->assertEquals(300000, $totalMoney);

        // Audit ikkala hisobda ham to'g'ri
        $this->assertTrue($cashService->auditBalanceAgainstLedger($this->cashAccount->id)['is_consistent']);
        $this->assertTrue($cashService->auditBalanceAgainstLedger($this->bankAccount->id)['is_consistent']);
    }

    /**
     * TEST 6: PaymentService atomikligi (Mijoz va ta'minotchi to'lovlari).
     */
    public function test_payment_service_atomic_processing(): void
    {
        $paymentService = app(PaymentService::class);

        // Boshlang'ich kassa: 500 000
        $this->cashAccount->update(['balance' => 500000]);
        // Mijoz qarzi: 300 000
        $this->customer->update(['current_debt' => 300000]);
        // Ta'minotchi qarzi: 400 000
        $this->supplier->update(['balance' => 400000]);

        // 1. Mijozdan 100 000 so'm to'lov qabul qilish
        $pCustomer = $paymentService->recordCustomerPayment(
            customerId: $this->customer->id,
            cashAccountId: $this->cashAccount->id,
            amount: 100000,
            paymentMethod: 'CASH',
            operationId: Str::uuid()->toString(),
            userId: $this->owner->id
        );

        $this->assertNotNull($pCustomer->payment_number);
        $this->assertEquals('COMPLETED', $pCustomer->status);
        $this->assertEquals(600000, $this->cashAccount->fresh()->balance, 'Kassa +100 000 ga oshishi kerak');
        $this->assertEquals(200000, $this->customer->fresh()->current_debt, 'Mijoz qarzi 300 000 dan 200 000 ga tushishi kerak');

        // 2. Ta'minotchiga 250 000 so'm to'lov qilish
        $pSupplier = $paymentService->recordSupplierPayment(
            supplierId: $this->supplier->id,
            cashAccountId: $this->cashAccount->id,
            amount: 250000,
            paymentMethod: 'CASH',
            operationId: Str::uuid()->toString(),
            userId: $this->owner->id
        );

        $this->assertNotNull($pSupplier->payment_number);
        $this->assertEquals('COMPLETED', $pSupplier->status);
        $this->assertEquals(350000, $this->cashAccount->fresh()->balance, 'Kassa 600 000 dan 250 000 ayirilib 350 000 bo\'lishi kerak');
        $this->assertEquals(150000, $this->supplier->fresh()->balance, 'Ta\'minotchi qarzi 400 000 dan 150 000 ga tushishi kerak');
    }

    /**
     * TEST 7: Boshlang'ich qoldiqlar (Opening Balance) va Idempotency (Retry bitta yozuv beradi).
     */
    public function test_opening_balances_idempotency_retry(): void
    {
        $openingService = app(OpeningBalanceService::class);
        $operationId = Str::uuid()->toString();

        // 1-marta ombor boshlang'ich qoldig'i kiritish
        $res1 = $openingService->recordStockOpening(
            productVariantId: $this->variantFanta->id,
            quantity: 150,
            unitCost: 5000,
            operationId: $operationId,
            warehouseId: $this->warehouse->id,
            userId: $this->owner->id
        );

        $this->assertEquals(150, $res1['stock']['balance_quantity']);
        $this->assertEquals(750000, $res1['stock']['balance_total_value']);

        $balanceAfter1 = InventoryBalance::where('product_variant_id', $this->variantFanta->id)->first();
        $this->assertEquals(150, (int) $balanceAfter1->quantity);
        $this->assertEquals(750000, (int) $balanceAfter1->total_value);

        // 2-marta XUDDI SHU operation_id bilan qayta yuborilganda:
        // Takror hisoblanmaydi! Eski natija qaytadi, ombor 300 bo'lib ketmaydi!
        $res2 = $openingService->recordStockOpening(
            productVariantId: $this->variantFanta->id,
            quantity: 150,
            unitCost: 5000,
            operationId: $operationId,
            warehouseId: $this->warehouse->id,
            userId: $this->owner->id
        );

        $this->assertEquals($res1['document_number'], $res2['document_number']);

        $balanceAfter2 = InventoryBalance::where('product_variant_id', $this->variantFanta->id)->first();
        $this->assertEquals(150, (int) $balanceAfter2->quantity, 'Idempotency: miqdor ikkinchi marta qo\'shilmasligi shart!');
        $this->assertEquals(750000, (int) $balanceAfter2->total_value);

        $movementsCount = InventoryMovement::where('product_variant_id', $this->variantFanta->id)
            ->where('movement_type', 'OPENING_BALANCE')
            ->count();
        $this->assertEquals(1, $movementsCount, 'Bazaga faqat 1 ta ochilish harakati yozilgan bo\'lishi shart');

        // Boshqa payload bilan shu operation_id yuborilsa -> 409 Conflict
        $this->expectException(OperationConflictException::class);
        $openingService->recordStockOpening(
            productVariantId: $this->variantFanta->id,
            quantity: 200, // boshqa miqdor
            unitCost: 5000,
            operationId: $operationId,
            warehouseId: $this->warehouse->id,
            userId: $this->owner->id
        );
    }

    /**
     * TEST 8: Barcha boshlang'ich qoldiqlarni bitta Batch hujjatda kiritish (Stock + Cash + Customers + Suppliers).
     */
    public function test_batch_opening_balances_recording(): void
    {
        $openingService = app(OpeningBalanceService::class);
        $operationId = Str::uuid()->toString();

        $payload = [
            'stock' => [
                ['variant_id' => $this->variantFanta->id, 'quantity' => 100, 'unit_cost' => 5000],
            ],
            'cash' => [
                ['account_id' => $this->cashAccount->id, 'amount' => 500000],
                ['account_id' => $this->bankAccount->id, 'amount' => 1000000],
            ],
            'customers' => [
                ['customer_id' => $this->customer->id, 'signed_amount' => 250000], // 250 000 qarz
            ],
            'suppliers' => [
                ['supplier_id' => $this->supplier->id, 'signed_amount' => -100000], // 100 000 avansimiz
            ],
            'notes' => 'Do\'kon ochilishidagi barcha qoldiqlar',
        ];

        $result = $openingService->recordBatchOpening(
            payload: $payload,
            operationId: $operationId,
            userId: $this->owner->id
        );

        $this->assertNotNull($result['document_number']);
        $this->assertStringStartsWith('OPN-', $result['document_number']);

        // Tekshiruvlar
        $this->assertEquals(100, (int) InventoryBalance::where('product_variant_id', $this->variantFanta->id)->value('quantity'));
        $this->assertEquals(500000, (int) CashAccount::where('id', $this->cashAccount->id)->value('balance'));
        $this->assertEquals(1000000, (int) CashAccount::where('id', $this->bankAccount->id)->value('balance'));
        $this->assertEquals(250000, (int) Customer::where('id', $this->customer->id)->value('current_debt'));
        $this->assertEquals(-100000, (int) Supplier::where('id', $this->supplier->id)->value('balance'));

        // Idempotent qayta yuborish
        $retryResult = $openingService->recordBatchOpening(
            payload: $payload,
            operationId: $operationId,
            userId: $this->owner->id
        );

        $this->assertEquals($result['document_number'], $retryResult['document_number']);
        $this->assertEquals(100, (int) InventoryBalance::where('product_variant_id', $this->variantFanta->id)->value('quantity'));
        $this->assertEquals(500000, (int) CashAccount::where('id', $this->cashAccount->id)->value('balance'));
    }

    /**
     * TEST 9: Rollback tekshiruvi: Agar tranzaksiya ichida xato bo'lsa, birorta ham ledger yozuvi qolmaydi.
     */
    public function test_ledger_transaction_rollback_safety(): void
    {
        $openingService = app(OpeningBalanceService::class);
        $operationId = Str::uuid()->toString();

        $invalidPayload = [
            'stock' => [
                ['variant_id' => $this->variantFanta->id, 'quantity' => 100, 'unit_cost' => 5000],
            ],
            'customers' => [
                ['customer_id' => 999999, 'signed_amount' => 50000], // Mavjud bo'lmagan mijoz -> Exception!
            ],
        ];

        try {
            $openingService->recordBatchOpening(
                payload: $invalidPayload,
                operationId: $operationId,
                userId: $this->owner->id
            );
            $this->fail('Mavjud bo\'lmagan mijozda exception bo\'lishi shart edi.');
        } catch (\Exception $e) {
            // Rollback kutilgan
        }

        // Rollbackdan keyin: Omborga ham hech narsa yozilmagan bo'lishi shart!
        $this->assertDatabaseMissing('inventory_balances', [
            'product_variant_id' => $this->variantFanta->id,
        ]);
        $this->assertDatabaseMissing('inventory_movements', [
            'operation_id' => $operationId,
        ]);
        $this->assertDatabaseMissing('opening_balance_documents', [
            'operation_id' => $operationId,
        ]);
    }

    /**
     * TEST 10: Livewire OpeningBalancesManager ruxsat va smoke testi.
     */
    public function test_livewire_opening_balances_manager_component(): void
    {
        // 1. Oddiy xodim uchun 403 Forbidden
        $this->actingAs($this->cashier);
        Livewire::test(OpeningBalancesManager::class)
            ->assertForbidden();

        // 2. Egasi (Owner) uchun to'liq ochiladi
        $this->actingAs($this->owner);
        Livewire::test(OpeningBalancesManager::class)
            ->assertOk()
            ->assertSee('Boshlang‘ich qoldiqlar (Hisob ochilishi)')
            ->assertSee('Ombor tovarlari')
            ->assertSee('Kassa hisoblari')
            ->assertSee('Mijozlar qarzdorligi')
            ->assertSee('Ta’minotchilar hisobi')
            ->call('switchTab', 'cash')
            ->assertSee('Asosiy Kassa')
            ->call('switchTab', 'customers')
            ->assertSee('Olim aka')
            ->call('switchTab', 'suppliers')
            ->assertSee('Coca-Cola Zavodi');
    }
}

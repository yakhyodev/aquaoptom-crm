<?php

namespace Tests\Feature;

use App\Livewire\Cash\CashManager;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Ledger\CashAccountService;
use App\Services\Ledger\CashSessionService;
use App\Services\Ledger\CashTransferService;
use App\Services\Ledger\Exceptions\InsufficientCashException;
use App\Services\Ledger\ExpenseService;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Ledger\OwnerFundsService;
use App\Services\Ledger\SupplierLedgerService;
use App\Services\Operations\Exceptions\OperationConflictException;
use App\Services\Operations\Exceptions\OperationPermissionException;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Payments\CustomerPaymentService;
use App\Services\Payments\SupplierPaymentService;
use App\Services\Sales\CreateSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CashSessionAndMovementsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $cashier;

    protected User $salesperson;

    protected CashAccount $cashAccount;

    protected CashAccount $bankAccount;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create([
            'email' => 'owner_cash_test@aquaoptom.uz',
        ]);

        $this->cashier = User::factory()->cashier()->create([
            'email' => 'cashier_cash_test@aquaoptom.uz',
        ]);

        $this->salesperson = User::factory()->salesManager()->create([
            'email' => 'sales_cash_test@aquaoptom.uz',
        ]);

        $this->cashAccount = CashAccount::create([
            'name' => 'Asosiy Naqd Kassa',
            'type' => 'CASH',
            'balance' => 0,
            'is_default' => true,
        ]);

        $this->bankAccount = CashAccount::create([
            'name' => 'Bank Hisob-Raqami',
            'type' => 'BANK',
            'balance' => 0,
            'is_default' => false,
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Asosiy Ombor',
            'is_default' => true,
        ]);
    }

    /**
     * 1. Concurrency: Parallel/ketma-ket pul chiqim hisobdan ortiq sarflamaydi.
     */
    public function test_cash_outflow_prevents_spending_more_than_available_balance(): void
    {
        $cashService = app(CashAccountService::class);

        // 100 000 so'm kirim
        $cashService->recordInflow($this->cashAccount->id, 100_000, 'OPENING_BALANCE');
        $this->assertEquals(100_000, $this->cashAccount->fresh()->balance);

        // 80 000 so'm chiqim muvaffaqiyatli
        $cashService->recordOutflow($this->cashAccount->id, 80_000, 'EXPENSE');
        $this->assertEquals(20_000, $this->cashAccount->fresh()->balance);

        // Yana 80 000 so'm chiqim qilishga urinish InsufficientCashException berishi shart
        $this->expectException(InsufficientCashException::class);
        $cashService->recordOutflow($this->cashAccount->id, 80_000, 'EXPENSE');
    }

    /**
     * 2. Idempotency: Bir xil operation_id bilan retry bitta xarajat yaratadi va pulni 2 marta yechmaydi.
     */
    public function test_expense_service_is_idempotent_on_retry(): void
    {
        $cashService = app(CashAccountService::class);
        $expenseService = app(ExpenseService::class);

        $cashService->recordInflow($this->cashAccount->id, 300_000, 'OPENING_BALANCE');

        $opId = (string) Str::uuid();

        // 1-marta xarajat qilish
        $res1 = $expenseService->createExpense(
            cashAccountId: $this->cashAccount->id,
            amount: 50_000,
            category: 'TRANSPORT',
            description: 'Gazel yoqilg\'isi',
            userId: $this->owner->id,
            operationId: $opId
        );

        $this->assertEquals(250_000, $this->cashAccount->fresh()->balance);
        $this->assertEquals(1, Expense::count());

        // 2-marta aynan bir xil operation_id bilan retry qilish
        $res2 = $expenseService->createExpense(
            cashAccountId: $this->cashAccount->id,
            amount: 50_000,
            category: 'TRANSPORT',
            description: 'Gazel yoqilg\'isi',
            userId: $this->owner->id,
            operationId: $opId
        );

        // Natija bir xil, kassa 2-marta kamaymaydi, xarajatlar soni 1 ta qoladi
        $this->assertEquals($res1['expense_number'], $res2['expense_number']);
        $this->assertEquals(250_000, $this->cashAccount->fresh()->balance);
        $this->assertEquals(1, Expense::count());

        // Boshqa payload bilan xuddi shu operation_id 409 Conflict berishi shart
        $this->expectException(OperationConflictException::class);
        $expenseService->createExpense(
            cashAccountId: $this->cashAccount->id,
            amount: 90_000, // boshqa summa!
            category: 'TRANSPORT',
            description: 'Gazel yoqilg\'isi',
            userId: $this->owner->id,
            operationId: $opId
        );
    }

    /**
     * 3. Idempotency: Bir xil operation_id bilan retry bitta transfer qiladi.
     */
    public function test_cash_transfer_service_is_idempotent_on_retry(): void
    {
        $cashService = app(CashAccountService::class);
        $transferService = app(CashTransferService::class);

        $cashService->recordInflow($this->cashAccount->id, 200_000, 'OPENING_BALANCE');

        $opId = (string) Str::uuid();

        // Transfer: Naqd -> Bank 70 000
        $res1 = $transferService->transfer(
            fromAccountId: $this->cashAccount->id,
            toAccountId: $this->bankAccount->id,
            amount: 70_000,
            description: 'Bankka topshirish',
            userId: $this->owner->id,
            operationId: $opId
        );

        $this->assertEquals(130_000, $this->cashAccount->fresh()->balance);
        $this->assertEquals(70_000, $this->bankAccount->fresh()->balance);

        // Retry transfer with identical payload
        $res2 = $transferService->transfer(
            fromAccountId: $this->cashAccount->id,
            toAccountId: $this->bankAccount->id,
            amount: 70_000,
            description: 'Bankka topshirish',
            userId: $this->owner->id,
            operationId: $opId
        );

        $this->assertEquals(130_000, $this->cashAccount->fresh()->balance);
        $this->assertEquals(70_000, $this->bankAccount->fresh()->balance);
    }

    /**
     * 4. 14.3 Qabul Mezoni:
     * Startingcash 500000, supplier−300000, sale+140000, debt+100000, supplier−50000 = 390000.
     * Hech qanday hisob (ombor, mijoz qarz, ta'minotchi qarz) naqdga aralashmaydi.
     */
    public function test_exact_section_14_3_cash_sequence_and_separation_of_ledgers(): void
    {
        $cashService = app(CashAccountService::class);
        $supplierPaymentService = app(SupplierPaymentService::class);
        $customerPaymentService = app(CustomerPaymentService::class);
        $saleService = app(CreateSaleService::class);
        $inventoryService = app(InventoryLedgerService::class);
        $supplierLedgerService = app(SupplierLedgerService::class);

        // 0. Boshlang'ich naqd: 500 000 so'm
        $cashService->recordInflow($this->cashAccount->id, 500_000, 'OPENING_BALANCE');
        $this->assertEquals(500_000, $this->cashAccount->fresh()->balance);

        // Tovar va ta'minotchi tayyorlash: Fanta 0.5L 150 dona × 5 000 = 750 000
        $product = Product::create([
            'name' => 'Fanta',
            'normalized_name' => 'fanta',
            'code' => 'PRD-FANTA',
        ]);
        $volume = Volume::create([
            'name' => '0.5 L',
            'value_ml' => 500,
            'unit' => 'L',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'volume_id' => $volume->id,
            'sku' => 'FANTA-05L',
            'default_sale_price' => 6500,
            'is_active' => true,
        ]);
        $supplier = Supplier::create([
            'name' => 'Coca-Cola Zavod',
            'phone' => '+998901234567',
            'balance' => 0,
        ]);
        $customer = Customer::create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Akmal Do\'koni',
            'phone' => '+998909876543',
            'current_debt' => 0,
        ]);

        // Omborga 150 dona kirim: WAC 5000, ta'minotchi majburiyati 750 000
        $inventoryService->recordInflow(
            productVariantId: $variant->id,
            warehouseId: $this->warehouse->id,
            quantity: 150,
            unitCost: 5_000,
            movementType: 'PURCHASE'
        );
        $supplierLedgerService->recordPurchaseCredit(
            supplierId: $supplier->id,
            amount: 750_000
        );
        $this->assertEquals(750_000, $supplier->fresh()->balance);

        // Qadam 1: Ta'minotchiga 300 000 to'landi -> Pul: 500 000 - 300 000 = 200 000
        $supplierPaymentService->execute(
            supplierId: $supplier->id,
            cashAccountId: $this->cashAccount->id,
            amount: 300_000,
            userId: $this->owner->id
        );
        $this->assertEquals(200_000, $this->cashAccount->fresh()->balance);
        $this->assertEquals(450_000, $supplier->fresh()->balance);

        // Qadam 2: Mijozga 60 dona × 6 500 sotildi (Jami 390 000, cost 300 000, gross 90 000).
        // Mijoz savdoda 140 000 to'ladi -> Pul: 200 000 + 140 000 = 340 000. Mijoz qarzi = 250 000.
        $saleService->execute(
            customerId: $customer->id,
            items: [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 60,
                    'unit_price' => 6_500,
                    'is_system_price' => true,
                ],
            ],
            paidAmount: 140_000,
            cashAccountId: $this->cashAccount->id,
            warehouseId: $this->warehouse->id,
            userId: $this->owner->id
        );

        $this->assertEquals(340_000, $this->cashAccount->fresh()->balance);
        $this->assertEquals(250_000, $customer->fresh()->current_debt);
        $this->assertEquals(90, $inventoryService->getBalance($variant->id, $this->warehouse->id)['quantity']);

        // Qadam 3: Keyin mijoz 100 000 qarz to'ladi -> Pul: 340 000 + 100 000 = 440 000. Mijoz qarzi: 150 000.
        $customerPaymentService->execute(
            customerId: $customer->id,
            cashAccountId: $this->cashAccount->id,
            amount: 100_000,
            userId: $this->owner->id
        );
        $this->assertEquals(440_000, $this->cashAccount->fresh()->balance);
        $this->assertEquals(150_000, $customer->fresh()->current_debt);

        // Qadam 4: Keyin ta'minotchiga 50 000 to'landi -> Pul: 440 000 - 50 000 = 390 000. Ta'minotchi qarzi: 400 000.
        $supplierPaymentService->execute(
            supplierId: $supplier->id,
            cashAccountId: $this->cashAccount->id,
            amount: 50_000,
            userId: $this->owner->id
        );

        // YAKUNIY ANIQ TEKSHIRUV:
        // Naqd pul aniq 390 000 so'm
        $this->assertEquals(390_000, $this->cashAccount->fresh()->balance);
        // Mijoz qarzi naqdga aralashmagan: 150 000 so'm
        $this->assertEquals(150_000, $customer->fresh()->current_debt);
        // Ta'minotchi majburiyati naqdga aralashmagan: 400 000 so'm
        $this->assertEquals(400_000, $supplier->fresh()->balance);
        // Ombor qoldig'i qiymati naqdga aralashmagan: 90 dona × 5000 = 450 000 so'm
        $this->assertEquals(450_000, $inventoryService->getBalance($variant->id, $this->warehouse->id)['total_value']);
    }

    /**
     * 5. Transfer ikki hisob harakati, yangi savdo yoki operatsion foyda emas.
     */
    public function test_transfer_is_pure_balance_movement_not_sale_or_revenue(): void
    {
        $cashService = app(CashAccountService::class);
        $transferService = app(CashTransferService::class);

        $cashService->recordInflow($this->cashAccount->id, 500_000, 'OPENING_BALANCE');

        $transferService->transfer(
            fromAccountId: $this->cashAccount->id,
            toAccountId: $this->bankAccount->id,
            amount: 150_000,
            description: 'Inkassatsiya',
            userId: $this->owner->id
        );

        $this->assertEquals(350_000, $this->cashAccount->fresh()->balance);
        $this->assertEquals(150_000, $this->bankAccount->fresh()->balance);

        // Transfer yangi savdo yoki xarajat yaratmaganini tekshirish
        $this->assertEquals(0, Expense::count());

        // Audit qoldig'i tekshiruvi (ledger balance == cached balance)
        $auditCash = $cashService->auditBalanceAgainstLedger($this->cashAccount->id);
        $auditBank = $cashService->auditBalanceAgainstLedger($this->bankAccount->id);

        $this->assertTrue($auditCash['is_consistent']);
        $this->assertTrue($auditBank['is_consistent']);
    }

    /**
     * 6. Owner Draw operatsion xarajat (expense) emas va foydani kamaytirmaydi.
     */
    public function test_owner_draw_is_not_an_operating_expense(): void
    {
        $cashService = app(CashAccountService::class);
        $ownerFundsService = app(OwnerFundsService::class);

        $cashService->recordInflow($this->cashAccount->id, 1_000_000, 'OPENING_BALANCE');

        // Egasi 400 000 so'm chiqarib oldi
        $result = $ownerFundsService->withdraw(
            cashAccountId: $this->cashAccount->id,
            amount: 400_000,
            description: 'Shaxsiy ehtiyojlar uchun',
            userId: $this->owner->id
        );

        $this->assertEquals(600_000, $this->cashAccount->fresh()->balance);

        // Expense modeli mutlaqo yaratilmagan!
        $this->assertEquals(0, Expense::count());

        $payment = Payment::find($result['payment_id']);
        $this->assertNotNull($payment);
        $this->assertEquals('OWNER', $payment->party_type);
        $this->assertEquals('OWNER_DRAW', $payment->payment_type);
    }

    /**
     * 7. Smena: Bitta naqd hisob uchun bir vaqtda faqat bitta OPEN session bo'lishi mumkin.
     */
    public function test_single_open_session_uniqueness_per_cash_account(): void
    {
        $sessionService = app(CashSessionService::class);

        // 1-smena ochish
        $session1 = $sessionService->openSession(
            cashAccountId: $this->cashAccount->id,
            userId: $this->cashier->id,
            openingBalance: 100_000
        );

        $this->assertEquals('OPEN', $session1->status);
        $this->assertEquals(100_000, $session1->opening_balance);

        // Shu kassa hisobiga ikkinchi smena ochishga urinish rad etilishi shart
        $this->expectException(OperationValidationException::class);
        $sessionService->openSession(
            cashAccountId: $this->cashAccount->id,
            userId: $this->cashier->id,
            openingBalance: 100_000
        );
    }

    /**
     * 8. Closed Session Guard: Yopilgan smenaga yangi operatsiya yozish taqiqlanadi.
     */
    public function test_closed_session_guard_prevents_posting_to_closed_session(): void
    {
        $sessionService = app(CashSessionService::class);
        $cashService = app(CashAccountService::class);

        // Smena ochish
        $session = $sessionService->openSession(
            cashAccountId: $this->cashAccount->id,
            userId: $this->cashier->id,
            openingBalance: 0
        );

        // Smenani yopish
        $sessionService->closeSession(
            sessionId: $session->id,
            userId: $this->cashier->id,
            actualClosingBalance: 0
        );

        $this->assertEquals('CLOSED', $session->fresh()->status);

        // Yopilgan smena ID si bilan kirim qilishga urinish rad etilishi shart!
        $this->expectException(OperationValidationException::class);
        $cashService->recordInflow(
            cashAccountId: $this->cashAccount->id,
            amount: 50_000,
            type: 'SALE_PAYMENT',
            cashSessionId: $session->id
        );
    }

    /**
     * 9. Smena yopilishida farq (discrepancy) va ruxsatli farq hujjati:
     * Farq sababsiz qabul qilinmaydi, yashirin overwrite qilinmaydi, ruxsatli tasdiq bilan qonuniy to'g'rilanadi.
     */
    public function test_session_closing_discrepancy_and_authorized_adjustment(): void
    {
        $sessionService = app(CashSessionService::class);
        $cashService = app(CashAccountService::class);

        // 500 000 boshlang'ich naqd bilan smena ochildi
        $cashService->recordInflow($this->cashAccount->id, 500_000, 'OPENING_BALANCE');

        $session = $sessionService->openSession(
            cashAccountId: $this->cashAccount->id,
            userId: $this->cashier->id,
            openingBalance: 500_000
        );

        // Smena davomida 100 000 tushum bo'ldi -> Kutilgan: 600 000
        $cashService->recordInflow($this->cashAccount->id, 100_000, 'SALE_PAYMENT');

        $expected = $sessionService->calculateExpectedBalance($session);
        $this->assertEquals(600_000, $expected);

        // Kassir 580 000 sanadi (20 000 kamomad).
        // 1) Sababsiz yopishga urinish rad etiladi:
        try {
            $sessionService->closeSession(
                sessionId: $session->id,
                userId: $this->cashier->id,
                actualClosingBalance: 580_000,
                differenceReason: '' // Bo'sh sabab!
            );
            $this->fail('Sababsiz farq bilan smenani yopish rad etilishi kerak edi!');
        } catch (OperationValidationException $e) {
            $this->assertEquals('DIFFERENCE_REASON_REQUIRED', $e->errorCode);
        }

        // 2) Sabab bilan yopish:
        $closedSession = $sessionService->closeSession(
            sessionId: $session->id,
            userId: $this->cashier->id,
            actualClosingBalance: 580_000,
            differenceReason: 'Kassada 20 000 so\'m mayda pul kam chiqdi'
        );

        $this->assertEquals('CLOSED', $closedSession->status);
        $this->assertEquals(-20_000, $closedSession->difference);
        $this->assertEquals('PENDING_APPROVAL', $closedSession->difference_status);

        // MUHIM INVARIANT: Kassa balansi yashirin o'zgarmadi! Hali ham 600 000!
        $this->assertEquals(600_000, $this->cashAccount->fresh()->balance);

        // 3) Oddiy sotuvchi (ruxsatsiz) farqni tasdiqlashga urinsa, rad etiladi:
        try {
            $sessionService->approveDifference(
                sessionId: $closedSession->id,
                approverId: $this->salesperson->id
            );
            $this->fail('Ruxsatsiz xodim kassa farqini tasdiqlamasligi kerak edi!');
        } catch (OperationPermissionException $e) {
            $this->assertEquals('PERMISSION_DENIED', $e->errorCode);
        }

        // 4) Do'kon egasi ruxsatli farq hujjati bilan tasdiqlaydi:
        $approvedSession = $sessionService->approveDifference(
            sessionId: $closedSession->id,
            approverId: $this->owner->id,
            adjustCashLedger: true
        );

        $this->assertEquals('APPROVED', $approvedSession->difference_status);
        $this->assertEquals($this->owner->id, $approvedSession->difference_approved_by);

        // Endi kassa balansi daftardagi DIFFERENCE_SHORTAGE harakati orqali qonuniy 580 000 bo'ldi!
        $this->assertEquals(580_000, $this->cashAccount->fresh()->balance);

        // Daftardagi harakatni tekshirish
        $shortageMovement = CashMovement::where('type', 'DIFFERENCE_SHORTAGE')->first();
        $this->assertNotNull($shortageMovement);
        $this->assertEquals(20_000, $shortageMovement->credit);
        $this->assertEquals(580_000, $shortageMovement->balance_after);
    }

    /**
     * 10. Provisional Smena Yopilishi:
     * Offline qurilmalar sinxronlanmagan bo'lsa, PROVISIONAL yopiladi.
     */
    public function test_session_provisional_closing_for_pending_offline_devices(): void
    {
        $sessionService = app(CashSessionService::class);

        $session = $sessionService->openSession(
            cashAccountId: $this->cashAccount->id,
            userId: $this->cashier->id,
            openingBalance: 100_000
        );

        $provisionalSession = $sessionService->closeSession(
            sessionId: $session->id,
            userId: $this->cashier->id,
            actualClosingBalance: 100_000,
            isProvisional: true
        );

        $this->assertEquals('PROVISIONAL', $provisionalSession->status);
        $this->assertTrue($provisionalSession->has_pending_offline_sync);
    }

    /**
     * 11. Livewire CashManager interfeys testi.
     */
    public function test_livewire_cash_manager_renders_and_executes_actions(): void
    {
        $cashService = app(CashAccountService::class);
        $cashService->recordInflow($this->cashAccount->id, 500_000, 'OPENING_BALANCE');

        Livewire::actingAs($this->owner)
            ->test(CashManager::class)
            ->assertSee('Asosiy Naqd Kassa')
            ->assertSee('Bank Hisob-Raqami')
            ->assertSee('500 000')
            // Smena ochish
            ->call('openNewSessionModal', $this->cashAccount->id)
            ->set('openBalance', 500_000)
            ->call('submitOpenSession')
            ->assertSee('muvaffaqiyatli ochildi')
            // Xarajat qilish
            ->call('openExpenseModal')
            ->set('expenseAccountId', $this->cashAccount->id)
            ->set('expenseAmount', 25_000)
            ->set('expenseCategory', 'TRANSPORT')
            ->set('expenseDescription', 'Yoqilg\'i xarajati')
            ->call('submitExpense')
            ->assertSee('muvaffaqiyatli saqlandi');

        $this->assertEquals(475_000, $this->cashAccount->fresh()->balance);
    }
}

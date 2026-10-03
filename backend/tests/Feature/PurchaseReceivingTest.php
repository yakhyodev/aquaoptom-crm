<?php

namespace Tests\Feature;

use App\Livewire\Inventory\QuickInward;
use App\Models\CashAccount;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Ledger\CashAccountService;
use App\Services\Operations\Exceptions\OperationConflictException;
use App\Services\Operations\Exceptions\OperationPermissionException;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Purchase\ReceivePurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseReceivingTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $warehouseManager;

    protected Warehouse $warehouse;

    protected ProductVariant $variantFanta05;

    protected Supplier $supplier;

    protected CashAccount $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->warehouseManager = User::factory()->warehouseManager()->create();

        $this->warehouse = Warehouse::create(['name' => 'Asosiy Ombor', 'is_default' => true]);

        $vol05 = Volume::create(['name' => '0.5 L', 'value_ml' => 500]);
        $prodFanta = Product::create([
            'name' => 'Fanta',
            'normalized_name' => 'fanta',
            'code' => 'PRD-000001',
            'status' => 'active',
        ]);

        $this->variantFanta05 = ProductVariant::create([
            'product_id' => $prodFanta->id,
            'volume_id' => $vol05->id,
            'sku' => 'FANTA-500',
            'default_sale_price' => 7000,
            'status' => 'active',
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Coca-Cola Zavodi',
            'company_name' => 'Coca-Cola Ichimligi Uzbekiston',
            'phone' => '+998712000000',
            'balance' => 0,
        ]);

        $cashService = app(CashAccountService::class);
        $this->cashAccount = $cashService->getOrCreateAccount('CASH', 'Asosiy Kassa', true);
        $this->cashAccount->update(['balance' => 1000000]); // 1 000 000 so'm boshlang'ich pul
    }

    /**
     * TEST 1: Qabul mezoni: Fanta 0.5L 150 x 5000 = 750 000 va +150 dona.
     * Supplierga 300 000 to'lov bo'lsa: qarz 450 000 / cash -300 000.
     */
    public function test_purchase_receiving_with_partial_payment(): void
    {
        $service = app(ReceivePurchaseService::class);
        $operationId = Str::uuid()->toString();

        $items = [
            [
                'variant_id' => $this->variantFanta05->id,
                'quantity' => 150,
                'unit_cost' => 5000,
                'new_sale_price' => 7500,
            ],
        ];

        $result = $service->execute(
            supplierId: $this->supplier->id,
            items: $items,
            operationId: $operationId,
            paidAmount: 300000,
            cashAccountId: $this->cashAccount->id,
            paymentMethod: 'CASH',
            supplierInvoiceNumber: 'FAK-9901',
            notes: 'Birinchi partiya Fanta 0.5L',
            userId: $this->owner->id
        );

        // 1. Natija tekshiruvi
        $this->assertNotNull($result['purchase_id']);
        $this->assertStringStartsWith('PUR-', $result['invoice_number']);
        $this->assertEquals(750000, $result['total_amount']);
        $this->assertEquals(300000, $result['paid_amount']);
        $this->assertEquals(450000, $result['debt_amount']);
        $this->assertEquals(450000, $result['supplier_balance_after']);

        // 2. Ombor tekshiruvi (+150 dona va 750 000 qiymat)
        $balance = InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->first();
        $this->assertNotNull($balance);
        $this->assertEquals(150, (int) $balance->quantity);
        $this->assertEquals(750000, (int) $balance->total_value);
        $this->assertEquals(5000, (int) $balance->average_cost);

        $movement = InventoryMovement::where('product_variant_id', $this->variantFanta05->id)->first();
        $this->assertEquals('PURCHASE', $movement->movement_type);
        $this->assertEquals(150, (int) $movement->quantity);
        $this->assertEquals(5000, (int) $movement->unit_cost);

        // 3. Ta'minotchi qarz daftari (Credit 750 000, Debit 300 000, Qoldiq 450 000)
        $this->assertEquals(450000, (int) $this->supplier->fresh()->balance);
        $supplierLedgers = SupplierLedger::where('supplier_id', $this->supplier->id)->get();
        $this->assertCount(2, $supplierLedgers);

        $purchaseCredit = $supplierLedgers->firstWhere('type', 'PURCHASE');
        $this->assertEquals(750000, $purchaseCredit->credit);
        $this->assertEquals(750000, $purchaseCredit->balance_after);

        $paymentDebit = $supplierLedgers->firstWhere('type', 'PAYMENT');
        $this->assertEquals(300000, $paymentDebit->debit);
        $this->assertEquals(450000, $paymentDebit->balance_after);

        // 4. Kassa tekshiruvi (1 000 000 - 300 000 = 700 000)
        $this->assertEquals(700000, (int) $this->cashAccount->fresh()->balance);

        $paymentDoc = Payment::where('party_id', $this->supplier->id)->first();
        $this->assertNotNull($paymentDoc);
        $this->assertEquals(300000, $paymentDoc->amount);
        $this->assertEquals('OUT', $paymentDoc->direction);
        $this->assertEquals('SUPPLIER_PAYMENT', $paymentDoc->payment_type);

        // 5. Variant sotuv narxi yangilanganligi
        $this->assertEquals(7500, $this->variantFanta05->fresh()->default_sale_price);
    }

    /**
     * TEST 2: To'lovsiz kirim (Unpaid Purchase): Kassa o'zgarmaydi, to'liq summa qarzga yoziladi.
     */
    public function test_purchase_receiving_unpaid_does_not_affect_cash(): void
    {
        $service = app(ReceivePurchaseService::class);
        $operationId = Str::uuid()->toString();

        $initialCash = $this->cashAccount->balance;

        $items = [
            [
                'variant_id' => $this->variantFanta05->id,
                'quantity' => 100,
                'unit_cost' => 5000,
            ],
        ];

        $result = $service->execute(
            supplierId: $this->supplier->id,
            items: $items,
            operationId: $operationId,
            paidAmount: 0, // To'lovsiz!
            userId: $this->warehouseManager->id
        );

        $this->assertEquals(500000, $result['total_amount']);
        $this->assertEquals(0, $result['paid_amount']);
        $this->assertEquals(500000, $result['debt_amount']);

        // Kassa o'zgarmasligi kafolati!
        $this->assertEquals($initialCash, (int) $this->cashAccount->fresh()->balance);
        $this->assertDatabaseMissing('payments', ['party_id' => $this->supplier->id]);

        // Ta'minotchi qarzi to'liq 500 000
        $this->assertEquals(500000, (int) $this->supplier->fresh()->balance);
    }

    /**
     * TEST 3: Omborchi uchun moliya huquqi cheklovi:
     * Omborchi to'lovsiz kirim qila oladi, lekin ruxsatsiz kassadan pul to'lay olmaydi.
     */
    public function test_warehouse_manager_cannot_issue_cash_payment(): void
    {
        $service = app(ReceivePurchaseService::class);
        $operationId = Str::uuid()->toString();

        $items = [
            [
                'variant_id' => $this->variantFanta05->id,
                'quantity' => 100,
                'unit_cost' => 5000,
            ],
        ];

        $this->expectException(OperationPermissionException::class);
        $service->execute(
            supplierId: $this->supplier->id,
            items: $items,
            operationId: $operationId,
            paidAmount: 200000, // Pul to'lashga urinyapti!
            cashAccountId: $this->cashAccount->id,
            userId: $this->warehouseManager->id
        );
    }

    /**
     * TEST 4: Double-click va Idempotency (Retry bitta yozuv beradi, takror to'lov va tovar yo'q).
     */
    public function test_purchase_receiving_idempotency_double_click_protection(): void
    {
        $service = app(ReceivePurchaseService::class);
        $operationId = Str::uuid()->toString();

        $items = [
            [
                'variant_id' => $this->variantFanta05->id,
                'quantity' => 150,
                'unit_cost' => 5000,
            ],
        ];

        // 1-bosish
        $res1 = $service->execute(
            supplierId: $this->supplier->id,
            items: $items,
            operationId: $operationId,
            paidAmount: 300000,
            cashAccountId: $this->cashAccount->id,
            userId: $this->owner->id
        );

        $this->assertEquals(150, InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->value('quantity'));
        $this->assertEquals(700000, $this->cashAccount->fresh()->balance);

        // 2-bosish (Double-click yoki tarmoq qayta yuborishi)
        $res2 = $service->execute(
            supplierId: $this->supplier->id,
            items: $items,
            operationId: $operationId,
            paidAmount: 300000,
            cashAccountId: $this->cashAccount->id,
            userId: $this->owner->id
        );

        $this->assertEquals($res1['invoice_number'], $res2['invoice_number']);

        // Omborda qoldiq 300 bo'lib ketmasligi shart!
        $this->assertEquals(150, InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->value('quantity'));
        // Kassadan pul ikkinchi marta yechilmasligi shart!
        $this->assertEquals(700000, $this->cashAccount->fresh()->balance);

        // Bazada faqat 1 ta Purchase bo'lishi shart
        $this->assertEquals(1, Purchase::where('supplier_id', $this->supplier->id)->count());

        // Boshqa payload bilan xuddi shu ID yuborilsa -> 409 Conflict
        $this->expectException(OperationConflictException::class);
        $service->execute(
            supplierId: $this->supplier->id,
            items: [
                ['variant_id' => $this->variantFanta05->id, 'quantity' => 200, 'unit_cost' => 5000],
            ],
            operationId: $operationId,
            paidAmount: 300000,
            cashAccountId: $this->cashAccount->id,
            userId: $this->owner->id
        );
    }

    /**
     * TEST 5: Bir qator xatosida atomik Rollback (barcha amallar bekor bo'ladi).
     */
    public function test_purchase_receiving_rollback_on_item_error(): void
    {
        $service = app(ReceivePurchaseService::class);
        $operationId = Str::uuid()->toString();

        $items = [
            ['variant_id' => $this->variantFanta05->id, 'quantity' => 100, 'unit_cost' => 5000],
            ['variant_id' => 999999, 'quantity' => 50, 'unit_cost' => 4000], // Mavjud bo'lmagan tovar!
        ];

        try {
            $service->execute(
                supplierId: $this->supplier->id,
                items: $items,
                operationId: $operationId,
                paidAmount: 200000,
                cashAccountId: $this->cashAccount->id,
                userId: $this->owner->id
            );
            $this->fail('Mavjud bo\'lmagan tovar qatorida xatolik yuz berishi kerak edi.');
        } catch (OperationValidationException $e) {
            // Kutilgan xatolik
        }

        // Rollback tekshiruvi: 1-tovar ham omborga kirmagan, pul ham chiqmagan, qarz ham yozilmagan
        $this->assertDatabaseMissing('inventory_balances', ['product_variant_id' => $this->variantFanta05->id]);
        $this->assertDatabaseMissing('purchases', ['operation_id' => $operationId]);
        $this->assertDatabaseMissing('payments', ['operation_id' => $operationId.'-pay']);
        $this->assertEquals(1000000, $this->cashAccount->fresh()->balance);
        $this->assertEquals(0, $this->supplier->fresh()->balance);
    }

    /**
     * TEST 6: Livewire QuickInward qoralamasi saqlanishi va inline modal orqali tovar qo'shilishi.
     */
    public function test_livewire_quick_inward_preserves_draft_and_posts(): void
    {
        $this->actingAs($this->owner);

        $test = Livewire::test(QuickInward::class)
            ->set('activeTab', 'receiving')
            ->call('selectSupplier', $this->supplier->id)
            // 1-tovarni qo'shish
            ->dispatch('product-created', [
                'variant_id' => $this->variantFanta05->id,
                'display_name' => 'Fanta — 0.5 L',
                'sku' => 'FANTA-500',
                'default_price' => 7000,
            ])
            ->assertSet('items.0.variant_id', $this->variantFanta05->id)
            ->assertSet('items.0.quantity', 100);

        // Qoralama saqlanadi va 2-marta inline modal hodisasi kelsa mavjud qoralama yo'qolmaydi
        $test->dispatch('product-created', [
            'variant_id' => $this->variantFanta05->id,
            'display_name' => 'Fanta — 0.5 L',
            'sku' => 'FANTA-500',
            'default_price' => 7000,
        ])
            ->assertSet('items.0.quantity', 150)
            ->assertSet('selectedSupplierId', $this->supplier->id);

        // Kirimni tasdiqlash
        $test->set('paymentType', 'UNPAID')
            ->call('postPurchase')
            ->assertHasNoErrors()
            ->assertSee('Kirim muvaffaqiyatli qabul qilindi');

        // Bazada tekshirish
        $this->assertEquals(150, InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->value('quantity'));
        $this->assertEquals(750000, $this->supplier->fresh()->balance);
    }
}

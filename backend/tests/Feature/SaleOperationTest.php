<?php

namespace Tests\Feature;

use App\Livewire\Sales\OptomPos;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Sales\CreateSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SaleOperationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Customer $customer;

    protected Warehouse $warehouse;

    protected CashAccount $cashAccount;

    protected ProductVariant $variantFanta05;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create([
            'email' => 'owner_sale_test@aquaoptom.uz',
        ]);
        $this->actingAs($this->owner);

        $this->warehouse = Warehouse::firstOrCreate(
            ['name' => 'Asosiy Ombor'],
            ['is_default' => true]
        );

        $this->cashAccount = CashAccount::firstOrCreate(
            ['name' => 'Asosiy Naqd Kassa'],
            ['type' => 'CASH', 'balance' => 0, 'is_default' => true]
        );

        $this->customer = Customer::create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Akmal aka',
            'phone' => '+998901234567',
            'store_name' => 'Oazis Savdo',
            'address' => 'Chilonzor 9',
            'status' => 'active',
            'current_debt' => 0,
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
     * TEST 1: Qabul mezoni: 60 × 6500 jami 390 000 / cost 300 000 / paid 140 000 / debt 250 000 / gross 90 000.
     */
    public function test_audit_sale_retry_preserves_price_after_catalog_changes(): void
    {
        app(InventoryLedgerService::class)->recordInflow($this->variantFanta05->id, 10, 5000, warehouseId: $this->warehouse->id);
        $id = (string) Str::uuid();
        $items = [['variant_id' => $this->variantFanta05->id, 'quantity' => 2, 'is_system_price' => true, 'price_version' => 1]];
        $service = app(CreateSaleService::class);
        $sale = $service->execute(null, $items, $id, userId: $this->owner->id);
        $this->variantFanta05->update(['default_sale_price' => 7500, 'version' => 2]);
        $retry = $service->execute(null, $items, $id, userId: $this->owner->id);
        $this->assertSame($sale->id, $retry->id);
        $this->assertSame(13000, (int) $retry->total_amount);
        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(8, (int) $this->variantFanta05->inventoryBalances()->value('quantity'));
    }

    public function test_audit_fractional_sale_price_is_rejected_before_posting(): void
    {
        $this->expectException(OperationValidationException::class);
        $this->expectExceptionMessage('butun');
        app(CreateSaleService::class)->execute(null, [['variant_id' => $this->variantFanta05->id, 'quantity' => 1, 'sale_price' => 6500.5, 'is_system_price' => false]], userId: $this->owner->id);
    }

    public function test_audit_mobile_rejects_fractional_quantity_price_and_packages(): void
    {
        foreach ([['quantity' => 0.5], ['sale_price' => 6500.5], ['package_name' => 'blok']] as $invalid) {
            $this->actingAs($this->owner, 'sanctum')->postJson('/api/sales', ['items' => [array_merge(['variant_id' => $this->variantFanta05->id, 'quantity' => 1, 'sale_price' => 6500], $invalid)]])->assertUnprocessable();
        }
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_sale_exact_acceptance_specification(): void
    {
        $invService = app(InventoryLedgerService::class);
        $saleService = app(CreateSaleService::class);

        // 1. Dastlabki qoldiq kiritish: 100 dona @ 5000 so'm (WAC tannarx: 5000)
        $invService->recordInflow(
            productVariantId: $this->variantFanta05->id,
            quantity: 100,
            unitCost: 5000,
            movementType: 'OPENING_BALANCE',
            warehouseId: $this->warehouse->id,
            operationId: Str::uuid()->toString(),
            userId: $this->owner->id
        );

        // 2. Sotuv: 60 dona x 6500 so'm = 390 000 so'm, paid 140 000
        $operationId = Str::uuid()->toString();
        $items = [
            [
                'variant_id' => $this->variantFanta05->id,
                'quantity' => 60,
                'sale_price' => 6500,
                'is_system_price' => true,
            ],
        ];

        $sale = $saleService->execute(
            customerId: $this->customer->id,
            items: $items,
            operationId: $operationId,
            paidAmount: 140000,
            cashAccountId: $this->cashAccount->id,
            paymentType: 'PARTIAL',
            paymentMethod: 'CASH',
            notes: 'Optom 60 dona sotuvi',
            userId: $this->owner->id,
            source: 'web'
        );

        // 3. Moliyaviy ko'rsatkichlar tekshiruvi (Exact match with prompt)
        $this->assertEquals(390000, $sale->total_amount); // 60 * 6500
        $this->assertEquals(300000, $sale->total_cost);   // 60 * 5000
        $this->assertEquals(140000, $sale->paid_amount);  // Hozir olingan pul
        $this->assertEquals(250000, $sale->debt_amount);  // Yangi nasiya qarz
        $this->assertEquals(90000, $sale->gross_profit);  // 390000 - 300000

        // 4. Ombor qoldig'i tekshiruvi (100 - 60 = 40 dona, qiymat: 200 000 so'm)
        $balance = InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->first();
        $this->assertEquals(40, (int) $balance->quantity);
        $this->assertEquals(5000, (int) $balance->average_cost);
        $this->assertEquals(200000, (int) $balance->total_value);

        // 5. Mijoz qarzi tekshiruvi (+250 000 so'm qarz)
        $this->customer->refresh();
        $this->assertEquals(250000, (int) $this->customer->current_debt);

        // 6. Kassa tekshiruvi (+140 000 so'm kassa kirimi)
        $this->cashAccount->refresh();
        $this->assertEquals(140000, (int) $this->cashAccount->balance);

        // 7. Elektron hujjat snapshot tekshiruvi
        $this->assertNotNull($sale->receipt_data);
        $this->assertEquals(390000, $sale->receipt_data['total_amount']);
        $this->assertEquals(140000, $sale->receipt_data['paid_amount']);
        $this->assertEquals(250000, $sale->receipt_data['debt_amount']);
        $this->assertEquals(90000, $sale->receipt_data['gross_profit']);
    }

    /**
     * TEST 2: Rad etish: Mijozsiz DEBT (Guest debt taqiqlangan).
     */
    public function test_cannot_make_debt_sale_without_customer(): void
    {
        $invService = app(InventoryLedgerService::class);
        $saleService = app(CreateSaleService::class);

        $invService->recordInflow(
            productVariantId: $this->variantFanta05->id,
            quantity: 50,
            unitCost: 5000,
            movementType: 'OPENING_BALANCE',
            warehouseId: $this->warehouse->id,
            operationId: Str::uuid()->toString()
        );

        $this->expectException(OperationValidationException::class);
        $this->expectExceptionMessage("Noma'lum xaridorga (mijozsiz) nasiyaga savdo qilish taqiqlangan!");

        $saleService->execute(
            customerId: null, // Guest
            items: [
                ['variant_id' => $this->variantFanta05->id, 'quantity' => 10, 'sale_price' => 6500],
            ],
            operationId: Str::uuid()->toString(),
            paidAmount: 20000, // Jami 65 000, lekin faqat 20 000 to'lanmoqda (45 000 qarz) -> Rad etiladi!
            paymentType: 'PARTIAL'
        );
    }

    /**
     * TEST 3: Rad etish: Manfiy yoki nol narx (0 yoki negative price taqiqlangan).
     */
    public function test_cannot_make_sale_with_zero_or_negative_price(): void
    {
        $saleService = app(CreateSaleService::class);

        $this->expectException(OperationValidationException::class);
        $this->expectExceptionMessage("Tovar narxi 0 dan katta butun so'm bo'lishi shart!");

        $saleService->execute(
            customerId: $this->customer->id,
            items: [
                ['variant_id' => $this->variantFanta05->id, 'quantity' => 10, 'sale_price' => 0, 'is_system_price' => false],
            ],
            operationId: Str::uuid()->toString()
        );
    }

    /**
     * TEST 4: Rad etish: Kasr dona (Fractional quantity taqiqlangan).
     */
    public function test_cannot_make_sale_with_fractional_quantity(): void
    {
        $saleService = app(CreateSaleService::class);

        $this->expectException(OperationValidationException::class);
        $this->expectExceptionMessage('Kasr dona bilan savdo taqiqlanadi');

        $saleService->execute(
            customerId: $this->customer->id,
            items: [
                ['variant_id' => $this->variantFanta05->id, 'quantity' => 2.5, 'sale_price' => 6500],
            ],
            operationId: Str::uuid()->toString()
        );
    }

    /**
     * TEST 5: Rad etish: 100 qoldiqdan 60 + 60 o'tmaydi (Takror qatorlar va Concurrency lock).
     */
    public function test_cannot_overdraw_stock_with_duplicate_lines_or_concurrency(): void
    {
        $invService = app(InventoryLedgerService::class);
        $saleService = app(CreateSaleService::class);

        // Omborda aniq 100 dona qoldiq bor
        $invService->recordInflow(
            productVariantId: $this->variantFanta05->id,
            quantity: 100,
            unitCost: 5000,
            movementType: 'OPENING_BALANCE',
            warehouseId: $this->warehouse->id,
            operationId: Str::uuid()->toString()
        );

        // A holat: Bitta savatda ikki qator: 60 + 60 = 120 dona (120 > 100) -> Rad etiladi!
        $duplicateLines = [
            ['variant_id' => $this->variantFanta05->id, 'quantity' => 60, 'sale_price' => 6500],
            ['variant_id' => $this->variantFanta05->id, 'quantity' => 60, 'sale_price' => 6000],
        ];

        try {
            $saleService->execute(
                customerId: $this->customer->id,
                items: $duplicateLines,
                operationId: Str::uuid()->toString()
            );
            $this->fail("100 qoldiqdan 60+60 savat o'tmasligi kerak edi!");
        } catch (OperationValidationException $e) {
            $this->assertStringContainsString('Omborda yetarli mahsulot mavjud emas', $e->getMessage());
        }

        // B holat: Birinchi so'rov 60 dona oladi (muvaffaqiyatli, 40 dona qoladi)
        $sale1 = $saleService->execute(
            customerId: $this->customer->id,
            items: [['variant_id' => $this->variantFanta05->id, 'quantity' => 60, 'sale_price' => 6500]],
            operationId: Str::uuid()->toString(),
            paidAmount: 390000
        );
        $this->assertNotNull($sale1);

        // Ikkinchi so'rov yana 60 dona so'raydi (mavjud 40 dona) -> Rad etiladi!
        try {
            $saleService->execute(
                customerId: $this->customer->id,
                items: [['variant_id' => $this->variantFanta05->id, 'quantity' => 60, 'sale_price' => 6500]],
                operationId: Str::uuid()->toString()
            );
            $this->fail("Mavjud 40 qoldiqdan 60 dona savdo o'tmasligi kerak edi!");
        } catch (OperationValidationException $e) {
            $this->assertStringContainsString('Omborda yetarli mahsulot mavjud emas', $e->getMessage());
        }

        // Omborda aniq 40 dona qolishi kafolatlangan
        $balance = InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->first();
        $this->assertEquals(40, (int) $balance->quantity);
    }

    /**
     * TEST 6: 20 bosish / parallel so'rov bitta chek (Idempotency with 20 parallel requests).
     */
    public function test_twenty_repeated_clicks_yield_single_sale(): void
    {
        $invService = app(InventoryLedgerService::class);
        $saleService = app(CreateSaleService::class);

        $invService->recordInflow(
            productVariantId: $this->variantFanta05->id,
            quantity: 200,
            unitCost: 5000,
            movementType: 'OPENING_BALANCE',
            warehouseId: $this->warehouse->id,
            operationId: Str::uuid()->toString()
        );

        $operationId = Str::uuid()->toString();
        $items = [
            ['variant_id' => $this->variantFanta05->id, 'quantity' => 10, 'sale_price' => 6500],
        ];

        $invoiceNumbers = [];

        // 20 marta bir xil operation_id bilan chaqiriladi (double click / parallel retry simulyatsiyasi)
        for ($i = 0; $i < 20; $i++) {
            $sale = $saleService->execute(
                customerId: $this->customer->id,
                items: $items,
                operationId: $operationId,
                paidAmount: 65000,
                cashAccountId: $this->cashAccount->id,
                userId: $this->owner->id
            );
            $invoiceNumbers[] = $sale->invoice_number;
        }

        // Barcha 20 ta chaqiruv bitta chek raqamini qaytargan
        $uniqueInvoices = array_unique($invoiceNumbers);
        $this->assertCount(1, $uniqueInvoices);

        // Bazada faqat bitta chek yaratilgan
        $this->assertEquals(1, Sale::where('operation_id', $operationId)->count());

        // Ombordan faqat 1 marta 10 dona ayrilgan (200 - 10 = 190)
        $balance = InventoryBalance::where('product_variant_id', $this->variantFanta05->id)->first();
        $this->assertEquals(190, (int) $balance->quantity);

        // Kassaga faqat 1 marta 65 000 so'm kirim bo'lgan
        $this->cashAccount->refresh();
        $this->assertEquals(65000, (int) $this->cashAccount->balance);
    }

    /**
     * TEST 7: Mijozning mavjud avansi (Avans ikkinchi cash emas).
     */
    public function test_customer_advance_does_not_create_duplicate_cash(): void
    {
        $invService = app(InventoryLedgerService::class);
        $saleService = app(CreateSaleService::class);

        $invService->recordInflow(
            productVariantId: $this->variantFanta05->id,
            quantity: 50,
            unitCost: 5000,
            movementType: 'OPENING_BALANCE',
            warehouseId: $this->warehouse->id,
            operationId: Str::uuid()->toString()
        );

        // Mijoz hisobida 100 000 so'm oldingi avans bor (-100 000 current_debt)
        $this->customer->current_debt = -100000;
        $this->customer->save();

        $initialCashBalance = (int) $this->cashAccount->balance;

        // Savdo: 10 dona x 6500 = 65 000 so'm. Hozirgi to'lov = 0 (avansdan qoplanadi)
        $sale = $saleService->execute(
            customerId: $this->customer->id,
            items: [['variant_id' => $this->variantFanta05->id, 'quantity' => 10, 'sale_price' => 6500]],
            operationId: Str::uuid()->toString(),
            paidAmount: 0,
            paymentType: 'DEBT'
        );

        $this->customer->refresh();
        // -100 000 + 65 000 = -35 000 so'm (hali ham 35 000 avans bor)
        $this->assertEquals(-35000, (int) $this->customer->current_debt);

        // Kassa o'zgarmasligi kerak! Chunki bu pul oldinroq kelgan, bugun yangi kassa kirimi yo'q
        $this->cashAccount->refresh();
        $this->assertEquals($initialCashBalance, (int) $this->cashAccount->balance);
    }

    /**
     * TEST 8: Livewire POS - Xatoda savat qoladi.
     */
    public function test_pos_ui_preserves_cart_draft_on_validation_error(): void
    {
        $this->actingAs($this->owner);

        // 1. Omborda qoldiq bor
        $invService = app(InventoryLedgerService::class);
        $invService->recordInflow(
            productVariantId: $this->variantFanta05->id,
            quantity: 50,
            unitCost: 5000,
            movementType: 'OPENING_BALANCE',
            warehouseId: $this->warehouse->id,
            operationId: Str::uuid()->toString()
        );

        $component = Livewire::test(OptomPos::class)
            ->call('onProductCreated', [
                'variant_id' => $this->variantFanta05->id,
                'sku' => $this->variantFanta05->sku,
                'display_name' => 'Fanta 0.5 L',
                'sale_price' => 6500,
            ])
            ->call('setPaymentType', 'DEBT') // Mijozsiz nasiya qilishga urinish (paidAmount = 0)
            ->call('checkout');

        // 2. Xatolik xabari chiqishi kerak
        $component->assertSet('errorMessage', "Mijozsiz (noma'lum xaridorga) nasiya savdo qilish taqiqlanadi! To'liq to'lov yoki mijozni tanlang.");

        // 3. Savat qoralamasi SAQLANIB QOLISHI kerak!
        $component->assertCount('items', 1);

        // 4. Mijozni tanlab qayta tasdiqlaganda muvaffaqiyatli bo'ladi
        $component->call('selectExistingCustomer', $this->customer->id)
            ->call('setPaymentType', 'DEBT')
            ->call('checkout')
            ->assertHasNoErrors()
            ->assertSet('errorMessage', null)
            ->assertCount('items', 0); // Faqat muvaffaqiyatli chekdan keyin savat tozalanadi
    }

    /**
     * TEST 9: Tizim narxi yo'q bo'lsa tasodifiy default yo'q (Rad etiladi).
     */
    public function test_system_price_not_set_rejected(): void
    {
        $saleService = app(CreateSaleService::class);

        // Narxsiz variant
        $this->variantFanta05->default_sale_price = null;
        $this->variantFanta05->save();

        $this->expectException(OperationValidationException::class);
        $this->expectExceptionMessage('uchun tizim narxi belgilanmagan!');

        $saleService->execute(
            customerId: $this->customer->id,
            items: [
                ['variant_id' => $this->variantFanta05->id, 'quantity' => 5, 'is_system_price' => true],
            ],
            operationId: Str::uuid()->toString()
        );
    }

    /**
     * TEST 10: Online eski narx versiyasida qayta tasdiq (Price version mismatch).
     */
    public function test_price_version_mismatch_requires_reconfirmation(): void
    {
        $saleService = app(CreateSaleService::class);

        // Variant version = 2 ga o'zgardi
        $this->variantFanta05->version = 2;
        $this->variantFanta05->default_sale_price = 7000;
        $this->variantFanta05->save();

        // Xaridor savatiga eski version 1 bo'yicha narx kiritilgan edi
        $this->expectException(OperationValidationException::class);
        $this->expectExceptionMessage("mahsulotining tizim narxi o'zgargan! Yangi narx bilan qayta tasdiqlang.");

        $saleService->execute(
            customerId: $this->customer->id,
            items: [
                ['variant_id' => $this->variantFanta05->id, 'quantity' => 5, 'is_system_price' => true, 'price_version' => 1],
            ],
            operationId: Str::uuid()->toString()
        );
    }
}

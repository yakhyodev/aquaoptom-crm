<?php

namespace Tests\Feature;

use App\Livewire\Inventory\StockManager;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Customer;
use App\Models\Device;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Inventory\DamageDisposalService;
use App\Services\Inventory\InventoryAuditService;
use App\Services\Inventory\SaleReturnService;
use App\Services\Inventory\SupplierReturnService;
use App\Services\Ledger\CashAccountService;
use App\Services\Ledger\Exceptions\CannotReturnMoreThanPurchasedException;
use App\Services\Ledger\Exceptions\CannotReturnMoreThanSoldException;
use App\Services\Ledger\Exceptions\DeviceFreezePendingException;
use App\Services\Ledger\Exceptions\DocumentImmutableException;
use App\Services\Ledger\Exceptions\InsufficientCashException;
use App\Services\Ledger\Exceptions\ReservedStockProtectionException;
use App\Services\Ledger\InventoryAllocationService;
use App\Services\Ledger\InventoryLedgerService;
use App\Services\Operations\Exceptions\OperationConflictException;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Purchase\ReceivePurchaseService;
use App\Services\Sales\CreateSaleService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ReturnsDamagesAndAdjustmentsTest extends TestCase
{
    use RefreshDatabase;

    protected Warehouse $warehouse;

    protected User $owner;

    protected User $cashier;

    protected Volume $v05;

    protected Volume $v10;

    protected Supplier $supplier;

    protected Customer $customer;

    protected CashAccount $cashAccount;

    protected ProductVariant $variantA;

    protected ProductVariant $variantB;

    protected ReceivePurchaseService $purchaseService;

    protected CreateSaleService $saleService;

    protected SaleReturnService $saleReturnService;

    protected SupplierReturnService $supplierReturnService;

    protected DamageDisposalService $damageDisposalService;

    protected InventoryAuditService $inventoryAuditService;

    protected InventoryAllocationService $allocationService;

    protected InventoryLedgerService $inventoryLedgerService;

    protected CashAccountService $cashAccountService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->warehouse = Warehouse::create([
            'name' => 'Asosiy Ombor',
            'code' => 'WH-MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'role' => 'OWNER',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);
        $this->actingAs($this->owner);

        $this->cashier = User::factory()->create([
            'role' => 'CASHIER',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->cashAccount = CashAccount::create([
            'name' => 'Asosiy Kassa',
            'type' => 'CASH',
            'currency' => 'UZS',
            'balance' => 0,
            'is_active' => true,
        ]);

        $this->v05 = Volume::firstOrCreate(['value_ml' => 500], ['name' => '0.5 L']);
        $this->v10 = Volume::firstOrCreate(['value_ml' => 1000], ['name' => '1.0 L']);

        $product = Product::create([
            'name' => 'Chortoq Suvi',
            'code' => 'CHORTOQ',
            'status' => 'active',
        ]);

        $this->variantA = ProductVariant::create([
            'product_id' => $product->id,
            'volume_id' => $this->v05->id,
            'sku' => 'CHOR-05',
            'default_sale_price' => 8000,
            'status' => 'active',
            'is_active' => true,
            'minimum_stock' => 10,
        ]);

        $this->variantB = ProductVariant::create([
            'product_id' => $product->id,
            'volume_id' => $this->v10->id,
            'sku' => 'CHOR-10',
            'default_sale_price' => 12000,
            'status' => 'active',
            'is_active' => true,
            'minimum_stock' => 5,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Chortoq Zavod',
            'balance' => 0,
        ]);

        $this->customer = Customer::create([
            'name' => 'Alisher Navoiy Savdo',
            'current_debt' => 0,
        ]);

        $this->purchaseService = app(ReceivePurchaseService::class);
        $this->saleService = app(CreateSaleService::class);
        $this->saleReturnService = app(SaleReturnService::class);
        $this->supplierReturnService = app(SupplierReturnService::class);
        $this->damageDisposalService = app(DamageDisposalService::class);
        $this->inventoryAuditService = app(InventoryAuditService::class);
        $this->allocationService = app(InventoryAllocationService::class);
        $this->inventoryLedgerService = app(InventoryLedgerService::class);
        $this->cashAccountService = app(CashAccountService::class);
    }

    /**
     * Helper: omborga tovar kirim qilish
     */
    protected function purchaseStock(int $variantId, int $qty, int $unitCost): Purchase
    {
        $res = $this->purchaseService->execute(
            supplierId: $this->supplier->id,
            items: [
                [
                    'variant_id' => $variantId,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                ],
            ],
            operationId: (string) Str::uuid(),
            paidAmount: 0,
            warehouseId: $this->warehouse->id,
            userId: $this->owner->id
        );

        return Purchase::with('items')->find($res->purchase_id);
    }

    /**
     * Helper: savdo amalga oshirish
     */
    protected function makeSale(int $variantId, int $qty, int $salePrice, int $paidAmount = 0): Sale
    {
        return $this->saleService->execute(
            customerId: $this->customer->id,
            items: [
                [
                    'variant_id' => $variantId,
                    'quantity' => $qty,
                    'sale_price' => $salePrice,
                    'is_system_price' => false,
                ],
            ],
            operationId: (string) Str::uuid(),
            paidAmount: $paidAmount,
            cashAccountId: $this->cashAccount->id,
            paymentMethod: 'CASH',
            warehouseId: $this->warehouse->id,
            userId: $this->cashier->id
        );
    }

    /**
     * Test 1: Sotuv qaytarish original sotuv narxi va cost snapshotni ishlatadi.
     */
    public function test_sale_return_partial_and_full_with_original_price_and_cost_snapshot(): void
    {
        // 1. Tovar kirimi: 10 dona x 5 000 so'm (WAC = 5 000)
        $this->purchaseStock($this->variantA->id, 10, 5000);

        // 2. Savdo: 5 dona x 8 000 so'm sotildi (Cost snapshot = 5 000). To'liq naqd.
        $sale = $this->makeSale($this->variantA->id, 5, 8000, 40000);
        $saleItem = $sale->items->first();

        // 3. Omborga yangi qimmat kirim: 10 dona x 7 000 so'm. Joriy WAC oshib ketadi.
        $this->purchaseStock($this->variantA->id, 10, 7000);

        // 4. Mijoz 2 dona tovar qaytaradi (Naqd pul qaytarildi: 16 000 so'm)
        $returnRes = $this->saleReturnService->createSaleReturn(
            saleId: $sale->id,
            items: [
                [
                    'sale_item_id' => $saleItem->id,
                    'quantity' => 2,
                    'is_damaged' => false,
                ],
            ],
            reason: 'Mijoz ortiqcha oldi',
            refundAmount: 16000,
            cashAccountId: $this->cashAccount->id,
            userId: $this->cashier->id
        );

        $this->assertTrue($returnRes['success']);
        $this->assertEquals(16000, $returnRes['total_amount']);
        $this->assertEquals(10000, $returnRes['total_cost']); // 2 dona x 5000 cost snapshot

        // Qoldiq tekshiruvi: 5 dona sotilgach 5 qolgan edi + 10 kirim = 15. Endi 2 qaytdi = 17 dona
        $bal = InventoryBalance::where('product_variant_id', $this->variantA->id)->first();
        $this->assertEquals(17, (int) $bal->quantity);

        // Kassadan 16 000 so'm naqd chiqim bo'lgan (Savdodan 40k tushgan edi, 16k qaytdi -> 24k qoldi)
        $this->cashAccount->refresh();
        $this->assertEquals(24000, (int) $this->cashAccount->balance);
    }

    /**
     * Test 2: Qisman returnlar sum/cost yaxlitlashni oxirgi donada to'liq yopadi.
     */
    public function test_partial_returns_cleanly_close_rounding_residues_on_final_unit(): void
    {
        // 1. Kirim: 3 dona x 6 666 so'm (Jami qiymat 20 000)
        $this->inventoryLedgerService->recordInflow($this->variantA->id, 3, 6666, 'PURCHASE', $this->warehouse->id);
        $bal = InventoryBalance::where('product_variant_id', $this->variantA->id)->first();
        $bal->update(['total_value' => 20000, 'average_cost' => 6667]);

        // 2. Savdo: 3 dona x 11 666 so'm (Jami 35 000 so'm)
        $sale = $this->makeSale($this->variantA->id, 3, 11666, 35000);
        $saleItem = $sale->items->first();
        $saleItem->update(['line_total' => 35000, 'cost_total' => 20000, 'purchase_cost_snapshot' => 6667]);

        // 3. Birinchi qaytarish: 1 dona
        $ret1 = $this->saleReturnService->createSaleReturn(
            saleId: $sale->id,
            items: [['sale_item_id' => $saleItem->id, 'quantity' => 1]],
            reason: 'Qisman qaytarish 1',
            refundAmount: 11666,
            cashAccountId: $this->cashAccount->id
        );

        // 4. Ikkinchi qaytarish: 1 dona
        $ret2 = $this->saleReturnService->createSaleReturn(
            saleId: $sale->id,
            items: [['sale_item_id' => $saleItem->id, 'quantity' => 1]],
            reason: 'Qisman qaytarish 2',
            refundAmount: 11666,
            cashAccountId: $this->cashAccount->id
        );

        // 5. Uchinchi (OXIRGI) qaytarish: 1 dona
        $ret3 = $this->saleReturnService->createSaleReturn(
            saleId: $sale->id,
            items: [['sale_item_id' => $saleItem->id, 'quantity' => 1]],
            reason: 'Qisman qaytarish 3 (Oxirgi)',
            refundAmount: 11668, // Qolgan qoldiq
            cashAccountId: $this->cashAccount->id
        );

        // INVARIANT: Jami 3 ta returnning line_total yig'indisi qat'iy 35 000 bo'lishi shart!
        $sumLineTotals = $ret1['total_amount'] + $ret2['total_amount'] + $ret3['total_amount'];
        $sumCostTotals = $ret1['total_cost'] + $ret2['total_cost'] + $ret3['total_cost'];

        $this->assertEquals(35000, $sumLineTotals, "Qisman qaytarishlar sotuv qiymatini to'liq yopishi shart!");
        $this->assertEquals(20000, $sumCostTotals, "Qisman qaytarishlar tannarx snapshotini to'liq yopishi shart!");
    }

    /**
     * Test 3: Sotilgandan ko'p qaytarish taqiqlanadi (CannotReturnMoreThanSoldException).
     */
    public function test_cannot_return_more_than_sold_quantity(): void
    {
        $this->purchaseStock($this->variantA->id, 10, 5000);
        $sale = $this->makeSale($this->variantA->id, 3, 8000, 24000);
        $saleItem = $sale->items->first();

        // 4 dona qaytarishga urinish (sotilgan: 3)
        $this->expectException(CannotReturnMoreThanSoldException::class);

        $this->saleReturnService->createSaleReturn(
            saleId: $sale->id,
            items: [['sale_item_id' => $saleItem->id, 'quantity' => 4]],
            reason: "Ko'p qaytarish"
        );
    }

    /**
     * Test 4: Yaroqsiz (damaged) qaytgan tovar sotiladigan qoldiqqa qo'shilmasligi kerak.
     */
    public function test_damaged_sale_return_does_not_enter_sellable_stock(): void
    {
        $this->purchaseStock($this->variantA->id, 10, 5000);
        $sale = $this->makeSale($this->variantA->id, 4, 8000, 32000);
        $saleItem = $sale->items->first();

        // Savdodan keyin omborda 6 dona qoldi
        $balBefore = InventoryBalance::where('product_variant_id', $this->variantA->id)->first();
        $this->assertEquals(6, (int) $balBefore->quantity);

        // 2 dona tovar yaroqsiz (is_damaged = true) holda qaytarildi
        $returnRes = $this->saleReturnService->createSaleReturn(
            saleId: $sale->id,
            items: [
                [
                    'sale_item_id' => $saleItem->id,
                    'quantity' => 2,
                    'is_damaged' => true,
                ],
            ],
            reason: 'Singan shisha idish',
            refundAmount: 16000,
            cashAccountId: $this->cashAccount->id
        );

        $this->assertTrue($returnRes['success']);

        // INVARIANT: Yaroqsiz tovar sotiladigan qoldiqqa QO'SHILMAYDI! Qoldiq hamon 6 dona!
        $balAfter = InventoryBalance::where('product_variant_id', $this->variantA->id)->first();
        $this->assertEquals(6, (int) $balAfter->quantity, "Yaroqsiz qaytgan tovar sotiladigan ombor qoldig'iga kirmasligi shart!");
    }

    /**
     * Test 5: Refund cash va qarzni ikki kamaytirmaydi.
     * Invariant: Agar tovar qaytsa va naqd pul olinsa, mijoz qarzi yana to'liq kamayib ketmasligi shart!
     */
    public function test_sale_return_refund_does_not_double_deduct_debt_and_cash(): void
    {
        $this->purchaseStock($this->variantA->id, 20, 5000);

        // Savdo: 10 dona x 10 000 = 100 000 so'm.
        // To'landi: 40 000 so'm naqd. Qarz: 60 000 so'm.
        $sale = $this->makeSale($this->variantA->id, 10, 10000, 40000);
        $saleItem = $sale->items->first();

        $this->customer->refresh();
        $this->assertEquals(60000, (int) $this->customer->current_debt);

        // Mijoz 5 dona tovar qaytaradi (50 000 so'mlik tovar).
        // Kassir unga 20 000 so'm naqd pul beradi.
        // INVARIANT:
        // Mijoz qarzidan faqat qolgan 30 000 so'm (50k - 20k) ayirilishi shart!
        // Qarzi 60 000 - 30 000 = 30 000 so'm bo'lib qoladi!
        $retRes = $this->saleReturnService->createSaleReturn(
            saleId: $sale->id,
            items: [['sale_item_id' => $saleItem->id, 'quantity' => 5]],
            reason: 'Qisman qaytarish va naqd refund',
            refundAmount: 20000,
            cashAccountId: $this->cashAccount->id
        );

        $this->customer->refresh();
        $this->assertEquals(30000, (int) $this->customer->current_debt, "Mijoz qarzi faqat (total - refund) bo'yicha kamayishi shart!");
        $this->assertEquals(30000, $retRes['debt_deduction_amount']);
        $this->assertEquals(20000, $retRes['refund_amount']);
    }

    /**
     * Test 6: Kassada yetarli pul bo'lmasa, qaytarish atomik rollback bo'ladi.
     */
    public function test_sale_return_insufficient_cash_in_drawer_fails_atomically(): void
    {
        $this->purchaseStock($this->variantA->id, 10, 5000);
        $sale = $this->makeSale($this->variantA->id, 5, 8000, 40000);
        $saleItem = $sale->items->first();

        // Kassa pulini sun'iy kamaytiramiz (faqat 5 000 so'm qoldi)
        $this->cashAccount->update(['balance' => 5000]);

        $this->expectException(InsufficientCashException::class);

        // 20 000 so'm naqd qaytarishga urinish (kassada 5 000 so'm bor)
        $this->saleReturnService->createSaleReturn(
            saleId: $sale->id,
            items: [['sale_item_id' => $saleItem->id, 'quantity' => 3]],
            reason: 'Pul yetarli emas',
            refundAmount: 20000,
            cashAccountId: $this->cashAccount->id
        );
    }

    /**
     * Test 7: Ta'minotchiga qaytarish xarid narxi bo'yicha commercial credit beradi va WAC chiqimini hisoblaydi.
     */
    public function test_audit_supplier_return_rejects_duplicate_lines_atomically(): void
    {
        $purchase = $this->purchaseStock($this->variantA->id, 10, 6000);
        $itemId = $purchase->items->first()->id;
        $this->expectException(OperationValidationException::class);
        try {
            $this->supplierReturnService->createSupplierReturn($purchase->id, [['purchase_item_id' => $itemId, 'quantity' => 6], ['purchase_item_id' => $itemId, 'quantity' => 6]], 'duplicate');
        } finally {
            $this->assertEquals(10, InventoryBalance::where('product_variant_id', $this->variantA->id)->value('quantity'));
            $this->assertEquals(60000, $this->supplier->fresh()->balance);
        }
    }

    public function test_audit_damage_replay_rejects_changed_payload(): void
    {
        $this->purchaseStock($this->variantA->id, 10, 6000);
        $id = (string) Str::uuid();
        $rows = [['product_variant_id' => $this->variantA->id, 'quantity' => 2]];
        $this->damageDisposalService->recordDamage($this->warehouse->id, $rows, 'damage', $id);
        $replay = $this->damageDisposalService->recordDamage($this->warehouse->id, $rows, 'damage', $id);
        $this->assertTrue($replay['is_replay']);
        $this->expectException(OperationConflictException::class);
        try {
            $this->damageDisposalService->recordDamage($this->warehouse->id, [['product_variant_id' => $this->variantA->id, 'quantity' => 3]], 'damage', $id);
        } finally {
            $this->assertEquals(8, InventoryBalance::where('product_variant_id', $this->variantA->id)->value('quantity'));
        }
    }

    public function test_supplier_return_reduces_supplier_liability_at_purchase_price_and_outflow_at_wac(): void
    {
        // 1. Kirim: 10 dona x 6 000 so'm = 60 000 so'm qarzimiz
        $purchase = $this->purchaseStock($this->variantA->id, 10, 6000);
        $purchaseItem = $purchase->items->first();

        $this->supplier->refresh();
        $this->assertEquals(60000, (int) $this->supplier->balance);

        // 2. Ta'minotchiga 4 dona tovar qaytarish
        $retRes = $this->supplierReturnService->createSupplierReturn(
            purchaseId: $purchase->id,
            items: [
                [
                    'purchase_item_id' => $purchaseItem->id,
                    'quantity' => 4,
                ],
            ],
            reason: "Sifatsiz partiya ta'minotchiga qaytarildi",
            userId: $this->owner->id
        );

        $this->assertTrue($retRes['success']);
        $this->assertEquals(24000, $retRes['total_credit_amount']); // 4 x 6000

        // Ta'minotchi oldidagi qarzimiz 60 000 - 24 000 = 36 000 bo'lib qoladi
        $this->supplier->refresh();
        $this->assertEquals(36000, (int) $this->supplier->balance);

        // Ombordan 4 dona chiqdi: 10 - 4 = 6 dona qoldi
        $bal = InventoryBalance::where('product_variant_id', $this->variantA->id)->first();
        $this->assertEquals(6, (int) $bal->quantity);
    }

    /**
     * Test 8: Ta'minotchiga xarid qilingandan ko'p qaytarish taqiqlanadi.
     */
    public function test_supplier_return_cannot_exceed_purchased_quantity(): void
    {
        $purchase = $this->purchaseStock($this->variantA->id, 5, 6000);
        $purchaseItem = $purchase->items->first();

        $this->expectException(CannotReturnMoreThanPurchasedException::class);

        // 6 dona qaytarishga urinish
        $this->supplierReturnService->createSupplierReturn(
            purchaseId: $purchase->id,
            items: [['purchase_item_id' => $purchaseItem->id, 'quantity' => 6]],
            reason: "Ko'p qaytarish"
        );
    }

    /**
     * Test 9: Rezervga qarshi supplier return stockni yashirin sarflamaydi (ReservedStockProtectionException).
     */
    public function test_supplier_return_blocked_if_infringing_on_device_reserved_stock(): void
    {
        $purchase = $this->purchaseStock($this->variantA->id, 10, 6000);
        $purchaseItem = $purchase->items->first();

        // Offline qurilma ro'yxatdan o'tkazilib, unga 8 dona rezerv beriladi (Erkin qoldiq = 2 dona)
        $device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'DEV-9901',
            'name' => 'Kassir Plansheti',
            'status' => 'ACTIVE',
            'is_active' => true,
            'current_lease_epoch' => 1,
        ]);

        $this->allocationService->grantAllocation(
            device: $device,
            variantId: $this->variantA->id,
            quantity: 8,
            warehouseId: $this->warehouse->id
        );

        // Ta'minotchiga 4 dona qaytarishga urinish (Erkin qoldiq faqat 2 dona, 8 tasi qurilmada rezerv!)
        $this->expectException(ReservedStockProtectionException::class);

        $this->supplierReturnService->createSupplierReturn(
            purchaseId: $purchase->id,
            items: [['purchase_item_id' => $purchaseItem->id, 'quantity' => 4]],
            reason: 'Rezervni buzishga urinish'
        );
    }

    /**
     * Test 10: Brak tannarx yo'qotishi, kassa xarajati emas.
     * Invariant: Brak faqat omborni kamaytiradi, kassa balansi va harakatlariga daxl qilmaydi!
     */
    public function test_damage_disposal_is_cost_loss_without_any_cash_expense(): void
    {
        // 10 dona x 5 000 so'm kirim
        $this->purchaseStock($this->variantA->id, 10, 5000);
        $this->cashAccount->update(['balance' => 100000]);

        $cashMovementsBefore = CashMovement::count();

        // 3 dona brakka chiqariladi
        $dmgRes = $this->damageDisposalService->recordDamage(
            warehouseId: $this->warehouse->id,
            items: [
                [
                    'product_variant_id' => $this->variantA->id,
                    'quantity' => 3,
                    'reason' => 'Yorilgan idish',
                ],
            ],
            reason: 'Buzilgan partiya',
            userId: $this->owner->id
        );

        $this->assertTrue($dmgRes['success']);
        $this->assertEquals(15000, $dmgRes['total_loss_value']);

        // Ombor qoldig'i 7 dona qoldi
        $bal = InventoryBalance::where('product_variant_id', $this->variantA->id)->first();
        $this->assertEquals(7, (int) $bal->quantity);

        // QAT'IY INVARIANT: Kassa harakati yaratilmaydi va kassa balansi o'zgarmaydi!
        $this->cashAccount->refresh();
        $this->assertEquals(100000, (int) $this->cashAccount->balance, 'Brak kassa pulini kamaytirmaydi!');
        $this->assertEquals($cashMovementsBefore, CashMovement::count(), 'Brak kassa operatsiyasi emas!');
    }

    /**
     * Test 11: Rezervga qarshi brak stockni yashirin sarflamaydi.
     */
    public function test_damage_disposal_blocked_if_infringing_on_device_reserved_stock(): void
    {
        $this->purchaseStock($this->variantA->id, 10, 5000);

        $device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'DEV-9902',
            'name' => 'Kassir Telefoni',
            'status' => 'ACTIVE',
            'is_active' => true,
            'current_lease_epoch' => 1,
        ]);

        $this->allocationService->grantAllocation(
            device: $device,
            variantId: $this->variantA->id,
            quantity: 7,
            warehouseId: $this->warehouse->id
        );

        // Erkin qoldiq faqat 3 dona. 5 dona brak chiqarishga urinish rad etilishi kerak!
        $this->expectException(ReservedStockProtectionException::class);

        $this->damageDisposalService->recordDamage(
            warehouseId: $this->warehouse->id,
            items: [['product_variant_id' => $this->variantA->id, 'quantity' => 5]],
            reason: "Rezervdan ko'p brak"
        );
    }

    /**
     * Test 12: Inventarizatsiya jarayoni: Prepare -> Count -> Apply va farq tuzatishlari.
     */
    public function test_inventory_audit_workflow_prepare_counting_apply_with_adjustments(): void
    {
        $this->purchaseStock($this->variantA->id, 10, 5000);
        $this->purchaseStock($this->variantB->id, 5, 8000);

        // 1. Prepare
        $audit = $this->inventoryAuditService->prepareAudit(
            warehouseId: $this->warehouse->id,
            variantIds: [$this->variantA->id, $this->variantB->id],
            notes: 'Oylik sanash',
            userId: $this->owner->id
        );

        $this->assertEquals('PREPARED', $audit->status);
        $this->assertEquals('NOT_REQUIRED', $audit->device_freeze_status);

        // 2. Sanash: Variant A da 8 dona topildi (-2 kamomad), Variant B da 7 dona topildi (+2 ortiqcha)
        $audit = $this->inventoryAuditService->recordCounts(
            audit: $audit,
            counts: [
                ['product_variant_id' => $this->variantA->id, 'counted_quantity' => 8, 'reason' => 'Kamomad'],
                ['product_variant_id' => $this->variantB->id, 'counted_quantity' => 7, 'reason' => 'Ortiqcha'],
            ]
        );

        $this->assertEquals('COUNTING', $audit->status);
        $this->assertEquals(-2, (int) $audit->items()->where('product_variant_id', $this->variantA->id)->value('discrepancy_quantity'));
        $this->assertEquals(2, (int) $audit->items()->where('product_variant_id', $this->variantB->id)->value('discrepancy_quantity'));

        // 3. Tasdiqlash (Apply)
        $applyRes = $this->inventoryAuditService->applyAudit($audit, $this->owner);
        $this->assertTrue($applyRes['success']);

        // Ombor qoldiqlari muvofiqlashtirildi
        $balA = InventoryBalance::where('product_variant_id', $this->variantA->id)->first();
        $balB = InventoryBalance::where('product_variant_id', $this->variantB->id)->first();

        $this->assertEquals(8, (int) $balA->quantity, 'Variant A sanalgan 8 donaga tenglashtirildi');
        $this->assertEquals(7, (int) $balB->quantity, 'Variant B sanalgan 7 donaga tenglashtirildi');

        // Harakatlar daftari tekshiruvi: ADJUSTMENT_OUT va ADJUSTMENT_IN yozilgan
        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $this->variantA->id,
            'movement_type' => 'ADJUSTMENT_OUT',
            'quantity' => -2,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $this->variantB->id,
            'movement_type' => 'ADJUSTMENT_IN',
            'quantity' => 2,
        ]);
    }

    /**
     * Test 13: Uzilgan qurilma freeze ACK bermagan bo'lsa, yakuniy tasdiq kutadi (DeviceFreezePendingException).
     */
    public function test_inventory_audit_waits_for_device_freeze_ack_when_devices_have_allocations(): void
    {
        $this->purchaseStock($this->variantA->id, 10, 5000);

        $device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'DEV-9903',
            'name' => 'Offline Savdo Qurilmasi',
            'status' => 'ACTIVE',
            'is_active' => true,
            'current_lease_epoch' => 1,
        ]);

        $this->allocationService->grantAllocation(
            device: $device,
            variantId: $this->variantA->id,
            quantity: 5,
            warehouseId: $this->warehouse->id
        );

        // Prepare audit: qurilmada rezerv bo'lgani sababli PENDING_ACK bo'ladi
        $audit = $this->inventoryAuditService->prepareAudit(
            warehouseId: $this->warehouse->id,
            variantIds: [$this->variantA->id],
            userId: $this->owner->id
        );

        $this->assertEquals('PENDING_ACK', $audit->device_freeze_status);

        // Sanash
        $audit = $this->inventoryAuditService->recordCounts($audit, [
            ['product_variant_id' => $this->variantA->id, 'counted_quantity' => 10],
        ]);

        // Qurilma tasdiq bermasdan apply qilishga urinish rad etiladi
        try {
            $this->inventoryAuditService->applyAudit($audit, $this->owner);
            $this->fail('Device freeze kutilayotganda tasdiqlash rad etilishi kerak edi!');
        } catch (DeviceFreezePendingException $e) {
            $this->assertStringContainsString('muzlatish tasdig\'ini', $e->getMessage());
        }

        // Qurilma freeze ACK yuboradi
        $this->inventoryAuditService->acknowledgeDeviceFreeze($device);
        $audit->refresh();
        $this->assertEquals('ACKNOWLEDGED', $audit->device_freeze_status);

        // Endi tasdiqlash muvaffaqiyatli o'tadi
        $applyRes = $this->inventoryAuditService->applyAudit($audit, $this->owner);
        $this->assertTrue($applyRes['success']);
        $this->assertEquals('COMPLETED', $applyRes['audit']->status);
    }

    /**
     * Test 14: Qaytarish amallarida operation_id orqali idempotentlik.
     */
    public function test_duplicate_return_idempotency_with_operation_id(): void
    {
        $this->purchaseStock($this->variantA->id, 10, 5000);
        $sale = $this->makeSale($this->variantA->id, 5, 8000, 40000);
        $saleItem = $sale->items->first();
        $opId = (string) Str::uuid();

        // 1-marta return yuborish
        $res1 = $this->saleReturnService->createSaleReturn(
            saleId: $sale->id,
            items: [['sale_item_id' => $saleItem->id, 'quantity' => 2]],
            reason: 'Birinchi yuborish',
            operationId: $opId,
            refundAmount: 16000,
            cashAccountId: $this->cashAccount->id
        );

        $this->assertFalse($res1['is_replay']);

        // 2-marta ayni operation_id bilan replay
        $res2 = $this->saleReturnService->createSaleReturn(
            saleId: $sale->id,
            items: [['sale_item_id' => $saleItem->id, 'quantity' => 2]],
            reason: 'Birinchi yuborish',
            operationId: $opId,
            refundAmount: 16000,
            cashAccountId: $this->cashAccount->id
        );

        $this->assertTrue($res2['is_replay']);
        $this->assertEquals($res1['return_number'], $res2['return_number']);
        $this->assertEquals(1, SaleReturn::where('operation_id', $opId)->count());
    }

    /**
     * Test 15: Tasdiqlangan return, damage va audit hujjatlari o'zgarmasdir (DocumentImmutableException).
     */
    public function test_posted_returns_damages_and_audits_are_immutable_and_cannot_be_deleted(): void
    {
        $this->purchaseStock($this->variantA->id, 10, 5000);
        $sale = $this->makeSale($this->variantA->id, 2, 8000, 16000);

        $retRes = $this->saleReturnService->createSaleReturn(
            saleId: $sale->id,
            items: [['sale_item_id' => $sale->items->first()->id, 'quantity' => 1]],
            reason: 'Test immutability',
            refundAmount: 8000,
            cashAccountId: $this->cashAccount->id
        );

        $saleReturn = $retRes['return'];

        try {
            $saleReturn->delete();
            $this->fail("Tasdiqlangan SaleReturn o'chirilishi taqiqlanishi kerak edi!");
        } catch (DocumentImmutableException $e) {
            $this->assertStringContainsString('Tarixiy hujjatlar o\'zgarmasdir', $e->getMessage());
        }

        // 2. Damage record o'chirish taqiqlangan
        $dmgRes = $this->damageDisposalService->recordDamage(
            warehouseId: $this->warehouse->id,
            items: [['product_variant_id' => $this->variantA->id, 'quantity' => 1]],
            reason: 'Test damage immutable'
        );

        $damageRecord = $dmgRes['damage_record'];

        try {
            $damageRecord->delete();
            $this->fail("Tasdiqlangan DamageRecord o'chirilishi taqiqlanishi kerak edi!");
        } catch (DocumentImmutableException $e) {
            $this->assertStringContainsString('Tarixiy hujjatlar o\'zgarmasdir', $e->getMessage());
        }
    }

    /**
     * Test 16: Livewire StockManager interfeysi yangi tablar va amallar bilan ishlaydi.
     */
    public function test_livewire_stock_manager_returns_damages_and_audit_ui(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(StockManager::class)
            ->set('activeTab', 'returns')
            ->assertSee('Mijoz Qaytarishlari (Sale Returns)')
            ->assertSee('Supplier Returns')
            ->set('activeTab', 'damages')
            ->assertSee('Brak & Yaroqsiz Tovar Chiqimlari Jurnali', false)
            ->assertSee('Brakka Chiqarish')
            ->set('activeTab', 'audits')
            ->assertSee('Inventarizatsiya & Sanash Tarixi', false)
            ->assertSee('Yangi Sanash Boshlash')
            ->call('prepareNewAudit')
            ->assertSee('PREPARED');
    }
}

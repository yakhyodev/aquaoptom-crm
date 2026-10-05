<?php

namespace Tests\Feature;

use App\Exceptions\CannotDeleteReferencedRecordException;
use App\Livewire\Catalog\ProductManager;
use App\Livewire\Inventory\QuickInward;
use App\Livewire\Modals\InlineCustomerModal;
use App\Livewire\Modals\InlineProductModal;
use App\Livewire\Parties\CustomerManager;
use App\Livewire\Sales\OptomPos;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\InventoryMovement;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\ProductNormalizer;
use App\Services\Catalog\VolumeNormalizer;
use App\Services\Parties\CustomerService;
use App\Services\Parties\SupplierService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogAndPartiesManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $seller;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->warehouse = Warehouse::create([
            'name' => 'Asosiy Ombor',
            'is_default' => true,
        ]);

        $this->owner = User::factory()->create([
            'role' => 'OWNER',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->seller = User::factory()->create([
            'role' => 'SALES_MANAGER',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);
        $this->seller->givePermission('custom_sale_price');
    }

    /**
     * 1. Mahsulotga nom yetadi: kod va ID server tomonidan concurrency-safe usulda beriladi
     */
    public function test_product_creation_requires_only_name_and_generates_code(): void
    {
        $product = ProductNormalizer::findOrCreate('Fanta Apelsin');

        $this->assertNotNull($product->id);
        $this->assertEquals('Fanta Apelsin', $product->name);
        $this->assertEquals('fanta apelsin', $product->normalized_name);
        $this->assertStringStartsWith('PRD-', $product->code);
    }

    /**
     * 2. Nom normalizatsiyasi: Fanta, fanta, FANTA va bo'shliqlar bitta mahsulotga birlashadi
     */
    public function test_product_name_normalization_and_duplicate_prevention(): void
    {
        $p1 = ProductNormalizer::findOrCreate('  Fanta  ');
        $p2 = ProductNormalizer::findOrCreate('fanta');
        $p3 = ProductNormalizer::findOrCreate('FANTA');
        $p4 = ProductNormalizer::findOrCreate("Fanta O'rik");
        $p5 = ProductNormalizer::findOrCreate('Fanta O‘rik'); // turli tutuq belgilari

        $this->assertEquals($p1->id, $p2->id);
        $this->assertEquals($p1->id, $p3->id);
        $this->assertEquals($p4->id, $p5->id);
        $this->assertEquals(2, Product::count());
    }

    /**
     * 3. Hajm normalizatsiyasi: 0.5 L, 0,5 L, 500 ml, 500 formatlari 500 ml ga o'giriladi
     */
    public function test_volume_normalization_cases(): void
    {
        $v1 = VolumeNormalizer::normalize('0.5 L');
        $this->assertEquals(500, $v1['value_ml']);
        $this->assertEquals('0.5 L', $v1['name']);

        $v2 = VolumeNormalizer::normalize('0,5 L');
        $this->assertEquals(500, $v2['value_ml']);

        $v3 = VolumeNormalizer::normalize('500 ml');
        $this->assertEquals(500, $v3['value_ml']);

        $v4 = VolumeNormalizer::normalize('500ml');
        $this->assertEquals(500, $v4['value_ml']);

        $v5 = VolumeNormalizer::normalize(500);
        $this->assertEquals(500, $v5['value_ml']);

        $v6 = VolumeNormalizer::normalize('1.5 L');
        $this->assertEquals(1500, $v6['value_ml']);
        $this->assertEquals('1.5 L', $v6['name']);

        $v7 = VolumeNormalizer::normalize('1 L');
        $this->assertEquals(1000, $v7['value_ml']);
        $this->assertEquals('1 L', $v7['name']);

        $v8 = VolumeNormalizer::normalize('18.9 L');
        $this->assertEquals(18900, $v8['value_ml']);
        $this->assertEquals('18.9 L', $v8['name']);
    }

    /**
     * 4. Hajm takrorlanishining oldini olish (Unique value_ml)
     */
    public function test_volume_duplicate_protection(): void
    {
        $vol1 = VolumeNormalizer::findOrCreate('0.5 L');
        $vol2 = VolumeNormalizer::findOrCreate('500 ml');
        $vol3 = VolumeNormalizer::findOrCreate('0,5');

        $this->assertEquals($vol1->id, $vol2->id);
        $this->assertEquals($vol1->id, $vol3->id);
        $this->assertEquals(1, Volume::count());
    }

    /**
     * 5. Mahsulot varianti: product + volume unique, avtomatik SKU va boshlang'ich narx
     */
    public function test_variant_creation_with_sku_and_optional_price(): void
    {
        $service = new CatalogService;

        // Narxsiz variant
        $variant1 = $service->createVariant('Coca-Cola', '0.5 L', null, 60, null, $this->owner->id);
        $this->assertNotNull($variant1->id);
        $this->assertEquals('COCACOLA-500', $variant1->sku);
        $this->assertNull($variant1->default_sale_price);
        $this->assertFalse($variant1->isSystemPriceSet());
        $this->assertEquals(60, $variant1->minimum_stock);

        // Narxli variant
        $variant2 = $service->createVariant('Coca-Cola', '1.5 L', 12000, 40, null, $this->owner->id);
        $this->assertEquals('COCACOLA-1500', $variant2->sku);
        $this->assertEquals(12000, $variant2->default_sale_price);
        $this->assertTrue($variant2->isSystemPriceSet());

        // Price history tekshiruvi
        $history = PriceHistory::where('product_variant_id', $variant2->id)->first();
        $this->assertNotNull($history);
        $this->assertEquals(12000, $history->new_price);
        $this->assertEquals(1, $history->version);
    }

    /**
     * 6. Narx o'zgarishi: versiyaning oshishi, price_history va audit log yozilishi
     */
    public function test_price_update_increments_version_and_records_history(): void
    {
        $service = new CatalogService;
        $variant = $service->createVariant('Fanta', '1 L', 8000, 50, null, $this->owner->id);

        $this->assertEquals(1, $variant->version);
        $this->assertEquals(8000, $variant->default_sale_price);

        // Narxni 9500 so'mga ko'taramiz
        $updated = $service->updatePrice($variant, 9500, 'Zavod narxi oshdi', $this->owner->id);

        $this->assertEquals(9500, $updated->default_sale_price);
        $this->assertEquals(2, $updated->version);

        $histories = PriceHistory::where('product_variant_id', $variant->id)->orderBy('id')->get();
        $this->assertCount(2, $histories);
        $this->assertEquals(8000, $histories[1]->old_price);
        $this->assertEquals(9500, $histories[1]->new_price);
        $this->assertEquals(2, $histories[1]->version);
        $this->assertEquals('Zavod narxi oshdi', $histories[1]->reason);

        // Audit log tekshiruvi
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PRICE_CHANGE',
            'auditable_type' => ProductVariant::class,
            'auditable_id' => $variant->id,
        ]);
    }

    /**
     * 7. Variantni arxivlash va qayta faollashtirish
     */
    public function test_variant_archive_and_activate(): void
    {
        $service = new CatalogService;
        $variant = $service->createVariant('Nestle', '5 L', 15000);

        $this->assertEquals('active', $variant->status);

        $service->archiveVariant($variant);
        $this->assertEquals('archived', $variant->fresh()->status);

        $service->activateVariant($variant);
        $this->assertEquals('active', $variant->fresh()->status);
    }

    /**
     * 8. Tarixda ishlatilgan variantni o'chirib bo'lmaydi (Immutability)
     */
    public function test_cannot_delete_variant_with_history(): void
    {
        $service = new CatalogService;
        $variant = $service->createVariant('Chortoq', '0.5 L', 6000);

        // Ombor harakati qo'shamiz
        InventoryMovement::create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse->id,
            'movement_type' => 'PURCHASE',
            'quantity' => 100,
            'unit_cost' => 4500,
            'total_cost' => 450000,
            'reference_type' => 'Purchase',
            'reference_id' => 1,
            'created_by' => $this->owner->id,
        ]);

        $this->assertTrue($variant->hasHistoricalRecords());

        $this->expectException(CannotDeleteReferencedRecordException::class);
        $service->deleteVariant($variant);
    }

    /**
     * 9. Tarixda ishlatilmagan variant xavfsiz soft-delete bo'ladi
     */
    public function test_can_delete_unreferenced_variant(): void
    {
        $service = new CatalogService;
        $variant = $service->createVariant('Sinov Suvi', '1 L', 5000);

        $this->assertFalse($variant->hasHistoricalRecords());
        $deleted = $service->deleteVariant($variant);

        $this->assertTrue($deleted);
        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
    }

    /**
     * 10. Mijoz biznes qoidalari:
     * - Ism majburiy
     * - Telefon yo'q bo'lsa, do'kon nomi yoki manzil kiritilishi shart
     */
    public function test_customer_creation_validation_rules(): void
    {
        $service = new CustomerService;

        // Ism bo'sh
        $this->expectException(ValidationException::class);
        $service->createCustomer(['name' => '']);
    }

    public function test_customer_requires_store_or_address_when_phone_is_empty(): void
    {
        $service = new CustomerService;

        // Telefon yo'q, do'kon ham manzil ham yo'q -> Xatolik!
        $this->expectException(ValidationException::class);
        $service->createCustomer([
            'name' => 'Rustam',
            'phone' => null,
            'store_name' => null,
            'address' => null,
        ]);
    }

    public function test_customer_allowed_without_phone_if_store_or_address_provided(): void
    {
        $service = new CustomerService;

        $c1 = $service->createCustomer([
            'name' => 'Rustam',
            'phone' => null,
            'store_name' => 'Katta Do\'kon',
            'address' => null,
        ]);

        $this->assertNotNull($c1->id);
        $this->assertEquals("Rustam — Katta Do'kon — telefon yo'q", $c1->display_name);

        $c2 = $service->createCustomer([
            'name' => 'Sherzod',
            'phone' => '+998901234567',
            'store_name' => 'Bahor',
            'address' => 'Chilonzor',
        ]);

        $this->assertEquals('Sherzod — Bahor — tel: +998901234567 — Chilonzor', $c2->display_name);
    }

    /**
     * 11. Telefon takrorlanganda avtomatik merge qilinmaydi va alohida saqlanadi
     */
    public function test_no_automatic_customer_merge_on_duplicate_phone(): void
    {
        $service = new CustomerService;

        $c1 = $service->createCustomer([
            'name' => 'Akbar Aka',
            'phone' => '+998935555555',
            'store_name' => '1-Do\'kon',
        ]);

        $warning = $service->checkPhoneDuplicate('+998935555555');
        $this->assertNotNull($warning);
        $this->assertStringContainsString('allaqachon 1 ta mijoz mavjud', $warning);

        $c2 = $service->createCustomer([
            'name' => 'Akbar Aka (Filial)',
            'phone' => '+998935555555',
            'store_name' => '2-Do\'kon',
        ]);

        $this->assertNotEquals($c1->id, $c2->id);
        $this->assertEquals(2, Customer::count());
    }

    /**
     * 12. Offline barqaror UUID retry (Idempotent retry)
     */
    public function test_customer_offline_uuid_idempotent_retry(): void
    {
        $service = new CustomerService;
        $offlineUuid = (string) Str::uuid();

        // 1-marta yaratish
        $c1 = $service->createCustomer([
            'uuid' => $offlineUuid,
            'name' => 'Offline Xaridor',
            'phone' => '+998909998877',
        ]);

        // 2-marta tarmoq qayta yuborganda aynan shu mijoz qaytadi, yangi dublikat yaratilmaydi
        $c2 = $service->createCustomer([
            'uuid' => $offlineUuid,
            'name' => 'Offline Xaridor Qayta',
            'phone' => '+998909998877',
        ]);

        $this->assertEquals($c1->id, $c2->id);
        $this->assertEquals(1, Customer::where('uuid', $offlineUuid)->count());
    }

    /**
     * 13. Tarixda ishlatilgan mijozni o'chirib bo'lmaydi
     */
    public function test_cannot_delete_customer_with_history(): void
    {
        $service = new CustomerService;
        $customer = $service->createCustomer([
            'name' => 'Eldor',
            'phone' => '+998901112233',
        ]);

        // Qarz daftari yozuvi
        CustomerLedger::create([
            'customer_id' => $customer->id,
            'type' => 'ADJUSTMENT',
            'debit' => 500000,
            'credit' => 0,
            'balance_after' => 500000,
            'notes' => 'Boshlang\'ich qoldiq',
            'created_by' => $this->owner->id,
        ]);

        $this->assertTrue($customer->hasHistoricalRecords());

        $this->expectException(CannotDeleteReferencedRecordException::class);
        $service->deleteCustomer($customer);
    }

    /**
     * 14. Ta'minotchi yaratish, qidirish va o'chirish himoyasi
     */
    public function test_supplier_service_workflow_and_protection(): void
    {
        $service = new SupplierService;

        $supplier = $service->createSupplier([
            'name' => 'Alisher',
            'company_name' => 'Hydrolife Tashkent',
            'phone' => '+998712001122',
            'address' => 'Bektemir',
        ]);

        $this->assertNotNull($supplier->id);
        $this->assertEquals('Alisher — Hydrolife Tashkent — tel: +998712001122', $supplier->display_name);

        // Qidiruv
        $results = $service->search('Hydrolife');
        $this->assertCount(1, $results);

        // Kirim hujjati bilan bog'langanda o'chirish taqiqlanadi
        Purchase::create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'total_amount' => 5000000,
            'status' => 'POSTED',
            'created_by' => $this->owner->id,
        ]);

        $this->assertTrue($supplier->hasHistoricalRecords());

        $this->expectException(CannotDeleteReferencedRecordException::class);
        $service->deleteSupplier($supplier);
    }

    /**
     * 15. Livewire ProductManager component testi
     */
    public function test_product_manager_livewire_component(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(ProductManager::class)
            ->call('openCreateModal')
            ->set('newProductName', 'Dinay Sharbat')
            ->set('newVolumeInput', '1 L')
            ->set('newDefaultPrice', 11000)
            ->call('saveProduct')
            ->assertHasNoErrors()
            ->assertSee('Dinay Sharbat')
            ->assertSee('1 L')
            ->assertSee('11 000');

        $this->assertDatabaseHas('products', ['normalized_name' => 'dinay sharbat']);
        $this->assertDatabaseHas('product_variants', ['default_sale_price' => 11000]);
    }

    /**
     * 16. Livewire CustomerManager component testi
     */
    public function test_customer_manager_livewire_component(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(CustomerManager::class)
            ->call('openCreateModal')
            ->set('name', 'Nodir Aka')
            ->set('phone', '+998901239999')
            ->set('storeName', 'Samarqand Darvoza')
            ->call('saveCustomer')
            ->assertHasNoErrors()
            ->assertSee('Nodir Aka')
            ->assertSee('Samarqand Darvoza');

        $this->assertDatabaseHas('customers', [
            'name' => 'Nodir Aka',
            'phone' => '+998901239999',
        ]);
    }

    /**
     * 17. Kirim / Sotuvda inline modal orqali yangi tovar va mijoz qo'shilganda qoralama saqlanadi
     */
    public function test_inline_modals_preserve_pos_draft_state(): void
    {
        $this->actingAs($this->seller);

        // 1. Dastlabki mavjud tovar yaratamiz
        $catService = new CatalogService;
        $v1 = $catService->createVariant('Mavjud Suv', '0.5 L', 4000);

        // 2. POS ekranida savatga 1 ta mahsulot qo'shamiz (draft)
        $posTest = Livewire::test(OptomPos::class)
            ->call('onProductCreated', [
                'variant_id' => $v1->id,
                'sku' => $v1->sku,
                'display_name' => 'Mavjud Suv 0.5 L',
                'sale_price' => 4000,
            ])
            ->assertSee('Mavjud Suv 0.5 L')
            ->assertSee('4 000');

        // Savatda 1 ta element borligini tekshiramiz
        $this->assertCount(1, $posTest->get('items'));

        // 3. Inline Mahsulot Modali orqali yangi tovar kiritamiz
        Livewire::test(InlineProductModal::class)
            ->call('open')
            ->set('productName', 'Yangi Ichimlik Flash')
            ->set('volumeInput', '0.5 L')
            ->set('defaultPrice', 7500)
            ->call('save')
            ->assertDispatched('product-created');

        // Yangi mahsulot id sini olamiz
        $newVariant = ProductVariant::whereHas('product', fn ($q) => $q->where('name', 'Yangi Ichimlik Flash'))->first();
        $this->assertNotNull($newVariant);

        // 4. POS ekrani 'product-created' hodisasini qabul qiladi
        $posTest->dispatch('product-created', [
            'variant_id' => $newVariant->id,
            'sku' => $newVariant->sku,
            'display_name' => 'Yangi Ichimlik Flash 0.5 L',
            'sale_price' => 7500,
        ]);

        // MAJBURIY QABUL MEZONI:
        // Ota komponentdagi eski savat qoralamasi (Mavjud Suv 0.5 L) saqlanib qolgan va yangi tovar qo'shilgan!
        $items = $posTest->get('items');
        $this->assertCount(2, $items);
        $this->assertEquals('Mavjud Suv 0.5 L', $items[0]['display_name']);
        $this->assertEquals('Yangi Ichimlik Flash 0.5 L', $items[1]['display_name']);
        $this->assertEquals(11500, $posTest->instance()->getTotalAmount());

        // 5. Inline Mijoz Modali orqali yangi mijoz kiritamiz
        Livewire::test(InlineCustomerModal::class)
            ->call('open')
            ->set('name', 'Zafar Aka')
            ->set('phone', '+998907770011')
            ->set('storeName', 'Zafar Do\'koni')
            ->call('save')
            ->assertDispatched('customer-created');

        $newCustomer = Customer::where('phone', '+998907770011')->first();
        $this->assertNotNull($newCustomer);

        // 6. POS ekrani 'customer-created' hodisasini oladi
        $posTest->dispatch('customer-created', [
            'customer_id' => $newCustomer->id,
            'name' => $newCustomer->name,
            'display_name' => $newCustomer->display_name,
        ]);

        // MAJBURIY QABUL MEZONI:
        // Mijoz tanlandi, lekin savatdagi barcha 2 ta tovar qoralamasi 100% o'zgarmas saqlandi!
        $this->assertEquals($newCustomer->id, $posTest->get('selectedCustomerId'));
        $this->assertCount(2, $posTest->get('items'));
        $this->assertEquals(11500, $posTest->instance()->getTotalAmount());
    }

    public function test_inline_product_lists_saved_custom_volumes_and_reuses_them(): void
    {
        $this->actingAs($this->owner);
        $volume = VolumeNormalizer::findOrCreate('0.75 L');
        $archived = VolumeNormalizer::findOrCreate('3 L');
        $archived->update(['status' => 'archived']);

        Livewire::test(InlineProductModal::class)
            ->call('open')
            ->assertSee('0.75 L (750 ml)')
            ->assertDontSee('3 L (3000 ml)')
            ->set('productName', 'Fanta')
            ->set('volumeInput', 'custom')
            ->set('customVolumeInput', '750 ml')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('isOpen', false)
            ->assertDispatched('product-created');

        $this->assertDatabaseHas('product_variants', ['volume_id' => $volume->id]);
        $this->assertEquals(1, Volume::where('value_ml', 750)->count());
    }

    public function test_pos_can_search_an_older_customer_and_preserve_cart_when_selecting(): void
    {
        $this->actingAs($this->owner);
        $service = new CustomerService;
        $older = $service->createCustomer([
            'name' => 'Old Customer', 'phone' => '+998901234567',
            'store_name' => 'Older Market', 'address' => 'Old Street',
        ]);
        $older->update(['created_at' => now()->subYear()]);
        for ($i = 0; $i < 7; $i++) {
            $service->createCustomer(['name' => 'Recent '.$i, 'store_name' => 'Recent Market']);
        }
        $archived = $service->createCustomer(['name' => 'Old Archived', 'store_name' => 'Older Market']);
        $archived->update(['status' => 'archived']);
        $variant = (new CatalogService)->createVariant('Fanta', '0.5 L', 7000);
        $pos = Livewire::test(OptomPos::class)
            ->call('onProductCreated', [
                'variant_id' => $variant->id, 'display_name' => 'Fanta 0.5 L', 'sale_price' => 7000,
            ]);

        foreach (['Old Customer', '1234567', 'Older Market', 'Old Street'] as $term) {
            $pos->set('customerSearch', $term)
                ->assertViewHas('customerResults', fn ($results) => $results->contains('id', $older->id)
                    && ! $results->contains('id', $archived->id));
        }
        $draft = $pos->get('items');
        $pos->call('selectExistingCustomer', $older->id)
            ->assertSet('selectedCustomerId', $older->id)
            ->assertSet('selectedCustomerName', $older->display_name)
            ->assertSet('items', $draft);
        $pos->call('selectExistingCustomer', $archived->id)
            ->assertSet('selectedCustomerId', $older->id);
    }

    public function test_inline_customer_save_retry_does_not_create_a_duplicate(): void
    {
        $this->actingAs($this->owner);
        $modal = Livewire::test(InlineCustomerModal::class)
            ->call('open')
            ->set('name', 'Repeat Customer')
            ->set('phone', '+998900001122')
            ->set('storeName', 'Repeat Market')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('customer-created');
        $uuid = $modal->get('customerUuid');
        $modal->call('save')->assertHasNoErrors();
        $this->assertEquals(1, Customer::where('uuid', $uuid)->count());
        $this->assertEquals(1, Customer::where('phone', '+998900001122')->count());
    }

    public function test_receiving_and_sales_pages_render_the_actual_workflow_forms(): void
    {
        $this->actingAs($this->owner)->get('/inward')
            ->assertOk()
            ->assertSee('Qaysi mahsulot keldi?')
            ->assertSee('Yangi Mahsulot')
            ->assertSee('Kimdan olindi?');

        $this->get('/sotuv')
            ->assertOk()
            ->assertSee('Mavjud mijozni qidirish')
            ->assertSee('Yangi Mijoz')
            ->assertSee('Nima sotiladi?');
    }

    public function test_simple_product_picker_finds_any_product_and_keeps_receiving_quantity_explicit(): void
    {
        $this->actingAs($this->owner);
        $catalog = new CatalogService;
        $first = $catalog->createVariant('First Product', '0.5 L', 7000);
        $second = $catalog->createVariant('First Product', '1 L', 10000);
        for ($i = 0; $i < 9; $i++) {
            $catalog->createVariant('Later '.$i, '0.5 L', 8000);
        }
        $pos = Livewire::test(OptomPos::class)
            ->set('selectedProductId', $first->product_id)->set('selectedVolumeId', $first->volume_id)
            ->call('addSelectedVariant')->assertSet('items.0.variant_id', $first->id)
            ->call('updateQuantity', 0, 10)->assertSet('paidAmount', 70000)
            ->call('updatePrice', 0, 6500)->assertSet('paidAmount', 65000)->assertSet('items.0.is_system_price', false)
            ->set('paymentType', 'PARTIAL')->assertSet('paidAmount', 0);
        $pos->call('updateQuantity', 0, '1.5')->assertSet('items.0.quantity', 10);
        $inward = Livewire::test(QuickInward::class)
            ->set('selectedProductId', $first->product_id)->set('selectedVolumeId', $first->volume_id)
            ->call('addSelectedVariant')->assertSet('items.0.quantity', 1)->assertSet('items.0.unit_cost', 0)
            ->call('updateQuantity', 0, 150)->call('updateUnitCost', 0, 5000)
            ->call('addSelectedVariant')->assertSet('items.0.quantity', 150);
        $this->assertCount(1, $inward->get('items'));
        $inward->dispatch('product-created', ['variant_id' => $second->id]);
        $this->assertCount(2, $inward->get('items'));
        $this->assertEquals($second->id, $inward->get('items')[1]['variant_id']);
        $inward->call('updateQuantity', 0, '1.5')->assertSet('items.0.quantity', 150);
    }

    public function test_receiving_cannot_save_an_untouched_blank_price(): void
    {
        $this->actingAs($this->owner);
        $supplier = (new SupplierService)->createSupplier(['name' => 'Test Supplier']);
        $variant = (new CatalogService)->createVariant('Test Product', '0.5 L');
        Livewire::test(QuickInward::class)
            ->call('selectSupplier', $supplier->id)
            ->dispatch('product-created', ['variant_id' => $variant->id])
            ->call('postPurchase')->assertSet('errorMessage', 'Har bir mahsulotning kirim narxini yozing.');
        $this->assertEquals(0, Purchase::count());
    }
}

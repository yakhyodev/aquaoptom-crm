<?php

namespace Tests\Feature;

use App\Livewire\Inventory\StockOpening;
use App\Models\CashAccount;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Catalog\CatalogService;
use App\Services\Opening\OpeningBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StockOpeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        Warehouse::create(['name' => 'Asosiy ombor', 'is_default' => true]);
    }

    public function test_new_product_and_custom_litres_are_saved_once_without_purchase_cash_or_debt(): void
    {
        $cash = CashAccount::create(['name' => 'Kassa', 'type' => 'CASH', 'balance' => 10000, 'is_default' => true]);
        $supplier = Supplier::create(['name' => 'Mavjud yetkazuvchi', 'balance' => 70000]);

        $component = Livewire::actingAs($this->owner)->test(StockOpening::class)
            ->set('productSelection', 'new')->set('newProductName', 'Limonad')
            ->set('volumeSelection', 'new')->set('customLitres', '0,75')
            ->set('quantity', '150')->set('unitCost', '5000')->set('salePrice', '7000')
            ->call('save')->assertHasNoErrors()->assertSet('savedOpening.quantity', 150)
            ->assertSee('Keyingi mahsulotni qo‘shish');
        $component->call('save');

        $variant = ProductVariant::whereHas('product', fn ($query) => $query->where('normalized_name', 'limonad'))->firstOrFail();
        $this->assertSame(750, (int) $variant->volume->value_ml);
        $this->assertSame(7000, $variant->default_sale_price);
        $this->assertSame(150, (int) $variant->balance->quantity);
        $this->assertSame(750000, (int) $variant->balance->total_value);
        $this->assertDatabaseCount('opening_balance_documents', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_movements', ['movement_type' => 'OPENING_BALANCE']);
        foreach (['purchases', 'cash_movements', 'customer_ledger', 'supplier_ledger'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(10000, (int) $cash->fresh()->balance);
        $this->assertSame(70000, (int) $supplier->fresh()->balance);

        $oldOperationId = $component->get('operationId');
        $component->call('startNextProduct')->assertSet('savedOpening', null)->assertSet('quantity', '');
        $this->assertNotSame($oldOperationId, $component->get('operationId'));
    }

    public function test_existing_stock_and_sale_price_are_preserved_when_unrecorded_units_are_added(): void
    {
        $variant = app(CatalogService::class)->createVariant('Fanta', '0.5 L', 7000);
        app(OpeningBalanceService::class)->recordStockOpening($variant->id, 100, 5000, (string) Str::uuid(), userId: $this->owner->id);

        Livewire::actingAs($this->owner)->test(StockOpening::class)
            ->set('productSelection', (string) $variant->product_id)->set('volumeSelection', (string) $variant->volume_id)
            ->assertSee('Faqat hali kiritilmagan donalarni qo‘shing')
            ->set('quantity', '20')->set('unitCost', '6000')->set('salePrice', '8000')
            ->call('save')->assertHasNoErrors()->assertSet('savedOpening.quantity', 120);

        $this->assertSame(120, (int) $variant->balance->quantity);
        $this->assertSame(620000, (int) $variant->balance->total_value);
        $this->assertSame(7000, $variant->fresh()->default_sale_price);
        $this->assertDatabaseCount('product_variants', 1);
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_do_not_create_a_product_or_stock(string $field, string $value): void
    {
        Livewire::actingAs($this->owner)->test(StockOpening::class)
            ->set('productSelection', 'new')->set('newProductName', 'Invalid drink')
            ->set('volumeSelection', 'new')->set('customLitres', '0.75')
            ->set('quantity', '150')->set('unitCost', '5000')->set($field, $value)
            ->call('save')->assertHasErrors([$field]);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public static function invalidValues(): array
    {
        return [
            'fractional quantity' => ['quantity', '1.5'],
            'negative quantity' => ['quantity', '-2'],
            'missing cost' => ['unitCost', ''],
            'negative cost' => ['unitCost', '-1'],
            'zero litres' => ['customLitres', '0'],
            'invalid litres' => ['customLitres', 'abc'],
        ];
    }

    public function test_failed_stock_write_rolls_back_new_catalog_records(): void
    {
        $this->mock(OpeningBalanceService::class, function ($mock) {
            $mock->shouldReceive('recordStockOpening')->once()->andThrow(new \RuntimeException('Stock write failed'));
        });
        Livewire::actingAs($this->owner)->test(StockOpening::class)
            ->set('productSelection', 'new')->set('newProductName', 'Rollback drink')
            ->set('volumeSelection', 'new')->set('customLitres', '0.75')
            ->set('quantity', '150')->set('unitCost', '5000')->call('save')
            ->assertSet('savedOpening', null)->assertSee('Qoldiq saqlanmadi');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('volumes', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_cashier_cannot_enter_opening_stock(): void
    {
        Livewire::actingAs(User::factory()->cashier()->create())->test(StockOpening::class)->assertForbidden();
    }

    public function test_draft_survives_reload_and_missing_ack_recovers_the_saved_result(): void
    {
        $component = Livewire::actingAs($this->owner)->test(StockOpening::class)
            ->set('productSelection', 'new')->set('newProductName', 'Qoralama suv')
            ->set('volumeSelection', 'new')->set('customLitres', '0.75')
            ->set('quantity', '200')->set('unitCost', '4000');
        $operationId = $component->get('operationId');
        $draft = session()->get('stock-opening.'.$this->owner->id);

        $reloaded = Livewire::test(StockOpening::class)->assertSet('operationId', $operationId)
            ->assertSet('quantity', '200')->assertSet('newProductName', 'Qoralama suv');
        $reloaded->call('save')->assertHasNoErrors()->assertSet('savedOpening.quantity', 200);
        session()->put('stock-opening.'.$this->owner->id, $draft);

        Livewire::test(StockOpening::class)->assertSet('operationId', $operationId)
            ->assertSet('savedOpening.quantity', 200)->call('save');
        $this->assertDatabaseCount('opening_balance_documents', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_unknown_cost_cannot_be_disguised_as_free_stock(): void
    {
        $component = Livewire::actingAs($this->owner)->test(StockOpening::class)
            ->set('productSelection', 'new')->set('newProductName', 'Bepul suv')
            ->set('volumeSelection', 'new')->set('customLitres', '1')
            ->set('quantity', '10')->set('unitCost', '0')->call('save')->assertHasErrors('unitCost');
        $this->assertDatabaseCount('products', 0);
        $component->set('costMode', 'free')->call('save')->assertHasErrors('confirmFreeStock');
        $this->assertDatabaseCount('products', 0);
        $component->set('confirmFreeStock', true)->call('save')->assertHasNoErrors()
            ->assertSet('savedOpening.total_cost', 0)->assertSet('savedOpening.quantity', 10);
    }

    public function test_warehouse_provides_owner_with_a_direct_opening_stock_entry(): void
    {
        $this->actingAs($this->owner)->get('/ombor?tab=opening')->assertOk()
            ->assertSee('Boshlang‘ich qoldiqni saqlash')->assertSee('Yangi mahsulot nomini yozish');

        $this->actingAs(User::factory()->cashier()->create())->get('/ombor?tab=opening')->assertOk()
            ->assertDontSee('Boshlang‘ich qoldiqni saqlash');
    }
}

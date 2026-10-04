<?php

namespace App\Livewire\Inventory;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Volume;
use App\Services\Inventory\InventoryCalculatorService;
use App\Services\Inventory\InventoryStockService;
use App\Services\Operations\Exceptions\OperationPermissionException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class StockManager extends Component
{
    use WithPagination;

    // Asosiy faol tab
    #[Url(as: 'tab')]
    public string $activeTab = 'balances'; // 'balances', 'calculator', 'inward'

    // 1. Qoldiqlar filtrlari
    #[Url(as: 'q')]
    public string $search = '';

    public array $selectedVolumeIds = [];

    public ?int $minStock = null;

    public ?int $maxStock = null;

    public string $thresholdFilter = 'all'; // 'all', 'below_threshold', 'normal'

    public string $zeroStockFilter = 'all'; // 'all', 'zero_only', 'non_zero'

    public string $priceFilter = 'all'; // 'all', 'has_price', 'no_price'

    public string $statusFilter = 'active'; // 'active', 'archived', 'all'

    public bool $slowMovingFilter = false;

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public int $perPage = 20;

    // 2. Variant tafsiloti va harakatlar modal
    public bool $showDetailModal = false;

    public ?int $selectedVariantId = null;

    public ?array $variantDetail = null;

    public int $movementsPage = 1;

    // 3. Interaktiv Kalkulyator
    public array $calcSelectedProductIds = [];

    public array $calcSelectedVolumeIds = [];

    public array $simulationPrices = [];

    public ?string $calculatorMessage = null;

    public ?string $calculatorError = null;

    protected InventoryStockService $stockService;

    protected InventoryCalculatorService $calculatorService;

    public function boot(
        InventoryStockService $stockService,
        InventoryCalculatorService $calculatorService
    ): void {
        $this->stockService = $stockService;
        $this->calculatorService = $calculatorService;
    }

    public function mount(): void
    {
        // Standart kalkulyator: barcha hajmlarni tanlangan qilib boshlash
        $this->calcSelectedVolumeIds = Volume::pluck('id')->map(fn ($id) => (int) $id)->toArray();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedVolumeIds(): void
    {
        $this->resetPage();
    }

    public function updatedThresholdFilter(): void
    {
        $this->resetPage();
    }

    public function updatedZeroStockFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPriceFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSlowMovingFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->selectedVolumeIds = [];
        $this->minStock = null;
        $this->maxStock = null;
        $this->thresholdFilter = 'all';
        $this->zeroStockFilter = 'all';
        $this->priceFilter = 'all';
        $this->statusFilter = 'active';
        $this->slowMovingFilter = false;
        $this->sortBy = 'name';
        $this->sortDirection = 'asc';
        $this->resetPage();
    }

    public function setSort(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    // Modal amallari
    public function openVariantDetail(int $variantId): void
    {
        $this->selectedVariantId = $variantId;
        $this->movementsPage = 1;
        $this->loadVariantDetail();
        $this->showDetailModal = true;
    }

    public function closeVariantDetail(): void
    {
        $this->showDetailModal = false;
        $this->selectedVariantId = null;
        $this->variantDetail = null;
    }

    public function loadVariantDetail(): void
    {
        if (! $this->selectedVariantId) {
            return;
        }

        $canViewCost = $this->canViewCost;
        $this->variantDetail = $this->stockService->getVariantDetail($this->selectedVariantId, null, $canViewCost);
    }

    public function setMovementsPage(int $page): void
    {
        $this->movementsPage = max(1, $page);
    }

    // Kalkulyator amallari
    public function selectAllCalculator(): void
    {
        $this->calcSelectedProductIds = Product::pluck('id')->map(fn ($id) => (int) $id)->toArray();
        $this->calcSelectedVolumeIds = Volume::pluck('id')->map(fn ($id) => (int) $id)->toArray();
    }

    public function clearCalculator(): void
    {
        $this->calcSelectedProductIds = [];
        $this->calcSelectedVolumeIds = [];
        $this->simulationPrices = [];
        $this->calculatorMessage = null;
        $this->calculatorError = null;
    }

    public function selectAllVolumes(): void
    {
        $this->calcSelectedVolumeIds = Volume::pluck('id')->map(fn ($id) => (int) $id)->toArray();
    }

    public function clearVolumes(): void
    {
        $this->calcSelectedVolumeIds = [];
    }

    public function selectAllProducts(): void
    {
        $this->calcSelectedProductIds = Product::pluck('id')->map(fn ($id) => (int) $id)->toArray();
    }

    public function clearProducts(): void
    {
        $this->calcSelectedProductIds = [];
    }

    public function setSimulationPrice(int $variantId, int|string $price): void
    {
        $cleanPrice = (int) $price;
        if ($cleanPrice > 0) {
            $this->simulationPrices[$variantId] = $cleanPrice;
        } else {
            unset($this->simulationPrices[$variantId]);
        }
    }

    public function clearSimulationPrices(): void
    {
        $this->simulationPrices = [];
        $this->calculatorMessage = null;
        $this->calculatorError = null;
    }

    public function applySimulationPricesToCatalog(): void
    {
        $this->calculatorMessage = null;
        $this->calculatorError = null;

        $user = Auth::user();
        if (! $user) {
            $this->calculatorError = 'Tizimga kirish talab qilinadi.';

            return;
        }

        try {
            $count = $this->calculatorService->applySimulationPricesToCatalog(
                $this->simulationPrices,
                $user,
                'Ombor kalkulyatoridan yangilandi'
            );

            $this->calculatorMessage = "Muvaffaqiyatli: {$count} ta mahsulot varianti uchun yangi tizim narxi saqlandi!";
            $this->simulationPrices = [];
        } catch (OperationPermissionException $e) {
            $this->calculatorError = $e->getMessage();
        } catch (\Throwable $e) {
            $this->calculatorError = 'Xatolik yuz berdi: '.$e->getMessage();
        }
    }

    // Computed Properties
    public function getCanViewCostProperty(): bool
    {
        $user = Auth::user();

        return $user ? $user->hasPermission('view_cost_price') : false;
    }

    public function getCanManagePricesProperty(): bool
    {
        $user = Auth::user();

        return $user ? $user->hasPermission('manage_prices') : false;
    }

    public function getCalculatorResultsProperty(): array
    {
        $query = ProductVariant::query()->where('status', 'active');

        if (! empty($this->calcSelectedProductIds)) {
            $query->whereIn('product_id', $this->calcSelectedProductIds);
        }

        if (! empty($this->calcSelectedVolumeIds)) {
            $query->whereIn('volume_id', $this->calcSelectedVolumeIds);
        }

        $variantIds = $query->pluck('id')->toArray();

        return $this->calculatorService->calculate(
            $variantIds,
            $this->simulationPrices,
            null,
            $this->canViewCost
        );
    }

    public function render()
    {
        $filters = [
            'search' => $this->search,
            'volume_ids' => $this->selectedVolumeIds,
            'min_stock' => $this->minStock,
            'max_stock' => $this->maxStock,
            'threshold_filter' => $this->thresholdFilter,
            'zero_stock_filter' => $this->zeroStockFilter,
            'price_filter' => $this->priceFilter,
            'status_filter' => $this->statusFilter,
            'slow_moving' => $this->slowMovingFilter,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ];

        // 1. Umumiy agregatlar (Barcha filtrlangan to'plam bo'yicha, faqat bitta sahifa emas!)
        $totals = $this->stockService->getAggregatedTotals($filters, null, $this->canViewCost);

        // 2. Sahifalangan ro'yxat
        $stockPaginator = $this->stockService->getPaginatedStock(
            $filters,
            $this->perPage,
            $this->getPage(),
            null,
            $this->canViewCost
        );

        // 3. Variant harakatlari (agar modal ochiq bo'lsa)
        $movementsPaginator = null;
        if ($this->showDetailModal && $this->selectedVariantId) {
            $movementsPaginator = $this->stockService->getVariantMovements(
                $this->selectedVariantId,
                15,
                $this->movementsPage,
                null,
                $this->canViewCost
            );
        }

        // 4. Ma'lumotnomalar
        $allVolumes = Volume::orderBy('value_ml')->get();
        $allProducts = Product::where('status', 'active')->orderBy('name')->get();

        return view('livewire.inventory.stock-manager', [
            'totals' => $totals,
            'stockList' => $stockPaginator,
            'movements' => $movementsPaginator,
            'allVolumes' => $allVolumes,
            'allProducts' => $allProducts,
            'calcResults' => $this->calculatorResults,
        ]);
    }
}

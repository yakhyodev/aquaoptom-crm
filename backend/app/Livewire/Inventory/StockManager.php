<?php

namespace App\Livewire\Inventory;

use App\Models\DamageRecord;
use App\Models\Device;
use App\Models\InventoryAudit;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Volume;
use App\Services\Inventory\DamageDisposalService;
use App\Services\Inventory\InventoryAuditService;
use App\Services\Inventory\InventoryCalculatorService;
use App\Services\Inventory\InventoryStockService;
use App\Services\Inventory\SaleReturnService;
use App\Services\Inventory\SupplierReturnService;
use App\Services\Operations\Exceptions\OperationPermissionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class StockManager extends Component
{
    use WithPagination;

    // Asosiy faol tab
    #[Url(as: 'tab')]
    public string $activeTab = 'balances'; // 'balances', 'calculator', 'inward', 'returns', 'damages', 'audits'

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

    // 4. Qaytarishlar (Returns)
    public bool $showSaleReturnModal = false;

    public ?int $returnSaleId = null;

    public ?Sale $selectedSaleForReturn = null;

    public array $saleReturnQuantities = [];

    public array $saleReturnDamaged = [];

    public string $saleReturnReason = 'Mijoz tovar qaytardi';

    public int $saleReturnRefundAmount = 0;

    public ?string $saleReturnSuccess = null;

    public ?string $saleReturnError = null;

    public bool $showSupplierReturnModal = false;

    public ?int $returnPurchaseId = null;

    public ?Purchase $selectedPurchaseForReturn = null;

    public array $supplierReturnQuantities = [];

    public string $supplierReturnReason = "Ta'minotchiga tovar qaytarildi";

    public ?string $supplierReturnSuccess = null;

    public ?string $supplierReturnError = null;

    // 5. Brak va Yaroqsiz tovar chiqimi (Damages)
    public bool $showDamageModal = false;

    public ?int $damageVariantId = null;

    public int $damageQuantity = 1;

    public string $damageReason = 'Yaroqsiz / singan idish';

    public ?string $damageNotes = null;

    public ?string $damageSuccess = null;

    public ?string $damageError = null;

    // 6. Inventarizatsiya (Audits)
    public bool $showPrepareAuditModal = false;

    public string $auditNotes = '';

    public ?int $activeAuditId = null;

    public ?InventoryAudit $selectedAudit = null;

    public array $auditCountInputs = [];

    public array $auditItemReasons = [];

    public ?string $auditSuccess = null;

    public ?string $auditError = null;

    protected InventoryStockService $stockService;

    protected InventoryCalculatorService $calculatorService;

    protected SaleReturnService $saleReturnService;

    protected SupplierReturnService $supplierReturnService;

    protected DamageDisposalService $damageDisposalService;

    protected InventoryAuditService $inventoryAuditService;

    public function boot(
        InventoryStockService $stockService,
        InventoryCalculatorService $calculatorService,
        SaleReturnService $saleReturnService,
        SupplierReturnService $supplierReturnService,
        DamageDisposalService $damageDisposalService,
        InventoryAuditService $inventoryAuditService
    ): void {
        $this->stockService = $stockService;
        $this->calculatorService = $calculatorService;
        $this->saleReturnService = $saleReturnService;
        $this->supplierReturnService = $supplierReturnService;
        $this->damageDisposalService = $damageDisposalService;
        $this->inventoryAuditService = $inventoryAuditService;
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

    // Modal amallari (Variant Tafsiloti)
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

    // --- 4. Sotuv Qaytarish Amallari (Sale Return) ---
    public function openSaleReturnModal(int $saleId): void
    {
        $this->saleReturnSuccess = null;
        $this->saleReturnError = null;
        $this->returnSaleId = $saleId;
        $this->selectedSaleForReturn = Sale::with(['items.variant.product', 'items.variant.volume', 'customer'])->find($saleId);

        $this->saleReturnQuantities = [];
        $this->saleReturnDamaged = [];
        $this->saleReturnRefundAmount = 0;

        if ($this->selectedSaleForReturn) {
            foreach ($this->selectedSaleForReturn->items as $item) {
                $alreadyReturned = (int) SaleReturnItem::where('sale_item_id', $item->id)->sum('quantity');
                $avail = max(0, (int) $item->quantity - $alreadyReturned);
                $this->saleReturnQuantities[$item->id] = 0;
                $this->saleReturnDamaged[$item->id] = false;
            }
        }

        $this->showSaleReturnModal = true;
    }

    public function closeSaleReturnModal(): void
    {
        $this->showSaleReturnModal = false;
        $this->returnSaleId = null;
        $this->selectedSaleForReturn = null;
    }

    public function submitSaleReturn(): void
    {
        $this->saleReturnSuccess = null;
        $this->saleReturnError = null;

        if (! $this->selectedSaleForReturn) {
            return;
        }

        $itemsToReturn = [];
        foreach ($this->saleReturnQuantities as $itemId => $qty) {
            $intQty = (int) $qty;
            if ($intQty > 0) {
                $itemsToReturn[] = [
                    'sale_item_id' => $itemId,
                    'quantity' => $intQty,
                    'is_damaged' => (bool) ($this->saleReturnDamaged[$itemId] ?? false),
                ];
            }
        }

        if (empty($itemsToReturn)) {
            $this->saleReturnError = 'Qaytarish uchun kamida bitta tovar soni kiritilishi shart!';

            return;
        }

        try {
            $res = $this->saleReturnService->createSaleReturn(
                saleId: $this->selectedSaleForReturn->id,
                items: $itemsToReturn,
                reason: $this->saleReturnReason ?: 'Mijoz tovar qaytardi',
                operationId: (string) Str::uuid(),
                refundAmount: (int) $this->saleReturnRefundAmount,
                cashAccountId: $this->selectedSaleForReturn->cash_account_id,
                userId: Auth::id()
            );

            $this->saleReturnSuccess = "Sotuv muvaffaqiyatli qaytarildi! Hujjat: #{$res['return_number']}. Jami summa: ".number_format($res['total_amount'])." so'm.";
            $this->showSaleReturnModal = false;
        } catch (\Throwable $e) {
            $this->saleReturnError = 'Xatolik: '.$e->getMessage();
        }
    }

    // --- 5. Ta'minotchiga Qaytarish Amallari (Supplier Return) ---
    public function openSupplierReturnModal(int $purchaseId): void
    {
        $this->supplierReturnSuccess = null;
        $this->supplierReturnError = null;
        $this->returnPurchaseId = $purchaseId;
        $this->selectedPurchaseForReturn = Purchase::with(['items.variant.product', 'items.variant.volume', 'supplier'])->find($purchaseId);

        $this->supplierReturnQuantities = [];
        if ($this->selectedPurchaseForReturn) {
            foreach ($this->selectedPurchaseForReturn->items as $item) {
                $this->supplierReturnQuantities[$item->id] = 0;
            }
        }

        $this->showSupplierReturnModal = true;
    }

    public function closeSupplierReturnModal(): void
    {
        $this->showSupplierReturnModal = false;
        $this->returnPurchaseId = null;
        $this->selectedPurchaseForReturn = null;
    }

    public function submitSupplierReturn(): void
    {
        $this->supplierReturnSuccess = null;
        $this->supplierReturnError = null;

        if (! $this->selectedPurchaseForReturn) {
            return;
        }

        $itemsToReturn = [];
        foreach ($this->supplierReturnQuantities as $itemId => $qty) {
            $intQty = (int) $qty;
            if ($intQty > 0) {
                $itemsToReturn[] = [
                    'purchase_item_id' => $itemId,
                    'quantity' => $intQty,
                ];
            }
        }

        if (empty($itemsToReturn)) {
            $this->supplierReturnError = "Ta'minotchiga qaytarish uchun kamida bitta tovar soni kiritilishi shart!";

            return;
        }

        try {
            $res = $this->supplierReturnService->createSupplierReturn(
                purchaseId: $this->selectedPurchaseForReturn->id,
                items: $itemsToReturn,
                reason: $this->supplierReturnReason ?: "Ta'minotchiga tovar qaytarildi",
                operationId: (string) Str::uuid(),
                userId: Auth::id()
            );

            $this->supplierReturnSuccess = "Ta'minotchiga muvaffaqiyatli qaytarildi! Hujjat: #{$res['return_number']}. Majburiyat kamaydi: ".number_format($res['total_credit_amount'])." so'm.";
            $this->showSupplierReturnModal = false;
        } catch (\Throwable $e) {
            $this->supplierReturnError = 'Xatolik: '.$e->getMessage();
        }
    }

    // --- 6. Brak va Yaroqsiz tovar chiqimi (Damage Disposal) ---
    public function openDamageModal(?int $variantId = null): void
    {
        $this->damageSuccess = null;
        $this->damageError = null;
        $this->damageVariantId = $variantId;
        $this->damageQuantity = 1;
        $this->damageReason = 'Yaroqsiz / singan idish';
        $this->damageNotes = null;
        $this->showDamageModal = true;
    }

    public function closeDamageModal(): void
    {
        $this->showDamageModal = false;
        $this->damageVariantId = null;
    }

    public function submitDamageDisposal(): void
    {
        $this->damageSuccess = null;
        $this->damageError = null;

        if (! $this->damageVariantId || $this->damageQuantity <= 0) {
            $this->damageError = "Tovar va miqdor to'g'ri kiritilishi shart!";

            return;
        }

        try {
            $res = $this->damageDisposalService->recordDamage(
                warehouseId: 1, // Asosiy ombor
                items: [
                    [
                        'product_variant_id' => $this->damageVariantId,
                        'quantity' => $this->damageQuantity,
                        'reason' => $this->damageReason,
                    ],
                ],
                reason: $this->damageReason,
                operationId: (string) Str::uuid(),
                userId: Auth::id(),
                notes: $this->damageNotes
            );

            $this->damageSuccess = "Brak chiqimi muvaffaqiyatli yozildi! Hujjat: #{$res['damage_number']}. Tannarx yo'qotishi: ".number_format($res['total_loss_value'])." so'm (Kassa xarajati emas).";
            $this->showDamageModal = false;
        } catch (\Throwable $e) {
            $this->damageError = 'Xatolik: '.$e->getMessage();
        }
    }

    // --- 7. Inventarizatsiya Amallari (Audits) ---
    public function prepareNewAudit(): void
    {
        $this->auditSuccess = null;
        $this->auditError = null;

        try {
            $audit = $this->inventoryAuditService->prepareAudit(
                warehouseId: 1,
                variantIds: [], // barcha variantlar
                notes: $this->auditNotes ?: 'Rejali inventarizatsiya',
                userId: Auth::id(),
                operationId: (string) Str::uuid()
            );

            $this->auditSuccess = "Yangi inventarizatsiya boshlandi (#{$audit->audit_number}). Holati: {$audit->status}, Muzlatish holati: {$audit->device_freeze_status}";
            $this->viewAudit($audit->id);
        } catch (\Throwable $e) {
            $this->auditError = 'Xatolik: '.$e->getMessage();
        }
    }

    public function viewAudit(int $auditId): void
    {
        $this->activeAuditId = $auditId;
        $this->selectedAudit = InventoryAudit::with(['items.variant.product', 'items.variant.volume'])->find($auditId);

        $this->auditCountInputs = [];
        $this->auditItemReasons = [];

        if ($this->selectedAudit) {
            foreach ($this->selectedAudit->items as $item) {
                $this->auditCountInputs[$item->product_variant_id] = $item->counted_quantity ?? (int) $item->expected_quantity;
                $this->auditItemReasons[$item->product_variant_id] = $item->reason ?? '';
            }
        }
    }

    public function closeAuditView(): void
    {
        $this->activeAuditId = null;
        $this->selectedAudit = null;
    }

    public function saveAuditCounts(): void
    {
        if (! $this->selectedAudit) {
            return;
        }

        $this->auditSuccess = null;
        $this->auditError = null;

        $counts = [];
        foreach ($this->auditCountInputs as $variantId => $countedQty) {
            $counts[] = [
                'product_variant_id' => $variantId,
                'counted_quantity' => $countedQty,
                'reason' => $this->auditItemReasons[$variantId] ?? null,
            ];
        }

        try {
            $updated = $this->inventoryAuditService->recordCounts($this->selectedAudit, $counts);
            $this->selectedAudit = $updated;
            $this->auditSuccess = 'Sanalgan natijalar saqlandi! Kutilayotgan farq: '.number_format($updated->total_discrepancy_qty).' dona ('.number_format($updated->total_discrepancy_value)." so'm).";
        } catch (\Throwable $e) {
            $this->auditError = 'Xatolik: '.$e->getMessage();
        }
    }

    public function executeApplyAudit(bool $force = false): void
    {
        if (! $this->selectedAudit) {
            return;
        }

        $this->auditSuccess = null;
        $this->auditError = null;

        try {
            $res = $this->inventoryAuditService->applyAudit(
                $this->selectedAudit,
                Auth::user(),
                forceIfFreezePending: $force,
                reason: 'Inventarizatsiya yakunlandi'
            );

            $this->selectedAudit = $res['audit'];
            $this->auditSuccess = 'Inventarizatsiya tasdiqlandi va ombor qoldiqlari muvofiqlashtirildi! Jami farq: '.number_format($res['total_discrepancy_qty']).' dona.';
        } catch (\Throwable $e) {
            $this->auditError = 'Xatolik: '.$e->getMessage();
        }
    }

    public function acknowledgeDeviceFreezeAction(int $deviceId): void
    {
        $device = Device::find($deviceId);
        if ($device) {
            $this->inventoryAuditService->acknowledgeDeviceFreeze($device);
            $this->auditSuccess = "Qurilma (#{$device->device_code}) muzlatish tasdig'i (Freeze ACK) qabul qilindi.";
            if ($this->selectedAudit) {
                $this->viewAudit($this->selectedAudit->id);
            }
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

        // 5. Qaytarishlar ro'yxatlari
        $saleReturnsList = SaleReturn::with(['sale', 'customer', 'items.variant.product'])
            ->latest('posted_at')
            ->limit(20)
            ->get();

        $purchaseReturnsList = PurchaseReturn::with(['purchase', 'supplier', 'items.variant.product'])
            ->latest('posted_at')
            ->limit(20)
            ->get();

        // 6. Brak chiqimlari ro'yxati
        $damageRecordsList = DamageRecord::with(['items.variant.product', 'creator'])
            ->latest('posted_at')
            ->limit(20)
            ->get();

        // 7. Inventarizatsiyalar ro'yxati
        $inventoryAuditsList = InventoryAudit::with(['creator', 'completedBy'])
            ->latest('started_at')
            ->limit(20)
            ->get();

        // 8. So'nggi savdo va kirimlar (modalda tanlash uchun)
        $recentSales = Sale::with(['customer', 'items.variant.product'])->where('status', 'COMPLETED')->latest()->limit(10)->get();
        $recentPurchases = Purchase::with(['supplier', 'items.variant.product'])->where('status', 'POSTED')->latest()->limit(10)->get();

        // 9. Muzlatish kutilayotgan qurilmalar
        $devicesPendingFreeze = Device::whereNotNull('freeze_requested_at')
            ->whereNull('freeze_acknowledged_at')
            ->get();

        return view('livewire.inventory.stock-manager', [
            'totals' => $totals,
            'stockList' => $stockPaginator,
            'movements' => $movementsPaginator,
            'allVolumes' => $allVolumes,
            'allProducts' => $allProducts,
            'calcResults' => $this->calculatorResults,
            'saleReturns' => $saleReturnsList,
            'purchaseReturns' => $purchaseReturnsList,
            'damageRecords' => $damageRecordsList,
            'inventoryAudits' => $inventoryAuditsList,
            'recentSales' => $recentSales,
            'recentPurchases' => $recentPurchases,
            'devicesPendingFreeze' => $devicesPendingFreeze,
        ]);
    }
}

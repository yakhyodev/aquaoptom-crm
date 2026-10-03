<?php

namespace App\Livewire\Catalog;

use App\Exceptions\CannotDeleteReferencedRecordException;
use App\Models\ProductVariant;
use App\Models\Volume;
use App\Services\Catalog\CatalogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class ProductManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $volumeFilter = '';

    public string $statusFilter = 'active'; // active, archived, all

    public bool $filterLowStock = false;

    public bool $filterMissingPrice = false;

    // Create Product & Variant modal state
    public bool $showCreateModal = false;

    public string $newProductName = '';

    public string $newVolumeInput = '0.5 L';

    public string $customVolumeInput = '';

    public ?int $newDefaultPrice = null;

    public int $newMinimumStock = 50;

    public ?string $newBarcode = null;

    // Price Edit Modal state
    public bool $showPriceModal = false;

    public ?int $editingVariantId = null;

    public ?int $editingCurrentPrice = null;

    public ?int $newPriceInput = null;

    public string $priceChangeReason = '';

    public array $editingPriceHistory = [];

    // Delete / error feedback
    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'volumeFilter' => ['except' => ''],
        'statusFilter' => ['except' => 'active'],
        'filterLowStock' => ['except' => false],
        'filterMissingPrice' => ['except' => false],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingVolumeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->newProductName = '';
        $this->newVolumeInput = '0.5 L';
        $this->customVolumeInput = '';
        $this->newDefaultPrice = null;
        $this->newMinimumStock = 50;
        $this->newBarcode = null;
        $this->showCreateModal = true;
    }

    public function saveProduct(CatalogService $catalogService): void
    {
        $this->validate([
            'newProductName' => 'required|min:2|max:100',
            'newMinimumStock' => 'required|integer|min:0',
            'newDefaultPrice' => 'nullable|integer|min:0',
        ], [
            'newProductName.required' => 'Mahsulot nomi kiritilishi shart.',
            'newProductName.min' => 'Mahsulot nomi kamida 2 ta belgidan iborat bo\'lsin.',
            'newMinimumStock.required' => 'Minimal qoldiq chegarasi kiritilishi shart.',
            'newDefaultPrice.min' => 'Sotuv narxi musbat bo\'lishi kerak.',
        ]);

        $volumeValue = $this->newVolumeInput === 'custom'
            ? $this->customVolumeInput
            : $this->newVolumeInput;

        if (empty(trim($volumeValue))) {
            $this->addError('newVolumeInput', 'Hajm kiritilishi shart.');

            return;
        }

        try {
            $variant = $catalogService->createVariant(
                productName: $this->newProductName,
                volumeInput: $volumeValue,
                defaultSalePrice: $this->newDefaultPrice,
                minimumStock: $this->newMinimumStock,
                barcode: $this->newBarcode,
                createdBy: Auth::id()
            );

            $this->showCreateModal = false;
            $this->successMessage = "Mahsulot varianti muvaffaqiyatli saqlandi: {$variant->product->name} {$variant->volume->name} (SKU: {$variant->sku})";
        } catch (\Exception $e) {
            $this->errorMessage = 'Xatolik: '.$e->getMessage();
        }
    }

    public function openPriceModal(int $variantId): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        $variant = ProductVariant::with(['product', 'volume', 'priceHistories.user'])->findOrFail($variantId);

        // Huquq tekshiruvi: manage_prices ruxsati yoki Owner/Admin
        if (! Gate::allows('manage_prices') && ! Auth::user()->isOwner() && ! Auth::user()->isAdmin()) {
            $this->errorMessage = 'Sizda sotuv narxlarini o\'zgartirish ruxsati mavjud emas!';

            return;
        }

        $this->editingVariantId = $variant->id;
        $this->editingCurrentPrice = $variant->default_sale_price;
        $this->newPriceInput = $variant->default_sale_price;
        $this->priceChangeReason = '';
        $this->editingPriceHistory = $variant->priceHistories()
            ->orderBy('id', 'desc')
            ->take(10)
            ->get()
            ->toArray();

        $this->showPriceModal = true;
    }

    public function savePrice(CatalogService $catalogService): void
    {
        $this->validate([
            'newPriceInput' => 'nullable|integer|min:0',
            'priceChangeReason' => 'nullable|string|max:255',
        ]);

        if (! $this->editingVariantId) {
            return;
        }

        $variant = ProductVariant::findOrFail($this->editingVariantId);

        try {
            $catalogService->updatePrice(
                variant: $variant,
                newPrice: $this->newPriceInput,
                reason: $this->priceChangeReason,
                userId: Auth::id()
            );

            $this->showPriceModal = false;
            $this->successMessage = "Narx muvaffaqiyatli yangilandi: {$variant->product->name} {$variant->volume->name}";
        } catch (\Exception $e) {
            $this->errorMessage = 'Xatolik: '.$e->getMessage();
        }
    }

    public function toggleArchive(int $variantId, CatalogService $catalogService): void
    {
        $variant = ProductVariant::findOrFail($variantId);
        if ($variant->status === 'active') {
            $catalogService->archiveVariant($variant);
            $this->successMessage = "Mahsulot varianti arxivlandi: {$variant->product->name} {$variant->volume->name}";
        } else {
            $catalogService->activateVariant($variant);
            $this->successMessage = "Mahsulot varianti faollashtirildi: {$variant->product->name} {$variant->volume->name}";
        }
    }

    public function deleteVariant(int $variantId, CatalogService $catalogService): void
    {
        $variant = ProductVariant::findOrFail($variantId);

        try {
            $catalogService->deleteVariant($variant);
            $this->successMessage = 'Mahsulot varianti muvaffaqiyatli o\'chirildi.';
        } catch (CannotDeleteReferencedRecordException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $this->errorMessage = 'Xatolik: '.$e->getMessage();
        }
    }

    public function render()
    {
        $query = ProductVariant::with(['product', 'volume', 'balance'])
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->join('volumes', 'product_variants.volume_id', '=', 'volumes.id')
            ->select('product_variants.*');

        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('products.name', 'ilike', "%{$term}%")
                    ->orWhere('product_variants.sku', 'ilike', "%{$term}%")
                    ->orWhere('product_variants.barcode', 'ilike', "%{$term}%")
                    ->orWhere('volumes.name', 'ilike', "%{$term}%");
            });
        }

        if ($this->volumeFilter !== '') {
            $query->where('volumes.id', $this->volumeFilter);
        }

        if ($this->statusFilter === 'active') {
            $query->where('product_variants.status', 'active');
        } elseif ($this->statusFilter === 'archived') {
            $query->where('product_variants.status', 'archived');
        }

        if ($this->filterLowStock) {
            $query->leftJoin('inventory_balances', 'product_variants.id', '=', 'inventory_balances.product_variant_id')
                ->whereRaw('COALESCE(inventory_balances.quantity, 0) <= product_variants.minimum_stock');
        }

        if ($this->filterMissingPrice) {
            $query->whereNull('product_variants.default_sale_price');
        }

        $variants = $query->orderBy('products.name')
            ->orderBy('volumes.value_ml')
            ->paginate(15);

        $volumes = Volume::where('status', 'active')
            ->orderBy('value_ml')
            ->get();

        return view('livewire.catalog.product-manager', [
            'variants' => $variants,
            'volumes' => $volumes,
        ]);
    }
}

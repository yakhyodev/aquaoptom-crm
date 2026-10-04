<?php

namespace App\Livewire\Modals;

use App\Models\Volume;
use App\Services\Catalog\CatalogService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class InlineProductModal extends Component
{
    public bool $isOpen = false;

    public string $productName = '';

    public string $volumeInput = '0.5 L';

    public string $customVolumeInput = '';

    public ?int $defaultPrice = null;

    public ?string $errorMessage = null;

    #[On('open-inline-product-modal')]
    public function open(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->productName = '';
        $this->volumeInput = '0.5 L';
        $this->customVolumeInput = '';
        $this->defaultPrice = null;
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function save(CatalogService $catalogService): void
    {
        $this->validate([
            'productName' => 'required|min:2|max:100',
            'defaultPrice' => 'nullable|integer|min:0',
        ], [
            'productName.required' => 'Mahsulot nomi kiritilishi shart.',
            'productName.min' => 'Mahsulot nomi kamida 2 ta belgidan iborat bo\'lsin.',
        ]);

        $vol = $this->volumeInput === 'custom' ? $this->customVolumeInput : $this->volumeInput;
        if (empty(trim($vol))) {
            $this->addError('volumeInput', 'Hajm kiritilishi shart.');

            return;
        }

        try {
            $variant = $catalogService->createVariant(
                productName: $this->productName,
                volumeInput: $vol,
                defaultSalePrice: $this->defaultPrice,
                createdBy: Auth::id()
            );

            $this->isOpen = false;

            // Ota komponentga hodisa yuboramiz — ota komponentning mavjud qoralamasi buzilmaydi!
            $this->dispatch('product-created', [
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'product_name' => $variant->product->name,
                'volume_name' => $variant->volume->name,
                'display_name' => $variant->product->name.' '.$variant->volume->name,
                'sale_price' => $variant->default_sale_price,
            ]);
        } catch (\Exception $e) {
            $this->errorMessage = 'Xatolik: '.$e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.modals.inline-product-modal', [
            'volumes' => Volume::where('status', 'active')->orderBy('value_ml')->get(),
        ]);
    }
}

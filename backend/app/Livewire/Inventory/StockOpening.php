<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryMovement;
use App\Models\OperationResult;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Volume;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\ProductNormalizer;
use App\Services\Catalog\VolumeNormalizer;
use App\Services\Opening\OpeningBalanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class StockOpening extends Component
{
    public string $productSelection = '';

    public string $newProductName = '';

    public string $volumeSelection = '';

    public string $customLitres = '';

    public string $quantity = '';

    public string $unitCost = '';

    public string $salePrice = '';

    public string $costMode = 'known';

    public bool $confirmFreeStock = false;

    private const DRAFT_FIELDS = ['productSelection', 'newProductName', 'volumeSelection', 'customLitres', 'quantity', 'unitCost', 'salePrice', 'costMode', 'confirmFreeStock', 'operationId', 'savedOpening'];

    public ?string $errorMessage = null;

    #[Locked]
    public string $operationId = '';

    #[Locked]
    public ?array $savedOpening = null;

    public function mount(): void
    {
        $this->authorizeOwner();
        $draft = session()->get($this->draftKey());
        if (is_array($draft) && Str::isUuid($draft['operationId'] ?? '')) {
            foreach (self::DRAFT_FIELDS as $field) {
                if (array_key_exists($field, $draft)) {
                    $this->{$field} = $draft[$field];
                }
            }
            $this->recoverSavedOpening();
        } else {
            $this->startNextProduct();
        }
        if (request()->filled('opening_variant') && $this->quantity === '' && $this->savedOpening === null) {
            $variant = ProductVariant::find(request()->integer('opening_variant'));
            if ($variant) {
                $this->productSelection = (string) $variant->product_id;
                $this->volumeSelection = (string) $variant->volume_id;
            }
        }
    }

    protected function draftKey(): string
    {
        return 'stock-opening.'.Auth::id();
    }

    public function dehydrate(): void
    {
        $draft = [];
        foreach (self::DRAFT_FIELDS as $field) {
            $draft[$field] = $this->{$field};
        }
        session()->put($this->draftKey(), $draft);
    }

    protected function recoverSavedOpening(): void
    {
        $operation = OperationResult::where('operation_id', $this->operationId)
            ->where('actor_id', Auth::id())->where('operation_type', 'RECORD_STOCK_OPENING')
            ->where('status', 'PROCESSED')->first();
        if (! $operation) {
            return;
        }
        $movement = InventoryMovement::with(['variant.product', 'variant.volume'])
            ->where('operation_id', $this->operationId)->where('movement_type', 'OPENING_BALANCE')->firstOrFail();
        $this->savedOpening = [
            'name' => $movement->variant->product->name.' — '.$movement->variant->volume->name,
            'added' => (int) $movement->quantity,
            'quantity' => (int) $movement->balance_after_quantity,
            'total_cost' => $movement->total_cost,
            'document_number' => $operation->result_payload['document_number'],
            'saved_at' => $movement->created_at->timezone('Asia/Tashkent')->format('d.m.Y H:i:s'),
        ];
    }

    public function startNextProduct(): void
    {
        $this->authorizeOwner();
        $this->reset(['productSelection', 'newProductName', 'volumeSelection', 'customLitres', 'quantity', 'unitCost', 'salePrice', 'costMode', 'confirmFreeStock', 'errorMessage', 'savedOpening']);
        $this->resetValidation();
        $this->operationId = (string) Str::uuid();
        $this->dehydrate();
    }

    public function save(CatalogService $catalogService, OpeningBalanceService $openingService): void
    {
        $this->authorizeOwner();
        $this->recoverSavedOpening();
        if ($this->savedOpening !== null) {
            return;
        }

        $this->errorMessage = null;
        $this->newProductName = trim($this->newProductName);
        if ($this->costMode === 'free') {
            $this->unitCost = '0';
        }
        $this->dehydrate();
        $this->validate([
            'productSelection' => $this->productSelection === 'new'
                ? ['required', 'in:new']
                : ['bail', 'required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'newProductName' => $this->productSelection === 'new' ? ['required', 'string', 'min:2', 'max:100'] : ['nullable'],
            'volumeSelection' => $this->volumeSelection === 'new'
                ? ['required', 'in:new']
                : ['bail', 'required', 'integer', Rule::exists('volumes', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'customLitres' => $this->volumeSelection === 'new' ? ['required', 'max:20', 'regex:/^\d+(?:[.,]\d{1,3})?$/'] : ['nullable'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'costMode' => ['required', 'in:known,free'],
            'confirmFreeStock' => $this->costMode === 'free' ? ['accepted'] : ['boolean'],
            'unitCost' => ['required', 'integer', $this->costMode === 'free' ? 'min:0' : 'min:1', 'max:1000000000'],
            'salePrice' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ], [
            'customLitres.regex' => 'Litrni raqam bilan yozing. Masalan: 0.75 yoki 1,5.',
            'quantity.integer' => 'Dona butun son bo‘lishi kerak. Masalan: 150.',
            'quantity.min' => 'Kamida 1 dona yozing.',
            'unitCost.integer' => 'Narxni butun so‘mda yozing.',
            'unitCost.min' => 'Tannarx noma’lum bo‘lsa 0 yozmang. Haqiqiy tannarxni aniqlang yoki bepul kelganini alohida belgilang.',
            'confirmFreeStock.accepted' => 'Mahsulot haqiqatan bepul kelganini tasdiqlang.',
        ], [
            'productSelection' => 'Mahsulot', 'newProductName' => 'Yangi mahsulot nomi',
            'volumeSelection' => 'Litri', 'customLitres' => 'Yangi hajm',
            'quantity' => 'Dona', 'unitCost' => '1 dona tannarxi', 'salePrice' => 'Sotuv narxi',
        ]);

        if ($this->volumeSelection === 'new') {
            $litres = (float) str_replace(',', '.', $this->customLitres);
            if ($litres <= 0 || $litres > 1000) {
                $this->addError('customLitres', 'Hajm 0 dan katta va 1000 litrdan oshmagan bo‘lishi kerak.');

                return;
            }
        }

        $volumeInput = $this->volumeSelection === 'new'
            ? $this->customLitres.' L'
            : Volume::findOrFail($this->volumeSelection)->value_ml.' ml';
        $volumeMl = VolumeNormalizer::normalize($volumeInput)['value_ml'];
        if ($volumeMl < 1 || $volumeMl > 1000000) {
            $this->addError('customLitres', 'Hajm 0 dan katta va 1000 litrdan oshmagan bo‘lishi kerak.');

            return;
        }

        $productName = $this->productSelection === 'new'
            ? $this->newProductName
            : Product::findOrFail($this->productSelection)->name;

        try {
            $this->savedOpening = DB::transaction(function () use ($catalogService, $openingService, $productName, $volumeInput): array {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$this->operationId]);
                }
                $variant = $catalogService->createVariant(
                    productName: $productName,
                    volumeInput: $volumeInput,
                    defaultSalePrice: $this->salePrice === '' ? null : (int) $this->salePrice,
                    createdBy: Auth::id()
                );
                if (strtoupper($variant->status) !== 'ACTIVE' || strtoupper($variant->product->status) !== 'ACTIVE' || strtoupper($variant->volume->status) !== 'ACTIVE') {
                    throw new \InvalidArgumentException('Arxivdagi mahsulot uchun boshlang‘ich qoldiq kiritilmaydi.');
                }
                $result = $openingService->recordStockOpening(
                    productVariantId: $variant->id,
                    quantity: (int) $this->quantity,
                    unitCost: (int) $this->unitCost,
                    operationId: $this->operationId,
                    userId: Auth::id()
                );

                return [
                    'name' => $variant->product->name.' — '.$variant->volume->name,
                    'added' => (int) $this->quantity,
                    'quantity' => $result['stock']['balance_quantity'],
                    'total_cost' => $result['stock']['total_cost'],
                    'document_number' => $result['document_number'],
                    'saved_at' => now()->timezone('Asia/Tashkent')->format('d.m.Y H:i:s'),
                ];
            });
            $this->dispatch('refresh-dashboard');
        } catch (Throwable $exception) {
            report($exception);
            $this->errorMessage = 'Qoldiq saqlanmadi. Ma’lumotlarni tekshiring va qayta urinib ko‘ring. Kiritgan yozuvlaringiz shu yerda saqlanib turibdi.';
        }
    }

    protected function authorizeOwner(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->isActive() && $user->hasRole(['OWNER', 'ADMIN']), 403);
    }

    protected function selectedVariant(): ?ProductVariant
    {
        $product = $this->productSelection === 'new'
            ? Product::where('normalized_name', ProductNormalizer::normalize($this->newProductName))->first()
            : (ctype_digit($this->productSelection) ? Product::find($this->productSelection) : null);
        if (! $product || $this->volumeSelection === '') {
            return null;
        }

        if ($this->volumeSelection === 'new') {
            if (! preg_match('/^\d+(?:[.,]\d{1,3})?$/', $this->customLitres)) {
                return null;
            }
            $ml = (int) round((float) str_replace(',', '.', $this->customLitres) * 1000);
            $volumeId = Volume::where('value_ml', $ml)->value('id');
        } else {
            $volumeId = ctype_digit($this->volumeSelection) ? $this->volumeSelection : null;
        }

        return ProductVariant::with('balance')->where('product_id', $product->id)->where('volume_id', $volumeId)->first();
    }

    public function render(): View
    {
        $this->authorizeOwner();

        return view('livewire.inventory.stock-opening', [
            'products' => Product::where('status', 'active')->orderBy('name')->get(),
            'volumes' => Volume::where('status', 'active')->orderBy('value_ml')->get(),
            'selectedVariant' => $this->selectedVariant(),
            'recentOpenings' => InventoryMovement::with(['variant.product', 'variant.volume', 'creator'])
                ->where('movement_type', 'OPENING_BALANCE')->latest('created_at')->limit(12)->get(),
        ]);
    }
}

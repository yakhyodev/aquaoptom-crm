<?php

namespace App\Livewire\Inventory;

use App\Models\CashAccount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\Volume;
use App\Services\Purchase\ReceivePurchaseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class QuickInward extends Component
{
    public string $activeTab = 'receiving'; // 'receiving', 'catalog', 'suppliers'

    // Ta'minotchi
    public ?int $selectedSupplierId = null;

    public ?string $selectedSupplierName = null;

    public string $supplierInvoiceNumber = '';

    public string $notes = '';

    // Tovarlar qatorlari
    public ?int $selectedProductId = null;

    public ?int $selectedVolumeId = null;

    public array $items = [];

    public ?int $quickVariantId = null;

    // To'lov parametrlari
    public string $paymentType = 'UNPAID'; // UNPAID, FULL, PARTIAL

    public int $paidAmount = 0;

    public ?int $cashAccountId = null;

    public string $paymentMethod = 'CASH'; // CASH, CARD, BANK

    // Idempotent operation_id
    public string $operationId = '';

    // Xabarlar va holatlar
    public ?string $inwardMessage = null;

    public ?string $errorMessage = null;

    public ?array $successPurchase = null;

    public function updatedSelectedProductId(): void
    {
        $this->selectedVolumeId = null;
    }

    public function mount(): void
    {
        $this->operationId = Str::uuid()->toString();

        $defaultCash = CashAccount::where('type', 'CASH')->where('is_default', true)->first()
            ?: CashAccount::where('type', 'CASH')->first();

        if ($defaultCash) {
            $this->cashAccountId = $defaultCash->id;
        }
    }

    #[On('product-created')]
    public function onProductCreated(array $payload): void
    {
        $this->selectedProductId = null;
        $this->selectedVolumeId = null;
        $this->quickVariantId = (int) $payload['variant_id'];
        $this->addSelectedVariant();
    }

    #[On('supplier-created')]
    public function onSupplierCreated(array $payload): void
    {
        $this->selectedSupplierId = $payload['supplier_id'];
        $this->selectedSupplierName = $payload['display_name'];
        $this->inwardMessage = "Yangi ta'minotchi saqlandi va tanlandi: {$payload['name']}";
    }

    public function selectSupplier(int $supplierId): void
    {
        $supplier = Supplier::find($supplierId);
        if ($supplier) {
            $this->selectedSupplierId = $supplier->id;
            $this->selectedSupplierName = $supplier->display_name;
        }
    }

    public function addSelectedVariant(): void
    {
        $this->errorMessage = null;
        if ($this->selectedProductId && $this->selectedVolumeId) {
            $this->quickVariantId = ProductVariant::where('product_id', $this->selectedProductId)
                ->where('volume_id', $this->selectedVolumeId)->where('status', 'active')->value('id');
        }
        $variant = ProductVariant::with(['product', 'volume', 'balance'])
            ->where('status', 'active')->find($this->quickVariantId);
        if (! $variant) {
            $this->errorMessage = 'Avval mahsulotni, keyin uning hajmini tanlang.';

            return;
        }
        foreach ($this->items as $item) {
            if ($item['variant_id'] === $variant->id) {
                $this->inwardMessage = 'Bu mahsulot ro‘yxatda bor. Donasini shu qatorda o‘zgartiring.';
                $this->quickVariantId = null;

                return;
            }
        }
        $this->items[] = [
            'variant_id' => $variant->id,
            'display_name' => $variant->product->name.' — '.$variant->volume->name,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_cost' => 0,
            'cost_entered' => false,
            'new_sale_price' => $variant->default_sale_price,
        ];
        $this->quickVariantId = null;
        $this->inwardMessage = 'Mahsulot qo‘shildi. Endi donasi va kirim narxini yozing.';
        $this->recalculatePayment();
    }

    public function updateQuantity(int $index, mixed $qty): void
    {
        if (! isset($this->items[$index])) {
            return;
        }
        $errorKey = 'quantity-'.$this->items[$index]['variant_id'];
        $value = filter_var($qty, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            $this->errorMessage = 'Dona sonini 1 yoki undan katta butun raqam bilan yozing.';

            $this->addError($errorKey, $this->errorMessage);

            return;
        }
        $this->resetErrorBag($errorKey);
        $this->errorMessage = null;
        $this->items[$index]['quantity'] = $value;
        $this->recalculatePayment();
    }

    public function updateUnitCost(int $index, mixed $cost): void
    {
        if (! isset($this->items[$index])) {
            return;
        }
        $errorKey = 'price-'.$this->items[$index]['variant_id'];
        $value = filter_var($cost, FILTER_VALIDATE_INT);
        if ($value === false || $value < 0) {
            $this->errorMessage = 'Kirim narxini 0 yoki undan katta butun raqam bilan yozing.';

            $this->addError($errorKey, $this->errorMessage);

            return;
        }
        $this->resetErrorBag($errorKey);
        $this->errorMessage = null;
        $this->items[$index]['unit_cost'] = $value;
        $this->items[$index]['cost_entered'] = true;
        $this->recalculatePayment();
    }

    public function removeItem(int $index): void
    {
        if (isset($this->items[$index])) {
            $this->resetErrorBag('quantity-'.$this->items[$index]['variant_id']);
            $this->resetErrorBag('price-'.$this->items[$index]['variant_id']);
        }
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->recalculatePayment();
    }

    public function updatedPaymentType(string $val): void
    {
        $total = $this->calculateTotalAmount();

        if ($val === 'FULL') {
            $this->paidAmount = $total;
        } elseif ($val === 'UNPAID') {
            $this->paidAmount = 0;
        } elseif ($val === 'PARTIAL') {
            $this->paidAmount = 0;
        }
    }

    protected function recalculatePayment(): void
    {
        if ($this->paymentType === 'FULL') {
            $this->paidAmount = $this->calculateTotalAmount();
        } elseif ($this->paymentType === 'UNPAID') {
            $this->paidAmount = 0;
        }
    }

    public function calculateTotalAmount(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += ((int) ($item['quantity'] ?? 0) * (int) ($item['unit_cost'] ?? 0));
        }

        return $total;
    }

    public function clearDraft(): void
    {
        $this->resetErrorBag();
        $this->reset(['items', 'selectedSupplierId', 'selectedSupplierName', 'supplierInvoiceNumber', 'notes', 'paidAmount', 'successPurchase']);
        $this->paymentType = 'UNPAID';
        $this->operationId = Str::uuid()->toString();
        $this->errorMessage = null;
        $this->inwardMessage = 'Kirim qoralamasi tozalandi.';
    }

    public function postPurchase(ReceivePurchaseService $receiveService): void
    {
        if ($this->getErrorBag()->any()) {
            $this->errorMessage = 'Dona va narx maydonlaridagi xatolarni tuzating.';

            return;
        }

        $this->reset(['errorMessage', 'inwardMessage']);

        if (! $this->selectedSupplierId) {
            $this->errorMessage = "Iltimos, ta'minotchini tanlang!";

            return;
        }

        if (empty($this->items)) {
            $this->errorMessage = "Kirim ro'yxatida kamida bitta tovar bo'lishi shart!";

            return;
        }

        foreach ($this->items as $item) {
            if (! ($item['cost_entered'] ?? false)) {
                $this->errorMessage = 'Har bir mahsulotning kirim narxini yozing.';

                return;
            }
        }

        $totalAmount = $this->calculateTotalAmount();
        $user = Auth::user();
        $canManageCash = $user && ($user->hasRole(['OWNER', 'ADMIN', 'CASHIER']) || $user->hasPermission('manage_cash_outflow') || $user->hasPermission('view_cash'));

        if ($this->paidAmount > 0 && ! $canManageCash) {
            $this->errorMessage = "Sizda kassadan pul to'lash huquqi yo'q. Faqat to'lovsiz (nasiya) kirim qila olasiz.";

            return;
        }

        try {
            $res = $receiveService->execute(
                supplierId: $this->selectedSupplierId,
                items: $this->items,
                operationId: $this->operationId,
                paidAmount: $this->paidAmount,
                cashAccountId: $this->cashAccountId,
                paymentMethod: $this->paymentMethod,
                supplierInvoiceNumber: $this->supplierInvoiceNumber ?: null,
                notes: $this->notes ?: null,
                userId: Auth::id(),
                source: 'web'
            );

            $this->successPurchase = $res instanceof Fluent ? $res->toArray() : (array) $res;
            $this->inwardMessage = "Kirim muvaffaqiyatli qabul qilindi! Hujjat: #{$res['invoice_number']}";

            // Toza yangi qoralama uchun yangi operation_id
            $this->items = [];
            $this->selectedSupplierId = null;
            $this->selectedSupplierName = null;
            $this->supplierInvoiceNumber = '';
            $this->notes = '';
            $this->paidAmount = 0;
            $this->paymentType = 'UNPAID';
            $this->operationId = Str::uuid()->toString();

        } catch (\Exception $e) {
            // UI qoralamasi saqlanadi!
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $products = Product::where('status', 'active')
            ->whereHas('variants', fn ($query) => $query->where('status', 'active'))
            ->orderBy('name')->get();
        $availableVolumes = Volume::where('status', 'active')
            ->whereHas('variants', fn ($query) => $query->where('product_id', $this->selectedProductId)
                ->where('status', 'active'))->orderBy('value_ml')->get();
        $user = Auth::user();

        return view('livewire.inventory.quick-inward', [
            'products' => $products,
            'availableVolumes' => $availableVolumes,
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(),
            'cashAccounts' => CashAccount::orderBy('id')->get(),
            'canManageCash' => $user && ($user->hasRole(['OWNER', 'ADMIN', 'CASHIER'])
                || $user->hasPermission('manage_cash_outflow') || $user->hasPermission('view_cash')),
            'totalAmount' => $this->calculateTotalAmount(),
        ]);
    }
}

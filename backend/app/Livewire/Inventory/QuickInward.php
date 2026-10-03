<?php

namespace App\Livewire\Inventory;

use App\Models\CashAccount;
use App\Models\ProductVariant;
use App\Models\Supplier;
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
        $variantId = $payload['variant_id'];

        foreach ($this->items as $index => $item) {
            if ($item['variant_id'] === $variantId) {
                $this->items[$index]['quantity'] += 50;
                $this->inwardMessage = "Kirim qoralamasidagi mahsulot soni oshirildi: {$payload['display_name']}";

                return;
            }
        }

        $this->items[] = [
            'variant_id' => $variantId,
            'display_name' => $payload['display_name'],
            'sku' => $payload['sku'] ?? '',
            'quantity' => 100, // Standard optom receiving batch
            'unit_cost' => 5000,
            'new_sale_price' => $payload['default_price'] ?? null,
        ];

        $this->inwardMessage = "Yangi mahsulot saqlandi va kirim ro'yxatiga qo'shildi: {$payload['display_name']}";
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
        if (! $this->quickVariantId) {
            return;
        }

        $variant = ProductVariant::with(['product', 'volume', 'balance'])->find($this->quickVariantId);
        if (! $variant) {
            return;
        }

        $variantId = $variant->id;
        $displayName = $variant->product->name.' — '.$variant->volume->name;

        foreach ($this->items as $index => $item) {
            if ($item['variant_id'] === $variantId) {
                $this->items[$index]['quantity'] += 50;
                $this->quickVariantId = null;

                return;
            }
        }

        $unitCost = ($variant->balance && $variant->balance->average_cost > 0)
            ? (int) $variant->balance->average_cost
            : 5000;

        $this->items[] = [
            'variant_id' => $variantId,
            'display_name' => $displayName,
            'sku' => $variant->sku,
            'quantity' => 100,
            'unit_cost' => $unitCost,
            'new_sale_price' => $variant->default_sale_price,
        ];

        $this->quickVariantId = null;
    }

    public function updateQuantity(int $index, $qty): void
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['quantity'] = max(1, (int) $qty);
            $this->recalculatePayment();
        }
    }

    public function updateUnitCost(int $index, $cost): void
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['unit_cost'] = max(0, (int) $cost);
            $this->recalculatePayment();
        }
    }

    public function removeItem(int $index): void
    {
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
            $this->paidAmount = (int) round($total / 2);
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
        $this->reset(['items', 'selectedSupplierId', 'selectedSupplierName', 'supplierInvoiceNumber', 'notes', 'paidAmount', 'successPurchase']);
        $this->paymentType = 'UNPAID';
        $this->operationId = Str::uuid()->toString();
        $this->errorMessage = null;
        $this->inwardMessage = 'Kirim qoralamasi tozalandi.';
    }

    public function postPurchase(ReceivePurchaseService $receiveService): void
    {
        $this->reset(['errorMessage', 'inwardMessage']);

        if (! $this->selectedSupplierId) {
            $this->errorMessage = "Iltimos, ta'minotchini tanlang!";

            return;
        }

        if (empty($this->items)) {
            $this->errorMessage = "Kirim ro'yxatida kamida bitta tovar bo'lishi shart!";

            return;
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
        $suppliers = Supplier::where('status', 'active')->orderBy('name')->get();
        $cashAccounts = CashAccount::orderBy('id')->get();
        $availableVariants = ProductVariant::with(['product', 'volume'])->where('status', 'active')->orderBy('id')->get();

        $user = Auth::user();
        $canManageCash = $user && ($user->hasRole(['OWNER', 'ADMIN', 'CASHIER']) || $user->hasPermission('manage_cash_outflow') || $user->hasPermission('view_cash'));

        return view('livewire.inventory.quick-inward', [
            'suppliers' => $suppliers,
            'cashAccounts' => $cashAccounts,
            'availableVariants' => $availableVariants,
            'canManageCash' => $canManageCash,
            'totalAmount' => $this->calculateTotalAmount(),
        ]);
    }
}

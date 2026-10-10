<?php

namespace App\Livewire\Admin;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\OpeningBalanceDocument;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\Ledger\CashAccountService;
use App\Services\Opening\OpeningBalanceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class OpeningBalancesManager extends Component
{
    use WithPagination;

    public string $activeTab = 'stock'; // stock, cash, customers, suppliers, history

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    // Stock opening
    public bool $showStockModal = false;

    public ?int $stockVariantId = null;

    public ?int $stockQuantity = null;

    public ?int $stockUnitCost = null;

    public string $stockOperationId = '';

    // Cash opening
    public bool $showCashModal = false;

    public ?int $cashAccountId = null;

    public ?int $cashAmount = null;

    public string $cashOperationId = '';

    // Customer opening
    public bool $showCustomerModal = false;

    public ?int $customerId = null;

    public string $customerBalanceType = 'DEBT'; // DEBT, ADVANCE

    public ?int $customerAmount = null;

    public string $customerOperationId = '';

    // Supplier opening
    public bool $showSupplierModal = false;

    public ?int $supplierId = null;

    public string $supplierBalanceType = 'PAYABLE'; // PAYABLE, ADVANCE

    public ?int $supplierAmount = null;

    public string $supplierOperationId = '';

    public function mount(CashAccountService $cashService): void
    {
        $user = Auth::user();
        if (! $user || (! $user->hasRole(['OWNER', 'ADMIN']) && ! $user->hasPermission('manage_settings'))) {
            abort(403, "Boshlang'ich qoldiqlarni kiritish faqat do'kon egasi yoki administrator uchun ruxsat etilgan.");
        }

        // Birlamchi kassa hisoblarini kafolatlash
        $cashService->getOrCreateAccount('CASH', 'Asosiy Kassa (Naqd)', true);
        $cashService->getOrCreateAccount('CARD', 'Terminal (Humo / Uzcard)', false);
        $cashService->getOrCreateAccount('BANK', 'Hisob-kitob varag\'i (Bank)', false);
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetErrorBag();
        $this->successMessage = null;
        $this->errorMessage = null;
        $this->resetPage();
    }

    // --- Stock Modal ---
    public function openStockModal(?int $variantId = null): void
    {
        $this->reset(['stockVariantId', 'stockQuantity', 'stockUnitCost']);
        $this->stockVariantId = $variantId;
        $this->stockOperationId = Str::uuid()->toString();
        $this->showStockModal = true;
    }

    public function submitStockOpening(OpeningBalanceService $openingService): void
    {
        $this->validate([
            'stockVariantId' => 'required|exists:product_variants,id',
            'stockQuantity' => 'required|integer|min:1',
            'stockUnitCost' => 'required|integer|min:0',
        ], [
            'stockVariantId.required' => 'Mahsulot variantini tanlang.',
            'stockQuantity.min' => 'Miqdor kamida 1 dona bo\'lishi shart.',
            'stockUnitCost.min' => 'Tannarx manfiy bo\'lishi mumkin emas.',
        ]);

        try {
            $res = $openingService->recordStockOpening(
                productVariantId: $this->stockVariantId,
                quantity: $this->stockQuantity,
                unitCost: $this->stockUnitCost,
                operationId: $this->stockOperationId,
                userId: Auth::id()
            );

            $this->successMessage = "Boshlang'ich ombor qoldig'i kiritildi: #{$res['document_number']}";
            $this->showStockModal = false;
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    // --- Cash Modal ---
    public function openCashModal(?int $accountId = null): void
    {
        $this->cashAccountId = $accountId;
        $this->cashAmount = null;
        $this->cashOperationId = Str::uuid()->toString();
        $this->showCashModal = true;
    }

    public function submitCashOpening(OpeningBalanceService $openingService): void
    {
        $this->validate([
            'cashAccountId' => 'required|exists:cash_accounts,id',
            'cashAmount' => 'required|integer|min:1',
        ], [
            'cashAccountId.required' => 'Kassa hisobini tanlang.',
            'cashAmount.min' => 'Summa kamida 1 so\'m bo\'lishi kerak.',
        ]);

        try {
            $res = $openingService->recordCashOpening(
                cashAccountId: $this->cashAccountId,
                amount: $this->cashAmount,
                operationId: $this->cashOperationId,
                userId: Auth::id()
            );

            $this->successMessage = "Boshlang'ich kassa qoldig'i kiritildi: #{$res['document_number']}";
            $this->showCashModal = false;
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    // --- Customer Modal ---
    public function openCustomerModal(): void
    {
        $this->reset(['customerId', 'customerAmount']);
        $this->customerBalanceType = 'DEBT';
        $this->customerOperationId = Str::uuid()->toString();
        $this->showCustomerModal = true;
    }

    public function submitCustomerOpening(OpeningBalanceService $openingService): void
    {
        $this->validate([
            'customerId' => 'required|exists:customers,id',
            'customerBalanceType' => 'required|in:DEBT,ADVANCE',
            'customerAmount' => 'required|integer|min:1',
        ], [
            'customerId.required' => 'Mijozni tanlang.',
            'customerAmount.min' => 'Summa kamida 1 so\'m bo\'lishi kerak.',
        ]);

        $signedAmount = $this->customerBalanceType === 'DEBT'
            ? $this->customerAmount
            : -$this->customerAmount;

        try {
            $res = $openingService->recordCustomerOpening(
                customerId: $this->customerId,
                signedAmount: $signedAmount,
                operationId: $this->customerOperationId,
                userId: Auth::id()
            );

            $typeLabel = $this->customerBalanceType === 'DEBT' ? 'qarz' : 'avans';
            $this->successMessage = "Mijoz boshlang'ich {$typeLabel}i kiritildi: #{$res['document_number']}";
            $this->showCustomerModal = false;
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    // --- Supplier Modal ---
    public function openSupplierModal(): void
    {
        $this->reset(['supplierId', 'supplierAmount']);
        $this->supplierBalanceType = 'PAYABLE';
        $this->supplierOperationId = Str::uuid()->toString();
        $this->showSupplierModal = true;
    }

    public function submitSupplierOpening(OpeningBalanceService $openingService): void
    {
        $this->validate([
            'supplierId' => 'required|exists:suppliers,id',
            'supplierBalanceType' => 'required|in:PAYABLE,ADVANCE',
            'supplierAmount' => 'required|integer|min:1',
        ], [
            'supplierId.required' => 'Ta\'minotchini tanlang.',
            'supplierAmount.min' => 'Summa kamida 1 so\'m bo\'lishi kerak.',
        ]);

        $signedAmount = $this->supplierBalanceType === 'PAYABLE'
            ? $this->supplierAmount
            : -$this->supplierAmount;

        try {
            $res = $openingService->recordSupplierOpening(
                supplierId: $this->supplierId,
                signedAmount: $signedAmount,
                operationId: $this->supplierOperationId,
                userId: Auth::id()
            );

            $typeLabel = $this->supplierBalanceType === 'PAYABLE' ? 'qarzimiz' : 'avansimiz';
            $this->successMessage = "Ta'minotchi boshlang'ich {$typeLabel} kiritildi: #{$res['document_number']}";
            $this->showSupplierModal = false;
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $variants = ProductVariant::with(['product', 'volume', 'balance'])
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        $cashAccounts = CashAccount::orderBy('id')->get();

        $customers = Customer::orderBy('name')->paginate(15);
        $suppliers = Supplier::orderBy('name')->paginate(15);

        $documents = OpeningBalanceDocument::with('creator')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.admin.opening-balances', [
            'variants' => $variants,
            'cashAccounts' => $cashAccounts,
            'customers' => $customers,
            'suppliers' => $suppliers,
            'documents' => $documents,
        ]);
    }
}

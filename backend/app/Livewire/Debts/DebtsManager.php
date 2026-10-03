<?php

namespace App\Livewire\Debts;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\Accounting\StatementService;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Payments\CustomerPaymentService;
use App\Services\Payments\SupplierPaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class DebtsManager extends Component
{
    use WithPagination;

    // Asosiy ko'rinish va tablar
    public string $activeTab = 'customers'; // 'customers' yoki 'suppliers'

    public string $search = '';

    public string $statusFilter = 'all'; // 'all', 'debtors', 'advance', 'overdue'

    // Mijoz to'lovini qabul qilish modali
    public bool $showCustomerPaymentModal = false;

    public ?int $selectedCustomerId = null;

    public ?string $paymentAmount = '';

    public ?int $paymentCashAccountId = null;

    public string $paymentMethod = 'CASH';

    public string $paymentNotes = '';

    public bool $confirmExcessAsAdvance = false;

    public ?string $paymentOperationId = null;

    // Ta'minotchi to'lov modali
    public bool $showSupplierPaymentModal = false;

    public ?int $selectedSupplierId = null;

    public ?string $supplierPaymentAmount = '';

    public ?int $supplierPaymentCashAccountId = null;

    public string $supplierPaymentMethod = 'CASH';

    public string $supplierPaymentNotes = '';

    public bool $supplierConfirmExcessAsAdvance = false;

    public ?string $supplierPaymentOperationId = null;

    // Hisob ko'chirmasi (Statement) modali
    public bool $showStatementModal = false;

    public string $statementPartyType = 'CUSTOMER';

    public ?int $statementPartyId = null;

    public ?string $statementStartDate = null;

    public ?string $statementEndDate = null;

    public ?array $statementData = null;

    // Sozlamalar modali (Kredit limiti va to'lov sanasi)
    public bool $showSettingsModal = false;

    public string $settingsPartyType = 'CUSTOMER';

    public ?int $settingsPartyId = null;

    public string $settingsPartyName = '';

    public ?string $settingsDebtLimit = '0';

    public ?string $settingsPaymentDueDate = null;

    // Bildirishnoma va xatoliklar
    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    protected $queryString = [
        'activeTab' => ['except' => 'customers'],
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        $defaultAccount = CashAccount::where('is_default', true)->first() ?: CashAccount::first();
        if ($defaultAccount) {
            $this->paymentCashAccountId = $defaultAccount->id;
            $this->supplierPaymentCashAccountId = $defaultAccount->id;
        }

        $this->statementStartDate = Carbon::now('Asia/Tashkent')->startOfMonth()->format('Y-m-d');
        $this->statementEndDate = Carbon::now('Asia/Tashkent')->format('Y-m-d');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->search = '';
        $this->statusFilter = 'all';
        $this->resetPage();
        $this->clearMessages();
    }

    public function clearMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    // ==========================================
    // MIJOZ TO'LOVI
    // ==========================================

    public function openCustomerPaymentModal(int $customerId): void
    {
        $this->clearMessages();
        $customer = Customer::findOrFail($customerId);

        $this->selectedCustomerId = $customerId;
        $currentDebt = (int) $customer->current_debt;
        $this->paymentAmount = $currentDebt > 0 ? (string) $currentDebt : '';
        $this->paymentMethod = 'CASH';
        $this->paymentNotes = '';
        $this->confirmExcessAsAdvance = false;
        $this->paymentOperationId = (string) Str::uuid();

        if (! $this->paymentCashAccountId) {
            $this->paymentCashAccountId = CashAccount::where('is_default', true)->value('id') ?: CashAccount::value('id');
        }

        $this->showCustomerPaymentModal = true;
    }

    public function setQuickCustomerAmount(int $amount): void
    {
        $this->paymentAmount = (string) $amount;
    }

    public function setFullCustomerDebt(): void
    {
        if ($this->selectedCustomerId) {
            $customer = Customer::find($this->selectedCustomerId);
            if ($customer && $customer->current_debt > 0) {
                $this->paymentAmount = (string) $customer->current_debt;
            }
        }
    }

    public function submitCustomerPayment(CustomerPaymentService $service): void
    {
        $this->clearMessages();

        $amount = (int) str_replace([' ', ','], '', $this->paymentAmount);
        if ($amount <= 0) {
            $this->errorMessage = "To'lov summasi 0 dan katta bo'lishi shart!";

            return;
        }

        if (! $this->paymentCashAccountId) {
            $this->errorMessage = 'Kassa hisobi tanlanishi shart!';

            return;
        }

        try {
            $result = $service->execute(
                customerId: $this->selectedCustomerId,
                amount: $amount,
                cashAccountId: $this->paymentCashAccountId,
                paymentMethod: $this->paymentMethod,
                operationId: $this->paymentOperationId,
                userId: Auth::id(),
                notes: $this->paymentNotes ?: null,
                confirmExcessAsAdvance: $this->confirmExcessAsAdvance
            );

            $this->showCustomerPaymentModal = false;
            $paidFormatted = number_format($amount, 0, '.', ' ');
            $newDebtFormatted = number_format(abs($result['new_debt']), 0, '.', ' ');

            if ($result['is_advance']) {
                $this->successMessage = "To'lov qabul qilindi: {$paidFormatted} so'm. Hujjat #{$result['payment_number']}. Mijoz avansi: {$newDebtFormatted} so'm.";
            } else {
                $this->successMessage = "To'lov qabul qilindi: {$paidFormatted} so'm. Hujjat #{$result['payment_number']}. Qolgan qarz: {$newDebtFormatted} so'm.";
            }
        } catch (OperationValidationException $e) {
            if ($e->getErrorCode() === 'EXCESS_PAYMENT_REQUIRES_ADVANCE_CONFIRMATION') {
                $this->errorMessage = $e->getMessage();
            } else {
                $this->errorMessage = $e->getMessage();
            }
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    // ==========================================
    // TA'MINOTCHI TO'LOVI
    // ==========================================

    public function openSupplierPaymentModal(int $supplierId): void
    {
        $this->clearMessages();
        $supplier = Supplier::findOrFail($supplierId);

        $this->selectedSupplierId = $supplierId;
        $currentPayable = (int) $supplier->balance;
        $this->supplierPaymentAmount = $currentPayable > 0 ? (string) $currentPayable : '';
        $this->supplierPaymentMethod = 'CASH';
        $this->supplierPaymentNotes = '';
        $this->supplierConfirmExcessAsAdvance = false;
        $this->supplierPaymentOperationId = (string) Str::uuid();

        if (! $this->supplierPaymentCashAccountId) {
            $this->supplierPaymentCashAccountId = CashAccount::where('is_default', true)->value('id') ?: CashAccount::value('id');
        }

        $this->showSupplierPaymentModal = true;
    }

    public function setQuickSupplierAmount(int $amount): void
    {
        $this->supplierPaymentAmount = (string) $amount;
    }

    public function setFullSupplierPayable(): void
    {
        if ($this->selectedSupplierId) {
            $supplier = Supplier::find($this->selectedSupplierId);
            if ($supplier && $supplier->balance > 0) {
                $this->supplierPaymentAmount = (string) $supplier->balance;
            }
        }
    }

    public function submitSupplierPayment(SupplierPaymentService $service): void
    {
        $this->clearMessages();

        $amount = (int) str_replace([' ', ','], '', $this->supplierPaymentAmount);
        if ($amount <= 0) {
            $this->errorMessage = "To'lov summasi 0 dan katta bo'lishi shart!";

            return;
        }

        if (! $this->supplierPaymentCashAccountId) {
            $this->errorMessage = 'Kassa hisobi tanlanishi shart!';

            return;
        }

        try {
            $result = $service->execute(
                supplierId: $this->selectedSupplierId,
                amount: $amount,
                cashAccountId: $this->supplierPaymentCashAccountId,
                paymentMethod: $this->supplierPaymentMethod,
                operationId: $this->supplierPaymentOperationId,
                userId: Auth::id(),
                notes: $this->supplierPaymentNotes ?: null,
                confirmExcessAsAdvance: $this->supplierConfirmExcessAsAdvance
            );

            $this->showSupplierPaymentModal = false;
            $paidFormatted = number_format($amount, 0, '.', ' ');
            $newPayableFormatted = number_format(abs($result['new_payable']), 0, '.', ' ');

            if ($result['is_advance']) {
                $this->successMessage = "Ta'minotchiga to'lov amalga oshirildi: {$paidFormatted} so'm. Hujjat #{$result['payment_number']}. Bizning avansimiz: {$newPayableFormatted} so'm.";
            } else {
                $this->successMessage = "Ta'minotchiga to'lov amalga oshirildi: {$paidFormatted} so'm. Hujjat #{$result['payment_number']}. Qolgan qarzimiz: {$newPayableFormatted} so'm.";
            }
        } catch (OperationValidationException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    // ==========================================
    // HISOB KO'CHIRMASI (STATEMENT)
    // ==========================================

    public function openStatementModal(string $partyType, int $partyId): void
    {
        $this->statementPartyType = $partyType;
        $this->statementPartyId = $partyId;
        $this->loadStatement();
        $this->showStatementModal = true;
    }

    public function loadStatement(): void
    {
        if (! $this->statementPartyId) {
            return;
        }

        $statementService = app(StatementService::class);

        if ($this->statementPartyType === 'CUSTOMER') {
            $this->statementData = $statementService->getCustomerStatement(
                customerId: $this->statementPartyId,
                startDate: $this->statementStartDate,
                endDate: $this->statementEndDate
            );
        } else {
            $this->statementData = $statementService->getSupplierStatement(
                supplierId: $this->statementPartyId,
                startDate: $this->statementStartDate,
                endDate: $this->statementEndDate
            );
        }
    }

    public function setStatementPreset(string $preset): void
    {
        $now = Carbon::now('Asia/Tashkent');
        match ($preset) {
            'today' => [
                $this->statementStartDate = $now->format('Y-m-d'),
                $this->statementEndDate = $now->format('Y-m-d'),
            ],
            'yesterday' => [
                $this->statementStartDate = $now->copy()->subDay()->format('Y-m-d'),
                $this->statementEndDate = $now->copy()->subDay()->format('Y-m-d'),
            ],
            'this_week' => [
                $this->statementStartDate = $now->copy()->startOfWeek()->format('Y-m-d'),
                $this->statementEndDate = $now->format('Y-m-d'),
            ],
            'this_month' => [
                $this->statementStartDate = $now->copy()->startOfMonth()->format('Y-m-d'),
                $this->statementEndDate = $now->format('Y-m-d'),
            ],
            'all' => [
                $this->statementStartDate = '2026-01-01',
                $this->statementEndDate = $now->format('Y-m-d'),
            ],
            default => null,
        };

        $this->loadStatement();
    }

    // ==========================================
    // SOZLAMALAR (KREDIT LIMITI VA TO'LOV SANASI)
    // ==========================================

    public function openSettingsModal(string $partyType, int $partyId): void
    {
        $this->settingsPartyType = $partyType;
        $this->settingsPartyId = $partyId;

        if ($partyType === 'CUSTOMER') {
            $customer = Customer::findOrFail($partyId);
            $this->settingsPartyName = $customer->display_name;
            $this->settingsDebtLimit = (string) $customer->debt_limit;
            $this->settingsPaymentDueDate = $customer->payment_due_date ? $customer->payment_due_date->format('Y-m-d') : null;
        } else {
            $supplier = Supplier::findOrFail($partyId);
            $this->settingsPartyName = $supplier->display_name;
            $this->settingsDebtLimit = (string) $supplier->credit_limit;
            $this->settingsPaymentDueDate = $supplier->payment_due_date ? $supplier->payment_due_date->format('Y-m-d') : null;
        }

        $this->showSettingsModal = true;
    }

    public function saveSettings(): void
    {
        $debtLimit = (int) str_replace([' ', ','], '', $this->settingsDebtLimit ?? '0');
        $dueDate = $this->settingsPaymentDueDate ?: null;

        if ($this->settingsPartyType === 'CUSTOMER') {
            $customer = Customer::findOrFail($this->settingsPartyId);
            $customer->update([
                'debt_limit' => max(0, $debtLimit),
                'payment_due_date' => $dueDate,
            ]);
            $this->successMessage = "Mijoz ({$customer->name}) kredit limiti va to'lov sanasi yangilandi.";
        } else {
            $supplier = Supplier::findOrFail($this->settingsPartyId);
            $supplier->update([
                'credit_limit' => max(0, $debtLimit),
                'payment_due_date' => $dueDate,
            ]);
            $this->successMessage = "Ta'minotchi ({$supplier->name}) kredit limiti va to'lov sanasi yangilandi.";
        }

        $this->showSettingsModal = false;
    }

    // ==========================================
    // RENDER
    // ==========================================

    public function render()
    {
        $cashAccounts = CashAccount::orderBy('name')->get();

        // 1. Mijozlar statistikasi (Alohida - avans qarzni yashirmaydi!)
        $customerStats = [
            'total_debt' => (int) Customer::where('current_debt', '>', 0)->sum('current_debt'),
            'total_advance' => abs((int) Customer::where('current_debt', '<', 0)->sum('current_debt')),
            'net_balance' => (int) Customer::sum('current_debt'),
            'debtors_count' => Customer::where('current_debt', '>', 0)->count(),
            'advance_count' => Customer::where('current_debt', '<', 0)->count(),
            'overdue_count' => Customer::where('current_debt', '>', 0)
                ->whereNotNull('payment_due_date')
                ->where('payment_due_date', '<', Carbon::now('Asia/Tashkent')->format('Y-m-d'))
                ->count(),
        ];

        // 2. Ta'minotchilar statistikasi (Alohida - avans qarzni yashirmaydi!)
        $supplierStats = [
            'total_payable' => (int) Supplier::where('balance', '>', 0)->sum('balance'),
            'total_prepaid' => abs((int) Supplier::where('balance', '<', 0)->sum('balance')),
            'net_balance' => (int) Supplier::sum('balance'),
            'payables_count' => Supplier::where('balance', '>', 0)->count(),
            'prepaid_count' => Supplier::where('balance', '<', 0)->count(),
            'overdue_count' => Supplier::where('balance', '>', 0)
                ->whereNotNull('payment_due_date')
                ->where('payment_due_date', '<', Carbon::now('Asia/Tashkent')->format('Y-m-d'))
                ->count(),
        ];

        // 3. Tab bo'yicha ma'lumotlar ro'yxati
        $items = null;
        if ($this->activeTab === 'customers') {
            $query = Customer::query();

            if (! empty($this->search)) {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'ilike', $term)
                        ->orWhere('store_name', 'ilike', $term)
                        ->orWhere('phone', 'ilike', $term)
                        ->orWhere('address', 'ilike', $term);
                });
            }

            match ($this->statusFilter) {
                'debtors' => $query->where('current_debt', '>', 0),
                'advance' => $query->where('current_debt', '<', 0),
                'overdue' => $query->where('current_debt', '>', 0)
                    ->whereNotNull('payment_due_date')
                    ->where('payment_due_date', '<', Carbon::now('Asia/Tashkent')->format('Y-m-d')),
                default => null,
            };

            $items = $query->orderByDesc('current_debt')->paginate(15);
        } else {
            $query = Supplier::query();

            if (! empty($this->search)) {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'ilike', $term)
                        ->orWhere('company_name', 'ilike', $term)
                        ->orWhere('phone', 'ilike', $term)
                        ->orWhere('address', 'ilike', $term);
                });
            }

            match ($this->statusFilter) {
                'debtors' => $query->where('balance', '>', 0),
                'advance' => $query->where('balance', '<', 0),
                'overdue' => $query->where('balance', '>', 0)
                    ->whereNotNull('payment_due_date')
                    ->where('payment_due_date', '<', Carbon::now('Asia/Tashkent')->format('Y-m-d')),
                default => null,
            };

            $items = $query->orderByDesc('balance')->paginate(15);
        }

        $selectedCustomer = $this->selectedCustomerId ? Customer::find($this->selectedCustomerId) : null;
        $selectedSupplier = $this->selectedSupplierId ? Supplier::find($this->selectedSupplierId) : null;

        return view('livewire.debts.debts-manager', [
            'items' => $items,
            'customerStats' => $customerStats,
            'supplierStats' => $supplierStats,
            'cashAccounts' => $cashAccounts,
            'selectedCustomer' => $selectedCustomer,
            'selectedSupplier' => $selectedSupplier,
        ]);
    }
}

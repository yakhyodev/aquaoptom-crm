<?php

namespace App\Livewire\Cash;

use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Services\Ledger\CashSessionService;
use App\Services\Ledger\CashTransferService;
use App\Services\Ledger\ExpenseService;
use App\Services\Ledger\OwnerFundsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class CashManager extends Component
{
    use WithPagination;

    // Active View Tab: 'movements' | 'sessions'
    public string $activeTab = 'movements';

    // Filters for movements
    public ?int $filterAccountId = null;

    public string $filterDirection = 'ALL'; // ALL, IN, OUT

    public string $filterType = 'ALL';

    public string $filterDateRange = 'today'; // today, 7days, month, all

    public string $search = '';

    // Active modals
    public bool $showOpenSessionModal = false;

    public bool $showCloseSessionModal = false;

    public bool $showExpenseModal = false;

    public bool $showTransferModal = false;

    public bool $showOwnerFundsModal = false;

    public bool $showApproveDiscrepancyModal = false;

    // Open Session Form
    public ?int $openAccountId = null;

    public int $openBalance = 0;

    public string $openNotes = '';

    // Close Session Form
    public ?int $closeSessionId = null;

    public int $closeExpectedBalance = 0;

    public int $closeActualBalance = 0;

    public int $closeDifference = 0;

    public string $closeDifferenceReason = '';

    public bool $closeIsProvisional = false;

    public string $closeNotes = '';

    // Expense Form
    public ?int $expenseAccountId = null;

    public int $expenseAmount = 0;

    public string $expenseCategory = 'TRANSPORT';

    public string $expenseDescription = '';

    // Transfer Form
    public ?int $transferFromAccountId = null;

    public ?int $transferToAccountId = null;

    public int $transferAmount = 0;

    public string $transferDescription = '';

    // Owner Funds Form
    public ?int $ownerAccountId = null;

    public string $ownerFundType = 'DEPOSIT'; // DEPOSIT, DRAW

    public int $ownerAmount = 0;

    public string $ownerDescription = '';

    // Discrepancy Approval Form
    public ?int $selectedSessionId = null;

    public ?CashSession $selectedSession = null;

    public bool $adjustLedgerOnApproval = true;

    // Flash message
    public ?string $feedbackMessage = null;

    public ?string $feedbackType = 'success';

    protected $queryString = [
        'activeTab' => ['except' => 'movements'],
        'filterAccountId' => ['except' => null],
        'filterDateRange' => ['except' => 'today'],
    ];

    public function mount(): void
    {
        $defaultAccount = CashAccount::where('is_default', true)->first() ?: CashAccount::first();
        if ($defaultAccount) {
            $this->openAccountId = $defaultAccount->id;
            $this->expenseAccountId = $defaultAccount->id;
            $this->ownerAccountId = $defaultAccount->id;
            $this->transferFromAccountId = $defaultAccount->id;

            $otherAccount = CashAccount::where('id', '!=', $defaultAccount->id)->first();
            if ($otherAccount) {
                $this->transferToAccountId = $otherAccount->id;
            }
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    // --- Smena Ochish / Yopish ---
    public function openNewSessionModal(?int $accountId = null): void
    {
        $accId = $accountId ?: $this->openAccountId;
        $account = CashAccount::find($accId);
        $this->openAccountId = $accId;
        $this->openBalance = $account ? (int) $account->balance : 0;
        $this->openNotes = '';
        $this->showOpenSessionModal = true;
    }

    public function submitOpenSession(CashSessionService $sessionService): void
    {
        $this->validate([
            'openAccountId' => 'required|exists:cash_accounts,id',
            'openBalance' => 'required|integer|min:0',
        ]);

        try {
            $session = $sessionService->openSession(
                cashAccountId: $this->openAccountId,
                userId: Auth::id(),
                openingBalance: $this->openBalance,
                notes: $this->openNotes ?: null
            );

            $this->showOpenSessionModal = false;
            $this->feedbackType = 'success';
            $this->feedbackMessage = "Yangi smena (#{$session->session_number}) muvaffaqiyatli ochildi!";
        } catch (\Exception $e) {
            $this->feedbackType = 'error';
            $this->feedbackMessage = $e->getMessage();
        }
    }

    public function openCloseModal(int $sessionId, CashSessionService $sessionService): void
    {
        $session = CashSession::findOrFail($sessionId);
        $this->closeSessionId = $session->id;
        $this->closeExpectedBalance = $sessionService->calculateExpectedBalance($session);
        $this->closeActualBalance = $this->closeExpectedBalance;
        $this->closeDifference = 0;
        $this->closeDifferenceReason = '';
        $this->closeIsProvisional = false;
        $this->closeNotes = '';
        $this->showCloseSessionModal = true;
    }

    public function updatedCloseActualBalance(): void
    {
        $this->closeDifference = (int) $this->closeActualBalance - (int) $this->closeExpectedBalance;
    }

    public function submitCloseSession(CashSessionService $sessionService): void
    {
        $this->closeDifference = (int) $this->closeActualBalance - (int) $this->closeExpectedBalance;

        if ($this->closeDifference !== 0 && empty(trim($this->closeDifferenceReason))) {
            $this->addError('closeDifferenceReason', 'Kassa sanalganda farq aniqlandi. Farq sababi ko\'rsatilishi shart!');

            return;
        }

        try {
            $session = $sessionService->closeSession(
                sessionId: $this->closeSessionId,
                userId: Auth::id(),
                actualClosingBalance: $this->closeActualBalance,
                differenceReason: $this->closeDifferenceReason ?: null,
                isProvisional: $this->closeIsProvisional,
                notes: $this->closeNotes ?: null
            );

            $this->showCloseSessionModal = false;
            $this->feedbackType = 'success';
            $this->feedbackMessage = "Smena (#{$session->session_number}) muvaffaqiyatli yopildi!";
        } catch (\Exception $e) {
            $this->feedbackType = 'error';
            $this->feedbackMessage = $e->getMessage();
        }
    }

    // --- Farqni tasdiqlash ---
    public function openApproveDiscrepancyModal(int $sessionId): void
    {
        $this->selectedSessionId = $sessionId;
        $this->selectedSession = CashSession::with(['account', 'opener', 'closer'])->findOrFail($sessionId);
        $this->adjustLedgerOnApproval = true;
        $this->showApproveDiscrepancyModal = true;
    }

    public function submitApproveDiscrepancy(CashSessionService $sessionService): void
    {
        try {
            $sessionService->approveDifference(
                sessionId: $this->selectedSessionId,
                approverId: Auth::id(),
                adjustCashLedger: $this->adjustLedgerOnApproval
            );

            $this->showApproveDiscrepancyModal = false;
            $this->feedbackType = 'success';
            $this->feedbackMessage = 'Kassa farqi muvaffaqiyatli tasdiqlandi va rasmiy pul daftariga kiritildi!';
        } catch (\Exception $e) {
            $this->feedbackType = 'error';
            $this->feedbackMessage = $e->getMessage();
        }
    }

    public function submitRejectDiscrepancy(CashSessionService $sessionService): void
    {
        try {
            $sessionService->rejectDifference(
                sessionId: $this->selectedSessionId,
                approverId: Auth::id()
            );

            $this->showApproveDiscrepancyModal = false;
            $this->feedbackType = 'success';
            $this->feedbackMessage = 'Kassa farqi rad etildi (balans tuzatishsiz).';
        } catch (\Exception $e) {
            $this->feedbackType = 'error';
            $this->feedbackMessage = $e->getMessage();
        }
    }

    // --- Xarajat ---
    public function openExpenseModal(): void
    {
        $this->expenseAmount = 0;
        $this->expenseCategory = 'TRANSPORT';
        $this->expenseDescription = '';
        $this->showExpenseModal = true;
    }

    public function submitExpense(ExpenseService $expenseService): void
    {
        $this->validate([
            'expenseAccountId' => 'required|exists:cash_accounts,id',
            'expenseAmount' => 'required|integer|min:100',
            'expenseCategory' => 'required|string',
        ]);

        try {
            $result = $expenseService->createExpense(
                cashAccountId: $this->expenseAccountId,
                amount: $this->expenseAmount,
                category: $this->expenseCategory,
                description: $this->expenseDescription ?: null,
                userId: Auth::id()
            );

            $this->showExpenseModal = false;
            $this->feedbackType = 'success';
            $this->feedbackMessage = "Operatsion xarajat (#{$result['expense_number']}) muvaffaqiyatli saqlandi!";
        } catch (\Exception $e) {
            $this->feedbackType = 'error';
            $this->feedbackMessage = $e->getMessage();
        }
    }

    // --- O'tkazma ---
    public function openTransferModal(): void
    {
        $this->transferAmount = 0;
        $this->transferDescription = '';
        $this->showTransferModal = true;
    }

    public function submitTransfer(CashTransferService $transferService): void
    {
        $this->validate([
            'transferFromAccountId' => 'required|exists:cash_accounts,id',
            'transferToAccountId' => 'required|exists:cash_accounts,id|different:transferFromAccountId',
            'transferAmount' => 'required|integer|min:100',
        ]);

        try {
            $transferService->transfer(
                fromAccountId: $this->transferFromAccountId,
                toAccountId: $this->transferToAccountId,
                amount: $this->transferAmount,
                description: $this->transferDescription ?: null,
                userId: Auth::id()
            );

            $this->showTransferModal = false;
            $this->feedbackType = 'success';
            $this->feedbackMessage = 'Hisoblararo pul o\'tkazmasi muvaffaqiyatli bajarildi!';
        } catch (\Exception $e) {
            $this->feedbackType = 'error';
            $this->feedbackMessage = $e->getMessage();
        }
    }

    // --- Egasi mablag'i ---
    public function openOwnerFundsModal(string $type = 'DEPOSIT'): void
    {
        $this->ownerFundType = $type;
        $this->ownerAmount = 0;
        $this->ownerDescription = '';
        $this->showOwnerFundsModal = true;
    }

    public function submitOwnerFunds(OwnerFundsService $ownerFundsService): void
    {
        $this->validate([
            'ownerAccountId' => 'required|exists:cash_accounts,id',
            'ownerAmount' => 'required|integer|min:100',
        ]);

        try {
            if ($this->ownerFundType === 'DEPOSIT') {
                $ownerFundsService->deposit(
                    cashAccountId: $this->ownerAccountId,
                    amount: $this->ownerAmount,
                    description: $this->ownerDescription ?: null,
                    userId: Auth::id()
                );
                $this->feedbackMessage = "Do'kon egasi mablag'i kassaga muvaffaqiyatli kiritildi!";
            } else {
                $ownerFundsService->withdraw(
                    cashAccountId: $this->ownerAccountId,
                    amount: $this->ownerAmount,
                    description: $this->ownerDescription ?: null,
                    userId: Auth::id()
                );
                $this->feedbackMessage = "Do'kon egasi mablag'i (Owner Draw) chiqarildi! (Operatsion foydaga ta'sir qilmaydi)";
            }

            $this->showOwnerFundsModal = false;
            $this->feedbackType = 'success';
        } catch (\Exception $e) {
            $this->feedbackType = 'error';
            $this->feedbackMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $accounts = CashAccount::orderBy('id')->get();
        $totalBalance = (int) $accounts->sum('balance');

        // Ochiq smena
        $activeSession = CashSession::with(['account', 'opener'])
            ->where('status', 'OPEN')
            ->first();

        $activeSessionExpected = 0;
        if ($activeSession) {
            $activeSessionExpected = app(CashSessionService::class)->calculateExpectedBalance($activeSession);
        }

        // Movements query
        $movementsQuery = CashMovement::with(['account', 'creator', 'session'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($this->filterAccountId) {
            $movementsQuery->where('cash_account_id', $this->filterAccountId);
        }

        if ($this->filterDirection !== 'ALL') {
            $movementsQuery->where('direction', $this->filterDirection);
        }

        if ($this->filterType !== 'ALL') {
            $movementsQuery->where('type', $this->filterType);
        }

        if ($this->search) {
            $s = '%'.trim($this->search).'%';
            $movementsQuery->where(function ($q) use ($s) {
                $q->where('description', 'like', $s)
                    ->orWhere('operation_id', 'like', $s);
            });
        }

        // Date range filter
        $now = Carbon::now();
        if ($this->filterDateRange === 'today') {
            $movementsQuery->whereDate('created_at', $now->toDateString());
        } elseif ($this->filterDateRange === '7days') {
            $movementsQuery->where('created_at', '>=', $now->copy()->subDays(7)->startOfDay());
        } elseif ($this->filterDateRange === 'month') {
            $movementsQuery->where('created_at', '>=', $now->copy()->startOfMonth());
        }

        $movements = $movementsQuery->paginate(20);

        // Sessions query
        $sessions = CashSession::with(['account', 'opener', 'closer', 'approver'])
            ->orderByDesc('opened_at')
            ->paginate(15);

        return view('livewire.cash.cash-manager', [
            'accounts' => $accounts,
            'totalBalance' => $totalBalance,
            'activeSession' => $activeSession,
            'activeSessionExpected' => $activeSessionExpected,
            'movements' => $movements,
            'sessions' => $sessions,
        ]);
    }
}

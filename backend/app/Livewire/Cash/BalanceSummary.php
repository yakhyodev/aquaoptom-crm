<?php

namespace App\Livewire\Cash;

use App\Models\CashAccount;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class BalanceSummary extends Component
{
    #[On('refresh-dashboard')]
    public function refreshBalance(): void {}

    public function render()
    {
        Gate::authorize('view_cash');

        return view('livewire.cash.balance-summary', [
            'balance' => (int) CashAccount::sum('balance'),
        ]);
    }
}

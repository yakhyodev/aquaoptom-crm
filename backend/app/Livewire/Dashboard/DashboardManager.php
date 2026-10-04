<?php

namespace App\Livewire\Dashboard;

use App\Services\Dashboard\DashboardQueryService;
use Livewire\Attributes\On;
use Livewire\Component;

class DashboardManager extends Component
{
    public string $period = 'today';

    public string $customStart = '';

    public string $customEnd = '';

    // Ochiq savat / modal holati saqlanishini tekshirish uchun qoralama maydoni
    public string $draftNote = '';

    public bool $showDraftModal = false;

    public function mount(): void
    {
        $this->customStart = now()->startOfMonth()->format('Y-m-d');
        $this->customEnd = now()->format('Y-m-d');
    }

    public function setPeriod(string $p): void
    {
        $this->period = $p;
    }

    public function applyCustomDates(): void
    {
        $this->period = 'custom';
    }

    public function toggleDraftModal(): void
    {
        $this->showDraftModal = ! $this->showDraftModal;
    }

    /**
     * Reverb/Echo yoki ichki event orqali real vaqtda kartalarni yangilash.
     * Foydalanuvchining ochiq modal yoki form maydonlari buzilmaydi.
     */
    #[On('echo-private:store.operations,SaleCreatedBroadcastEvent')]
    #[On('echo-private:store.operations,PurchaseReceivedBroadcastEvent')]
    #[On('echo-private:store.operations,PaymentRecordedBroadcastEvent')]
    #[On('echo-private:store.operations,StockChangedBroadcastEvent')]
    #[On('refresh-dashboard')]
    public function refreshDashboard(): void
    {
        // Livewire re-render qiladi, lekin $draftNote va $showDraftModal saqlanib qoladi
    }

    public function render(DashboardQueryService $service)
    {
        $data = $service->getDashboardData(
            user: auth()->user(),
            period: $this->period,
            customStart: $this->period === 'custom' ? $this->customStart : null,
            customEnd: $this->period === 'custom' ? $this->customEnd : null
        );

        return view('livewire.dashboard.dashboard-manager', [
            'dashboard' => $data,
        ]);
    }
}

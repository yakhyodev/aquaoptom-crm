<?php

namespace App\Livewire\Dashboard;

use App\Services\Dashboard\DashboardQueryService;
use App\Services\Reports\ReportPeriod;
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
        $this->setPeriod('today');
    }

    public function setPeriod(string $p): void
    {
        abort_unless(in_array($p, ['today', 'yesterday', 'this_week', 'this_month', 'last_month', 'all_time'], true), 422);
        $range = ReportPeriod::resolve($p);
        $this->period = $p;
        $this->customStart = $p === 'all_time' ? '2020-01-01' : $range['from_date'];
        $this->customEnd = $range['to_date'];
        $this->resetValidation();
    }

    public function applyCustomDates(): void
    {
        $this->validate([
            'customStart' => ['required', 'date_format:Y-m-d'],
            'customEnd' => ['required', 'date_format:Y-m-d', 'after_or_equal:customStart'],
        ], [
            'customStart.required' => 'Boshlanish sanasini tanlang.',
            'customStart.date_format' => 'Boshlanish sanasini to‘g‘ri kiriting.',
            'customEnd.required' => 'Tugash sanasini tanlang.',
            'customEnd.date_format' => 'Tugash sanasini to‘g‘ri kiriting.',
            'customEnd.after_or_equal' => 'Tugash sanasi boshlanish sanasidan oldin bo‘lmasin.',
        ]);
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

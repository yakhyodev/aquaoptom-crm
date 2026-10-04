<?php

namespace App\Livewire\Sales;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Services\Reports\ExportService;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportQueryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class SalesHistory extends Component
{
    use WithPagination;

    // Filtrlar
    public string $period = 'today'; // today, yesterday, this_week, this_month, last_month, month, custom

    public ?string $fromDate = null;

    public ?string $toDate = null;

    public ?string $month = null;

    public ?int $customerId = null;

    public ?int $staffId = null;

    public ?string $paymentMethod = null;

    public ?string $status = null;

    public string $search = '';

    // Modal
    public bool $showDetailModal = false;

    public ?int $selectedSaleId = null;

    public ?Sale $selectedSale = null;

    // Xabarlar
    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public ?string $downloadUrl = null;

    protected ReportQueryService $reportQueryService;

    protected ExportService $exportService;

    public function boot(ReportQueryService $reportQueryService, ExportService $exportService): void
    {
        $this->reportQueryService = $reportQueryService;
        $this->exportService = $exportService;
    }

    public function mount(): void
    {
        $now = now()->setTimezone(ReportPeriod::TIMEZONE);
        $this->fromDate = $now->copy()->startOfMonth()->format('Y-m-d');
        $this->toDate = $now->format('Y-m-d');
        $this->month = $now->format('Y-m');
    }

    public function updated($property): void
    {
        if (in_array($property, ['period', 'fromDate', 'toDate', 'month', 'customerId', 'staffId', 'paymentMethod', 'status', 'search'])) {
            $this->resetPage();
        }
    }

    public function setPeriod(string $preset): void
    {
        $this->period = $preset;
        $this->resetPage();
    }

    public function openSaleDetail(int $saleId): void
    {
        $this->selectedSaleId = $saleId;
        $this->selectedSale = Sale::with([
            'customer',
            'creator',
            'device',
            'cashAccount',
            'items.variant.product',
            'items.variant.volume',
            'returns.items',
        ])->find($saleId);

        $this->showDetailModal = true;
    }

    public function closeSaleDetail(): void
    {
        $this->showDetailModal = false;
        $this->selectedSaleId = null;
        $this->selectedSale = null;
    }

    public function export(string $format = 'csv')
    {
        $user = Auth::user();
        if (! $user || (! $user->isOwner() && ! $user->hasPermission('export_reports'))) {
            $this->errorMessage = 'Sizda hisobotlarni eksport qilish ruxsati mavjud emas.';

            return;
        }

        try {
            $filters = $this->getFilterPayload();
            $export = $this->exportService->exportSalesReport($user, $filters, $format);

            $this->successMessage = "Eksport tayyorlandi: {$export->file_name}";
            $this->downloadUrl = route('exports.download', $export->uuid);

            return redirect()->route('exports.download', $export->uuid);
        } catch (\Throwable $e) {
            $this->errorMessage = 'Eksportda xatolik: '.$e->getMessage();
        }
    }

    protected function getFilterPayload(): array
    {
        return [
            'period' => $this->period,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'month' => $this->month,
            'customer_id' => $this->customerId,
            'staff_id' => $this->staffId,
            'payment_method' => $this->paymentMethod,
            'status' => $this->status,
            'search' => $this->search,
        ];
    }

    public function render()
    {
        $filters = $this->getFilterPayload();
        $periodData = ReportPeriod::resolve($this->period, $this->fromDate, $this->toDate, $this->month);

        // 1. Butun filtrlangan to'plam bo'yicha yig'indilar (KPI)
        $summary = $this->reportQueryService->getSalesSummary($filters);

        // 2. Sahifalangan ro'yxat (Joriy sahifa)
        $salesQuery = $this->reportQueryService->buildSalesQuery($filters, $periodData)
            ->with(['customer', 'creator'])
            ->orderBy('created_at', 'desc');

        $sales = $salesQuery->paginate(20);

        $customers = Customer::orderBy('name')->get();
        $staffUsers = User::orderBy('name')->get();

        $canViewCost = Auth::user()?->isOwner() || Auth::user()?->hasPermission('view_cost_price');

        return view('livewire.sales.sales-history', [
            'sales' => $sales,
            'summary' => $summary,
            'periodData' => $periodData,
            'customers' => $customers,
            'staffUsers' => $staffUsers,
            'canViewCost' => $canViewCost,
        ]);
    }
}

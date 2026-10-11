<?php

namespace App\Livewire\Reports;

use App\Models\Customer;
use App\Models\Supplier;
use App\Services\Reports\ExportService;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportQueryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ReportDashboard extends Component
{
    public string $activeTab = 'sales'; // sales, profit_loss, purchases, inventory, statements, cash, staff, sync

    public string $period = 'this_month'; // today, yesterday, this_week, this_month, last_month, month, custom

    public ?string $fromDate = null;

    public ?string $toDate = null;

    public ?string $month = null;

    // Statements tab filters
    public string $statementPartyType = 'customer'; // customer or supplier

    public ?int $statementPartyId = null;

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

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

        if (request()->has('tab')) {
            $this->activeTab = request()->query('tab');
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    public function setPeriod(string $preset): void
    {
        $this->period = $preset;
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

            $export = match ($this->activeTab) {
                'profit_loss' => $this->exportService->exportProfitLossReport($user, $filters, $format),
                'inventory' => $this->exportService->exportInventoryReport($user, $filters, $format),
                'statements' => $this->exportService->exportPartyStatementReport($user, $this->statementPartyType, $this->statementPartyId, $filters, $format),
                'cash' => $this->exportService->exportCashReport($user, $filters, $format),
                'purchases', 'staff', 'sync' => $this->exportService->exportAdditionalReport($user, $this->activeTab, $filters, $format),
                default => $this->exportService->exportSalesReport($user, $filters, $format),
            };

            $this->successMessage = "Eksport muvaffaqiyatli tayyorlandi: {$export->file_name}";

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
        ];
    }

    public function render()
    {
        $filters = $this->getFilterPayload();
        $periodData = ReportPeriod::resolve($this->period, $this->fromDate, $this->toDate, $this->month);
        $canViewCost = Auth::user()?->isOwner() || Auth::user()?->hasPermission('view_cost_price');

        $salesReport = null;
        $pnlReport = null;
        $purchasesReport = null;
        $inventoryReport = null;
        $statementsReport = null;
        $cashReport = null;
        $staffReport = null;
        $syncReport = null;

        if ($this->activeTab === 'sales') {
            $salesReport = $this->reportQueryService->getSalesSummary($filters);
        } elseif ($this->activeTab === 'profit_loss') {
            $pnlReport = $this->reportQueryService->getProfitAndLoss($filters, $canViewCost);
        } elseif ($this->activeTab === 'purchases' && $canViewCost) {
            $purchasesReport = $this->reportQueryService->getPurchasesSummary($filters);
        } elseif ($this->activeTab === 'inventory') {
            $inventoryReport = $this->reportQueryService->getInventoryValuationReport($filters, $canViewCost);
        } elseif ($this->activeTab === 'statements') {
            $statementsReport = $this->reportQueryService->getPartyStatements($this->statementPartyType, $this->statementPartyId, $filters);
        } elseif ($this->activeTab === 'cash') {
            $cashReport = $this->reportQueryService->getCashSummary($filters);
        } elseif ($this->activeTab === 'staff') {
            $staffReport = $this->reportQueryService->getStaffSummary($filters);
        } elseif ($this->activeTab === 'sync') {
            $syncReport = $this->reportQueryService->getSyncSummary($filters);
        }

        $customers = Customer::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();

        return view('livewire.reports.report-dashboard', [
            'periodData' => $periodData,
            'canViewCost' => $canViewCost,
            'salesReport' => $salesReport,
            'pnlReport' => $pnlReport,
            'purchasesReport' => $purchasesReport,
            'inventoryReport' => $inventoryReport,
            'statementsReport' => $statementsReport,
            'cashReport' => $cashReport,
            'staffReport' => $staffReport,
            'syncReport' => $syncReport,
            'customers' => $customers,
            'suppliers' => $suppliers,
        ]);
    }
}

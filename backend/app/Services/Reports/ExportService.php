<?php

namespace App\Services\Reports;

use App\Models\Device;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Reports\Exceptions\UnauthorizedExportException;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportService
{
    public function __construct(
        protected ReportQueryService $reportQueryService
    ) {}

    /**
     * Savdo hisobotini eksport qilish
     */
    public function exportSalesReport(User $user, array $filters, string $format = 'csv'): ReportExport
    {
        $this->authorize($user);

        $period = ReportPeriod::resolve(
            preset: $filters['period'] ?? ($filters['preset'] ?? 'today'),
            fromDate: $filters['from_date'] ?? null,
            toDate: $filters['to_date'] ?? null,
            month: $filters['month'] ?? null
        );

        $salesQuery = $this->reportQueryService->buildSalesQuery($filters, $period)
            ->with(['customer', 'creator', 'items.variant.product', 'items.variant.volume'])
            ->orderBy('created_at', 'desc');

        $sales = $salesQuery->get();

        $headers = [
            'Chek #',
            'Sana (Toshkent)',
            'Mijoz',
            'Telefon',
            'To\'lov turi',
            'Jami Summa (so\'m)',
            'Dastlabki To\'langan (so\'m)',
            'Dastlabki Nasiya (so\'m)',
            'Holati',
            'Mas\'ul Xodim',
            'Izoh',
        ];

        $alignRightColumns = [5, 6, 7];

        $rows = [];
        $totalSales = 0;
        $totalPaid = 0;
        $totalDebt = 0;

        foreach ($sales as $sale) {
            $totalSales += (int) $sale->total_amount;
            $totalPaid += (int) $sale->paid_amount;
            $totalDebt += (int) $sale->debt_amount;

            $rows[] = [
                self::escapeFormula($sale->invoice_number),
                Carbon::parse($sale->posted_at ?: $sale->created_at)->setTimezone(ReportPeriod::TIMEZONE)->format('Y-m-d H:i'),
                self::escapeFormula($sale->customer?->name ?: 'Tezkor xaridor'),
                self::escapeFormula($sale->customer?->phone ?: '-'),
                $sale->payment_method ?: $sale->payment_type ?: 'CASH',
                (int) $sale->total_amount,
                (int) $sale->paid_amount,
                (int) $sale->debt_amount,
                $sale->status,
                self::escapeFormula($sale->creator?->name ?: 'Noma\'lum'),
                self::escapeFormula($sale->notes ?: ''),
            ];
        }

        $totals = [
            'JAMI',
            count($sales).' ta savdo',
            '',
            '',
            '',
            $totalSales,
            $totalPaid,
            $totalDebt,
            '',
            '',
            '',
        ];

        $kpis = [
            'Jami Savdo' => $totalSales,
            'Dastlabki To\'langan' => $totalPaid,
            'Dastlabki Nasiya' => $totalDebt,
            'Cheklar Soni' => count($sales),
        ];

        return $this->generateExport(
            user: $user,
            reportType: 'sales',
            format: $format,
            title: 'Savdo Tarixi va Operatsiyalari Hisoboti',
            period: $period,
            headers: $headers,
            rows: $rows,
            totals: $totals,
            kpis: $kpis,
            alignRightColumns: $alignRightColumns,
            filters: $filters
        );
    }

    /**
     * Yalpi va Operatsion Foyda hisobotini eksport qilish
     */
    public function exportProfitLossReport(User $user, array $filters, string $format = 'csv'): ReportExport
    {
        $this->authorize($user);
        $canViewCost = $user->isOwner() || $user->hasPermission('view_cost_price');

        $pnl = $this->reportQueryService->getProfitAndLoss($filters, $canViewCost);
        $period = $pnl['period'];

        $headers = ['Ko\'rsatkich Nomi', 'Summa (so\'m)', 'Izoh / Nisbat'];
        $alignRightColumns = [1];

        $rows = [
            ['Jami Savdo (Brutto)', $pnl['gross_sales'], 'Cheklar bo\'yicha tushum'],
            ['Qaytarilgan Tovarlar (Returns)', $pnl['returns'], 'Mijozlar qaytargan summalar'],
            ['Sof Savdo Tushumi (Net Sales)', $pnl['net_sales'], 'Brutto savdo - Qaytarishlar'],
            ['Sotilgan Mahsulotlar Tannarxi (COGS)', $pnl['cogs'], 'WAC asosidagi xarid tannarxi'],
            ['Yalpi Foyda (Gross Profit)', $pnl['gross_profit'], 'Sof savdo - Tannarx'],
            ['Yalpi Marja (%)', $pnl['gross_margin_percent'].'%', 'Yalpi foyda / Sof savdo'],
            ['Operatsion Xarajatlar (Expenses)', $pnl['operating_expenses'], 'Kassa xarajatlari (Egasi yechgan pul kirmaydi)'],
            ['Brak va Yaroqsiz Tovarlar Yo\'qotishi', $pnl['damage_loss'], 'Ombor tannarx yo\'qotishi (WAC)'],
            ['Sof Operatsion Foyda', $pnl['operating_profit'], 'Yalpi foyda - Xarajatlar - Brak'],
            ['Operatsion Rentabellik (%)', $pnl['operating_margin_percent'].'%', 'Operatsion foyda / Sof savdo'],
        ];

        $kpis = [
            'Sof Savdo' => $pnl['net_sales'],
            'Yalpi Foyda' => $pnl['gross_profit'],
            'Xarajatlar' => $pnl['operating_expenses'],
            'Operatsion Foyda' => $pnl['operating_profit'],
        ];

        return $this->generateExport(
            user: $user,
            reportType: 'profit_loss',
            format: $format,
            title: 'Yalpi va Operatsion Natijalar (P&L) Hisoboti',
            period: $period,
            headers: $headers,
            rows: $rows,
            totals: [],
            kpis: $kpis,
            alignRightColumns: $alignRightColumns,
            filters: $filters
        );
    }

    /**
     * Ombor qoldig'i va Tarixiy baholash hisobotini eksport qilish
     */
    public function exportInventoryReport(User $user, array $filters, string $format = 'csv'): ReportExport
    {
        $this->authorize($user);

        $inv = $this->reportQueryService->getInventoryValuationReport($filters);
        $period = $inv['period'];

        $headers = [
            'Mahsulot',
            'Hajm',
            'SKU',
            'Boshlang\'ich Qoldiq (dona)',
            'Kirim (dona)',
            'Chiqim (dona)',
            'Tuzatish (dona)',
            'Yakuniy Qoldiq (dona)',
            'WAC Tannarx (so\'m)',
            'Yakuniy Qiymat (so\'m)',
        ];

        $alignRightColumns = [3, 4, 5, 6, 7, 8, 9];

        $rows = [];
        foreach ($inv['rows'] as $r) {
            $rows[] = [
                self::escapeFormula($r['product_name']),
                self::escapeFormula($r['volume_name']),
                self::escapeFormula($r['sku']),
                $r['opening_units'],
                $r['inward_units'],
                $r['outward_units'],
                $r['adjustment_units'],
                $r['closing_units'],
                $r['wac_cost'],
                $r['closing_valuation'],
            ];
        }

        $totals = [
            'JAMI',
            '',
            '',
            $inv['summary']['total_opening_units'],
            $inv['summary']['total_inward_units'],
            $inv['summary']['total_outward_units'],
            $inv['summary']['total_adjustment_units'],
            $inv['summary']['total_closing_units'],
            '',
            $inv['summary']['total_closing_valuation'],
        ];

        $kpis = [
            'Boshlang\'ich Dona' => $inv['summary']['total_opening_units'],
            'Kirim Dona' => $inv['summary']['total_inward_units'],
            'Chiqim Dona' => $inv['summary']['total_outward_units'],
            'Yakuniy Qiymat' => $inv['summary']['total_closing_valuation'],
        ];

        return $this->generateExport(
            user: $user,
            reportType: 'inventory',
            format: $format,
            title: 'Ombor Qoldiqlari va Tarixiy Baholash Hisoboti',
            period: $period,
            headers: $headers,
            rows: $rows,
            totals: $totals,
            kpis: $kpis,
            alignRightColumns: $alignRightColumns,
            filters: $filters
        );
    }

    /**
     * Taraf Ko'chirmasi (Party Statement: Customer/Supplier)
     */
    public function exportPartyStatementReport(User $user, string $partyType, ?int $partyId, array $filters, string $format = 'csv'): ReportExport
    {
        $this->authorize($user);

        $res = $this->reportQueryService->getPartyStatements($partyType, $partyId, $filters);
        $period = $res['period'];

        $partyLabel = $partyType === 'customer' ? 'Mijoz' : 'Ta\'minotchi';
        $headers = [
            $partyLabel.' Nomi',
            'Telefon',
            'Davr Boshi Balansi (so\'m)',
            'Davr Debet / Kirim (so\'m)',
            'Davr Kredit / Chiqim (so\'m)',
            'Davr Yakuni Balansi (so\'m)',
        ];

        $alignRightColumns = [2, 3, 4, 5];

        $rows = [];
        $totOpening = 0;
        $totDebits = 0;
        $totCredits = 0;
        $totClosing = 0;

        foreach ($res['statements'] as $st) {
            $totOpening += $st['opening_balance'];
            $totDebits += $st['period_debits'];
            $totCredits += $st['period_credits'];
            $totClosing += $st['closing_balance'];

            $rows[] = [
                self::escapeFormula($st['party_name']),
                self::escapeFormula($st['party_phone'] ?: '-'),
                $st['opening_balance'],
                $st['period_debits'],
                $st['period_credits'],
                $st['closing_balance'],
            ];
        }

        $totals = ['JAMI', '', $totOpening, $totDebits, $totCredits, $totClosing];

        $kpis = [
            'Boshlang\'ich Balans' => $totOpening,
            'Davr Aylanmasi (+)' => $totDebits,
            'Davr To\'lovlari (-)' => $totCredits,
            'Yakuniy Balans' => $totClosing,
        ];

        return $this->generateExport(
            user: $user,
            reportType: 'statement',
            format: $format,
            title: $partyLabel.'lar Hisob-Kitob Ko\'chirmasi (Signed Balance)',
            period: $period,
            headers: $headers,
            rows: $rows,
            totals: $totals,
            kpis: $kpis,
            alignRightColumns: $alignRightColumns,
            filters: $filters
        );
    }

    /**
     * Kassa va Smena hisobotini eksport qilish
     */
    public function exportCashReport(User $user, array $filters, string $format = 'csv'): ReportExport
    {
        $this->authorize($user);

        $cash = $this->reportQueryService->getCashSummary($filters);
        $period = $cash['period'];

        $headers = [
            'Hisob Raqam / Kassa Nomi',
            'Turi',
            'Boshlang\'ich Balans (so\'m)',
            'Kirimlar (so\'m)',
            'Chiqimlar (so\'m)',
            'Yakuniy Balans (so\'m)',
        ];

        $alignRightColumns = [2, 3, 4, 5];

        $rows = [];
        foreach ($cash['account_summaries'] as $acc) {
            $rows[] = [
                self::escapeFormula($acc['account_name']),
                $acc['account_type'],
                $acc['opening_balance'],
                $acc['inflows'],
                $acc['outflows'],
                $acc['closing_balance'],
            ];
        }

        $totals = [
            'JAMI',
            '',
            $cash['summary']['total_opening_cash'],
            $cash['summary']['total_inflows'],
            $cash['summary']['total_outflows'],
            $cash['summary']['total_closing_cash'],
        ];

        $kpis = [
            'Boshlang\'ich Kassa' => $cash['summary']['total_opening_cash'],
            'Jami Kirimlar' => $cash['summary']['total_inflows'],
            'Jami Chiqimlar' => $cash['summary']['total_outflows'],
            'Yakuniy Kassa' => $cash['summary']['total_closing_cash'],
        ];

        return $this->generateExport(
            user: $user,
            reportType: 'cash',
            format: $format,
            title: 'Kassa va Pul Mablag\'lari Harakati Hisoboti',
            period: $period,
            headers: $headers,
            rows: $rows,
            totals: $totals,
            kpis: $kpis,
            alignRightColumns: $alignRightColumns,
            filters: $filters
        );
    }

    /**
     * Faylni generatsiya qilish va saqlash
     */
    protected function generateExport(
        User $user,
        string $reportType,
        string $format,
        string $title,
        array $period,
        array $headers,
        array $rows,
        array $totals = [],
        array $kpis = [],
        array $alignRightColumns = [],
        array $filters = []
    ): ReportExport {
        $uuid = (string) Str::uuid();
        $dateStamp = Carbon::now(ReportPeriod::TIMEZONE)->format('Ymd_His');
        $fileName = "{$reportType}_report_{$dateStamp}.{$format}";
        $relativePath = "exports/{$uuid}.{$format}";

        // To'liqlik belgisi (Completeness status)
        $hasPendingDevices = Device::whereNotNull('freeze_requested_at')
            ->whereNull('freeze_acknowledged_at')
            ->exists();
        $completenessStatus = $hasPendingDevices ? 'Ogohlantirish: Uzilgan qurilmalar sinxronlanmagan' : 'To\'liq server ma\'lumotlari';

        $metadata = [
            'title' => $title,
            'report_type' => $reportType,
            'period_label' => $period['label'],
            'period_from' => $period['from_date'],
            'period_to' => $period['to_date'],
            'exported_at' => Carbon::now(ReportPeriod::TIMEZONE)->format('Y-m-d H:i:s'),
            'user_name' => $user->name.' ('.$user->role.')',
            'completeness_status' => $completenessStatus,
            'rows_count' => count($rows),
        ];

        // Format bo'yicha kontent yaratish
        if ($format === 'pdf') {
            $pdfContent = Pdf::loadView('exports.pdf-report', [
                'title' => $title,
                'metadata' => $metadata,
                'headers' => $headers,
                'rows' => $rows,
                'totals' => $totals,
                'kpis' => $kpis,
                'alignRightColumns' => $alignRightColumns,
            ])
                ->setPaper('a4', 'landscape')
                ->output();

            Storage::disk('local')->put($relativePath, $pdfContent);
            $fileSize = strlen($pdfContent);
        } else {
            // CSV / Excel format (UTF-8 with BOM and formula injection protection)
            $content = self::buildCsvContent($metadata, $headers, $rows, $totals);
            Storage::disk('local')->put($relativePath, $content);
            $fileSize = strlen($content);
        }

        $export = ReportExport::create([
            'uuid' => $uuid,
            'user_id' => $user->id,
            'report_type' => $reportType,
            'format' => $format,
            'file_name' => $fileName,
            'file_path' => $relativePath,
            'file_size' => $fileSize,
            'status' => 'COMPLETED',
            'filter_payload' => $filters,
            'metadata' => $metadata,
            'completed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        return $export;
    }

    /**
     * CSV kontentini UTF-8 BOM va xavfsizlik bilan shakllantirish
     */
    public static function buildCsvContent(array $metadata, array $headers, array $rows, array $totals = []): string
    {
        // 1. UTF-8 BOM qo'shish (Excel uchun to'g'ri kodirovka kafolati)
        $csv = "\xEF\xBB\xBF";

        // 2. Metadata sarlavhalari
        $csv .= '# AquaOptom CRM — '.$metadata['title']."\r\n";
        $csv .= '# Davr: '.$metadata['period_label']."\r\n";
        $csv .= '# Eksport vaqti: '.$metadata['exported_at']." (Asia/Tashkent)\r\n";
        $csv .= '# Mas\'ul: '.$metadata['user_name']."\r\n";
        $csv .= '# To\'liqlik holati: '.$metadata['completeness_status']."\r\n";
        $csv .= "\r\n";

        // 3. Ustun nomlari
        $csv .= self::formatCsvRow($headers);

        // 4. Qatorlar
        foreach ($rows as $row) {
            $csv .= self::formatCsvRow($row);
        }

        // 5. Jami (Agar mavjud bo'lsa)
        if (! empty($totals)) {
            $csv .= self::formatCsvRow($totals);
        }

        return $csv;
    }

    /**
     * CSV qatorini formatlash va formula escaping qilish
     */
    protected static function formatCsvRow(array $fields): string
    {
        $escaped = array_map(function ($field) {
            $val = (string) $field;
            $val = self::escapeFormula($val);
            // Vergul, qo'shtirnoq yoki yangi qator bo'lsa, qo'shtirnoqqa olish
            if (str_contains($val, ',') || str_contains($val, '"') || str_contains($val, "\n") || str_contains($val, "\r")) {
                $val = '"'.str_replace('"', '""', $val).'"';
            }

            return $val;
        }, $fields);

        return implode(',', $escaped)."\r\n";
    }

    /**
     * Formula Injection (OWASP / CSV Injection) dan himoyalash
     */
    public static function escapeFormula(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $firstChar = $value[0];
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Ruxsatni tekshirish
     */
    protected function authorize(User $user): void
    {
        if (! $user->isOwner() && ! $user->hasPermission('export_reports')) {
            throw new UnauthorizedExportException('Hisobotlarni eksport qilish huquqi mavjud emas.');
        }
    }
}

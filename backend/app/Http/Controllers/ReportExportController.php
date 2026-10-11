<?php

namespace App\Http\Controllers;

use App\Models\ReportExport;
use App\Services\Reports\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function create(Request $request, ExportService $service): JsonResponse
    {
        $filters = $request->validate([
            'type' => 'required|in:sales,profit_loss,purchases,inventory,statements,cash,staff,sync',
            'period' => 'required|in:today,yesterday,this_week,this_month,last_month,all_time,custom',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d|after_or_equal:from_date',
            'party_type' => 'nullable|in:customer,supplier', 'party_id' => 'nullable|integer|min:1',
        ]);
        $user = $request->user();
        $export = match ($filters['type']) {
            'profit_loss' => $service->exportProfitLossReport($user, $filters, 'xlsx'),
            'inventory' => $service->exportInventoryReport($user, $filters, 'xlsx'),
            'statements' => $service->exportPartyStatementReport($user, $filters['party_type'] ?? 'customer', $filters['party_id'] ?? null, $filters, 'xlsx'),
            'cash' => $service->exportCashReport($user, $filters, 'xlsx'),
            'purchases', 'staff', 'sync' => $service->exportAdditionalReport($user, $filters['type'], $filters, 'xlsx'),
            default => $service->exportSalesReport($user, $filters, 'xlsx'),
        };

        return response()->json(['status' => 'success', 'data' => ['file_name' => $export->file_name, 'uuid' => $export->uuid]]);
    }

    /**
     * Yopiq, xavfsiz va ruxsatli eksport faylini yuklab olish
     */
    public function download(Request $request, string $uuid): StreamedResponse
    {
        $user = $request->user();
        if (! $user || (! $user->isOwner() && ! $user->hasPermission('export_reports'))) {
            abort(403, 'Ushbu hisobotni yuklab olish uchun sizda ruxsat mavjud emas.');
        }

        $export = ReportExport::where('uuid', $uuid)->firstOrFail();
        abort_unless($export->canBeDownloadedBy($user), 403, 'Bu hisobotni ko‘rish uchun ruxsat yo‘q.');
        abort_if($export->expires_at?->isPast(), 410, 'Hisobot muddati tugagan. Yangisini tayyorlang.');
        abort_unless($export->isCompleted(), 404);

        if (! Storage::disk('local')->exists($export->file_path)) {
            abort(404, 'Eksport fayli topilmadi yoki muddati o\'tgan.');
        }

        $mimeType = match ($export->format) {
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'text/csv; charset=UTF-8',
        };

        return Storage::disk('local')->download(
            $export->file_path,
            $export->file_name,
            [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]
        );
    }
}

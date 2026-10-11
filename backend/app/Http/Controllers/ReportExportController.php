<?php

namespace App\Http\Controllers;

use App\Models\ReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
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

<?php

namespace App\Services\Reports;

use Carbon\Carbon;

class ReportPeriod
{
    public const TIMEZONE = 'Asia/Tashkent';

    /**
     * Berilgan parametrlar asosida vaqt oralig'ini Asia/Tashkent chegaralari bo'yicha hisoblaydi
     * va ma'lumotlar bazasi so'rovlari uchun UTC Carbon obyektlarini qaytaradi.
     */
    public static function resolve(
        ?string $preset = 'today',
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $month = null
    ): array {
        $preset = $preset ?: 'today';
        $nowLocal = Carbon::now(self::TIMEZONE);

        switch ($preset) {
            case 'yesterday':
                $startLocal = $nowLocal->copy()->subDay()->startOfDay();
                $endLocal = $nowLocal->copy()->subDay()->endOfDay();
                $label = 'Kecha ('.$startLocal->format('d.m.Y').')';
                break;

            case 'this_week':
                $startLocal = $nowLocal->copy()->startOfWeek();
                $endLocal = $nowLocal->copy()->endOfWeek();
                $label = 'Bu hafta ('.$startLocal->format('d.m.Y').' - '.$endLocal->format('d.m.Y').')';
                break;

            case 'this_month':
                $startLocal = $nowLocal->copy()->startOfMonth();
                $endLocal = $nowLocal->copy()->endOfMonth();
                $label = 'Bu oy ('.$startLocal->format('d.m.Y').' - '.$endLocal->format('d.m.Y').')';
                break;

            case 'last_month':
                $startLocal = $nowLocal->copy()->subMonth()->startOfMonth();
                $endLocal = $nowLocal->copy()->subMonth()->endOfMonth();
                $label = 'O\'tgan oy ('.$startLocal->format('d.m.Y').' - '.$endLocal->format('d.m.Y').')';
                break;

            case 'this_year':
                $startLocal = $nowLocal->copy()->startOfYear();
                $endLocal = $nowLocal->copy()->endOfYear();
                $label = 'Bu yil ('.$startLocal->format('Y').')';
                break;

            case 'month':
                if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
                    $parsed = Carbon::createFromFormat('Y-m', $month, self::TIMEZONE);
                    $startLocal = $parsed->copy()->startOfMonth();
                    $endLocal = $parsed->copy()->endOfMonth();
                } else {
                    $startLocal = $nowLocal->copy()->startOfMonth();
                    $endLocal = $nowLocal->copy()->endOfMonth();
                }
                $label = $startLocal->format('F Y').' ('.$startLocal->format('d.m.Y').' - '.$endLocal->format('d.m.Y').')';
                break;

            case 'custom':
                if ($fromDate) {
                    $startLocal = Carbon::createFromFormat('Y-m-d', $fromDate, self::TIMEZONE)->startOfDay();
                } else {
                    $startLocal = $nowLocal->copy()->startOfMonth();
                }

                if ($toDate) {
                    $endLocal = Carbon::createFromFormat('Y-m-d', $toDate, self::TIMEZONE)->endOfDay();
                } else {
                    $endLocal = $nowLocal->copy()->endOfDay();
                }

                if ($startLocal->gt($endLocal)) {
                    // Sana teskari kiritilgan bo'lsa, o'rinlarini almashtirish
                    [$startLocal, $endLocal] = [$endLocal->copy()->startOfDay(), $startLocal->copy()->endOfDay()];
                }

                $label = 'Ixtiyoriy oraliq ('.$startLocal->format('d.m.Y').' - '.$endLocal->format('d.m.Y').')';
                break;

            case 'today':
            default:
                $preset = 'today';
                $startLocal = $nowLocal->copy()->startOfDay();
                $endLocal = $nowLocal->copy()->endOfDay();
                $label = 'Bugun ('.$startLocal->format('d.m.Y').')';
                break;
        }

        // Asia/Tashkent vaqtini bazada qidirish uchun UTC ga o'tkazish
        $startUtc = $startLocal->copy()->setTimezone('UTC');
        $endUtc = $endLocal->copy()->setTimezone('UTC');

        return [
            'preset' => $preset,
            'start_local' => $startLocal,
            'end_local' => $endLocal,
            'start_utc' => $startUtc,
            'end_utc' => $endUtc,
            'from_date' => $startLocal->format('Y-m-d'),
            'to_date' => $endLocal->format('Y-m-d'),
            'label' => $label,
            'timezone' => self::TIMEZONE,
        ];
    }
}

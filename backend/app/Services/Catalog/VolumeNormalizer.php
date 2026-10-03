<?php

namespace App\Services\Catalog;

use App\Models\Volume;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

class VolumeNormalizer
{
    /**
     * Hajm kiritmasini normallashtirish: integer ml va standart ko'rinish nomi.
     *
     * Qo'llab-quvvatlanadi:
     * - "0.5 L", "0,5 L", "0.5l", "0,5" -> 500 ml, "0.5 L"
     * - "1 L", "1.0 L", "1" -> 1000 ml, "1 L"
     * - "1.5 L", "1,5 L", "1.5" -> 1500 ml, "1.5 L"
     * - "0.25 L", "0,25" -> 250 ml, "0.25 L"
     * - "0.33 L", "0,33" -> 330 ml, "0.33 L"
     * - "2 L", "2.5 L", "5 L", "18.9 L", "19 L"
     * - "500 ml", "500ml", "500 ML", 500 -> 500 ml, "0.5 L"
     * - "250 ml", "330 ml", "750 ml"
     *
     * @return array{value_ml: int, name: string}
     */
    public static function normalize(string|int|float $input): array
    {
        $raw = trim((string) $input);
        if ($raw === '') {
            throw new InvalidArgumentException('Hajm kiritilishi shart!');
        }

        // Vergulni nuqtaga almashtiramiz
        $cleaned = str_replace(',', '.', $raw);

        // 1. "500 ml" yoki "250ml"
        if (preg_match('/^(\d+(?:\.\d+)?)\s*ml$/i', $cleaned, $matches)) {
            $ml = (int) round((float) $matches[1]);
        }
        // 2. "0.5 L", "1.5l", "1 litr"
        elseif (preg_match('/^(\d+(?:\.\d+)?)\s*l(?:itr)?$/i', $cleaned, $matches)) {
            $liters = (float) $matches[1];
            $ml = (int) round($liters * 1000);
        }
        // 3. Sof raqam: masalan "0.5", "1.5", "500", 500, "1"
        elseif (is_numeric($cleaned)) {
            $val = (float) $cleaned;
            // Agar son >= 50 bo'lsa, bu to'g'ridan-to'g'ri ml
            // Agar son < 50 bo'lsa, bu litr
            if ($val >= 50) {
                $ml = (int) round($val);
            } else {
                $ml = (int) round($val * 1000);
            }
        } else {
            throw new InvalidArgumentException("Noto'g'ri hajm formati: [{$input}]. Masalan: 0.5 L, 1 L yoki 500 ml.");
        }

        if ($ml <= 0) {
            throw new InvalidArgumentException("Hajm 0 dan katta bo'lishi shart!");
        }

        // Standart nom: 500 -> "0.5 L", 1000 -> "1 L", 1500 -> "1.5 L", 250 -> "0.25 L"
        $liters = $ml / 1000;
        $formattedLiters = rtrim(rtrim(number_format($liters, 2, '.', ''), '0'), '.');
        $standardName = "{$formattedLiters} L";

        return [
            'value_ml' => $ml,
            'name' => $standardName,
        ];
    }

    /**
     * Hajmni topish yoki xavfsiz yaratish (concurrent duplicate protection)
     */
    public static function findOrCreate(string|int|float $input, ?int $createdBy = null): Volume
    {
        $normalized = static::normalize($input);

        $existing = Volume::where('value_ml', $normalized['value_ml'])->first();
        if ($existing) {
            return $existing;
        }

        try {
            return Volume::create([
                'name' => $normalized['name'],
                'value_ml' => $normalized['value_ml'],
                'status' => 'active',
                'created_by' => $createdBy,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return Volume::where('value_ml', $normalized['value_ml'])->firstOrFail();
        }
    }
}

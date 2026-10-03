<?php

namespace App\Services\Operations;

class PayloadFingerprint
{
    /**
     * Kanonik payload fingerprintini hisoblash (SHA-256).
     *
     * Qoidalar:
     * - Kalitlar bo'yicha rekursiv saralash (ksort).
     * - Vaqtinchalik transport kalitlari (masalan _token, request_id, client_time) chiqarib tashlanadi.
     * - Barcha bo'shliqlar tozalangan, raqamlar standartlashtirilgan.
     * - json_encode(..., JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) asosida hash.
     */
    public static function compute(array $payload): string
    {
        $canonical = static::canonicalize($payload);

        $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $json);
    }

    /**
     * Rekursiv kanonikalizatsiya
     */
    public static function canonicalize(array $data): array
    {
        // Transport-only maydonlarni tozalaymiz
        $ignoredKeys = ['_token', 'request_id', 'client_time', 'nonce'];
        foreach ($ignoredKeys as $ignored) {
            unset($data[$ignored]);
        }

        ksort($data);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = static::canonicalize($value);
            } elseif (is_string($value)) {
                $data[$key] = trim($value);
            } elseif (is_float($value)) {
                // Ikkilik float noaniqliklarini yo'qotish uchun
                $data[$key] = (float) number_format($value, 4, '.', '');
            }
        }

        return $data;
    }
}

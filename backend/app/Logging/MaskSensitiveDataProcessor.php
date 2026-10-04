<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class MaskSensitiveDataProcessor implements ProcessorInterface
{
    /**
     * Yashirin/maxfiy maydonlar kalit so'zlari
     */
    protected array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'remember_token',
        'api_key',
        'secret',
        'app_key',
        'authorization',
        'bearer',
        'card_number',
        'credit_card',
        'cvv',
        'pin',
        'telegram_bot_token',
        'bot_token',
        'encryption_key',
        'backup_encryption_key',
    ];

    /**
     * Support both Monolog Processor and Laravel Logger "tap"
     */
    public function __invoke(mixed $loggerOrRecord): mixed
    {
        if ($loggerOrRecord instanceof LogRecord) {
            return $this->processRecord($loggerOrRecord);
        }

        // Laravel Logger wrapper
        if ($loggerOrRecord instanceof Logger) {
            $monolog = $loggerOrRecord->getLogger();
            $monolog->pushProcessor(fn (LogRecord $record) => $this->processRecord($record));

            return $loggerOrRecord;
        }

        if (is_object($loggerOrRecord) && method_exists($loggerOrRecord, 'pushProcessor')) {
            $loggerOrRecord->pushProcessor(fn (LogRecord $record) => $this->processRecord($record));
        }

        if (is_object($loggerOrRecord) && method_exists($loggerOrRecord, 'getHandlers')) {
            foreach ($loggerOrRecord->getHandlers() as $handler) {
                $handler->pushProcessor(fn (LogRecord $record) => $this->processRecord($record));
            }
        }

        return $loggerOrRecord;
    }

    public function processRecord(LogRecord $record): LogRecord
    {
        $context = $this->sanitizeData($record->context);
        $extra = $this->sanitizeData($record->extra);
        $message = $this->sanitizeMessage($record->message);

        return $record->with(
            message: $message,
            context: $context,
            extra: $extra
        );
    }

    /**
     * Rekursiv massiv / context tozalash
     */
    public function sanitizeData(mixed $data): mixed
    {
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                if ($this->isSensitiveKey((string) $key)) {
                    $cleaned[$key] = '[REDACTED]';
                } else {
                    $cleaned[$key] = $this->sanitizeData($value);
                }
            }

            return $cleaned;
        }

        if (is_object($data)) {
            if ($data instanceof \JsonSerializable) {
                return $this->sanitizeData($data->jsonSerialize());
            }

            return $data;
        }

        if (is_string($data)) {
            return $this->sanitizeMessage($data);
        }

        return $data;
    }

    /**
     * Matndagi ehtimoliy token yoki parollarni tozalash
     */
    public function sanitizeMessage(string $message): string
    {
        // Bearer token tozalash
        $message = preg_replace('/(Bearer\s+)[A-Za-z0-9_\-\.]{15,}/i', '$1[REDACTED]', $message);

        // Telegram Bot API token tozalash (e.g. 123456789:ABCdefGhIjkLmNoPqRsTuVwXyZ)
        $message = preg_replace('/\b[0-9]{8,12}:[A-Za-z0-9_-]{20,45}\b/', '[REDACTED_TELEGRAM_TOKEN]', $message);

        // Basic Authorization tozalash
        $message = preg_replace('/(Basic\s+)[A-Za-z0-9+\/=]{15,}/i', '$1[REDACTED]', $message);

        return $message;
    }

    /**
     * Kalit nomini tekshirish
     */
    public function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));

        foreach ($this->sensitiveKeys as $sensitive) {
            $normalizedSensitive = strtolower(str_replace(['-', '_'], '', $sensitive));
            if (str_contains($normalized, $normalizedSensitive)) {
                return true;
            }
        }

        return false;
    }
}

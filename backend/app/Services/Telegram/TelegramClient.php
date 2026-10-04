<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramClient
{
    protected ?string $token;

    /** @var array<int, array> */
    public static array $recordedMessages = [];

    public static bool $isFake = false;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN'));
    }

    public static function fake(): void
    {
        static::$isFake = true;
        static::$recordedMessages = [];
    }

    public static function resetFake(): void
    {
        static::$isFake = false;
        static::$recordedMessages = [];
    }

    /**
     * @return array<int, array>
     */
    public static function getRecordedMessages(): array
    {
        return static::$recordedMessages;
    }

    /**
     * Telegram chatga xabar yuborish
     */
    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null, string $parseMode = 'HTML'): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }

        if (static::$isFake) {
            static::$recordedMessages[] = [
                'method' => 'sendMessage',
                'payload' => $payload,
            ];

            return ['ok' => true, 'result' => ['message_id' => rand(1000, 9999), 'chat' => ['id' => $chatId]]];
        }

        if (! $this->token) {
            Log::info("Telegram [Token yo'q] sendMessage to {$chatId}: ".strip_tags($text));

            return ['ok' => false, 'error' => 'NO_TOKEN'];
        }

        try {
            $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
            $res = Http::post($url, $payload);

            return $res->json() ?? ['ok' => $res->successful()];
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage xatosi: '.$e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Telegram xabarni tahrirlash (Inline navigatsiyada qulay)
     */
    public function editMessageText(int|string $chatId, int $messageId, string $text, ?array $replyMarkup = null, string $parseMode = 'HTML'): array
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }

        if (static::$isFake) {
            static::$recordedMessages[] = [
                'method' => 'editMessageText',
                'payload' => $payload,
            ];

            return ['ok' => true, 'result' => ['message_id' => $messageId]];
        }

        if (! $this->token) {
            return ['ok' => false, 'error' => 'NO_TOKEN'];
        }

        try {
            $url = "https://api.telegram.org/bot{$this->token}/editMessageText";
            $res = Http::post($url, $payload);

            return $res->json() ?? ['ok' => $res->successful()];
        } catch (\Throwable $e) {
            Log::error('Telegram editMessageText xatosi: '.$e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Callback queryga javob berish (tugma yuklanishini to'xtatish)
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): bool
    {
        $payload = [
            'callback_query_id' => $callbackQueryId,
        ];

        if ($text !== null) {
            $payload['text'] = $text;
            $payload['show_alert'] = $showAlert;
        }

        if (static::$isFake) {
            static::$recordedMessages[] = [
                'method' => 'answerCallbackQuery',
                'payload' => $payload,
            ];

            return true;
        }

        if (! $this->token) {
            return false;
        }

        try {
            $url = "https://api.telegram.org/bot{$this->token}/answerCallbackQuery";
            $res = Http::post($url, $payload);

            return $res->successful();
        } catch (\Throwable $e) {
            Log::error('Telegram answerCallbackQuery xatosi: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Bot buyruqlarini Telegram API ga o'rnatish
     */
    public function setBotCommands(): bool
    {
        $commands = [
            ['command' => 'start', 'description' => 'Asosiy menyu va boshqaruv'],
            ['command' => 'dashboard', 'description' => 'Bugungi savdo va jonli holat'],
            ['command' => 'qoldiq', 'description' => 'Ombordagi tovarlar qoldig\'i'],
            ['command' => 'mijoz_qarzlari', 'description' => 'Mijozlar nasiya qarzdorligi'],
            ['command' => 'supplier_qarzlari', 'description' => 'Ta\'minotchilar qarzdorligi'],
            ['command' => 'kassa', 'description' => 'Kassalar balansi'],
            ['command' => 'hisobot', 'description' => 'Kunlik va oylik hisobot'],
            ['command' => 'savdo', 'description' => 'Yangi ko\'p qatorli savdo'],
            ['command' => 'kirim', 'description' => 'Yangi tovar kirimi'],
            ['command' => 'tolov', 'description' => 'Qarz to\'lovini qabul qilish'],
            ['command' => 'pwa', 'description' => 'Offline PWA ilovasi'],
            ['command' => 'cancel', 'description' => 'Joriy qoralamani bekor qilish'],
        ];

        if (static::$isFake) {
            return true;
        }

        if (! $this->token) {
            return false;
        }

        try {
            $url = "https://api.telegram.org/bot{$this->token}/setMyCommands";
            $res = Http::post($url, ['commands' => $commands]);

            return $res->successful();
        } catch (\Throwable $e) {
            Log::error('Telegram setMyCommands error: '.$e->getMessage());

            return false;
        }
    }
}

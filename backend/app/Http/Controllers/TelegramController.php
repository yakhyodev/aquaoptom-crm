<?php

namespace App\Http\Controllers;

use App\Models\TelegramUpdate;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    /**
     * Telegram Webhook dan kelgan so'rovlarni qabul qilish
     */
    public function webhook(Request $request, TelegramBotService $botService): JsonResponse
    {
        // 1. Webhook Secret Token tekshiruvi (X-Telegram-Bot-Api-Secret-Token)
        $expectedSecret = (string) config('services.telegram.webhook_secret');
        if ($expectedSecret !== '') {
            $receivedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');
            if (! hash_equals($expectedSecret, $receivedSecret)) {
                return response()->json(['error' => 'Unauthorized secret token'], 403);
            }
        }

        // 2. update_id deduplikatsiyasi (takroriy webhook so'rovlarini bir marta bajarish)
        $updateId = $request->input('update_id');
        if ($updateId !== null) {
            $alreadyProcessed = TelegramUpdate::where('update_id', $updateId)->exists();
            if ($alreadyProcessed) {
                return response()->json(['status' => 'already_processed']);
            }

            $chatId = $request->input('message.chat.id') ?? $request->input('callback_query.message.chat.id');
            TelegramUpdate::create([
                'update_id' => $updateId,
                'chat_id' => $chatId,
            ]);
        }

        // 3. Bot xizmatiga yo'naltirish
        $botService->handleUpdate($request->all());

        return response()->json(['status' => 'ok']);
    }

    /**
     * Bot buyruqlarini Telegram API ga o'rnatish (/ yozganda menyu chiqishi uchun)
     */
    public function setCommands(TelegramClient $client): JsonResponse
    {
        $success = $client->setBotCommands();

        return response()->json([
            'status' => $success ? 'success' : 'failed',
            'message' => $success
                ? 'Telegram bot buyruqlari muvaffaqiyatli ro\'yxatdan o\'tkazildi (/start, /dashboard, /qoldiq, /savdo, /kirim, /kassa)!'
                : 'Bot tokeni xato yoki ulanishda muammo bor.',
        ]);
    }
}

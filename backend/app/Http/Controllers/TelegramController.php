<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    /**
     * Telegram Webhook dan kelgan so'rovlarni qabul qilish
     */
    public function webhook(Request $request, TelegramService $telegram): JsonResponse
    {
        $update = $request->all();
        $telegram->handleWebhook($update);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Bot buyruqlarini Telegram API ga o'rnatish (/ yozganda menyu chiqishi uchun)
     */
    public function setCommands(TelegramService $telegram): JsonResponse
    {
        $success = $telegram->setBotCommands();

        return response()->json([
            'status' => $success ? 'success' : 'failed',
            'message' => $success
                ? 'Telegram bot buyruqlari muvaffaqiyatli ro\'yxatdan o\'tkazildi (/start, /hisobot, /qoldiq, /kassa, /kirim)!'
                : 'Bot tokeni xato yoki ulanishda muammo bor.',
        ]);
    }
}

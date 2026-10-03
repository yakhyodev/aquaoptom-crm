<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected ?string $token;

    protected ?string $channelId;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN'));
        $this->channelId = config('services.telegram.channel_id', env('TELEGRAM_CHANNEL_ID'));
    }

    /**
     * Telegram Bot buyruqlarini ro'yxatdan o'tkazish.
     * Foydalanuvchi '/' yozishi bilan ushbu menyu avtomatik chiqadi!
     */
    public function setBotCommands(): bool
    {
        if (! $this->token) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->token}/setMyCommands";
        $commands = [
            ['command' => 'start', 'description' => 'Asosiy menyu va tugmalar'],
            ['command' => 'hisobot', 'description' => 'Kunlik savdo, kassa va sof foyda'],
            ['command' => 'qoldiq', 'description' => 'Ombordagi suvlar qoldig\'i'],
            ['command' => 'kassa', 'description' => 'Hozirgi kassa summasi'],
            ['command' => 'kirim', 'description' => 'Tezkor yuk kirimi qilish'],
        ];

        try {
            $res = Http::post($url, ['commands' => $commands]);

            return $res->successful();
        } catch (\Exception $e) {
            Log::error('Telegram setMyCommands error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Real-vaqtda Telegram Kanalga xabar yuborish
     */
    public function sendToChannel(string $text): bool
    {
        if (! $this->token || ! $this->channelId) {
            Log::info("Telegram Kanalga xabar (Token/Channel o'rnatilmagan): \n".strip_tags($text));

            return false;
        }

        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
        try {
            $res = Http::post($url, [
                'chat_id' => $this->channelId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            return $res->successful();
        } catch (\Exception $e) {
            Log::error('Telegram sendToChannel error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Kirim bo'lganda Telegram Kanalga real-vaqtda to'liq ma'lumot yuborish
     */
    public function notifyInward(string $productName, string|float $litres, int|float $qty, float|int $costPrice, string $source = 'web'): void
    {
        $totalCost = $qty * $costPrice;
        $formattedCost = number_format($costPrice, 0, '.', ' ');
        $formattedTotal = number_format($totalCost, 0, '.', ' ');
        $now = Carbon::now('Asia/Tashkent')->format('d.m.Y H:i');

        $msg = "📥 <b>YANGI YUK KIRIMI QABUL QILINDI!</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🥤 <b>Mahsulot:</b> {$productName}\n";
        $msg .= "💧 <b>Hajmi:</b> {$litres} Litr\n";
        $msg .= "📦 <b>Miqdori:</b> <b>{$qty} dona</b>\n";
        $msg .= "💵 <b>Kirim narxi:</b> {$formattedCost} so'm / dona\n";
        $msg .= "💰 <b>Jami kirim summasi:</b> <b>{$formattedTotal} so'm</b>\n";
        $msg .= "📍 <b>Manba:</b> {$source}\n";
        $msg .= "⏱ <b>Vaqt:</b> {$now}";

        $this->sendToChannel($msg);
    }

    /**
     * Optom savdo (chiqim) bo'lganda Telegram Kanalga to'liq ma'lumot yuborish
     */
    public function notifySale(string $customer, array $items, float $totalRetail, float $totalCost, float $netProfit, string $paymentType, string $source = 'web'): void
    {
        $formattedRetail = number_format($totalRetail, 0, '.', ' ');
        $formattedProfit = number_format($netProfit, 0, '.', ' ');
        $now = Carbon::now('Asia/Tashkent')->format('d.m.Y H:i');

        $paymentIcons = [
            'cash' => '💵 Naqd',
            'card' => '💳 Karta (Humo/Uzcard)',
            'debt' => '📝 Nasiya (Qarz)',
        ];
        $paymentStr = $paymentIcons[$paymentType] ?? $paymentType;

        $msg = "💳 <b>YANGI OPTOM SAVDO (CHIQIM)!</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "👤 <b>Xaridor:</b> {$customer}\n\n";
        $msg .= "📋 <b>Sotilgan tovarlar:</b>\n";

        foreach ($items as $item) {
            $pName = $item['name'] ?? 'Mahsulot';
            $litres = $item['litres'] ?? 0;
            $qty = $item['quantity'] ?? 0;
            $price = number_format($item['unit_price'] ?? 0, 0, '.', ' ');
            $itemTotal = number_format($qty * ($item['unit_price'] ?? 0), 0, '.', ' ');
            $priceType = ($item['is_system_price'] ?? false) ? '(Tizim narxi)' : '(Kelishilgan narx)';

            $msg .= "  • <b>{$pName} {$litres}L</b>: {$qty} dona × {$price} = {$itemTotal} so'm {$priceType}\n";
        }

        $msg .= "\n💰 <b>JAMI TUSHUM:</b> <b>{$formattedRetail} so'm</b>\n";
        $msg .= "✨ <b>USHBU SAVDODAN SOF FOYDA:</b> <b>+{$formattedProfit} so'm</b>\n";
        $msg .= "💳 <b>To'lov usuli:</b> {$paymentStr}\n";
        $msg .= "📍 <b>Kassa manbasi:</b> {$source}\n";
        $msg .= "⏱ <b>Vaqt:</b> {$now}";

        $this->sendToChannel($msg);
    }

    /**
     * Telegram Bot Webhook xabarlarini qayta ishlash.
     * Foydalanuvchi kam yozadi, ko'p amallarni Inline & Reply buttonlarda bajaradi!
     */
    public function handleWebhook(array $update): void
    {
        $chatId = null;
        $text = null;
        $callbackData = null;
        $messageId = null;

        if (isset($update['message'])) {
            $chatId = $update['message']['chat']['id'] ?? null;
            $text = $update['message']['text'] ?? '';
        } elseif (isset($update['callback_query'])) {
            $chatId = $update['callback_query']['message']['chat']['id'] ?? null;
            $messageId = $update['callback_query']['message']['message_id'] ?? null;
            $callbackData = $update['callback_query']['data'] ?? '';
        }

        if (! $chatId) {
            return;
        }

        // 1. Callback query (Inline buttonlar bosilganda)
        if ($callbackData) {
            $this->handleCallback($chatId, $messageId, $callbackData);

            return;
        }

        // 2. Buyruqlar va matnlar
        if (str_starts_with($text, '/start')) {
            $this->sendMainMenu($chatId);
        } elseif (str_starts_with($text, '/hisobot') || $text === '📊 Bugungi Hisobot') {
            $this->sendReport($chatId);
        } elseif (str_starts_with($text, '/qoldiq') || $text === '💧 Suvlar Qoldig\'i') {
            $this->sendStockMenu($chatId);
        } elseif (str_starts_with($text, '/kassa') || $text === '💰 Kassa Balansi') {
            $this->sendCashBalance($chatId);
        } elseif (str_starts_with($text, '/kirim') || $text === '📥 Tezkor Kirim') {
            $this->sendInwardGuide($chatId);
        } else {
            $this->sendMainMenu($chatId, 'Quyidagi tugmalardan birini tanlang:');
        }
    }

    protected function sendMainMenu(int|string $chatId, string $greeting = "Xush kelibsiz! AquaOptom CRM do'kon boti."): void
    {
        $keyboard = [
            'keyboard' => [
                [['text' => '📊 Bugungi Hisobot'], ['text' => '💧 Suvlar Qoldig\'i']],
                [['text' => '💰 Kassa Balansi'], ['text' => '📥 Tezkor Kirim']],
            ],
            'resize_keyboard' => true,
        ];

        $inlineKeyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📊 To\'liq Hisobot', 'callback_data' => 'btn_report'],
                    ['text' => '💧 Qoldiqlar', 'callback_data' => 'btn_stock'],
                ],
                [
                    ['text' => '💰 Kassa Balansi', 'callback_data' => 'btn_cash'],
                ],
            ],
        ];

        $this->sendMessage($chatId, "👋 <b>{$greeting}</b>\n\nBarcha ma'lumotlarni tugmalar orqali qulay boshqaring:", $inlineKeyboard);
    }

    protected function handleCallback(int|string $chatId, ?int $messageId, string $data): void
    {
        if ($data === 'btn_report') {
            $this->sendReport($chatId);
        } elseif ($data === 'btn_stock') {
            $this->sendStockMenu($chatId);
        } elseif ($data === 'btn_cash') {
            $this->sendCashBalance($chatId);
        } elseif (str_starts_with($data, 'stock_p_')) {
            $prodId = (int) str_replace('stock_p_', '', $data);
            $this->sendProductDetailStock($chatId, $prodId);
        }
    }

    public function sendReport(int|string $chatId): void
    {
        $today = Carbon::today('Asia/Tashkent');
        $sales = Sale::whereDate('created_at', $today)->get();

        $totalSales = $sales->sum('total_amount');
        $totalCost = $sales->sum('total_cost');
        $netProfit = $sales->sum('net_profit');
        $count = $sales->count();

        $fTotal = number_format($totalSales, 0, '.', ' ');
        $fProfit = number_format($netProfit, 0, '.', ' ');

        $msg = "📊 <b>BUGUNGI KUNLIK HISOBOT:</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🧾 <b>Savdolar soni:</b> {$count} ta chek\n";
        $msg .= "💰 <b>Jami tushum:</b> <b>{$fTotal} so'm</b>\n";
        $msg .= "✨ <b>BUGUNGI SOF FOYDA:</b> <b>+{$fProfit} so'm</b>\n\n";
        $msg .= '<i>Barcha savdolar real-vaqtda kanalingizga yuborilmoqda.</i>';

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔄 Yangilash', 'callback_data' => 'btn_report']],
                [['text' => '💧 Qoldiqlarni ko\'rish', 'callback_data' => 'btn_stock']],
            ],
        ];

        $this->sendMessage($chatId, $msg, $keyboard);
    }

    public function sendStockMenu(int|string $chatId): void
    {
        $products = Product::with('variants')->get();
        if ($products->isEmpty()) {
            $this->sendMessage($chatId, 'Omborda hali mahsulotlar mavjud emas.');

            return;
        }

        $buttons = [];
        foreach ($products as $p) {
            $totalStock = $p->variants->sum('stock_qty');
            $buttons[] = [
                ['text' => "🥤 {$p->name} ({$totalStock} dona)", 'callback_data' => "stock_p_{$p->id}"],
            ];
        }

        $buttons[] = [['text' => '⬅️ Asosiy menyu', 'callback_data' => 'btn_report']];

        $msg = "💧 <b>OMBORDAGI MAHSULOTLAR RO'YXATI:</b>\nKerakli mahsulotni tanlang, litrlari bo'yicha qoldiq ko'rsatiladi:";
        $this->sendMessage($chatId, $msg, ['inline_keyboard' => $buttons]);
    }

    protected function sendProductDetailStock(int|string $chatId, int $prodId): void
    {
        $product = Product::with('variants')->find($prodId);
        if (! $product) {
            return;
        }

        $msg = "🥤 <b>{$product->name} QOLDIG'I (LITRLAR BO'YICHA):</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";

        foreach ($product->variants as $v) {
            $fPrice = number_format($v->retailPrice, 0, '.', ' ');
            $msg .= "• <b>{$v->litres} Litr</b>: <b>{$v->stock_qty} dona</b> (Narxi: {$fPrice} so'm)\n";
        }

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '⬅️ Barcha mahsulotlar', 'callback_data' => 'btn_stock']],
            ],
        ];

        $this->sendMessage($chatId, $msg, $keyboard);
    }

    public function sendCashBalance(int|string $chatId): void
    {
        $totalSales = Sale::sum('total_amount');
        $fCash = number_format($totalSales, 0, '.', ' ');

        $msg = "💰 <b>KASSA BALANSI:</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "Jami kassa summasi: <b>{$fCash} so'm</b>";

        $this->sendMessage($chatId, $msg, [
            'inline_keyboard' => [
                [['text' => '🔄 Yangilash', 'callback_data' => 'btn_cash']],
            ],
        ]);
    }

    protected function sendInwardGuide(int|string $chatId): void
    {
        $msg = "📥 <b>TEZKOR KIRIM QILISH:</b>\n";
        $msg .= "Tezkor yuk qabul qilish uchun kompyuterdagi Web panel yoki telefondagi Flutter ilovadan foydalanishingiz mumkin.\n\n";
        $msg .= 'U yerda mahsulot nomini yozsangiz avtomatik ID beriladi va 0.5L, 1L, 1.5L litrlari bilan saqlanadi!';
        $this->sendMessage($chatId, $msg);
    }

    protected function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): void
    {
        if (! $this->token) {
            return;
        }

        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        try {
            Http::post($url, $payload);
        } catch (\Exception $e) {
            Log::error('Telegram sendMessage error: '.$e->getMessage());
        }
    }
}

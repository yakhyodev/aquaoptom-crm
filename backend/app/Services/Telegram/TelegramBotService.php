<?php

namespace App\Services\Telegram;

use App\Models\BotDraft;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Dashboard\DashboardQueryService;
use App\Services\Payments\CustomerPaymentService;
use App\Services\Payments\SupplierPaymentService;
use App\Services\Purchase\ReceivePurchaseService;
use App\Services\Reports\ExportService;
use App\Services\Reports\ReportQueryService;
use App\Services\Sales\CreateSaleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TelegramBotService
{
    public function __construct(
        protected TelegramClient $client,
        protected DashboardQueryService $dashboardService,
        protected CreateSaleService $saleService,
        protected ReceivePurchaseService $purchaseService,
        protected CustomerPaymentService $customerPaymentService,
        protected SupplierPaymentService $supplierPaymentService,
        protected ReportQueryService $reportService,
        protected ExportService $exportService
    ) {}

    /**
     * Webhook dan kelgan asosiy update'ni qayta ishlash
     */
    public function handleUpdate(array $update): void
    {
        $chatId = null;
        $telegramUserId = null;
        $username = null;
        $text = null;
        $callbackQueryId = null;
        $callbackData = null;
        $messageId = null;

        if (isset($update['message'])) {
            $msg = $update['message'];
            $chatId = $msg['chat']['id'] ?? null;
            $telegramUserId = $msg['from']['id'] ?? $chatId;
            $username = $msg['from']['username'] ?? null;
            $text = trim($msg['text'] ?? '');
            $messageId = $msg['message_id'] ?? null;
        } elseif (isset($update['callback_query'])) {
            $cb = $update['callback_query'];
            $callbackQueryId = $cb['id'] ?? null;
            $chatId = $cb['message']['chat']['id'] ?? null;
            $telegramUserId = $cb['from']['id'] ?? $chatId;
            $username = $cb['from']['username'] ?? null;
            $callbackData = $cb['data'] ?? '';
            $messageId = $cb['message']['message_id'] ?? null;
        }

        if (! $chatId) {
            return;
        }

        // Acknowledge callback immediately
        if ($callbackQueryId) {
            $this->client->answerCallbackQuery($callbackQueryId);
        }

        // 1. Foydalanuvchini autentifikatsiya qilish
        $user = User::findByTelegramChatId($chatId);

        // Maxsus komanda: /link <user_id> yoki egasi tomonidan bog'lash
        if (! $user && $text && str_starts_with($text, '/link')) {
            $this->handleSelfLink($chatId, $username, $text);

            return;
        }

        // Agar foydalanuvchi tizimda topilmasa — Ruxsat yo'q (Stranger)
        if (! $user) {
            $this->sendAccessDenied($chatId);

            return;
        }

        // Bloklangan xodimni tekshirish
        if ($user->status === 'BLOCKED' || ! $user->isActive()) {
            $this->client->sendMessage(
                $chatId,
                "⛔ <b>Hisobingiz bloklangan!</b>\n\nSiz AquaOptom tizimidan foydalana olmaysiz. Iltimos, ma'muriyatga murojaat qiling."
            );

            return;
        }

        // 2. Callback querylarni qayta ishlash
        if ($callbackData !== null) {
            $this->handleCallback($user, $chatId, $messageId, $callbackData);

            return;
        }

        // 3. Matnli xabarlar va buyruqlarni qayta ishlash
        $this->handleMessage($user, $chatId, $text);
    }

    /**
     * Begona (ro'yxatdan o'tmagan) foydalanuvchiga ruxsat berilmasligi
     */
    protected function sendAccessDenied(int|string $chatId): void
    {
        $safeId = htmlspecialchars((string) $chatId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $msg = "⛔ <b>Ruxsat yo'q!</b>\n\n";
        $msg .= "Sizning Telegram hisobingiz AquaOptom tizimiga biriktirilmagan.\n";
        $msg .= "Sizning Telegram ID: <code>{$safeId}</code>\n\n";
        $msg .= "Iltimos, do'kon egasiga (administratorga) murojaat qiling va ushbu ID'ni profilingizga biriktirishini so'rang.";

        $this->client->sendMessage($chatId, $msg);
    }

    /**
     * Telegram ID ni foydalanuvchiga biriktirish komandasi: /link <code> yoki /link <userId>
     */
    protected function handleSelfLink(int|string $chatId, ?string $username, string $text): void
    {
        $parts = preg_split('/\s+/', trim($text));
        $target = $parts[1] ?? null;

        if (! $target) {
            $this->client->sendMessage(
                $chatId,
                'ℹ️ Foydalanuvchini ulash uchun: <code>/link &lt;foydalanuvchi_id&gt;</code> shaklida yuboring.'
            );

            return;
        }

        $targetUser = User::find($target);
        if (! $targetUser) {
            $this->client->sendMessage($chatId, "❌ Foydalanuvchi (#{$target}) topilmadi.");

            return;
        }

        // Agar boshqa kishi ulangan bo'lsa
        if ($targetUser->telegram_chat_id && (string) $targetUser->telegram_chat_id !== (string) $chatId) {
            $this->client->sendMessage($chatId, '❌ Ushbu foydalanuvchi profiliga boshqa Telegram ID ulangan.');

            return;
        }

        $targetUser->update([
            'telegram_chat_id' => $chatId,
            'telegram_username' => $username,
        ]);

        $this->client->sendMessage(
            $chatId,
            "✅ <b>Muvaffaqiyatli ulandi!</b>\n\nAssalomu alaykum, <b>".$this->escape($targetUser->name).'</b>! Siz tizimga kirdingiz.',
            $this->getMainReplyKeyboard()
        );
    }

    /**
     * Asosiy matnli xabarlarni yo'naltirish
     */
    protected function handleMessage(User $user, int|string $chatId, ?string $text): void
    {
        if (! $text) {
            return;
        }

        // 1. Bekor qilish buyrug'i
        if ($text === '/cancel' || $text === '❌ Bekor qilish') {
            BotDraft::where('chat_id', $chatId)->delete();
            $this->client->sendMessage($chatId, '🚫 Joriy amal bekor qilindi.', $this->getMainReplyKeyboard());

            return;
        }

        // 2. Agar aktiv draft bo'lsa va unda matn kiritish kutilayotgan bo'lsa:
        $draft = BotDraft::where('chat_id', $chatId)->latest()->first();
        if ($draft && $this->isDraftAwaitingTextInput($draft)) {
            $this->handleDraftTextInput($user, $chatId, $draft, $text);

            return;
        }

        // 3. Asosiy buyruqlar va menyu tugmalari
        if ($text === '/start' || $text === '🏠 Asosiy menyu') {
            $this->sendMainMenu($user, $chatId);
        } elseif ($text === '/dashboard' || $text === '📊 Dashboard') {
            $this->sendDashboard($user, $chatId);
        } elseif ($text === '/qoldiq' || $text === '📦 Qoldiq') {
            $this->sendStockMenu($user, $chatId, 1);
        } elseif ($text === '/mijoz_qarzlari' || $text === '👥 Mijoz Qarzlari') {
            $this->sendCustomerDebts($user, $chatId, 1);
        } elseif ($text === '/supplier_qarzlari' || $text === '🏭 Ta\'minotchilar') {
            $this->sendSupplierPayables($user, $chatId, 1);
        } elseif ($text === '/kassa' || $text === '💰 Kassa') {
            $this->sendCashBalances($user, $chatId);
        } elseif ($text === '/hisobot' || $text === '📈 Hisobot / Eksport') {
            $this->sendReportMenu($user, $chatId);
        } elseif ($text === '/savdo' || $text === '🛒 Yangi Savdo') {
            $this->startSaleWizard($user, $chatId);
        } elseif ($text === '/kirim' || $text === '📥 Yangi Kirim') {
            $this->startPurchaseWizard($user, $chatId);
        } elseif ($text === '/tolov' || $text === '💳 To\'lov Qabul Qilish') {
            $this->startPaymentWizard($user, $chatId);
        } elseif ($text === '/pwa' || $text === '📱 Offline PWA') {
            $this->sendPwaInfo($chatId);
        } else {
            $this->sendMainMenu($user, $chatId, 'Quyidagi tugmalardan birini tanlang:');
        }
    }

    /**
     * Asosiy doimiy Reply Keyboard
     */
    public function getMainReplyKeyboard(): array
    {
        return [
            'keyboard' => [
                [['text' => '📊 Dashboard'], ['text' => '📦 Qoldiq']],
                [['text' => '👥 Mijoz Qarzlari'], ['text' => '🏭 Ta\'minotchilar']],
                [['text' => '💰 Kassa'], ['text' => '📈 Hisobot / Eksport']],
                [['text' => '🛒 Yangi Savdo'], ['text' => '📥 Yangi Kirim']],
                [['text' => '💳 To\'lov Qabul Qilish'], ['text' => '📱 Offline PWA']],
            ],
            'resize_keyboard' => true,
        ];
    }

    protected function sendMainMenu(User $user, int|string $chatId, string $greeting = 'Xush kelibsiz!'): void
    {
        $safeName = $this->escape($user->name);
        $role = $user->role;
        $text = "👋 <b>{$greeting}</b>\n\n";
        $text .= "👤 Xodim: <b>{$safeName}</b> (Rol: <code>{$role}</code>)\n";
        $text .= "Suv va ichimliklar do'koni boshqaruv tizimi.\n";
        $text .= "Barcha operatsiyalar va ma'lumotlarni pastdagi tugmalar orqali boshqaring:";

        $this->client->sendMessage($chatId, $text, $this->getMainReplyKeyboard());
    }

    /**
     * 1. 📊 Rolga mos Dashboard ko'rish
     */
    public function sendDashboard(User $user, int|string $chatId): void
    {
        $data = $this->dashboardService->getDashboardData($user, 'today');
        $flow = $data['flow'];
        $balances = $data['balances'];
        $warnings = $data['warnings'];
        $canCost = $data['can_view_cost'];

        $fSales = number_format($flow['total_sales'], 0, '.', ' ');
        $fPaid = number_format($flow['cash_collected'], 0, '.', ' ');
        $fDebt = number_format($flow['new_debt'], 0, '.', ' ');
        $fExp = number_format($flow['operating_expenses'], 0, '.', ' ');
        $fCashAcc = number_format($balances['total_cash'], 0, '.', ' ');

        // Mijozlar va ta'minotchilar balansi alohida (net qilinmaydi!)
        $fCustDebt = number_format($balances['customer_debts'], 0, '.', ' ');
        $fCustAdv = number_format($balances['customer_advances'], 0, '.', ' ');
        $fSuppPay = number_format($balances['supplier_payables'], 0, '.', ' ');
        $fSuppAdv = number_format($balances['supplier_advances'], 0, '.', ' ');

        $msg = "📊 <b>BUGUNGI JONLI DASHBOARD</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "💰 <b>Savdo aylanmasi:</b> {$fSales} so'm ({$flow['sales_count']} ta chek)\n";
        $msg .= "💵 <b>Kassa tushumi:</b> {$fPaid} so'm\n";
        $msg .= '   • Naqd: '.number_format($flow['payments_by_method']['cash'], 0, '.', ' ')." so'm\n";
        $msg .= '   • Karta: '.number_format($flow['payments_by_method']['card'], 0, '.', ' ')." so'm\n";
        $msg .= '   • Bank: '.number_format($flow['payments_by_method']['bank'], 0, '.', ' ')." so'm\n";
        $msg .= "📝 <b>Yangi nasiya:</b> {$fDebt} so'm\n";
        $msg .= "📉 <b>Xarajatlar:</b> {$fExp} so'm\n";

        if ($canCost && $flow['gross_profit'] !== null) {
            $fCost = number_format($flow['total_cost'], 0, '.', ' ');
            $fGross = number_format($flow['gross_profit'], 0, '.', ' ');
            $msg .= "📦 <b>Tannarx (COGS):</b> {$fCost} so'm\n";
            $msg .= "✨ <b>Yalpi foyda:</b> <b>+{$fGross} so'm</b>\n";
        }

        $msg .= "\n🏛 <b>JONLI BALANSLAR (AS-OF):</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "💼 <b>Kassalarda mavjud:</b> {$fCashAcc} so'm\n";
        $msg .= "👥 <b>Mijozlar qarzi:</b> {$fCustDebt} so'm ({$balances['debtors_count']} ta xaridor)\n";
        $msg .= "🎁 <b>Mijozlar avansi:</b> {$fCustAdv} so'm (Alohida!)\n";
        $msg .= "🏭 <b>Ta'minotchilarga qarz:</b> {$fSuppPay} so'm\n";
        $msg .= "🤝 <b>Ta'minotchidagi avans:</b> {$fSuppAdv} so'm\n";
        $msg .= "📦 <b>Ombordagi tovarlar:</b> {$balances['stock_units']} dona\n";

        if ($canCost && $balances['stock_cost_valuation'] !== null) {
            $msg .= '🏷 <b>Ombor tannarxi:</b> '.number_format($balances['stock_cost_valuation'], 0, '.', ' ')." so'm\n";
        }

        $msg .= "\n📡 <b>To'liqlik ko'rsatkichi:</b> {$warnings['completeness_percent']}%\n";
        if ($warnings['stale_devices_count'] > 0) {
            $msg .= "⚠️ <i>{$warnings['completeness_note']}</i>\n";
        }

        $inlineKb = [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 Yangilash', 'callback_data' => 'nav:dashboard'],
                    ['text' => '📦 Qoldiqlar', 'callback_data' => 'stock_page:1'],
                ],
                [
                    ['text' => '👥 Qarzdorlar', 'callback_data' => 'cust_page:1'],
                    ['text' => '💰 Kassalar', 'callback_data' => 'nav:cash'],
                ],
            ],
        ];

        $this->client->sendMessage($chatId, $msg, $inlineKb);
    }

    /**
     * 2. 📦 Qoldiqlar ro'yxati (Paginatsiya bilan)
     */
    public function sendStockMenu(User $user, int|string $chatId, int $page = 1): void
    {
        $perPage = 5;
        $totalVariants = ProductVariant::count();
        $totalPages = max(1, (int) ceil($totalVariants / $perPage));
        $page = max(1, min($page, $totalPages));

        $canCost = $user->hasPermission('view_cost_price') || $user->isOwner();

        $variants = ProductVariant::with(['product', 'volume'])
            ->orderBy('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $msg = "📦 <b>OMBORDAGI TOVARLAR QOLDIG'I (Sahifa {$page}/{$totalPages})</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";

        foreach ($variants as $v) {
            $pName = $this->escape($v->product->name);
            $vName = $this->escape($v->volume->name);

            $balance = DB::table('inventory_balances')
                ->where('product_variant_id', $v->id)
                ->first();

            $qty = $balance ? (int) $balance->quantity : 0;
            $fPrice = number_format($v->default_sale_price ?? 0, 0, '.', ' ');

            $msg .= "• <b>{$pName} ({$vName})</b>\n";
            $msg .= "   Mavjud: <b>{$qty} dona</b> | Narx: {$fPrice} so'm\n";

            if ($canCost && $balance) {
                $fCost = number_format($balance->average_cost ?? 0, 0, '.', ' ');
                $msg .= "   WAC tannarx: {$fCost} so'm\n";
            }
        }

        $navButtons = [];
        if ($page > 1) {
            $navButtons[] = ['text' => '⬅️ Oldingi', 'callback_data' => 'stock_page:'.($page - 1)];
        }
        $navButtons[] = ['text' => "{$page}/{$totalPages}", 'callback_data' => 'noop'];
        if ($page < $totalPages) {
            $navButtons[] = ['text' => 'Keyingi ➡️', 'callback_data' => 'stock_page:'.($page + 1)];
        }

        $inlineKb = [
            'inline_keyboard' => [
                $navButtons,
                [['text' => '🏠 Asosiy menyu', 'callback_data' => 'nav:main']],
            ],
        ];

        $this->client->sendMessage($chatId, $msg, $inlineKb);
    }

    /**
     * 3. 👥 Mijozlar Qarzlari (Paginatsiya bilan)
     */
    public function sendCustomerDebts(User $user, int|string $chatId, int $page = 1): void
    {
        $perPage = 5;
        $query = Customer::where('current_debt', '>', 0)->orderByDesc('current_debt');
        $totalDebtors = $query->count();
        $totalPages = max(1, (int) ceil($totalDebtors / $perPage));
        $page = max(1, min($page, $totalPages));

        $debtors = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $msg = "👥 <b>MIJOZLAR NASIYA QARZDORLIGI (Sahifa {$page}/{$totalPages})</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";

        if ($debtors->isEmpty()) {
            $msg .= "Hozirda qarzdor mijozlar mavjud emas. Barcha hisob-kitoblar toza!\n";
        } else {
            foreach ($debtors as $d) {
                $name = $this->escape($d->name);
                $phone = $this->escape($d->phone);
                $debt = number_format($d->current_debt, 0, '.', ' ');
                $limit = number_format($d->debt_limit ?? 0, 0, '.', ' ');

                $msg .= "• <b>{$name}</b> ({$phone})\n";
                $msg .= "   Qarzi: <font color='red'><b>{$debt} so'm</b></font> (Limit: {$limit})\n";
            }
        }

        $navButtons = [];
        if ($page > 1) {
            $navButtons[] = ['text' => '⬅️ Oldingi', 'callback_data' => 'cust_page:'.($page - 1)];
        }
        $navButtons[] = ['text' => "{$page}/{$totalPages}", 'callback_data' => 'noop'];
        if ($page < $totalPages) {
            $navButtons[] = ['text' => 'Keyingi ➡️', 'callback_data' => 'cust_page:'.($page + 1)];
        }

        $inlineKb = [
            'inline_keyboard' => [
                $navButtons,
                [['text' => '💳 To\'lov qabul qilish', 'callback_data' => 'flow:payment:customer']],
            ],
        ];

        $this->client->sendMessage($chatId, $msg, $inlineKb);
    }

    /**
     * 4. 🏭 Ta'minotchilar Qarzdorligi (Paginatsiya bilan)
     */
    public function sendSupplierPayables(User $user, int|string $chatId, int $page = 1): void
    {
        $perPage = 5;
        $suppliers = Supplier::orderBy('id')->skip(($page - 1) * $perPage)->take($perPage)->get();
        $total = Supplier::count();
        $totalPages = max(1, (int) ceil($total / $perPage));

        $msg = "🏭 <b>TA'MINOTCHILAR BILAN HISOB-KITOB (Sahifa {$page}/{$totalPages})</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";

        foreach ($suppliers as $s) {
            $name = $this->escape($s->name);
            $balance = (int) $s->balance;

            $msg .= "• <b>{$name}</b>: ";
            if ($balance > 0) {
                $msg .= 'Bizning qarzimiz: <b>'.number_format($balance, 0, '.', ' ')." so'm</b>\n";
            } elseif ($balance < 0) {
                $msg .= 'Bizning avansimiz: <b>'.number_format(abs($balance), 0, '.', ' ')." so'm</b>\n";
            } else {
                $msg .= "Hisob nol (0 so'm)\n";
            }
        }

        $navButtons = [];
        if ($page > 1) {
            $navButtons[] = ['text' => '⬅️ Oldingi', 'callback_data' => 'supp_page:'.($page - 1)];
        }
        $navButtons[] = ['text' => "{$page}/{$totalPages}", 'callback_data' => 'noop'];
        if ($page < $totalPages) {
            $navButtons[] = ['text' => 'Keyingi ➡️', 'callback_data' => 'supp_page:'.($page + 1)];
        }

        $inlineKb = [
            'inline_keyboard' => [
                $navButtons,
                [['text' => '💳 Ta\'minotchiga to\'lov', 'callback_data' => 'flow:payment:supplier']],
            ],
        ];

        $this->client->sendMessage($chatId, $msg, $inlineKb);
    }

    /**
     * 5. 💰 Kassalar Balansi
     */
    public function sendCashBalances(User $user, int|string $chatId): void
    {
        $accounts = CashAccount::all();
        $total = $accounts->sum('balance');

        $msg = "💰 <b>KASSALAR VA HISOB-RAQAMLAR BALANSI</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";

        foreach ($accounts as $acc) {
            $name = $this->escape($acc->name);
            $type = $acc->type;
            $bal = number_format($acc->balance, 0, '.', ' ');
            $icon = $type === 'CASH' ? '💵' : ($type === 'CARD' ? '💳' : '🏦');

            $msg .= "{$icon} <b>{$name}</b> ({$type}): <b>{$bal} so'm</b>\n";
        }

        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "💼 <b>Jami mavjud naqd/kassa mablag'i:</b> <b>".number_format($total, 0, '.', ' ')." so'm</b>\n";

        $this->client->sendMessage($chatId, $msg, [
            'inline_keyboard' => [
                [['text' => '🔄 Yangilash', 'callback_data' => 'nav:cash']],
            ],
        ]);
    }

    /**
     * 6. 📈 Hisobot Menyu va Sana bo'yicha eksport
     */
    public function sendReportMenu(User $user, int|string $chatId): void
    {
        $msg = "📈 <b>MOLIYAVIY VA SAVDO HISOBOTLARI</b>\n\n";
        $msg .= 'Davrni tanlang, ledger asosidagi rasmiy hisobot yoki eksport havolasini olasiz:';

        $inlineKb = [
            'inline_keyboard' => [
                [
                    ['text' => '📅 Bugun', 'callback_data' => 'rep_range:today'],
                    ['text' => '📅 Kecha', 'callback_data' => 'rep_range:yesterday'],
                ],
                [
                    ['text' => '📅 Shu hafta', 'callback_data' => 'rep_range:this_week'],
                    ['text' => '📅 Shu oy', 'callback_data' => 'rep_range:this_month'],
                ],
            ],
        ];

        $this->client->sendMessage($chatId, $msg, $inlineKb);
    }

    /**
     * Hisobot davri bo'yicha ko'rsatish
     */
    protected function showReportPeriodSummary(User $user, int|string $chatId, string $period): void
    {
        $data = $this->dashboardService->getDashboardData($user, $period);
        $flow = $data['flow'];
        $canCost = $data['can_view_cost'];

        $fSales = number_format($flow['total_sales'], 0, '.', ' ');
        $fPaid = number_format($flow['cash_collected'], 0, '.', ' ');
        $fDebt = number_format($flow['new_debt'], 0, '.', ' ');
        $fExp = number_format($flow['operating_expenses'], 0, '.', ' ');

        $periodLabel = $data['period']['label'] ?? $period;
        $msg = "📈 <b>HISOBOT: {$periodLabel}</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "💰 <b>Savdo summasi:</b> {$fSales} so'm ({$flow['sales_count']} ta chek)\n";
        $msg .= "💵 <b>Tushum:</b> {$fPaid} so'm\n";
        $msg .= "📝 <b>Yangi nasiya:</b> {$fDebt} so'm\n";
        $msg .= "📉 <b>Xarajatlar:</b> {$fExp} so'm\n";

        if ($canCost && $flow['gross_profit'] !== null) {
            $msg .= '📦 <b>WAC Tannarx:</b> '.number_format($flow['total_cost'], 0, '.', ' ')." so'm\n";
            $msg .= '✨ <b>Yalpi foyda:</b> +'.number_format($flow['gross_profit'], 0, '.', ' ')." so'm\n";
        }

        $appUrl = config('services.telegram.pwa_url', env('APP_URL', 'http://127.0.0.1:8000'));
        $msg .= "\n📥 <i>Eksport faylini web panel orqali yuklab olish mumkin: {$appUrl}/reports</i>";

        $this->client->sendMessage($chatId, $msg, [
            'inline_keyboard' => [
                [['text' => '⬅️ Boshqa davr', 'callback_data' => 'nav:reports']],
            ],
        ]);
    }

    /**
     * 7. 📱 Offline PWA Ma'lumoti va Havola
     */
    public function sendPwaInfo(int|string $chatId): void
    {
        $pwaUrl = config('services.telegram.pwa_url', env('APP_URL', 'http://127.0.0.1:8000'));

        $msg = "📱 <b>OFFLINE SAVDO VA PWA ILOVASI</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "ℹ️ <b>Diqqat:</b> Telegram bot doimiy server internetiga bog'liq va <b>offline rejimda ishlamaydi</b>.\n\n";
        $msg .= "Agar do'konda internet uzilsa, savdoni to'xtatmaslik uchun kompyuter yoki telefonda <b>PWA (Progressive Web App)</b> ilovasini ishlating.\n\n";
        $msg .= "🔗 <b>PWA Havolasi:</b>\n<code>{$pwaUrl}</code>\n\n";
        $msg .= "PWA ilovasi orqali:\n";
        $msg .= "• Internetsiz chek yozish va kassa qilish\n";
        $msg .= "• Lokal IndexedDB bazasida xavfsiz saqlash\n";
        $msg .= '• Internet qaytganda avtomatik serverga sinxronlash mumkin.';

        $this->client->sendMessage($chatId, $msg);
    }

    // =========================================================================
    // 🛒 KO'P QATORLI SAVDO WIZARD (SALE FLOW)
    // =========================================================================

    public function startSaleWizard(User $user, int|string $chatId): void
    {
        // Ruxsat tekshiruvi
        if (! $user->isOwner() && ! $user->isAdmin() && ! in_array($user->role, ['SALES_MANAGER', 'CASHIER'], true) && ! $user->hasPermission('create_sale')) {
            $this->client->sendMessage($chatId, "⛔ Sizda savdo yaratish ruxsati yo'q.");

            return;
        }

        // Yangi qoralama
        BotDraft::where('chat_id', $chatId)->delete();

        $operationId = (string) Str::uuid();
        $draft = BotDraft::create([
            'user_id' => $user->id,
            'chat_id' => $chatId,
            'type' => 'SALE',
            'step' => 'SELECT_CUSTOMER',
            'operation_id' => $operationId,
            'payload' => [
                'items' => [],
                'customer_id' => null,
                'customer_name' => 'Tezkor xaridor',
                'paid_amount' => 0,
                'payment_method' => 'CASH',
                'cash_account_id' => null,
            ],
        ]);

        $customers = Customer::orderBy('name')->take(6)->get();
        $buttons = [
            [['text' => '⚡ Tezkor Xaridor (Naqd)', 'callback_data' => 'sale_cust:quick']],
        ];

        foreach ($customers as $c) {
            $name = $this->escape($c->name);
            $buttons[] = [['text' => "👤 {$name}", 'callback_data' => "sale_cust:{$c->id}"]];
        }

        $buttons[] = [['text' => '➕ Yangi mijoz qo\'shish', 'callback_data' => 'sale_cust:new']];
        $buttons[] = [['text' => '❌ Bekor qilish', 'callback_data' => "draft_cancel:{$operationId}"]];

        $msg = "🛒 <b>YANGI SAVDO BOSQICHI: 1/4</b>\n\nXaridorni tanlang:";
        $this->client->sendMessage($chatId, $msg, ['inline_keyboard' => $buttons]);
    }

    protected function showSaleProductList(User $user, int|string $chatId, BotDraft $draft): void
    {
        $draft->update(['step' => 'SELECT_PRODUCT']);

        $products = Product::orderBy('name')->take(8)->get();
        $buttons = [];

        foreach ($products as $p) {
            $buttons[] = [['text' => '🥤 '.$this->escape($p->name), 'callback_data' => "sale_prod:{$p->id}"]];
        }

        $buttons[] = [['text' => '❌ Savdoni bekor qilish', 'callback_data' => "draft_cancel:{$draft->operation_id}"]];

        $itemsCount = count($draft->payload['items'] ?? []);
        $msg = "🛒 <b>SAVDO: Tovar tanlash</b> (Savatda: {$itemsCount} xil tovar)\n\nMahsulot turini tanlang:";
        $this->client->sendMessage($chatId, $msg, ['inline_keyboard' => $buttons]);
    }

    protected function showSaleVolumeList(User $user, int|string $chatId, BotDraft $draft, int $productId): void
    {
        $product = Product::with('variants.volume')->find($productId);
        if (! $product) {
            return;
        }

        $draft->update(['step' => 'SELECT_VARIANT']);

        $buttons = [];
        foreach ($product->variants as $v) {
            $volName = $v->volume->name;
            $fPrice = number_format($v->default_sale_price ?? 0, 0, '.', ' ');
            $buttons[] = [
                ['text' => "💧 {$volName} (Tizim: {$fPrice} so'm)", 'callback_data' => "sale_var:{$v->id}"],
            ];
        }

        $buttons[] = [['text' => '⬅️ Orqaga', 'callback_data' => 'sale_back_prod']];

        $msg = "🥤 <b>{$this->escape($product->name)}</b> uchun hajmni tanlang:";
        $this->client->sendMessage($chatId, $msg, ['inline_keyboard' => $buttons]);
    }

    protected function promptQuantity(User $user, int|string $chatId, BotDraft $draft, int $variantId): void
    {
        $variant = ProductVariant::with(['product', 'volume'])->find($variantId);
        if (! $variant) {
            return;
        }

        $payload = $draft->payload;
        $payload['current_variant_id'] = $variantId;
        $payload['current_variant_title'] = "{$variant->product->name} ({$variant->volume->name})";
        $payload['current_system_price'] = (int) $variant->default_sale_price;

        $draft->update([
            'step' => 'INPUT_QTY',
            'payload' => $payload,
        ]);

        $msg = '🔢 <b>'.$this->escape($payload['current_variant_title'])."</b>\n\n";
        $msg .= 'Necha dona kiritasiz? (Faqat butun musbat son yozing, masalan: <code>10</code>):';

        $this->client->sendMessage($chatId, $msg);
    }

    protected function promptPriceType(User $user, int|string $chatId, BotDraft $draft, int $qty): void
    {
        $payload = $draft->payload;
        $payload['current_qty'] = $qty;
        $sysPrice = (int) ($payload['current_system_price'] ?? 0);
        $vTitle = $payload['current_variant_title'] ?? '';

        $draft->update([
            'step' => 'SELECT_PRICE_TYPE',
            'payload' => $payload,
        ]);

        $buttons = [
            [['text' => '🏷 Tizim narxi ('.number_format($sysPrice, 0, '.', ' ')." so'm)", 'callback_data' => 'sale_price_sys']],
            [['text' => '✏️ Kelishilgan narx kiritish', 'callback_data' => 'sale_price_manual']],
        ];

        $msg = "💰 <b>{$this->escape($vTitle)}</b> — {$qty} dona\n\nNarx turini tanlang:";
        $this->client->sendMessage($chatId, $msg, ['inline_keyboard' => $buttons]);
    }

    protected function addItemToSaleCart(User $user, int|string $chatId, BotDraft $draft, int $price, bool $isSystemPrice): void
    {
        $payload = $draft->payload;
        $variantId = (int) $payload['current_variant_id'];
        $qty = (int) $payload['current_qty'];
        $title = $payload['current_variant_title'];

        // Savatga qo'shish
        $payload['items'][] = [
            'variant_id' => $variantId,
            'title' => $title,
            'quantity' => $qty,
            'sale_price' => $price,
            'line_total' => $qty * $price,
            'is_system_price' => $isSystemPrice,
        ];

        unset($payload['current_variant_id'], $payload['current_qty'], $payload['current_variant_title'], $payload['current_system_price']);

        $draft->update([
            'step' => 'CART_REVIEW',
            'payload' => $payload,
        ]);

        $this->showCartReview($user, $chatId, $draft);
    }

    protected function showCartReview(User $user, int|string $chatId, BotDraft $draft): void
    {
        $items = $draft->payload['items'] ?? [];
        $totalSum = 0;

        $msg = "🛒 <b>SAVATDAGI TOVARLAR RO'YXATI:</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";

        foreach ($items as $idx => $it) {
            $num = $idx + 1;
            $fP = number_format($it['sale_price'], 0, '.', ' ');
            $fT = number_format($it['line_total'], 0, '.', ' ');
            $pType = $it['is_system_price'] ? '(Tizim)' : '(Kelishilgan)';
            $totalSum += $it['line_total'];

            $msg .= "{$num}. <b>".$this->escape($it['title'])."</b>\n";
            $msg .= "   {$it['quantity']} dona × {$fP} = <b>{$fT} so'm</b> {$pType}\n";
        }

        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= '💰 <b>JAMI SUMMA:</b> <b>'.number_format($totalSum, 0, '.', ' ')." so'm</b>\n\n";
        $msg .= "Yana tovar qo'shasizmi yoki to'lovga o'tasizmi?";

        $buttons = [
            [['text' => '➕ Yana tovar qo\'shish', 'callback_data' => 'sale_add_more']],
            [['text' => '💳 To\'lovga o\'tish', 'callback_data' => 'sale_to_payment']],
            [['text' => '❌ Bekor qilish', 'callback_data' => "draft_cancel:{$draft->operation_id}"]],
        ];

        $this->client->sendMessage($chatId, $msg, ['inline_keyboard' => $buttons]);
    }

    protected function promptSalePayment(User $user, int|string $chatId, BotDraft $draft): void
    {
        $customerId = $draft->payload['customer_id'] ?? null;
        $items = $draft->payload['items'] ?? [];
        $totalSum = array_sum(array_column($items, 'line_total'));

        // Agar tezkor xaridor bo'lsa faqat to'liq to'lov
        if (! $customerId) {
            $payload = $draft->payload;
            $payload['paid_amount'] = $totalSum;
            $payload['payment_degree'] = 'FULL';
            $draft->update([
                'step' => 'SELECT_CASH_ACCOUNT',
                'payload' => $payload,
            ]);

            $this->promptCashAccountSelection($chatId, $draft, 'sale');

            return;
        }

        // Doimiy mijoz bo'lsa: to'liq, qisman yoki nasiya tanlash
        $draft->update(['step' => 'SELECT_PAYMENT_DEGREE']);

        $buttons = [
            [['text' => "🟢 To'liq to'lov (".number_format($totalSum, 0, '.', ' ')." so'm)", 'callback_data' => 'sale_deg:full']],
            [['text' => "🟡 Qisman to'lov (qolgani nasiya)", 'callback_data' => 'sale_deg:partial']],
            [['text' => "🔴 To'liq nasiya (0 to'lov)", 'callback_data' => 'sale_deg:debt']],
        ];

        $msg = "💳 <b>TO'LOV DARAJASINI TANLANG:</b>\nJami summa: <b>".number_format($totalSum, 0, '.', ' ')." so'm</b>";
        $this->client->sendMessage($chatId, $msg, ['inline_keyboard' => $buttons]);
    }

    protected function promptCashAccountSelection(int|string $chatId, BotDraft $draft, string $prefix): void
    {
        $accounts = CashAccount::all();
        $buttons = [];

        foreach ($accounts as $acc) {
            $icon = $acc->type === 'CASH' ? '💵' : ($acc->type === 'CARD' ? '💳' : '🏦');
            $buttons[] = [
                ['text' => "{$icon} ".$this->escape($acc->name), 'callback_data' => "{$prefix}_acc:{$acc->id}"],
            ];
        }

        $msg = '💼 <b>Kassa hisobini tanlang:</b>';
        $this->client->sendMessage($chatId, $msg, ['inline_keyboard' => $buttons]);
    }

    protected function showSalePreview(User $user, int|string $chatId, BotDraft $draft): void
    {
        $draft->update(['step' => 'CONFIRM']);

        $payload = $draft->payload;
        $items = $payload['items'] ?? [];
        $totalSum = array_sum(array_column($items, 'line_total'));
        $paid = (int) ($payload['paid_amount'] ?? 0);
        $debt = max(0, $totalSum - $paid);
        $custName = $payload['customer_name'] ?? 'Tezkor xaridor';
        $acc = CashAccount::find($payload['cash_account_id'] ?? null);
        $accName = $acc ? $acc->name : 'Hisob ko\'rsatilmagan';

        $msg = "🧾 <b>SAVDO HUJJATI PREVIEW</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= '👤 <b>Xaridor:</b> '.$this->escape($custName)."\n";
        $msg .= "📋 <b>Tovarlar:</b>\n";

        foreach ($items as $idx => $it) {
            $num = $idx + 1;
            $fP = number_format($it['sale_price'], 0, '.', ' ');
            $fT = number_format($it['line_total'], 0, '.', ' ');
            $msg .= "  {$num}. {$this->escape($it['title'])} — {$it['quantity']} dona × {$fP} = {$fT} so'm\n";
        }

        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= '💰 <b>Jami summa:</b> <b>'.number_format($totalSum, 0, '.', ' ')." so'm</b>\n";
        $msg .= "💵 <b>To'lanadi:</b> <b>".number_format($paid, 0, '.', ' ')." so'm</b> ({$this->escape($accName)})\n";
        $msg .= '📝 <b>Yangi nasiya (qarz):</b> <b>'.number_format($debt, 0, '.', ' ')." so'm</b>\n";
        $msg .= "🔑 <b>Operatsiya ID:</b> <code>{$draft->operation_id}</code>\n\n";
        $msg .= 'Savdoni rasmiylashtirishni tasdiqlaysizmi?';

        $buttons = [
            [['text' => '✅ Tasdiqlash', 'callback_data' => "sale_confirm:{$draft->operation_id}"]],
            [['text' => '❌ Bekor qilish', 'callback_data' => "draft_cancel:{$draft->operation_id}"]],
        ];

        $this->client->sendMessage($chatId, $msg, ['inline_keyboard' => $buttons]);
    }

    /**
     * Savdoni tasdiqlash (20 tasdiq takrorlansa ham bitta yagona operatsiya!)
     */
    protected function confirmSale(User $user, int|string $chatId, string $operationId): void
    {
        // 1. Idempotency tekshiruvi: ushbu operation_id bilan allaqachon savdo bo'lganmi?
        $existingSale = Sale::where('operation_id', $operationId)->first();
        if ($existingSale) {
            // Allaqachon yaratilgan bo'lsa yangi qo'shmaymiz, faqat ma'lumot beramiz
            $this->client->sendMessage(
                $chatId,
                "✅ <b>Ushbu savdo allaqachon tasdiqlangan:</b>\n".
                "Chek raqami: <b>#{$existingSale->invoice_number}</b>\n".
                'Jami: <b>'.number_format($existingSale->total_amount, 0, '.', ' ')." so'm</b>\n".
                "To'landi: <b>".number_format($existingSale->paid_amount, 0, '.', ' ')." so'm</b>",
                $this->getMainReplyKeyboard()
            );

            return;
        }

        $draft = BotDraft::where('operation_id', $operationId)->first();
        if (! $draft) {
            $this->client->sendMessage($chatId, "❌ Savdo qoralamasi topilmadi yoki muddati o'tgan.", $this->getMainReplyKeyboard());

            return;
        }

        $payload = $draft->payload;
        $items = array_map(fn ($it) => [
            'variant_id' => $it['variant_id'],
            'quantity' => $it['quantity'],
            'sale_price' => $it['sale_price'],
            'is_system_price' => $it['is_system_price'] ?? false,
        ], $payload['items']);

        $warehouse = Warehouse::where('is_default', true)->first() ?? Warehouse::first();

        try {
            // Ayni backend servisni chaqiramiz (hech qanday yangi moliyaviy formula yo'q!)
            $sale = $this->saleService->execute(
                customerId: $payload['customer_id'] ?? null,
                items: $items,
                operationId: $operationId,
                paidAmount: (int) ($payload['paid_amount'] ?? 0),
                cashAccountId: $payload['cash_account_id'] ?? null,
                warehouseId: $warehouse?->id ?? 1,
                userId: $user->id,
                source: 'telegram'
            );

            // Draftni o'chiramiz
            $draft->delete();

            $msg = "✅ <b>SAVDO MUVAFFAQIYATLI SAQLANDI!</b>\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "🧾 <b>Chek raqami:</b> #{$sale->invoice_number}\n";
            $msg .= '💰 <b>Jami summa:</b> '.number_format($sale->total_amount, 0, '.', ' ')." so'm\n";
            $msg .= "💵 <b>To'landi:</b> ".number_format($sale->paid_amount, 0, '.', ' ')." so'm\n";
            $msg .= '📝 <b>Qolgan qarz:</b> '.number_format($sale->debt_amount, 0, '.', ' ')." so'm\n";
            $msg .= "🔑 <b>Operatsiya:</b> <code>{$operationId}</code>\n";

            $this->client->sendMessage($chatId, $msg, $this->getMainReplyKeyboard());
        } catch (\Throwable $e) {
            $this->client->sendMessage($chatId, '❌ <b>Xatolik yuz berdi:</b> '.$this->escape($e->getMessage()), $this->getMainReplyKeyboard());
        }
    }

    // =========================================================================
    // 📥 YUK KIRIMI WIZARD (PURCHASE FLOW)
    // =========================================================================

    public function startPurchaseWizard(User $user, int|string $chatId): void
    {
        if (! $user->isOwner() && ! $user->isAdmin() && ! in_array($user->role, ['WAREHOUSE_MANAGER'], true) && ! $user->hasPermission('receive_stock') && ! $user->hasPermission('create_purchase')) {
            $this->client->sendMessage($chatId, "⛔ Sizda tovar kirim qilish ruxsati yo'q.");

            return;
        }

        BotDraft::where('chat_id', $chatId)->delete();

        $operationId = (string) Str::uuid();
        $draft = BotDraft::create([
            'user_id' => $user->id,
            'chat_id' => $chatId,
            'type' => 'PURCHASE',
            'step' => 'SELECT_SUPPLIER',
            'operation_id' => $operationId,
            'payload' => [
                'supplier_id' => null,
                'supplier_name' => '',
                'items' => [],
            ],
        ]);

        $suppliers = Supplier::orderBy('name')->take(6)->get();
        $buttons = [];

        foreach ($suppliers as $s) {
            $buttons[] = [['text' => '🏭 '.$this->escape($s->name), 'callback_data' => "pur_supp:{$s->id}"]];
        }

        $buttons[] = [['text' => '❌ Bekor qilish', 'callback_data' => "draft_cancel:{$operationId}"]];

        $msg = "📥 <b>YANGI TOVAR KIRIMI (BOSQICH 1/4)</b>\n\nTa'minotchi zavodni tanlang:";
        $this->client->sendMessage($chatId, $msg, ['inline_keyboard' => $buttons]);
    }

    protected function showPurchaseProductList(int|string $chatId, BotDraft $draft): void
    {
        $draft->update(['step' => 'SELECT_PRODUCT']);

        $products = Product::orderBy('name')->take(8)->get();
        $buttons = [];

        foreach ($products as $p) {
            $buttons[] = [['text' => '🥤 '.$this->escape($p->name), 'callback_data' => "pur_prod:{$p->id}"]];
        }

        $buttons[] = [['text' => '❌ Bekor qilish', 'callback_data' => "draft_cancel:{$draft->operation_id}"]];

        $msg = '📥 <b>Kirim qilinadigan mahsulotni tanlang:</b>';
        $this->client->sendMessage($chatId, $msg, ['inline_keyboard' => $buttons]);
    }

    protected function showPurchaseVolumeList(int|string $chatId, BotDraft $draft, int $productId): void
    {
        $product = Product::with('variants.volume')->find($productId);
        if (! $product) {
            return;
        }

        $draft->update(['step' => 'SELECT_VARIANT']);

        $buttons = [];
        foreach ($product->variants as $v) {
            $buttons[] = [
                ['text' => "💧 {$v->volume->name}", 'callback_data' => "pur_var:{$v->id}"],
            ];
        }

        $msg = "🥤 <b>{$this->escape($product->name)}</b> uchun hajmni tanlang:";
        $this->client->sendMessage($chatId, $msg, ['inline_keyboard' => $buttons]);
    }

    protected function promptPurchaseQuantity(int|string $chatId, BotDraft $draft, int $variantId): void
    {
        $variant = ProductVariant::with(['product', 'volume'])->find($variantId);
        if (! $variant) {
            return;
        }

        $payload = $draft->payload;
        $payload['variant_id'] = $variantId;
        $payload['variant_title'] = "{$variant->product->name} ({$variant->volume->name})";

        $draft->update([
            'step' => 'INPUT_PURCHASE_QTY',
            'payload' => $payload,
        ]);

        $msg = '📦 <b>'.$this->escape($payload['variant_title'])."</b>\n\nQabul qilinayotgan dona miqdorini yozing (masalan: <code>100</code>):";
        $this->client->sendMessage($chatId, $msg);
    }

    protected function promptPurchaseUnitCost(int|string $chatId, BotDraft $draft, int $qty): void
    {
        $payload = $draft->payload;
        $payload['quantity'] = $qty;

        $draft->update([
            'step' => 'INPUT_PURCHASE_COST',
            'payload' => $payload,
        ]);

        $msg = "💵 1 dona mahsulot uchun <b>kirim (tannarx) summasini</b> yozing (so'm, masalan: <code>3000</code>):";
        $this->client->sendMessage($chatId, $msg);
    }

    protected function promptPurchasePayment(int|string $chatId, BotDraft $draft, int $unitCost): void
    {
        $payload = $draft->payload;
        $payload['unit_cost'] = $unitCost;
        $total = $payload['quantity'] * $unitCost;
        $payload['total_amount'] = $total;

        $draft->update([
            'step' => 'INPUT_PURCHASE_PAID',
            'payload' => $payload,
        ]);

        $msg = '💰 Jami kirim summasi: <b>'.number_format($total, 0, '.', ' ')." so'm</b>\n\n";
        $msg .= "Hozir ta'minotchiga qancha to'lanmoqda? (0 dan ".number_format($total, 0, '.', ' ')." gacha so'm kiriting):";

        $this->client->sendMessage($chatId, $msg);
    }

    protected function showPurchasePreview(int|string $chatId, BotDraft $draft): void
    {
        $draft->update(['step' => 'CONFIRM']);

        $payload = $draft->payload;
        $total = (int) $payload['total_amount'];
        $paid = (int) $payload['paid_amount'];
        $debt = max(0, $total - $paid);
        $suppName = $payload['supplier_name'];
        $title = $payload['variant_title'];
        $acc = CashAccount::find($payload['cash_account_id'] ?? null);

        $msg = "🧾 <b>KIRIM HUJJATI PREVIEW</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🏭 <b>Ta'minotchi:</b> ".$this->escape($suppName)."\n";
        $msg .= '📦 <b>Tovar:</b> '.$this->escape($title)."\n";
        $msg .= "🔢 <b>Miqdori:</b> {$payload['quantity']} dona × ".number_format($payload['unit_cost'], 0, '.', ' ')." so'm\n";
        $msg .= '💰 <b>Jami summa:</b> <b>'.number_format($total, 0, '.', ' ')." so'm</b>\n";
        $msg .= "💵 <b>To'landi:</b> <b>".number_format($paid, 0, '.', ' ')." so'm</b> (".$this->escape($acc?->name ?? 'Kassa yo\'q').")\n";
        $msg .= '📝 <b>Qarzimiz:</b> <b>'.number_format($debt, 0, '.', ' ')." so'm</b>\n";
        $msg .= "🔑 <b>Operatsiya:</b> <code>{$draft->operation_id}</code>\n\n";
        $msg .= 'Kirimni tasdiqlaysizmi?';

        $buttons = [
            [['text' => '✅ Kirimni tasdiqlash', 'callback_data' => "pur_confirm:{$draft->operation_id}"]],
            [['text' => '❌ Bekor qilish', 'callback_data' => "draft_cancel:{$draft->operation_id}"]],
        ];

        $this->client->sendMessage($chatId, $msg, ['inline_keyboard' => $buttons]);
    }

    protected function confirmPurchase(User $user, int|string $chatId, string $operationId): void
    {
        // Idempotency: allaqachon kirim qilinganmi?
        $existing = Purchase::where('operation_id', $operationId)->first();
        if ($existing) {
            $this->client->sendMessage(
                $chatId,
                "✅ <b>Ushbu kirim allaqachon tasdiqlangan:</b>\nHujjat: #{$existing->invoice_number}\nJami: ".number_format($existing->total_amount, 0, '.', ' ')." so'm",
                $this->getMainReplyKeyboard()
            );

            return;
        }

        $draft = BotDraft::where('operation_id', $operationId)->first();
        if (! $draft) {
            $this->client->sendMessage($chatId, '❌ Kirim qoralamasi topilmadi.', $this->getMainReplyKeyboard());

            return;
        }

        $payload = $draft->payload;
        $warehouse = Warehouse::where('is_default', true)->first() ?? Warehouse::first();

        try {
            $purchase = $this->purchaseService->execute(
                supplierId: (int) $payload['supplier_id'],
                items: [
                    [
                        'variant_id' => $payload['variant_id'],
                        'quantity' => $payload['quantity'],
                        'unit_cost' => $payload['unit_cost'],
                    ],
                ],
                operationId: $operationId,
                paidAmount: (int) $payload['paid_amount'],
                cashAccountId: $payload['cash_account_id'] ?? null,
                warehouseId: $warehouse?->id ?? 1,
                userId: $user->id,
                source: 'telegram'
            );

            $draft->delete();

            $msg = "✅ <b>YUK KIRIMI MUVAFFAQIYATLI QABUL QILINDI!</b>\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "🧾 <b>Kirim hujjati:</b> #{$purchase->invoice_number}\n";
            $msg .= '💰 <b>Jami summa:</b> '.number_format($purchase->total_amount, 0, '.', ' ')." so'm\n";
            $msg .= "💵 <b>To'langan summa:</b> ".number_format($purchase->paid_amount, 0, '.', ' ')." so'm\n";
            $msg .= "🏭 <b>Ta'minotchiga qarz:</b> ".number_format($purchase->debt_amount, 0, '.', ' ')." so'm\n";

            $this->client->sendMessage($chatId, $msg, $this->getMainReplyKeyboard());
        } catch (\Throwable $e) {
            $this->client->sendMessage($chatId, '❌ <b>Xatolik yuz berdi:</b> '.$this->escape($e->getMessage()), $this->getMainReplyKeyboard());
        }
    }

    // =========================================================================
    // 💳 TO'LOV QABUL QILISH WIZARD (PAYMENT FLOW)
    // =========================================================================

    public function startPaymentWizard(User $user, int|string $chatId): void
    {
        $buttons = [
            [['text' => '👥 Mijozdan qarz to\'lovi qabul qilish', 'callback_data' => 'flow:payment:customer']],
            [['text' => '🏭 Ta\'minotchiga to\'lov qilish', 'callback_data' => 'flow:payment:supplier']],
        ];

        $this->client->sendMessage($chatId, "💳 <b>To'lov turini tanlang:</b>", ['inline_keyboard' => $buttons]);
    }

    protected function startCustomerPaymentFlow(User $user, int|string $chatId): void
    {
        BotDraft::where('chat_id', $chatId)->delete();

        $operationId = (string) Str::uuid();
        BotDraft::create([
            'user_id' => $user->id,
            'chat_id' => $chatId,
            'type' => 'CUSTOMER_PAYMENT',
            'step' => 'SELECT_CUSTOMER',
            'operation_id' => $operationId,
            'payload' => [],
        ]);

        $debtors = Customer::where('current_debt', '>', 0)->orderByDesc('current_debt')->take(6)->get();
        $buttons = [];

        foreach ($debtors as $d) {
            $name = $this->escape($d->name);
            $debt = number_format($d->current_debt, 0, '.', ' ');
            $buttons[] = [['text' => "👤 {$name} ({$debt} so'm)", 'callback_data' => "pay_cust:{$d->id}"]];
        }

        $buttons[] = [['text' => '❌ Bekor qilish', 'callback_data' => "draft_cancel:{$operationId}"]];

        $msg = '💳 <b>Qarzdor mijozni tanlang:</b>';
        $this->client->sendMessage($chatId, $msg, ['inline_keyboard' => $buttons]);
    }

    protected function promptCustomerPaymentAmount(int|string $chatId, BotDraft $draft, int $customerId): void
    {
        $customer = Customer::find($customerId);
        if (! $customer) {
            return;
        }

        $payload = $draft->payload;
        $payload['customer_id'] = $customerId;
        $payload['customer_name'] = $customer->name;
        $payload['current_debt'] = $customer->current_debt;

        $draft->update([
            'step' => 'INPUT_PAYMENT_AMOUNT',
            'payload' => $payload,
        ]);

        $fDebt = number_format($customer->current_debt, 0, '.', ' ');
        $msg = '👤 <b>'.$this->escape($customer->name)."</b>\n";
        $msg .= "Mavjud qarzi: <b>{$fDebt} so'm</b>\n\n";
        $msg .= "Qabul qilingan summani so'mda kiriting (masalan: <code>50000</code>):";

        $this->client->sendMessage($chatId, $msg);
    }

    protected function showCustomerPaymentPreview(int|string $chatId, BotDraft $draft): void
    {
        $draft->update(['step' => 'CONFIRM']);

        $payload = $draft->payload;
        $amt = number_format($payload['amount'], 0, '.', ' ');
        $acc = CashAccount::find($payload['cash_account_id']);

        $msg = "🧾 <b>MIJOZ TO'LOVI PREVIEW</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= '👤 <b>Mijoz:</b> '.$this->escape($payload['customer_name'])."\n";
        $msg .= "💵 <b>To'lov summasi:</b> <b>{$amt} so'm</b>\n";
        $msg .= '💼 <b>Kassa:</b> '.$this->escape($acc?->name ?? 'Kassa')."\n";
        $msg .= "🔑 <b>Operatsiya:</b> <code>{$draft->operation_id}</code>\n\n";
        $msg .= "To'lovni tasdiqlaysizmi?";

        $buttons = [
            [['text' => '✅ To\'lovni tasdiqlash', 'callback_data' => "pay_confirm:customer:{$draft->operation_id}"]],
            [['text' => '❌ Bekor qilish', 'callback_data' => "draft_cancel:{$draft->operation_id}"]],
        ];

        $this->client->sendMessage($chatId, $msg, ['inline_keyboard' => $buttons]);
    }

    protected function confirmCustomerPayment(User $user, int|string $chatId, string $operationId): void
    {
        $existing = Payment::where('operation_id', $operationId)->first();
        if ($existing) {
            $this->client->sendMessage(
                $chatId,
                "✅ <b>Ushbu to'lov allaqachon tasdiqlangan:</b>\nKvitansiya: #{$existing->payment_number}\nSumma: ".number_format($existing->amount, 0, '.', ' ')." so'm",
                $this->getMainReplyKeyboard()
            );

            return;
        }

        $draft = BotDraft::where('operation_id', $operationId)->first();
        if (! $draft) {
            $this->client->sendMessage($chatId, "❌ To'lov qoralamasi topilmadi.", $this->getMainReplyKeyboard());

            return;
        }

        $payload = $draft->payload;

        try {
            $payment = $this->customerPaymentService->execute(
                customerId: (int) $payload['customer_id'],
                amount: (int) $payload['amount'],
                cashAccountId: (int) $payload['cash_account_id'],
                operationId: $operationId,
                paymentMethod: 'CASH',
                notes: 'Telegram bot orqali qabul qilindi',
                userId: $user->id
            );

            $draft->delete();

            $msg = "✅ <b>TO'LOV MUVAFFAQIYATLI QABUL QILINDI!</b>\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "🧾 <b>Kvitansiya:</b> #{$payment->payment_number}\n";
            $msg .= '👤 <b>Mijoz:</b> '.$this->escape($payload['customer_name'])."\n";
            $msg .= '💵 <b>Qabul qilingan summa:</b> '.number_format($payment->amount, 0, '.', ' ')." so'm\n";

            $this->client->sendMessage($chatId, $msg, $this->getMainReplyKeyboard());
        } catch (\Throwable $e) {
            $this->client->sendMessage($chatId, '❌ <b>Xatolik yuz berdi:</b> '.$this->escape($e->getMessage()), $this->getMainReplyKeyboard());
        }
    }

    // =========================================================================
    // CALLBACK QUERY & TEXT INPUT DISPATCHER
    // =========================================================================

    protected function handleCallback(User $user, int|string $chatId, ?int $messageId, string $data): void
    {
        // Cancel draft
        if (str_starts_with($data, 'draft_cancel:')) {
            $opId = str_replace('draft_cancel:', '', $data);
            BotDraft::where('operation_id', $opId)->delete();
            $this->client->sendMessage($chatId, '🚫 Amal bekor qilindi.', $this->getMainReplyKeyboard());

            return;
        }

        // Navigation
        if ($data === 'nav:dashboard') {
            $this->sendDashboard($user, $chatId);

            return;
        }
        if ($data === 'nav:main') {
            $this->sendMainMenu($user, $chatId);

            return;
        }
        if ($data === 'nav:cash') {
            $this->sendCashBalances($user, $chatId);

            return;
        }
        if ($data === 'nav:reports') {
            $this->sendReportMenu($user, $chatId);

            return;
        }

        // Pagination
        if (str_starts_with($data, 'stock_page:')) {
            $p = (int) str_replace('stock_page:', '', $data);
            $this->sendStockMenu($user, $chatId, $p);

            return;
        }
        if (str_starts_with($data, 'cust_page:')) {
            $p = (int) str_replace('cust_page:', '', $data);
            $this->sendCustomerDebts($user, $chatId, $p);

            return;
        }
        if (str_starts_with($data, 'supp_page:')) {
            $p = (int) str_replace('supp_page:', '', $data);
            $this->sendSupplierPayables($user, $chatId, $p);

            return;
        }

        // Report period
        if (str_starts_with($data, 'rep_range:')) {
            $range = str_replace('rep_range:', '', $data);
            $this->showReportPeriodSummary($user, $chatId, $range);

            return;
        }

        // Flow triggers
        if ($data === 'flow:payment:customer') {
            $this->startCustomerPaymentFlow($user, $chatId);

            return;
        }

        // Sale Flow
        $draft = BotDraft::where('chat_id', $chatId)->latest()->first();

        if (str_starts_with($data, 'sale_cust:') && $draft) {
            $target = str_replace('sale_cust:', '', $data);
            if ($target === 'quick') {
                $payload = $draft->payload;
                $payload['customer_id'] = null;
                $payload['customer_name'] = 'Tezkor xaridor (Naqd)';
                $draft->update(['payload' => $payload]);
                $this->showSaleProductList($user, $chatId, $draft);
            } elseif ($target === 'new') {
                $draft->update(['step' => 'INPUT_NEW_CUSTOMER_NAME']);
                $this->client->sendMessage($chatId, '👤 Yangi mijoz ismini kiriting (masalan: <i>Dilshod Savdo</i>):');
            } else {
                $c = Customer::find($target);
                if ($c) {
                    $payload = $draft->payload;
                    $payload['customer_id'] = $c->id;
                    $payload['customer_name'] = $c->name;
                    $draft->update(['payload' => $payload]);
                    $this->showSaleProductList($user, $chatId, $draft);
                }
            }

            return;
        }

        if (str_starts_with($data, 'sale_prod:') && $draft) {
            $pId = (int) str_replace('sale_prod:', '', $data);
            $this->showSaleVolumeList($user, $chatId, $draft, $pId);

            return;
        }

        if ($data === 'sale_back_prod' && $draft) {
            $this->showSaleProductList($user, $chatId, $draft);

            return;
        }

        if (str_starts_with($data, 'sale_var:') && $draft) {
            $vId = (int) str_replace('sale_var:', '', $data);
            $this->promptQuantity($user, $chatId, $draft, $vId);

            return;
        }

        if ($data === 'sale_price_sys' && $draft) {
            $sysPrice = (int) ($draft->payload['current_system_price'] ?? 0);
            $this->addItemToSaleCart($user, $chatId, $draft, $sysPrice, true);

            return;
        }

        if ($data === 'sale_price_manual' && $draft) {
            $draft->update(['step' => 'INPUT_PRICE']);
            $this->client->sendMessage($chatId, "✏️ 1 dona uchun <b>kelishilgan narxni</b> so'mda yozing (masalan: <code>4800</code>):");

            return;
        }

        if ($data === 'sale_add_more' && $draft) {
            $this->showSaleProductList($user, $chatId, $draft);

            return;
        }

        if ($data === 'sale_to_payment' && $draft) {
            $this->promptSalePayment($user, $chatId, $draft);

            return;
        }

        if (str_starts_with($data, 'sale_deg:') && $draft) {
            $deg = str_replace('sale_deg:', '', $data);
            $payload = $draft->payload;
            $items = $payload['items'] ?? [];
            $totalSum = array_sum(array_column($items, 'line_total'));

            if ($deg === 'full') {
                $payload['paid_amount'] = $totalSum;
                $payload['payment_degree'] = 'FULL';
                $draft->update(['step' => 'SELECT_CASH_ACCOUNT', 'payload' => $payload]);
                $this->promptCashAccountSelection($chatId, $draft, 'sale');
            } elseif ($deg === 'debt') {
                $payload['paid_amount'] = 0;
                $payload['payment_degree'] = 'DEBT';
                $payload['cash_account_id'] = null;
                $draft->update(['payload' => $payload]);
                $this->showSalePreview($user, $chatId, $draft);
            } elseif ($deg === 'partial') {
                $draft->update(['step' => 'INPUT_PARTIAL_PAID']);
                $this->client->sendMessage($chatId, "💵 Hozir to'lanayotgan summani so'mda yozing (1 dan ".number_format($totalSum - 1, 0, '.', ' ').' gacha):');
            }

            return;
        }

        if (str_starts_with($data, 'sale_acc:') && $draft) {
            $accId = (int) str_replace('sale_acc:', '', $data);
            $payload = $draft->payload;
            $payload['cash_account_id'] = $accId;
            $draft->update(['payload' => $payload]);
            $this->showSalePreview($user, $chatId, $draft);

            return;
        }

        if (str_starts_with($data, 'sale_confirm:')) {
            $opId = str_replace('sale_confirm:', '', $data);
            $this->confirmSale($user, $chatId, $opId);

            return;
        }

        // Purchase Flow
        if (str_starts_with($data, 'pur_supp:') && $draft) {
            $sId = (int) str_replace('pur_supp:', '', $data);
            $supp = Supplier::find($sId);
            if ($supp) {
                $payload = $draft->payload;
                $payload['supplier_id'] = $sId;
                $payload['supplier_name'] = $supp->name;
                $draft->update(['payload' => $payload]);
                $this->showPurchaseProductList($chatId, $draft);
            }

            return;
        }

        if (str_starts_with($data, 'pur_prod:') && $draft) {
            $pId = (int) str_replace('pur_prod:', '', $data);
            $this->showPurchaseVolumeList($chatId, $draft, $pId);

            return;
        }

        if (str_starts_with($data, 'pur_var:') && $draft) {
            $vId = (int) str_replace('pur_var:', '', $data);
            $this->promptPurchaseQuantity($chatId, $draft, $vId);

            return;
        }

        if (str_starts_with($data, 'pur_acc:') && $draft) {
            $accId = (int) str_replace('pur_acc:', '', $data);
            $payload = $draft->payload;
            $payload['cash_account_id'] = $accId;
            $draft->update(['payload' => $payload]);
            $this->showPurchasePreview($chatId, $draft);

            return;
        }

        if (str_starts_with($data, 'pur_confirm:')) {
            $opId = str_replace('pur_confirm:', '', $data);
            $this->confirmPurchase($user, $chatId, $opId);

            return;
        }

        // Payment Flow
        if (str_starts_with($data, 'pay_cust:') && $draft) {
            $cId = (int) str_replace('pay_cust:', '', $data);
            $this->promptCustomerPaymentAmount($chatId, $draft, $cId);

            return;
        }

        if (str_starts_with($data, 'pay_acc:') && $draft) {
            $accId = (int) str_replace('pay_acc:', '', $data);
            $payload = $draft->payload;
            $payload['cash_account_id'] = $accId;
            $draft->update(['payload' => $payload]);
            $this->showCustomerPaymentPreview($chatId, $draft);

            return;
        }

        if (str_starts_with($data, 'pay_confirm:customer:')) {
            $opId = str_replace('pay_confirm:customer:', '', $data);
            $this->confirmCustomerPayment($user, $chatId, $opId);

            return;
        }
    }

    protected function isDraftAwaitingTextInput(BotDraft $draft): bool
    {
        return in_array($draft->step, [
            'INPUT_QTY',
            'INPUT_PRICE',
            'INPUT_PARTIAL_PAID',
            'INPUT_NEW_CUSTOMER_NAME',
            'INPUT_NEW_CUSTOMER_PHONE',
            'INPUT_PURCHASE_QTY',
            'INPUT_PURCHASE_COST',
            'INPUT_PURCHASE_PAID',
            'INPUT_PAYMENT_AMOUNT',
        ], true);
    }

    protected function handleDraftTextInput(User $user, int|string $chatId, BotDraft $draft, string $text): void
    {
        switch ($draft->step) {
            case 'INPUT_QTY':
                $qty = (int) $text;
                if ($qty <= 0) {
                    $this->client->sendMessage($chatId, '⚠️ Iltimos, 0 dan katta butun son kiriting:');

                    return;
                }
                $this->promptPriceType($user, $chatId, $draft, $qty);
                break;

            case 'INPUT_PRICE':
                $price = (int) $text;
                if ($price <= 0) {
                    $this->client->sendMessage($chatId, '⚠️ Iltimos, musbat narx kiriting:');

                    return;
                }
                $this->addItemToSaleCart($user, $chatId, $draft, $price, false);
                break;

            case 'INPUT_PARTIAL_PAID':
                $paid = (int) $text;
                $payload = $draft->payload;
                $items = $payload['items'] ?? [];
                $totalSum = array_sum(array_column($items, 'line_total'));

                if ($paid < 0 || $paid >= $totalSum) {
                    $this->client->sendMessage($chatId, "⚠️ To'lov 0 dan katta va ".number_format($totalSum, 0, '.', ' ')." so'mdan kam bo'lishi kerak:");

                    return;
                }

                $payload['paid_amount'] = $paid;
                $payload['payment_degree'] = 'PARTIAL';
                $draft->update(['step' => 'SELECT_CASH_ACCOUNT', 'payload' => $payload]);
                $this->promptCashAccountSelection($chatId, $draft, 'sale');
                break;

            case 'INPUT_NEW_CUSTOMER_NAME':
                $payload = $draft->payload;
                $payload['pending_customer_name'] = $text;
                $draft->update([
                    'step' => 'INPUT_NEW_CUSTOMER_PHONE',
                    'payload' => $payload,
                ]);
                $this->client->sendMessage($chatId, '📞 Mijoz telefon raqamini kiriting (masalan: <code>+998901234567</code>):');
                break;

            case 'INPUT_NEW_CUSTOMER_PHONE':
                $payload = $draft->payload;
                $custName = $payload['pending_customer_name'] ?? 'Yangi mijoz';
                $custPhone = $text;

                $newCustomer = Customer::firstOrCreate(
                    ['phone' => $custPhone],
                    ['name' => $custName, 'current_debt' => 0, 'debt_limit' => 0]
                );

                $payload['customer_id'] = $newCustomer->id;
                $payload['customer_name'] = $newCustomer->name;
                unset($payload['pending_customer_name']);

                $draft->update([
                    'payload' => $payload,
                ]);

                $this->client->sendMessage($chatId, '✅ Mijoz saqlandi: <b>'.$this->escape($newCustomer->name).'</b>');
                $this->showSaleProductList($user, $chatId, $draft);
                break;

            case 'INPUT_PURCHASE_QTY':
                $qty = (int) $text;
                if ($qty <= 0) {
                    $this->client->sendMessage($chatId, '⚠️ Iltimos, musbat dona soni kiriting:');

                    return;
                }
                $this->promptPurchaseUnitCost($chatId, $draft, $qty);
                break;

            case 'INPUT_PURCHASE_COST':
                $cost = (int) $text;
                if ($cost <= 0) {
                    $this->client->sendMessage($chatId, '⚠️ Iltimos, 0 dan katta tannarx kiriting:');

                    return;
                }
                $this->promptPurchasePayment($chatId, $draft, $cost);
                break;

            case 'INPUT_PURCHASE_PAID':
                $paid = (int) $text;
                $payload = $draft->payload;
                $total = (int) $payload['total_amount'];

                if ($paid < 0 || $paid > $total) {
                    $this->client->sendMessage($chatId, "⚠️ To'lov 0 dan ".number_format($total, 0, '.', ' ')." gacha bo'lishi kerak:");

                    return;
                }

                $payload['paid_amount'] = $paid;
                $draft->update([
                    'step' => 'SELECT_CASH_ACCOUNT',
                    'payload' => $payload,
                ]);

                if ($paid > 0) {
                    $this->promptCashAccountSelection($chatId, $draft, 'pur');
                } else {
                    $payload['cash_account_id'] = null;
                    $draft->update(['payload' => $payload]);
                    $this->showPurchasePreview($chatId, $draft);
                }
                break;

            case 'INPUT_PAYMENT_AMOUNT':
                $amt = (int) $text;
                if ($amt <= 0) {
                    $this->client->sendMessage($chatId, '⚠️ Iltimos, 0 dan katta summa kiriting:');

                    return;
                }
                $payload = $draft->payload;
                $payload['amount'] = $amt;
                $draft->update([
                    'step' => 'SELECT_CASH_ACCOUNT',
                    'payload' => $payload,
                ]);
                $this->promptCashAccountSelection($chatId, $draft, 'pay');
                break;
        }
    }

    /**
     * Xavfsiz HTML matnni tozalash
     */
    protected function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

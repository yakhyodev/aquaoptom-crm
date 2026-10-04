<?php

namespace Tests\Feature;

use App\Models\BotDraft;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\TelegramUpdate;
use App\Models\User;
use App\Models\Volume;
use App\Models\Warehouse;
use App\Services\Admin\UserManagementService;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramNotificationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TelegramBotIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $salesUser;

    protected Warehouse $warehouse;

    protected CashAccount $cashAccount;

    protected ProductVariant $variantHydrolife;

    protected ProductVariant $variantFanta;

    protected Customer $customer;

    protected Supplier $supplier;

    protected string $webhookSecret = 'secret_test_crm_token_2026';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.webhook_secret' => $this->webhookSecret]);
        config(['services.telegram.bot_token' => '123456:FAKE_BOT_TOKEN']);
        config(['services.telegram.pwa_url' => 'https://crm.aquaoptom.uz/pwa']);

        TelegramClient::fake();

        // 1. Rollar va foydalanuvchilar
        $this->seed(RoleAndPermissionSeeder::class);

        $this->owner = User::firstOrCreate(
            ['email' => 'owner@aquaoptom.uz'],
            [
                'name' => 'Do\'kon Egasi',
                'password' => bcrypt('password'),
                'role' => 'OWNER',
                'status' => 'ACTIVE',
                'is_active' => true,
                'telegram_chat_id' => 9990001,
                'telegram_username' => 'owner_telegram',
            ]
        );

        $this->salesUser = User::firstOrCreate(
            ['email' => 'sotuvchi@aquaoptom.uz'],
            [
                'name' => 'Sotuvchi Botir',
                'password' => bcrypt('password'),
                'role' => 'SALES_MANAGER',
                'status' => 'ACTIVE',
                'is_active' => true,
                'telegram_chat_id' => 9990002,
                'telegram_username' => 'botir_sales',
            ]
        );

        // 2. Ombor va Kassalar
        $this->warehouse = Warehouse::firstOrCreate(
            ['name' => 'Asosiy Ombor'],
            ['code' => 'WH-MAIN', 'is_default' => true, 'is_active' => true]
        );

        $this->cashAccount = CashAccount::firstOrCreate(
            ['name' => 'Asosiy Kassa (Naqd)'],
            ['type' => 'CASH', 'balance' => 2000000, 'is_default' => true]
        );

        // 3. Tovar va Variantlar
        $p1 = Product::firstOrCreate(['name' => 'Hydrolife'], ['category' => 'Suv']);
        $p2 = Product::firstOrCreate(['name' => 'Fanta'], ['category' => 'Gazli']);

        $v15 = Volume::firstOrCreate(['value_ml' => 1500], ['name' => '1.5 L']);
        $v10 = Volume::firstOrCreate(['value_ml' => 1000], ['name' => '1.0 L']);

        $this->variantHydrolife = ProductVariant::firstOrCreate(
            ['sku' => 'HYDR-15'],
            [
                'product_id' => $p1->id,
                'volume_id' => $v15->id,
                'default_sale_price' => 5000,
            ]
        );

        $this->variantFanta = ProductVariant::firstOrCreate(
            ['sku' => 'FANT-10'],
            [
                'product_id' => $p2->id,
                'volume_id' => $v10->id,
                'default_sale_price' => 8000,
            ]
        );

        // Ombor qoldig'i (100 dona Hydrolife tannarxi 3000, 50 dona Fanta tannarxi 6000)
        InventoryBalance::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_variant_id' => $this->variantHydrolife->id],
            ['quantity' => 100, 'total_value' => 300000, 'average_cost' => 3000, 'updated_at' => now()]
        );

        InventoryBalance::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_variant_id' => $this->variantFanta->id],
            ['quantity' => 50, 'total_value' => 300000, 'average_cost' => 6000, 'updated_at' => now()]
        );

        // 4. Mijoz va Ta'minotchi
        $this->customer = Customer::create([
            'name' => 'Akbar Savdo',
            'phone' => '+998901112233',
            'current_debt' => 0,
            'debt_limit' => 500000,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Hydrolife Zavod',
            'balance' => 0,
        ]);
    }

    /**
     * Test 1: Webhook Secret Header tekshiruvi.
     * Noto'g'ri yoki yo'q header 403 beradi, to'g'ri header 200 OK beradi.
     */
    public function test_webhook_secret_header_verification(): void
    {
        // 1. Missing secret header -> 403 Forbidden
        $resMissing = $this->postJson('/telegram/webhook', [
            'update_id' => 1001,
            'message' => ['chat' => ['id' => 9990001], 'text' => '/start'],
        ]);
        $resMissing->assertStatus(403);

        // 2. Invalid secret header -> 403 Forbidden
        $resInvalid = $this->postJson('/telegram/webhook', [
            'update_id' => 1002,
            'message' => ['chat' => ['id' => 9990001], 'text' => '/start'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'wrong_secret']);
        $resInvalid->assertStatus(403);

        // 3. Valid secret header -> 200 OK
        $resValid = $this->postJson('/telegram/webhook', [
            'update_id' => 1003,
            'message' => ['chat' => ['id' => 9990001], 'text' => '/start'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);
        $resValid->assertStatus(200);
        $resValid->assertJson(['status' => 'ok']);
    }

    /**
     * Test 2: Begona (ro'yxatdan o'tmagan) foydalanuvchiga ruxsat yo'q.
     * Moliyaviy va operativ ma'lumotlar sir saqlanadi.
     */
    public function test_stranger_access_is_denied(): void
    {
        TelegramClient::resetFake();
        TelegramClient::fake();

        $strangerChatId = 11223344; // Tizimda mavjud emas

        $response = $this->postJson('/telegram/webhook', [
            'update_id' => 2001,
            'message' => [
                'chat' => ['id' => $strangerChatId],
                'from' => ['id' => $strangerChatId, 'username' => 'stranger_user'],
                'text' => '/dashboard',
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        $response->assertStatus(200);

        $messages = TelegramClient::getRecordedMessages();
        $this->assertNotEmpty($messages);

        $sentText = $messages[0]['payload']['text'];
        $this->assertStringContainsString("Ruxsat yo'q", $sentText);
        $this->assertStringContainsString((string) $strangerChatId, $sentText);
        // Hech qanday moliyaviy ma'lumot chiqmasligi kerak:
        $this->assertStringNotContainsString('Savdo aylanmasi', $sentText);
        $this->assertStringNotContainsString('Kassa tushumi', $sentText);
    }

    /**
     * Test 3: Telegram ID bog'lash va bloklangan foydalanuvchi nazorati.
     */
    public function test_user_telegram_linking_and_blocked_user_rejection(): void
    {
        // 1. Yangi xodim
        $newStaff = User::create([
            'name' => 'Kassir Jamshid',
            'email' => 'jamshid@aquaoptom.uz',
            'password' => bcrypt('secret'),
            'role' => 'CASHIER',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $userService = app(UserManagementService::class);
        $userService->updateTelegramChatId($newStaff, 55667788, 'jamshid_kassir', $this->owner);

        $this->assertEquals(55667788, $newStaff->fresh()->telegram_chat_id);

        // 2. Kassir /start yuboradi -> Muvaffaqiyatli xush kelibsiz xabari
        TelegramClient::resetFake();
        TelegramClient::fake();

        $res = $this->postJson('/telegram/webhook', [
            'update_id' => 3001,
            'message' => [
                'chat' => ['id' => 55667788],
                'from' => ['id' => 55667788],
                'text' => '/start',
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);
        $res->assertStatus(200);

        $messages = TelegramClient::getRecordedMessages();
        $this->assertStringContainsString('Kassir Jamshid', $messages[0]['payload']['text']);

        // 3. Xodim bloklanganda -> Ruxsat yopiladi
        $newStaff->update(['status' => 'BLOCKED', 'is_active' => false]);

        TelegramClient::resetFake();
        TelegramClient::fake();

        $resBlocked = $this->postJson('/telegram/webhook', [
            'update_id' => 3002,
            'message' => [
                'chat' => ['id' => 55667788],
                'from' => ['id' => 55667788],
                'text' => '/dashboard',
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);
        $resBlocked->assertStatus(200);

        $blockedMessages = TelegramClient::getRecordedMessages();
        $this->assertStringContainsString('Hisobingiz bloklangan', $blockedMessages[0]['payload']['text']);
    }

    /**
     * Test 4: Dashboard, Qoldiqlar, Qarzdorlik, Kassa va PWA menyulari.
     * Rolga mos ma'lumotlar va tannarx maskirovkasi.
     */
    public function test_viewing_dashboard_stock_debts_cash_and_pwa_info(): void
    {
        TelegramClient::resetFake();
        TelegramClient::fake();

        // 1. Owner ko'radi: Dashboardda Tannarx va Yalpi foyda chiqadi
        $this->postJson('/telegram/webhook', [
            'update_id' => 4001,
            'message' => ['chat' => ['id' => $this->owner->telegram_chat_id], 'text' => '/dashboard'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        $ownerDashboard = TelegramClient::getRecordedMessages()[0]['payload']['text'];
        $this->assertStringContainsString('BUGUNGI JONLI DASHBOARD', $ownerDashboard);
        $this->assertStringContainsString('Yalpi foyda', $ownerDashboard);
        $this->assertStringContainsString('Mijozlar qarzi', $ownerDashboard);
        $this->assertStringContainsString('Mijozlar avansi', $ownerDashboard);

        // 2. Sales User ko'radi: Tannarx va foyda yashirilgan (Masked)
        TelegramClient::resetFake();
        TelegramClient::fake();

        $this->postJson('/telegram/webhook', [
            'update_id' => 4002,
            'message' => ['chat' => ['id' => $this->salesUser->telegram_chat_id], 'text' => '/dashboard'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        $salesDashboard = TelegramClient::getRecordedMessages()[0]['payload']['text'];
        $this->assertStringNotContainsString('Yalpi foyda', $salesDashboard);
        $this->assertStringNotContainsString('Tannarx (COGS)', $salesDashboard);

        // 3. Qoldiqlar ko'rish (/qoldiq)
        TelegramClient::resetFake();
        TelegramClient::fake();

        $this->postJson('/telegram/webhook', [
            'update_id' => 4003,
            'message' => ['chat' => ['id' => $this->owner->telegram_chat_id], 'text' => '/qoldiq'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        $stockMsg = TelegramClient::getRecordedMessages()[0]['payload']['text'];
        $this->assertStringContainsString("OMBORDAGI TOVARLAR QOLDIG'I", $stockMsg);
        $this->assertStringContainsString('Hydrolife', $stockMsg);

        // 4. Kassa balansi (/kassa)
        TelegramClient::resetFake();
        TelegramClient::fake();

        $this->postJson('/telegram/webhook', [
            'update_id' => 4004,
            'message' => ['chat' => ['id' => $this->owner->telegram_chat_id], 'text' => '/kassa'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        $cashMsg = TelegramClient::getRecordedMessages()[0]['payload']['text'];
        $this->assertStringContainsString('KASSALAR VA HISOB-RAQAMLAR BALANSI', $cashMsg);
        $this->assertStringContainsString('Asosiy Kassa (Naqd)', $cashMsg);

        // 5. Offline PWA ma'lumoti (/pwa)
        TelegramClient::resetFake();
        TelegramClient::fake();

        $this->postJson('/telegram/webhook', [
            'update_id' => 4005,
            'message' => ['chat' => ['id' => $this->owner->telegram_chat_id], 'text' => '/pwa'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        $pwaMsg = TelegramClient::getRecordedMessages()[0]['payload']['text'];
        $this->assertStringContainsString('offline rejimda ishlamaydi', $pwaMsg);
        $this->assertStringContainsString('https://crm.aquaoptom.uz/pwa', $pwaMsg);
    }

    /**
     * Test 5: Ko'p qatorli savdo wizard oqimi (Multi-line Sale Flow).
     * Tovar 1 + Tovar 2, qisman to'lov va nasiya, backendCreateSaleService chaqirilishi.
     */
    public function test_multi_line_sale_wizard_flow(): void
    {
        $chatId = $this->salesUser->telegram_chat_id;

        // Step 1: Savdo boshlash
        $this->postJson('/telegram/webhook', [
            'update_id' => 5001,
            'message' => ['chat' => ['id' => $chatId], 'text' => '/savdo'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        // Step 2: Xaridorni tanlash (Akbar Savdo)
        $this->postJson('/telegram/webhook', [
            'update_id' => 5002,
            'callback_query' => [
                'id' => 'cb_1',
                'message' => ['chat' => ['id' => $chatId], 'message_id' => 10],
                'data' => "sale_cust:{$this->customer->id}",
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        // Step 3: 1-mahsulotni tanlash (Hydrolife)
        $this->postJson('/telegram/webhook', [
            'update_id' => 5003,
            'callback_query' => [
                'id' => 'cb_2',
                'message' => ['chat' => ['id' => $chatId], 'message_id' => 11],
                'data' => "sale_prod:{$this->variantHydrolife->product_id}",
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        // Step 4: Hajmni tanlash (1.5 L)
        $this->postJson('/telegram/webhook', [
            'update_id' => 5004,
            'callback_query' => [
                'id' => 'cb_3',
                'message' => ['chat' => ['id' => $chatId], 'message_id' => 12],
                'data' => "sale_var:{$this->variantHydrolife->id}",
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        // Step 5: Miqdor kiritish (10 dona)
        $this->postJson('/telegram/webhook', [
            'update_id' => 5005,
            'message' => ['chat' => ['id' => $chatId], 'text' => '10'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        // Step 6: Tizim narxini tanlash (5000 so'm -> 50 000 so'm)
        $this->postJson('/telegram/webhook', [
            'update_id' => 5006,
            'callback_query' => [
                'id' => 'cb_4',
                'message' => ['chat' => ['id' => $chatId], 'message_id' => 13],
                'data' => 'sale_price_sys',
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        // Step 7: 2-tovarni qo'shish (sale_add_more)
        $this->postJson('/telegram/webhook', [
            'update_id' => 5007,
            'callback_query' => [
                'id' => 'cb_5',
                'message' => ['chat' => ['id' => $chatId], 'message_id' => 14],
                'data' => 'sale_add_more',
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        // 2-tovar: Fanta
        $this->postJson('/telegram/webhook', [
            'update_id' => 5008,
            'callback_query' => [
                'id' => 'cb_6',
                'message' => ['chat' => ['id' => $chatId], 'message_id' => 15],
                'data' => "sale_prod:{$this->variantFanta->product_id}",
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        $this->postJson('/telegram/webhook', [
            'update_id' => 5009,
            'callback_query' => [
                'id' => 'cb_7',
                'message' => ['chat' => ['id' => $chatId], 'message_id' => 16],
                'data' => "sale_var:{$this->variantFanta->id}",
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        // Fanta miqdori: 5 dona
        $this->postJson('/telegram/webhook', [
            'update_id' => 5010,
            'message' => ['chat' => ['id' => $chatId], 'text' => '5'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        // Kelishilgan narx tanlash (Fanta uchun 7000 so'm kiritiladi -> 35 000 so'm)
        $this->postJson('/telegram/webhook', [
            'update_id' => 5011,
            'callback_query' => [
                'id' => 'cb_8',
                'message' => ['chat' => ['id' => $chatId], 'message_id' => 17],
                'data' => 'sale_price_manual',
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        $this->postJson('/telegram/webhook', [
            'update_id' => 5012,
            'message' => ['chat' => ['id' => $chatId], 'text' => '7000'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        // Jami summa: 50 000 + 35 000 = 85 000 so'm. To'lovga o'tish:
        $this->postJson('/telegram/webhook', [
            'update_id' => 5013,
            'callback_query' => [
                'id' => 'cb_9',
                'message' => ['chat' => ['id' => $chatId], 'message_id' => 18],
                'data' => 'sale_to_payment',
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        // Qisman to'lov tanlash (partial)
        $this->postJson('/telegram/webhook', [
            'update_id' => 5014,
            'callback_query' => [
                'id' => 'cb_10',
                'message' => ['chat' => ['id' => $chatId], 'message_id' => 19],
                'data' => 'sale_deg:partial',
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        // Hozir to'lanadigan summa: 35 000 so'm (Qolgan 50 000 nasiya)
        $this->postJson('/telegram/webhook', [
            'update_id' => 5015,
            'message' => ['chat' => ['id' => $chatId], 'text' => '35000'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        // Kassa hisobini tanlash
        $this->postJson('/telegram/webhook', [
            'update_id' => 5016,
            'callback_query' => [
                'id' => 'cb_11',
                'message' => ['chat' => ['id' => $chatId], 'message_id' => 20],
                'data' => "sale_acc:{$this->cashAccount->id}",
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        // Preview ekrani ko'rsatiladi. Draftdan operation_id ni olamiz
        $draft = BotDraft::where('chat_id', $chatId)->first();
        $this->assertNotNull($draft);
        $opId = $draft->operation_id;

        // Tasdiqlash tugmasini bosish
        $this->postJson('/telegram/webhook', [
            'update_id' => 5017,
            'callback_query' => [
                'id' => 'cb_12',
                'message' => ['chat' => ['id' => $chatId], 'message_id' => 21],
                'data' => "sale_confirm:{$opId}",
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        // 7. Savdo bazaga to'liq va to'g'ri yozilganini tekshirish
        $sale = Sale::where('operation_id', $opId)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(85000, $sale->total_amount);
        $this->assertEquals(35000, $sale->paid_amount);
        $this->assertEquals(50000, $sale->debt_amount);
        $this->assertEquals(2, $sale->items()->count());

        // Ombor qoldig'i kamaygan
        $bHydrolife = InventoryBalance::where('product_variant_id', $this->variantHydrolife->id)->first();
        $this->assertEquals(90, $bHydrolife->quantity); // 100 - 10 = 90

        $bFanta = InventoryBalance::where('product_variant_id', $this->variantFanta->id)->first();
        $this->assertEquals(45, $bFanta->quantity); // 50 - 5 = 45

        // Xaridor qarzi 50 000 ga oshgan
        $this->assertEquals(50000, $this->customer->fresh()->current_debt);
    }

    /**
     * Test 6: 20 ta tasdiq bir operatsiya (Idempotency Invariant).
     * 20 marta tasdiq tugmasi bosilganda ham yagona bir dona savdo hujjati saqlanadi.
     */
    public function test_twenty_duplicate_confirm_callbacks_produce_strictly_one_operation(): void
    {
        $chatId = $this->salesUser->telegram_chat_id;
        $opId = (string) Str::uuid();

        // Savat qoralamasini yaratish
        BotDraft::create([
            'user_id' => $this->salesUser->id,
            'chat_id' => $chatId,
            'type' => 'SALE',
            'step' => 'CONFIRM',
            'operation_id' => $opId,
            'payload' => [
                'customer_id' => $this->customer->id,
                'customer_name' => $this->customer->name,
                'items' => [
                    ['variant_id' => $this->variantHydrolife->id, 'quantity' => 2, 'sale_price' => 5000, 'is_system_price' => true],
                ],
                'paid_amount' => 10000,
                'cash_account_id' => $this->cashAccount->id,
            ],
        ]);

        $initialStock = InventoryBalance::where('product_variant_id', $this->variantHydrolife->id)->first()->quantity;
        $initialSalesCount = Sale::count();

        // 20 marta tasdiq yuboramiz
        for ($i = 1; $i <= 20; $i++) {
            $res = $this->postJson('/telegram/webhook', [
                'update_id' => 6000 + $i,
                'callback_query' => [
                    'id' => "cb_idem_{$i}",
                    'message' => ['chat' => ['id' => $chatId], 'message_id' => 100],
                    'data' => "sale_confirm:{$opId}",
                ],
            ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

            $res->assertStatus(200);
        }

        // Qat'iy invariant: Jami savdolar soni aniq +1 bo'ladi (20 ta emas!)
        $this->assertEquals($initialSalesCount + 1, Sale::count());
        $this->assertEquals(1, Sale::where('operation_id', $opId)->count());

        // Ombor qoldig'i faqat 1 marta (2 dona) kamayadi
        $finalStock = InventoryBalance::where('product_variant_id', $this->variantHydrolife->id)->first()->quantity;
        $this->assertEquals($initialStock - 2, $finalStock);
    }

    /**
     * Test 7: Tovarlar kirimi wizard oqimi (Purchase Flow).
     */
    public function test_purchase_wizard_flow(): void
    {
        $chatId = $this->owner->telegram_chat_id;
        $opId = (string) Str::uuid();

        BotDraft::create([
            'user_id' => $this->owner->id,
            'chat_id' => $chatId,
            'type' => 'PURCHASE',
            'step' => 'CONFIRM',
            'operation_id' => $opId,
            'payload' => [
                'supplier_id' => $this->supplier->id,
                'supplier_name' => $this->supplier->name,
                'variant_id' => $this->variantHydrolife->id,
                'variant_title' => 'Hydrolife 1.5L',
                'quantity' => 50,
                'unit_cost' => 3200,
                'total_amount' => 160000,
                'paid_amount' => 100000,
                'cash_account_id' => $this->cashAccount->id,
            ],
        ]);

        $initialStock = InventoryBalance::where('product_variant_id', $this->variantHydrolife->id)->first()->quantity;

        // Tasdiqlash
        $this->postJson('/telegram/webhook', [
            'update_id' => 7001,
            'callback_query' => [
                'id' => 'cb_pur_1',
                'message' => ['chat' => ['id' => $chatId], 'message_id' => 200],
                'data' => "pur_confirm:{$opId}",
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        $purchase = Purchase::where('operation_id', $opId)->first();
        $this->assertNotNull($purchase);
        $this->assertEquals(160000, $purchase->total_amount);
        $this->assertEquals(100000, $purchase->paid_amount);
        $this->assertEquals(60000, $purchase->debt_amount);

        // Ombor qoldig'i 50 donaga oshgan
        $newStock = InventoryBalance::where('product_variant_id', $this->variantHydrolife->id)->first()->quantity;
        $this->assertEquals($initialStock + 50, $newStock);

        // Ta'minotchi balansi 60 000 ga oshgan
        $this->assertEquals(60000, $this->supplier->fresh()->balance);
    }

    /**
     * Test 8: Mijozdan qarz to'lovi qabul qilish wizard oqimi (Customer Payment Flow).
     */
    public function test_customer_payment_wizard_flow(): void
    {
        // Avval mijozga 100 000 so'm qarz o'rnatamiz
        $this->customer->update(['current_debt' => 100000]);

        $chatId = $this->salesUser->telegram_chat_id;
        $opId = (string) Str::uuid();

        BotDraft::create([
            'user_id' => $this->salesUser->id,
            'chat_id' => $chatId,
            'type' => 'CUSTOMER_PAYMENT',
            'step' => 'CONFIRM',
            'operation_id' => $opId,
            'payload' => [
                'customer_id' => $this->customer->id,
                'customer_name' => $this->customer->name,
                'amount' => 40000,
                'cash_account_id' => $this->cashAccount->id,
            ],
        ]);

        $initialCash = $this->cashAccount->fresh()->balance;

        // Tasdiqlash
        $this->postJson('/telegram/webhook', [
            'update_id' => 8001,
            'callback_query' => [
                'id' => 'cb_pay_1',
                'message' => ['chat' => ['id' => $chatId], 'message_id' => 300],
                'data' => "pay_confirm:customer:{$opId}",
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret]);

        $payment = Payment::where('operation_id', $opId)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(40000, $payment->amount);

        // Mijoz qarzi 40 000 ga kamaygan (100 000 - 40 000 = 60 000)
        $this->assertEquals(60000, $this->customer->fresh()->current_debt);

        // Kassa 40 000 ga oshgan
        $this->assertEquals($initialCash + 40000, $this->cashAccount->fresh()->balance);
    }

    /**
     * Test 9: Update_id deduplikatsiyasi.
     * Bir xil update_id li takroriy request kelganda allaqachon qayta ishlangan status qaytaradi.
     */
    public function test_telegram_update_id_deduplication(): void
    {
        $updatePayload = [
            'update_id' => 99999,
            'message' => [
                'chat' => ['id' => $this->owner->telegram_chat_id],
                'text' => '/dashboard',
            ],
        ];

        // 1-chi urinish: status ok
        $res1 = $this->postJson('/telegram/webhook', $updatePayload, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret,
        ]);
        $res1->assertStatus(200);
        $res1->assertJson(['status' => 'ok']);

        $this->assertTrue(TelegramUpdate::where('update_id', 99999)->exists());

        // 2-chi urinish (takror): allaqachon qayta ishlangan
        $res2 = $this->postJson('/telegram/webhook', $updatePayload, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->webhookSecret,
        ]);
        $res2->assertStatus(200);
        $res2->assertJson(['status' => 'already_processed']);
    }

    /**
     * Test 10: Outbox bildirishnomalarini Telegramga tarqatish, HTML escaping va retry.
     */
    public function test_outbox_telegram_notifications_with_html_escaping_and_retry(): void
    {
        TelegramClient::resetFake();
        TelegramClient::fake();

        $outbox = OutboxEvent::create([
            'event_id' => (string) Str::uuid(),
            'operation_id' => (string) Str::uuid(),
            'event_name' => 'SaleCreated',
            'aggregate_type' => 'Sale',
            'aggregate_id' => '501',
            'payload' => [
                'invoice_number' => 'INV-<2026>', // Xavfli HTML belgisi
                'customer_name' => 'Ali & Vali Savdo <OOO>',
                'total_amount' => 150000,
                'paid_amount' => 100000,
                'debt_amount' => 50000,
            ],
            'status' => 'PENDING',
        ]);

        $notificationService = app(TelegramNotificationService::class);
        $deliveries = $notificationService->notifyOutboxEvent($outbox);

        $this->assertNotEmpty($deliveries);
        $delivery = $deliveries[0];

        $this->assertEquals('SENT', $delivery->status);
        $this->assertEquals('SaleCreated', $delivery->notification_type);

        // HTML escaping tekshiruvi: < va > belgilari xavfsiz aylantirilgan
        $this->assertStringContainsString('&lt;2026&gt;', $delivery->message_text);
        $this->assertStringContainsString('Ali &amp; Vali Savdo &lt;OOO&gt;', $delivery->message_text);

        // Retry testi: failed yetkazish qayta urinilganda muvaffaqiyatli yopiladi
        $delivery->update(['status' => 'FAILED', 'retry_count' => 0]);
        $retried = $notificationService->retryFailedDeliveries();

        $this->assertEquals(1, $retried);
        $this->assertEquals('SENT', $delivery->fresh()->status);
    }
}

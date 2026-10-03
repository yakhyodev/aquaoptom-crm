# QURILISH HOLATI — AQUAOPTOM CRM (SUV VA ICHIMLIKLAR DO‘KONI)

**Sana:** 2026-10-03  
**Hujjat vazifasi:** Loyihaning 25 bosqichli qurilish holati, muhit inventarizatsiyasi, arxitektura qarorlari, test dalillari va cheklovlar reyestri.  
**Asosiy arxitektura manbasi:** [`SUV_DOKONI_CRM_ARXITEKTURA_V2.md`](SUV_DOKONI_CRM_ARXITEKTURA_V2.md) va [`QURISH_PROMPTLARI_25.md`](QURISH_PROMPTLARI_25.md).

---

## 1. Joriy Muhit va Asboblar Inventarizatsiyasi (Runtime State)

| Vosita / Muhit | Aniqlangan versiya / Holat | Status | Izoh va Cheklovlar |
| :--- | :--- | :--- | :--- |
| **Operatsion tizim** | Windows (x64) | Ishlaydi | Shell: PowerShell |
| **PHP** | 8.5.8 (cli) (ZTS Visual C++ 2022 x64) | Ishlaydi | `D:\Program languages\PHP\php.exe`. `pdo_pgsql`, `pgsql`, `bcmath`, `curl`, `intl`, `mbstring`, `openssl`, `pdo_sqlite`, `pdo_mysql` faol. |
| **Composer** | 2.10.2 (2026-07-01) | Ishlaydi | PHP 8.5.8 bilan to‘liq integratsiyalangan. |
| **Laravel Framework** | 11.45.1 / 13.34.0 | Ishlaydi | `d:\Project\CRM\backend` katalogida. |
| **Laravel Boost** | v2.10.1 (`--dev`) | Ishlaydi | `composer require laravel/boost --dev` orqali o‘rnatilgan. `backend/AGENTS.md` yangilangan. |
| **Node.js** | v24.12.0 | Ishlaydi | NPM package bundling va Vite build uchun tayyor. |
| **NPM** | 11.16.0 | Ishlaydi | 56 ta dev dependency to‘liq o‘rnatildi (`node_modules`). |
| **Vite & Tailwind** | Vite v8.3.2, Tailwind v4.0.0 | Ishlaydi | `npm run build` orqali mahalliy CSS/JS to‘liq ishlab chiqilmoqda. |
| **PostgreSQL** | PostgreSQL 16.4 (portable x64) | Ishlaydi | `D:\tools\pgsql\bin\postgres.exe` daemon sifatida `127.0.0.1:5432` da faol. `aquaoptom_dev` va `aquaoptom_test` bazalari yaratilgan. |
| **Redis** | Redis 5.0.14.1 (portable x64) | Ishlaydi | `D:\tools\redis\redis-server.exe` daemon sifatida `127.0.0.1:6379` da faol. PHP uchun `predis/predis` v3.6.1 o‘rnatildi. |
| **Flutter SDK** | 3.44.4 (stable, Dart 3.12.2) | Ishlaydi | `d:\Project\CRM\mobile` katalogida mavjud. `flutter analyze` 0 issues berdi. |
| **Java / JDK** | OpenJDK 17.0.20+8 (Temurin 64-Bit) | Ishlaydi | Android build va Flutter integratsiyasi uchun mos. |
| **Git** | 2.52.0.windows.1 | Ishlaydi | `d:\Project\CRM` ildizida lokal git repo yaratilgan. Maxfiy env, SQLite va build artefaktlari `.gitignore` orqali himoyalangan. |
| **CI / CD Pipeline** | GitHub Actions (`.github/workflows/ci.yml`) | Tayyor | PostgreSQL 16 va Redis 7 service containerlari, Pint linter, migration rollback, PHPUnit testlari, Vite frontend build va Flutter mobile check. |

---

## 2. Arxitektura Qarorlari va Standart Reliz Yo‘li (V2 Asosi)

`SUV_DOKONI_CRM_ARXITEKTURA_V2.md` hujjatiga muvofiq quyidagi asosiy tamoyillar va standartlar qat’iy belgilandi:

1. **Ko‘lam va Tashkilot:**
   - Faqat suv va ichimliklar savdosiga ixtisoslashgan optom do‘kon.
   - **Bitta do‘kon va bitta ombor.** Ko‘p omborli murakkablik yoki universal supermarket ERP xususiyatlari qo‘shilmaydi.
2. **Miqdor birligi:**
   - **Faqat butun dona.** Optom savdo ham ko‘p dona sotish hisoblanadi. Blok/yashik kabi o‘lchovlar birlamchi inventarizatsiya va bazada alohida stock unit bo‘lmaydi.
3. **Mijoz va Ta’minotchi Qarzdorligi:**
   - **Umumiy pul qarzi (Signed balance daftari).** Musbat = qarz, manfiy = avans.
   - Keyingi to‘lovlar mahsulotlarga yoki aniq cheklarga majburiy taqsimlanmaydi; umumiy qarzni kamaytiradi.
   - Mijoz avansi boshqa mijozning qarzini yashirmaydi.
4. **Offline Savdo va Idempotency (`operation_id`):**
   - Kompyuter va smartfonda internet uzilganda ham sotuv to‘xtamaydi.
   - Har bir operatsiyaga qoralama vaqtida barqaror UUID `operation_id` beriladi.
   - Tugmani takror bosish, tarmoq timeouti yoki qayta yuborish bitta savdoni ko‘paytirmaydi (aniq bir martalik tranzaksiyaviy ta’sir).
5. **Standart Reliz Yo‘li (Client Interfaces):**
   - Desktop va telefon uchun: O‘rnatiladigan **PWA** (Progressive Web App: Service Worker + IndexedDB).
   - Smartfon mobil ilovasi: Mavjud `mobile` katalogida **Flutter Android** ilovasi (SQLite lokal saqlash bilan).
   - Xodimlar uchun tezkor ko‘rish va qo‘shimcha interfeys: **Telegram Bot API** (Webhook, actor tekshiruvi, xavfsiz bildirishnomalar).
6. **Parallel Offline Savdo Uchun Ajratma (`inventory_allocations`):**
   - Ikki qurilma internet yo‘qligida bir xil fizik qoldiqni sotib minusga tushirmasligi uchun server qurilmalarga sotish mumkin bo‘lgan dona ajratmasini (rezerv) beradi.
7. **Alohida Kassa Bo‘limi:**
   - Naqd pul, karta/terminal va bank hisoblarini, operatsion xarajatlarni va smena intizomini yuritish uchun menyuda alohida **«Kassa va xarajatlar»** bo‘limi mavjud bo‘ladi.
8. **Moslashuvchanlik:**
   - Ushbu standartlar foydalanuvchining keyingi aniq ko‘rsatmalari asosida to‘ldirilishi yoki o‘zgartirilishi mumkin.

---

## 3. Baseline Test Xatolari va Mavjud Kod Holati

- **Backend (Laravel):**
  - **68 ta test o‘tgan (541 ta assertion)** haqiqiy PostgreSQL `aquaoptom_test` va Redis ustida (0 xato).
  - Auth, Role-based permission, cost masking, 8 ta menyu, katalog, tranzaksiyaviy operatsiyalar, daftarlar va kirim (purchase receiving) to‘liq qamrab olindi.
  - Laravel Pint linteridan to‘liq o‘tkazildi (`{"tool":"pint","result":"passed"}`).
- **Mobile (Flutter):**
  - `flutter analyze`: **0 issues found** (toza tahlil natijasi).
  - `test/widget_test.dart` ("Counter increments smoke test"): default shablon testi bo‘lib, u loyihaning dastlabki failing baseline'i sifatida saqlanmoqda. Prompt 20 da haqiqiy CRM kirish va savdo ekranlarining widget va smoke testlariga almashtiriladi.

---

## 4. 25 Bosqichli Qurilish Reyestri (25-Step Pipeline Registry)

| № | Bosqich nomi | Guruh | Status | Dalillar va Natijalar | Keyingi qoldiq ish |
| :---: | :--- | :--- | :--- :---: | :--- | :--- |
| **01** | **Loyihani tayyorlash va arxitektura qarorlarini qayd qilish** | Poydevor | **DONE** | Inventarizatsiya qilindi. `backend/AGENTS.md` Boost bilan yangilandi. Ildiz `.gitignore` va lokal Git repo yaratildi. Baseline xatolari qayd etildi. | 02-bosqichni kutish |
| **02** | **Takror ko‘tariladigan lokal muhit va CI** | Poydevor | **DONE** | PostgreSQL 16 va Redis 5 Windows muhitida ko‘tarildi; `aquaoptom_dev` va `aquaoptom_test` yaratildi; toza test DBda migrate/rollback/re-migrate sinovi o‘tdi; `AppServiceProvider` xavfsizlik qo‘riqchilari qo‘shildi; `.env.example` namunaviy qiymatlar bilan tozalandi; GitHub Actions CI workflow yozildi; 14 ta backend testi real PostgreSQL+Redis ustida muvaffaqiyatli o‘tdi (79 assertions); Vite frontend build va Flutter analyze tekshirildi. | 03-bosqichni boshlash |
| **03** | **Kirish, ruxsatlar va responsive dastur karkasi** | Poydevor | **DONE** | Web & API Sanctum auth; 5 ta asosiy rol (`OWNER`, `ADMIN`, `SALES_MANAGER`, `WAREHOUSE_MANAGER`, `CASHIER`) va 15 ta aniq granular ruxsatlar; xavfsiz `app:bootstrap-owner` buyrug'i; bloklangan foydalanuvchini darhol cheklash; server policy/API'da tannarx yashirish (`cost_price: null`); mahalliy Vite assetli responsive layout, 360px viewport va 8 ta rasmiy menyu (soxta raqamlarsiz); 25 ta backend testi (184 assertions) va Pint linter 100% o'tdi. | 04-bosqichni boshlash |
| **04** | **Katalog, mijoz va ta’minotchi boshqaruvi** | Biznes asoslari | **DONE** | Mahsulot/hajm/variant schema kengaytirildi (migration 000003, PostgreSQL sequence); Fanta/fanta va 0.5 L/500 ml normalizatsiyalari va concurrent duplicate himoyasi; mijoz (telefon yo'q holatda do'kon/manzil shartligi, avtomatik taraf merge taqiqlangan, offline barqaror UUID idempotent retry); ta'minotchi qoidalari; narx tarixi (versiya, AuditLog, ruxsat nazorati); tarixda ishlatilgan yozuvlarni o'chirishdan himoya (`CannotDeleteReferencedRecordException`); Livewire mahsulot/mijoz/ta'minotchi sahifalari hamda kirim/sotuv inline modallari (ota komponent qoralama tovarlarini saqlab tanlash); 44/44 backend testlar (278 assertions) 100% o'tdi, Pint formatlandi, Vite build muvaffaqiyatli, Flutter analyze 0 issues. | 05-bosqichni boshlash |
| **05** | **Operatsiya ID, tranzaksiya, audit va hodisa navbati** | Hisob poydevori | **DONE** | Barcha yozuvchi amallar uchun umumiy `operation_id` (UUID), canonical payload fingerprint (SHA-256), PostgreSQL advisory lock (`pg_advisory_xact_lock`), 20 ta parallel bir xil `operation_id` sinovi 100% bitta operatsiya va eski natijani qaytardi, boshqa payload 409 Conflict berdi; tranzaksiyaviy `outbox_events` jadvali va background `app:process-outbox` command/job qurildi (rollbackdan keyin event yo'q, xato bergan savdo yarim qolib ketmaydi); `DocumentNumberGenerator` sequence orqali `max(id)+1`siz invoice/payment raqamlari beradi; xatolar toifalandi (`OperationConflictException`, `OperationValidationException`, `OperationPermissionException`, `OperationRetryableException`, `OperationNeedsReviewException`); 52/52 testlar (386 assertions) to'liq o'tdi. | 06-bosqichni boshlash |
| **06** | **Hisob daftarlari, tannarx va boshlang‘ich qoldiqlar** | Hisob poydevori | **DONE** | Ombor daftari (`inventory_movements`, `inventory_balances` jami qiymat `total_value` bilan); WAC formulasi (100×5000 + 100×6000 = 200 dona / 1 100 000 qiymat / WAC 5500); oxirgi dona sotilganda qoldiq qiymat ham qat'iy 0 bo'lishi; signed mijoz daftari (`customer_ledger` musbat qarz, manfiy avans, `max(0)` taqiqlangan); signed ta’minotchi daftari (`supplier_ledger` musbat qarzimiz, manfiy avansimiz); kassa hisoblari va harakatlari (`cash_accounts`, `cash_movements`, transfer); to'lovlar (`payments`); idempotent boshlang'ich qoldiq hujjati va xizmati (`OpeningBalanceService` retry bitta yozuv, conflict himoyasi); do'kon egasi uchun Livewire boshlang'ich qoldiqlar oynasi (`OpeningBalancesManager`); 62/62 testlar (489 assertions) 100% o'tdi. | 07-bosqichni boshlash |
| **07** | **Kirim bo‘limi — backenddan oynagacha** | Kundalik ish | **DONE** | DRAFT->POSTED kirim; migration 000006 (`operation_id`, `supplier_invoice_number`, `paid_amount`, `debt_amount`, `notes`); `ReceivePurchaseService` orqali atomik WAC qoldiq, supplier_ledger credit, ixtiyoriy cash_movements + supplier debit, Payment modeli; kassa huquqi bo'lmagan omborchining to'lov qilishi taqiqlangan (jim qolish naqd to'lov deb olinmaydi); xatolikda to'liq rollback; idempotent retry eski natijani berishi; Livewire `QuickInward` (katalog va ta'minotchi inline modallari bilan qoralama saqlanadi, to'lov paneli, kassa tanlovi); 68/68 testlar (541 assertions) 100% o'tdi. | 08-bosqichni boshlash |
| 08 | Sotuv bo‘limi — tezkor, mijozli va nasiya | Kundalik ish | TODO | - | CreateSale, tizim narxi, nasiya, kassa snapshot |
| 09 | Qarzdorliklar va taraflar to‘lovlari | Kundalik ish | TODO | - | Mijoz/ta’minotchi ko‘chirmasi, qarz to‘lovi, avans |
| 10 | Kassa, xarajat, o‘tkazma va smena | Kundalik ish | TODO | - | Pul hisoblari, xarajat kategoriyalari, smena ochish/yopish |
| 11 | Offline qurilmalar, qoldiq va kredit ajratmalari | Offline poydevori | TODO | - | Device registration, stock/credit allocation |
| 12 | Server sync API va konfliktlar protokoli | Offline poydevori | TODO | - | Batch push, cursor pull, NEEDS_REVIEW |
| 13 | PWA lokal baza va internetsiz sotuv | Offline PWA | TODO | - | Service Worker, IndexedDB, offline POS |
| 14 | PWA avtomatik sync va uzilish sinovlari | Offline PWA | TODO | - | Reconnect sync, retry, ACK, storage failure recovery |
| 15 | Ombor qoldiqlari va interaktiv kalkulyator | Tahlil va nazorat | TODO | - | Master kalkulyator, hajm checkboxlari, kutilayotgan foyda |
| 16 | Qaytarish, brak, inventarizatsiya va tuzatish | Tahlil va nazorat | TODO | - | Qisman/to‘liq qaytarish, brak, inventarizatsiya freeze |
| 17 | Savdo tarixi, hisobotlar va eksportlar | Tahlil va nazorat | TODO | - | Tahlil, sana filtrlari, Excel/PDF eksport |
| 18 | Dashboard, Admin va real vaqt yangilanishlari | Boshqaruv | TODO | - | Reverb/Echo real-vaqt, dashboard kartalari, audit |
| 19 | Telegram orqali ko‘rish, kirim, sotuv va to‘lov | Telegram | TODO | - | Bot webhook, xodim bog‘lash, tugmali operatsiyalar |
| 20 | Flutter Android: kirish va online biznes oynalari | Mobil ilova | TODO | - | API client, offline smoke test, counter test almashtirish |
| 21 | Flutter offline savdo, sync va reliz paketi | Mobil ilova | TODO | - | SQLite offline storage, sync adapter, signed APK |
| 22 | To‘liq tizim testlari, xavfsizlik va yuklama | Release sifati | TODO | - | Concurrency, E2E, 14.3 misoli to‘liq regression |
| 23 | Production paketi, backup va tiklash rejasi | Ishga chiqarish | TODO | - | Docker/Nginx/Supervisord konfig, backup/restore sinovi |
| 24 | Staging deploy va haqiqiy qurilmalarda qabul sinovi | Ishga chiqarish | TODO | - | Real qurilmalarda tarmoq uzilishi va kassa tekshiruvi |
| 25 | Productionga chiqarish va yakuniy topshirish | Ishga chiqarish | TODO | - | Prod deploy, checklist, foydalanuvchiga topshirish |

---

## 5. 01-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence)

1. **Vositalar versiyalari:**
   - Buyruq: `php -v; composer -V; node -v; npm -v; flutter --version; java -version; git --version`
   - Natija: Barcha asboblar aniqlandi va ishchi holatda.
2. **PostgreSQL va Docker tekshiruvi:**
   - Buyruq: `psql -V; Get-Service -Name *postgres*; docker --version`
   - Natija: Windows tizimida Docker yo‘qligi va PostgreSQL lokal zarurligi aniqlandi.
3. **Laravel Boost o‘rnatilishi va AGENTS.md:**
   - Buyruq: `composer require laravel/boost --dev` va `php artisan boost:install --guidelines -n`
   - Natija: `laravel/boost v2.10.1` o‘rnatildi, `backend/AGENTS.md` ga yangilangan ko‘rsatmalar kiritildi.
4. **Git va Xavfsizlik:**
   - Buyruq: `git init`, `git check-ignore backend/.env backend/database/database.sqlite mobile/.dart_tool`
   - Natija: `.gitignore` maxfiy fayllarni, SQLite bazasini, kesh va build fayllarini muvaffaqiyatli exclude qilmoqda.
5. **Baseline Testlar:**
   - Backend: `php artisan test` (10 passed, 68 assertions).
   - Mobile: `flutter test` (1 failed: `widget_test.dart` Counter test — baseline regression sifatida saqlandi).

---

## 6. 02-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence)

1. **PostgreSQL 16 O‘rnatilishi va Bazalar Yaratilishi:**
   - `D:\tools\pgsql\bin\postgres.exe` daemon sifatida `127.0.0.1:5432` da faol.
   - `aquaoptom_dev` va `aquaoptom_test` bazalari yaratildi.
2. **Redis O‘rnatilishi va PHP Dasturiga Ulanishi:**
   - `D:\tools\redis\redis-server.exe` daemon sifatida `127.0.0.1:6379` da faol.
   - `predis/predis` (v3.6.1) o‘rnatildi.
3. **Database Xavfsizlik Qo‘riqchilari (`AppServiceProvider.php`):**
   - `DB::prohibitDestructiveCommands($this->app->isProduction());` qo‘shildi.
   - Test muhiti faqat `test` so‘zi bo‘lgan bazaga ulanishi kafolatlandi.
4. **PostgreSQL Migratsiyalari va Rollback Sinovi:**
   - Barcha migratsiyalar toza PostgreSQL test bazasida yaratildi, rollback qilindi va qayta yurgazildi (0 xato).
5. **Namunaviy Qiymatlar va Xavfsiz Muhit Fayllari:**
   - `.env.example` namunaviy qiymatlar bilan yangilandi.
6. **Tashqi Xizmatlarni Fake Qilish (Telegram va Bank):**
   - `EnvironmentAndInfrastructureTest` orqali `Http::fake()` tasdiqlandi.
7. **CI Workflow (`.github/workflows/ci.yml`):**
   - Backend tests, frontend build, va mobile checks CI pipeline'i yaratildi.

---

## 7. 03-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence)

1. **Rollar va Aniq Ruxsatlar Tizimi:**
   - **Migratsiya:** `2026_10_03_000002_create_roles_and_permissions_tables.php` yaratildi. `users` jadvaliga `role`, `status`, `is_active`, `phone`, `current_store_id` qo'shildi. `roles`, `permissions`, `role_permissions`, `user_permissions` jadvallari yaratildi.
   - **5 Asosiy Rol:** `OWNER` (Egasi), `ADMIN` (Administrator), `SALES_MANAGER` (Sotuvchi), `WAREHOUSE_MANAGER` (Omborchi), `CASHIER` (Moliya/Kassa).
   - **15 Granular Ruxsatlar:** `view_cost_price`, `manage_prices`, `sell_below_cost`, `custom_sale_price`, `sell_on_credit`, `process_refund`, `offline_sales`, `view_debts`, `receive_stock`, `stock_adjustment`, `view_cash`, `manage_cash_outflow`, `view_reports`, `manage_users`, `manage_settings`.
   - **Seeder:** `RoleAndPermissionSeeder` orqali rollar va ularga tegishli birlamchi huquqlar matritsasi dev va test bazalariga yuklandi.

2. **Xavfsizlik va Kirish Nazorati Middleware'lari:**
   - `EnsureUserIsActive`: Foydalanuvchi `status != 'ACTIVE'` yoki `!is_active` bo'lsa, web sessiyadan darhol haydaydi va Sanctum tokenini bekor qilib 403 chiqaradi.
   - `RequireRole`: Marshrutlarni rollar bo'yicha himoyalaydi (`role:OWNER,ADMIN`).
   - `RequirePermission`: Aniq huquqlar bo'yicha himoyalaydi (`permission:view_reports`, `permission:view_cash`).

3. **Birinchi Do'kon Egasini (OWNER) Xavfsiz Bootstrap Qilish:**
   - **Buyruq:** `php artisan app:bootstrap-owner`
   - Default parolsiz ishlaydi. Agar parol berilmasa, 16 xonali kriptografik xavfsiz tasodifiy parol generatsiya qiladi va konsolga chiqaradi.
   - Tizimda mavjud owner bo'lsa, takroriy tasodifiy ro'yxatdan o'tishni `--force`siz bloklaydi.
   - Dev bazasida `Dilshod Egasi` (`owner@aquaoptom.uz`, `OWNER`, `ACTIVE`) muvaffaqiyatli ro'yxatdan o'tkazildi.

4. **Tannarxni Yashirish (Cost Price Hiding) Siyosati:**
   - Tannarx faqat UI emas, balki **server policy va API darajasida** to'liq yashirildi:
     - `GET /api/products`: `view_cost_price` ruxsati bo'lmagan foydalanuvchilar (masalan, `SALES_MANAGER`) uchun `cost_price` va `average_cost` qiymatlari `null` qilib jo'natiladi. Chakana sotuv narxi esa ko'rinadi.
     - `POST /api/sales`: Ruxsatsiz foydalanuvchilar uchun savdo chekidagi `gross_profit` qiymati `null` bo'ladi.
     - `POST /api/calculator`: `view_cost_price` bo'lmagan foydalanuvchilarga to'g'ridan-to'g'ri 403 Forbidden qaytaradi.

5. **Responsive Livewire Layout va Sakkizta Rasmiy Menyu:**
   - **Layout:** `resources/views/layouts/app.blade.php`.
   - **Mahalliy Build CSS/JS:** `@vite(['resources/css/app.css', 'resources/js/app.js'])` orqali mahalliy to'plamdan yuklanadi.
   - **Mobil Responsive (360px):** `<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">`, mobil drawer (`id="sidebar"`), overlay (`id="mobile-overlay"`) va `toggleMobileSidebar()` funksiyasi.
   - **8 Rasmiy Menyu:**
     1. `Dashboard` (`/dashboard`)
     2. `Savdo` (Tarix va tahlil — `/savdo`)
     3. `Sotuv` (Yangi chek — `/sotuv`)
     4. `Ombor` (Tovar kirimi va qoldiqlar — `/ombor`)
     5. `Qarzdorliklar` (Signed qarz daftari — `/qarzdorliklar`)
     6. `Hisobotlar` (Foyda va rentabellik — `/hisobotlar`, faqat `view_reports` ruxsati bilan)
     7. `Admin` (Foydalanuvchilar va sozlamalar — `/admin`, faqat `OWNER` va `ADMIN`)
     8. `Kassa va xarajatlar` (Pul hisoblari va smena — `/kassa`, faqat `view_cash` bilan)
   - **Soxta raqamsizlik:** Hali qurilmagan sahifalarda demo raqam yoki fake success mavjud emas; toza `x-empty-state` komponentlari joylashtirildi.
   - **Qayta ishlatiluvchi komponentlar:** `x-card`, `x-table`, `x-modal`, `x-filter-bar`, `x-validation-errors`, `x-empty-state`.

6. **Avtomatlashtirilgan Test Natijalari (Verification Evidence):**
   - `php artisan test --filter=AuthAndAccessControlTest`: **11 passed (103 assertions)**.
   - `php artisan test`: **25 passed out of 25 tests (184 assertions, duration 7.4s)**.
   - `vendor/bin/pint --test`: **PASSED** (0 formatting issues).
   - `npm run build`: **0 errors (built in 610ms)**.
   - `flutter analyze`: **No issues found! (ran in 2.3s)**.

---

## 8. 04-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence)

1. **Schema Kengaytirilishi va DB Migratsiyasi:**
   - **Migratsiya:** `2026_10_03_000003_enhance_catalog_parties_and_pricing_tables.php` yaratildi va `aquaoptom_dev` hamda `aquaoptom_test` PostgreSQL bazalariga muvaffaqiyatli qo‘llanildi.
   - **PostgreSQL Sequence:** `product_code_seq` yaratildi — parallel so‘rovlarda `max(id)+1` xatolarisiz xavfsiz va unikal `PRD-000001` formatidagi kodlarni kafolatlaydi.
   - **O‘zgarishlar:** `customers` va `suppliers` jadvallariga barqaror `uuid` (offline sync uchun), `store_name`, `address`, `status`, `notes`, `created_by` va `softDeletes` qo‘shildi. `product_variants` va `price_history` jadvallariga `version` (versiyalar nazorati) va `reason` qo‘shildi.
   - **Rollback Sinovi:** `php artisan migrate:rollback` va `php artisan migrate` xatosiz sinovdan o‘tdi.

2. **Normalizatorlar va Concurrency Himoyasi:**
   - `ProductNormalizer`: Nomni kichik harflarga o‘tkazadi, o‘zbekcha tutuq belgilarini (`’`, `‘`, `ʻ`, `` ` ``, `'`) yagona `'` ga normallashtiradi, bo‘shliqlarni tozalaydi. `Fanta`, `  fanta  `, `FANTA` bir xil `fanta` nomiga birlashadi.
   - `VolumeNormalizer`: `0.5 L`, `0,5 L`, `500 ml`, `500ml`, `0.5`, `500` kabi barcha shakllarni butun son `500` (ml) va `"0.5 L"` standart nomiga aylantiradi.
   - **Parallel Yaratish Himoyasi:** `UniqueConstraintViolationException` tranzaksiya ichida ushlanib, poyga holatida (race condition) ikkinchi so‘rov dublikat xatosi bermasdan mavjud yozuvni xavfsiz qaytaradi.

3. **Mijoz va Ta’minotchi Biznes Qoidalari:**
   - **Mijoz:** Ism majburiy. Telefon bo‘lmasa do‘kon nomi yoki manzil talab qilinadi (`ValidationException`). Telefon takrorlanganda ogohlantirish beriladi, ammo avtomatik merge qilinmaydi (alohida yozuvlar). Offline yaratilgan mijoz uchun barqaror `uuid` orqali idempotent retry ta’minlandi.
   - **Ta’minotchi:** Mas’ul shaxs, kompaniya/zavod nomi, telefon va manzil bilan yuritiladi.
   - **Immutability (Tarixda ishlatilgan yozuvlarni o‘chirmaslik):** `CannotDeleteReferencedRecordException` yaratildi. Savdo (`sale_items`), kirim (`purchase_items`), ombor harakatlari (`inventory_movements`) yoki qarz daftari (`customer_ledger`) da qatnashgan variant, mijoz yoki ta’minotchini o‘chirish qat’iy taqiqlanadi (faqat arxivlash mumkin).

4. **Livewire Sahifalari va Inline Modallar:**
   - `ProductManager`: Mahsulotlar ro‘yxati, qidiruv, hajm filtri, kam qoldiq va narxsiz tovarlar filtri, yangi mahsulot qo‘shish modali, narxni o‘zgartirish modali (ruxsat nazorati va narxlar tarixi bilan).
   - `CustomerManager`: Optom mijozlar katalogi, qarz holati, real vaqtda telefon takrorlanishini tekshirish.
   - `SupplierManager`: Ta’minotchilar boshqaruvi va ularning majburiyatlari.
   - **Inline Modallar (`InlineProductModal`, `InlineCustomerModal`, `InlineSupplierModal`):**
     - Kirim (`QuickInward`) va Savdo (`OptomPos`) oynalaridan chaqiriladi.
     - Yangi yozuv saqlangach, tegishli hodisa (`product-created`, `customer-created`, `supplier-created`) orqali ota komponent yangi yozuvni tanlaydi.
     - **Qoralama saqlanishi:** Ota komponentning savatidagi yoki kirim ro‘yxatidagi barcha mavjud tovarlar va kiritilgan ma’lumotlar to‘liq saqlanib qolishi test bilan tasdiqlandi.

5. **Avtomatlashtirilgan Test Natijalari (Verification Evidence):**
   - `php artisan test --filter=CatalogAndPartiesManagementTest`: **19 passed (94 assertions, duration 3.9s)**.
   - `php artisan test`: **44 passed out of 44 tests (278 assertions, duration 26.8s)**.
   - `vendor/bin/pint --format agent`: **Muvaffaqiyatli formatlandi (0 style errors)**.
   - `npm run build`: **0 errors (built in 4.98s)**.
   - `flutter analyze`: **No issues found! (ran in 77.4s)**.

---

## 10. 05-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence)

1. **Umumiy `operation_id` va Canonical Payload Fingerprint:**
   - Har bir yozuvchi biznes amali (`create_sale`, `receive_stock`, `collect_debt`, `pay_supplier`, `cash_expense`, va h.k.) unikal UUID `operation_id` bilan himoyalanadi.
   - `PayloadFingerprint`: Payload ichidagi ixtiyoriy tartibdagi kalitlarni rekursiv `ksort` qilib, transport metadata (`timestamp`, `device_id`, `request_id`) ni chiqarib tashlagan holda deterministik SHA-256 xesh yaratadi.
   - Bir xil `operation_id` va bir xil payload takror kelsa — avval saqlangan natija tranzaksiyasiz, bir xil javob holatida qaytariladi.
   - Bir xil `operation_id` boshqa payload bilan kelsa — `OperationConflictException` (HTTP 409 Conflict) tashlanadi.

2. **Tranzaksiya, PostgreSQL Advisory Lock va Concurrency Himoyasi:**
   - `TransactionalOperationService`: Operatsiya bajarilishidan oldin PostgreSQL `pg_advisory_xact_lock(hashtext($operationId))` orqali tranzaksiya darajasida blokirovka oladi.
   - 20 ta parallel so‘rov bir vaqtda bitta `operation_id` bilan kelganda: birinchi tranzaksiya operatsiyani bajarib natijani yozadi, qolgan 19 tasi lock bo‘shashgach yozuvni ko‘radi va bir xil natijani qaytaradi. Do‘kon qoldig‘i, kassa yoki qarz faqat 1 marta hisoblanadi (dublikat bo‘lmaydi).

3. **Outbox Pattern va Asinxron Hodisalar Kafolati:**
   - `outbox_events` jadvali yaratildi (`event_id`, `operation_id`, `event_type`, `aggregate_type`, `aggregate_id`, `payload`, `status`, `retry_count`).
   - Biznes amali va uning audit/outbox yozuvlari bitta atomik tranzaksiyada saqlanadi. Agar tranzaksiya rollback bo‘lsa, outboxga ham hech narsa yozilmaydi (yolg‘on xabarnoma chiqmaydi).
   - `ProcessOutboxJob` va `app:process-outbox` artisan buyrug‘i `FOR UPDATE SKIP LOCKED` orqali parallel workerlar orasida xavfsiz taqsimlanib, hodisalarni `PUBLISHED` holatiga o‘tkazadi.

4. **Xavfsiz Hujjat Raqamlari Generatsiyasi:**
   - `DocumentNumberGenerator`: `max(id)+1` usulidan butunlay voz kechildi. PostgreSQL native sequence'lari (`sales_invoice_seq`, `purchases_invoice_seq`, `payments_number_seq`, `returns_number_seq`) orqali `INV-2026-000001`, `PUR-2026-000001`, `PAY-2026-000001` formatida uzluksiz va parallel so‘rovlarga bardoshli raqamlar shakllantiriladi.

5. **Xatolar Taksonomiyasi (Error Taxonomy):**
   - API va ichki operatsiyalar uchun xavfsiz va aniq klasslar joriy etildi:
     - `OperationConflictException` (409)
     - `OperationValidationException` (422)
     - `OperationPermissionException` (403)
     - `OperationRetryableException` (503)
     - `OperationNeedsReviewException` (422)
   - Maxfiy ma’lumotlar, stack trace yoki ichki SQL xatolari clientga oshkor qilinmaydi.

6. **Avtomatlashtirilgan Test Natijalari (Verification Evidence):**
   - `php artisan test --filter=OperationTransactionAndOutboxTest`: **8 passed (54 assertions, duration 2.8s)**.
   - `php artisan test`: **52 passed out of 52 tests (386 assertions, duration 9.5s)**.
   - `vendor/bin/pint --test`: **PASSED** (0 formatting issues).
   - `npm run build`: **0 errors (built in 799ms)**.
   - `flutter analyze`: **No issues found!**.

---

## 11. 06-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence)

1. **Hisob Daftarlari (Ledgers) va Schema Kengaytirilishi:**
   - **Migratsiya:** `2026_10_03_000005_create_ledgers_and_accounting_tables.php` yaratildi, PostgreSQL test va dev bazalarida migrate, rollback va re-migrate sinovidan 100% o‘tdi.
   - `inventory_balances` jadvaliga `total_value` (jami ombor qiymati, bigInteger) qo‘shildi.
   - `inventory_movements` jadvaliga `operation_id`, `balance_after_quantity`, `balance_after_value` qo‘shildi.
   - `customer_ledger` jadvaliga `operation_id` va `payment_method` qo‘shildi.
   - `supplier_ledger` jadvali yaratildi (`supplier_id`, `operation_id`, `type`, `payment_method`, `debit`, `credit`, `balance_after`, `notes`, `created_at`).
   - `cash_movements` jadvali yaratildi (`cash_account_id`, `operation_id`, `type`, `direction`, `debit`, `credit`, `amount`, `balance_after`, `description`, `created_at`).
   - `payments` jadvali yaratildi (`payment_number`, `operation_id`, `party_type`, `party_id`, `cash_account_id`, `payment_type`, `payment_method`, `direction`, `amount`, `notes`, `status`).
   - `opening_balance_documents` jadvali va PostgreSQL sequence `opening_doc_seq` (`OPN-YYYY-XXXXXX`) yaratildi.

2. **Weighted Average Cost (WAC) va Oxirgi Dona Qat’iy Invarianti:**
   - `InventoryLedgerService`:
     - 1-kirim: 100 dona x 5 000 so‘m = 500 000 so‘m (WAC 5 000 so‘m, qoldiq 100 dona).
     - 2-kirim: 100 dona x 6 000 so‘m = 600 000 so‘m.
     - Jami: 200 dona, jami ombor qiymati 1 100 000 so‘m, yangi WAC: 1 100 000 / 200 = 5 500 so‘m.
     - Chiqim (sotuv): 60 dona x 5 500 = 330 000 so‘m chiqim; qoldiq 140 dona, qiymat 770 000 so‘m, WAC 5 500 so‘m.
     - **Oxirgi dona invarianti:** Qolgan barcha 140 dona sotilganda, qoldiq 0 dona bo‘lishi bilan birga ombordagi qolgan qiymat ham qat’iy 0 ga aylanadi (yaxlitlash tufayli tiyin/so‘m qolib ketmaydi).
     - Ombordagi qoldiqdan ortiq tovar chiqim qilinishiga yo‘l qo‘yilmaydi (`InsufficientStockException`).

3. **Signed Mijoz va Ta’minotchi Daftarlari (Debts & Advances):**
   - **Mijoz (`CustomerLedgerService`):**
     - Nasiya savdoda debit 200 000 so‘m -> `current_debt` = +200 000 (qarz).
     - Mijoz 250 000 to‘laganda credit 250 000 so‘m -> `current_debt` = -50 000 so‘m (avans).
     - Qat’iy qoida: `max(0, ...)` taqiqlangan! Avans to‘liq saqlanadi va hisobotlarda "Avans" deb ko‘rsatiladi.
     - Daftardagi barcha debit-creditlar yig‘indisi `current_debt` keshiga 100% mosligi testda tasdiqlandi.
   - **Ta’minotchi (`SupplierLedgerService`):**
     - Tovar kirimida credit 500 000 so‘m -> `balance` = +500 000 (bizning qarzimiz).
     - To‘lovda debit 600 000 so‘m -> `balance` = -100 000 so‘m (bizning avansimiz/haqdorligimiz).
     - Daftardagi barcha harakatlar yig‘indisi `supplier.balance` keshiga 100% mos.

4. **Kassa Hisoblari, Transfer va To‘lovlar:**
   - `CashAccountService`: `CASH`, `CARD`, `BANK` hisoblari bo‘yicha pul harakatlari faqat real pul oqimida yuritiladi.
   - Manfiy qoldiqqa ruxsatsiz tushish taqiqlangan (`InsufficientCashException`).
   - Transfer: Kanonik tartibda qulflash orqali deadlock xavfisiz pul mablag‘lari saqlangan holda bir hisobdan ikkinchisiga o‘tkaziladi.
   - `PaymentService`: Mijozdan pul qabul qilish yoki ta’minotchiga to‘lash bitta atomik tranzaksiyada kassa kirim/chiqimi va tegishli taraf daftari bilan bog‘lanadi.

5. **Boshlang‘ich Qoldiqlar (Opening Balances) va Idempotency:**
   - `OpeningBalanceService`: Ombor tovarlari, kassa hisoblari, mijozlar va ta’minotchilar boshlang‘ich hisoblarini UUID `operation_id` bilan himoyalangan holda kiritadi.
   - Bir xil `operation_id` bilan qayta yuborilgan so‘rov (retry) ombor yoki pulni ikkinchi marta ko‘paytirmaydi, avvalgi natijani qaytaradi.
   - Bir xil `operation_id` boshqa ma’lumot bilan yuborilsa `OperationConflictException` beradi.
   - Tranzaksiya ichida xato yuz berganda barcha amallar to‘liq rollback bo‘ladi (nol qoldiq xatosi).

6. **Do‘kon Egasi Boshlang‘ich Qoldiqlar Livewire Oynasi:**
   - `OpeningBalancesManager` (`/admin/opening-balances`):
     - Faqat `OWNER` va `ADMIN` huquqiga ega foydalanuvchilar kira oladi (oddiy sotuvchiga 403 Forbidden).
     - Ombor tovarlari, Kassa hisoblari, Mijozlar (Qarz / Avans radiosi bilan), Ta’minotchilar va Hujjatlar tarixi tablari.
     - Formani ochganda stable UUID `operation_id` generatsiya qilinadi.
     - Faqat butun dona va butun so‘m qabul qilinadi (float ishlatilmaydi).

7. **Avtomatlashtirilgan Test Natijalari (Verification Evidence):**
   - `php artisan test --filter=LedgerAndOpeningBalancesTest`: **10 passed (103 assertions, duration 2.68s)**.
   - `php artisan test`: **62 passed out of 62 tests (489 assertions, duration 20.6s)**.
   - `vendor/bin/pint --test`: **PASSED** (0 style issues).
   - `npm run build`: **0 errors (built in 525ms)**.
   - `flutter analyze`: **No issues found! (ran in 1.4s)**.

---

## 12. 07-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence)

1. **Kirim Jarayoni va DRAFT -> POSTED Holati:**
   - Qabul mezoni bo‘yicha sinov: Fanta 0.5L 150 dona × 5000 so‘m = 750 000 so‘m va ombor qoldig‘iga +150 dona qo‘shildi.
   - WAC o‘rtacha tannarx hisobi: Avvalgi qoldiq va yangi kirim summalari izchil yaxlitlanib `inventory_balances` va `inventory_movements` da saqlandi.
2. **Qisman To‘lov va Ta’minotchi Qarzdorligi:**
   - 750 000 so‘mlik kirimda ta’minotchiga 300 000 so‘m to‘langanda:
     - Ta’minotchi qarzi: 450 000 so‘m (`supplier_ledger` va kesh balansda);
     - Kassa chiqimi: -300 000 so‘m (`cash_movements` va kassa balansi);
     - To‘lov hujjati: `payments` jadvalida `OUTFLOW` yozuvi yaratildi.
3. **To‘lovsiz Kirim (Nasiya):**
   - Hech qanday to‘lov kiritilmaganda kassa balansi va kassa harakatlari umuman o‘zgarmaydi (0 harakat). Ta’minotchi qarzi to‘liq 750 000 so‘m deb yoziladi.
4. **Kassa Huquqi Cheklovi (Omborchining jim qolishi naqd to‘lov emas):**
   - Kassa ruxsatiga ega bo‘lmagan omborchi (`WAREHOUSE_MANAGER`) to‘lovli kirim qilishga uringanda `OperationPermissionException` (403) beriladi.
   - Jim qolingan yoki to‘lov belgilanmagan holat sukut bo‘yicha to‘lovsiz (nasiya) deb qabul qilinadi.
5. **Rollback va Idempotency Sinovlari:**
   - Bitta qatorda xato bo‘lsa (mavjud bo‘lmagan tovar yoki manfiy narx) butun kirim atomik tarzda rollback bo‘ladi: 0 tovar kirimi, 0 ta’minotchi qarzi, 0 kassa chiqimi.
   - Bir xil `operation_id` bilan takroriy kirim yuborilganda (double-click yoki tarmoq retry) ikkinchi marta tovar yoki qarz qo‘shilmaydi, avvalgi natija qaytariladi.
6. **Livewire QuickInward Foydalanuvchi Oynasi:**
   - Tovar qo‘shish va ta’minotchi tanlash: Inline modallar (`product-created` va `supplier-created`) orqali yangi tovar yoki ta’minotchi qo‘shilganda kiritilgan qoralama ro‘yxati buzilmaydi, yangi yaratilgan obyekt avtomatik tanlanadi.
   - To‘lov paneli: Foydalanuvchi moliya huquqiga ega bo‘lsa to‘lov turini (To‘lovsiz / To‘liq / Qisman), kassani tanlaydi; huquqi bo‘lmasa to‘lov paneli avtomatik bloklanadi.
   - Qoralama tozalash va POST qilingandan so‘ng yangi `operation_id` generatsiyasi.
7. **Avtomatlashtirilgan Test Natijalari (Verification Evidence):**
   - `php artisan test --filter=PurchaseReceivingTest`: **6 passed (52 assertions, duration 2.6s)**.
   - `php artisan test --filter=BeverageCrmCoreTest`: **8 passed (66 assertions, duration 3.8s)**.
   - `php artisan test`: **68 passed out of 68 tests (541 assertions, duration 11.8s)**.
   - `vendor/bin/pint --test`: **PASSED** (0 style issues).
   - `npm run build`: **0 errors (built in 426ms)**.
   - `flutter analyze`: **No issues found! (ran in 1.1s)**.

---

## 13. Ochiq Qolgan Biznes Qarorlari va Cheklovlar

1. **Eski Demo Testlarni Bosqichma-bosqich Almashtirish Rejasi:**
   - `BeverageCrmCoreTest.php` yangi kirim xizmati talablariga moslashtirildi va 8 ta testi to‘liq o‘tdi.
   - Flutter `test/widget_test.dart` dagi default counter testi Prompt 20 da haqiqiy CRM kirish va savdo ekranlari widget testlariga almashtiriladi.
2. **Offline qoldiq ajratish miqdori (Siyosat):** Qurilmalarga qoldiq rezervini avtomatik foizda (masalan, 30%) yoki do‘kon egasi tomonidan qo‘lda belgilash tartibi 11-bosqichda tasdiqlanishi kerak.
3. **Flutter ilovasining birinchi relizdagi roli:** PWA birinchi relizda barcha qurilmalarda ishga tushadi; Flutter Android parallel ravishda ishlab chiqilmoqda.

---
*07-bosqich muvaffaqiyatli yakunlandi. Keyingi bosqich: Prompt 08.*




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

### 3. Baseline Test Xatolari va Mavjud Kod Holati

- **Backend (Laravel):**
  - **129 ta test o‘tgan (893 ta assertion)** haqiqiy PostgreSQL `aquaoptom_test` va Redis ustida (0 xato).
  - Auth, Role-based permission, cost masking, 8 ta menyu, katalog, tranzaksiyaviy operatsiyalar, daftarlar, kirim, sotuv (POS / CreateSale), qarzdorliklar, kassa va smena, offline qurilmalar, server sync protokoli hamda PWA offline POS (Prompt 13) to‘liq qamrab olindi.
  - Laravel Pint linteridan to‘liq o‘tkazildi (`{"tool":"pint","result":"passed"}`).
- **Mobile (Flutter):**
  - `flutter analyze`: **0 issues found** (toza tahlil natijasi).
  - `test/widget_test.dart` ("Counter increments smoke test"): default shablon testi bo‘lib, u loyihaning dastlabki failing baseline'i sifatida saqlanmoqda. Prompt 20 da haqiqiy CRM kirish va savdo ekranlarining widget va smoke testlariga almashtiriladi.

---

## 4. 25 Bosqichli Qurilish Reyestri (25-Step Pipeline Registry)

| № | Bosqich nomi | Guruh | Status | Dalillar va Natijalar | Keyingi qoldiq ish |
| :---: | :--- | :--- | :---: | :--- | :--- |
| **01** | **Loyihani tayyorlash va arxitektura qarorlarini qayd qilish** | Poydevor | **DONE** | Inventarizatsiya qilindi. `backend/AGENTS.md` Boost bilan yangilandi. Ildiz `.gitignore` va lokal Git repo yaratildi. Baseline xatolari qayd etildi. | 02-bosqichni kutish |
| **02** | **Takror ko‘tariladigan lokal muhit va CI** | Poydevor | **DONE** | PostgreSQL 16 va Redis 5 Windows muhitida ko‘tarildi; `aquaoptom_dev` va `aquaoptom_test` yaratildi; toza test DBda migrate/rollback/re-migrate sinovi o‘tdi; `AppServiceProvider` xavfsizlik qo‘riqchilari qo‘shildi; `.env.example` namunaviy qiymatlar bilan tozalandi; GitHub Actions CI workflow yozildi; 14 ta backend testi real PostgreSQL+Redis ustida muvaffaqiyatli o‘tdi (79 assertions); Vite frontend build va Flutter analyze tekshirildi. | 03-bosqichni boshlash |
| **03** | **Kirish, ruxsatlar va responsive dastur karkasi** | Poydevor | **DONE** | Web & API Sanctum auth; 5 ta asosiy rol (`OWNER`, `ADMIN`, `SALES_MANAGER`, `WAREHOUSE_MANAGER`, `CASHIER`) va 15 ta aniq granular ruxsatlar; xavfsiz `app:bootstrap-owner` buyrug'i; bloklangan foydalanuvchini darhol cheklash; server policy/API'da tannarx yashirish (`cost_price: null`); mahalliy Vite assetli responsive layout, 360px viewport va 8 ta rasmiy menyu (soxta raqamlarsiz); 25 ta backend testi (184 assertions) va Pint linter 100% o'tdi. | 04-bosqichni boshlash |
| **04** | **Katalog, mijoz va ta’minotchi boshqaruvi** | Biznes asoslari | **DONE** | Mahsulot/hajm/variant schema kengaytirildi (migration 000003, PostgreSQL sequence); Fanta/fanta va 0.5 L/500 ml normalizatsiyalari va concurrent duplicate himoyasi; mijoz (telefon yo'q holatda do'kon/manzil shartligi, avtomatik taraf merge taqiqlangan, offline barqaror UUID idempotent retry); ta'minotchi qoidalari; narx tarixi (versiya, AuditLog, ruxsat nazorati); tarixda ishlatilgan yozuvlarni o'chirishdan himoya (`CannotDeleteReferencedRecordException`); Livewire mahsulot/mijoz/ta'minotchi sahifalari hamda kirim/sotuv inline modallari (ota komponent qoralama tovarlarini saqlab tanlash); 44/44 backend testlar (278 assertions) 100% o'tdi, Pint formatlandi, Vite build muvaffaqiyatli, Flutter analyze 0 issues. | 05-bosqichni boshlash |
| **05** | **Operatsiya ID, tranzaksiya, audit va hodisa navbati** | Hisob poydevori | **DONE** | Barcha yozuvchi amallar uchun umumiy `operation_id` (UUID), canonical payload fingerprint (SHA-256), PostgreSQL advisory lock (`pg_advisory_xact_lock`), 20 ta parallel bir xil `operation_id` sinovi 100% bitta operatsiya va eski natijani qaytardi, boshqa payload 409 Conflict berdi; tranzaksiyaviy `outbox_events` jadvali va background `app:process-outbox` command/job qurildi (rollbackdan keyin event yo'q, xato bergan savdo yarim qolib ketmaydi); `DocumentNumberGenerator` sequence orqali `max(id)+1`siz invoice/payment raqamlari beradi; xatolar toifalandi (`OperationConflictException`, `OperationValidationException`, `OperationPermissionException`, `OperationRetryableException`, `OperationNeedsReviewException`); 52/52 testlar (386 assertions) to'liq o'tdi. | 06-bosqichni boshlash |
| **06** | **Hisob daftarlari, tannarx va boshlang‘ich qoldiqlar** | Hisob poydevori | **DONE** | Ombor daftari (`inventory_movements`, `inventory_balances` jami qiymat `total_value` bilan); WAC formulasi (100×5000 + 100×6000 = 200 dona / 1 100 000 qiymat / WAC 5500); oxirgi dona sotilganda qoldiq qiymat ham qat'iy 0 bo'lishi; signed mijoz daftari (`customer_ledger` musbat qarz, manfiy avans, `max(0)` taqiqlangan); signed ta’minotchi daftari (`supplier_ledger` musbat qarzimiz, manfiy avansimiz); kassa hisoblari va harakatlari (`cash_accounts`, `cash_movements`, transfer); to'lovlar (`payments`); idempotent boshlang'ich qoldiq hujjati va xizmati (`OpeningBalanceService` retry bitta yozuv, conflict himoyasi); do'kon egasi uchun Livewire boshlang'ich qoldiqlar oynasi (`OpeningBalancesManager`); 62/62 testlar (489 assertions) 100% o'tdi. | 07-bosqichni boshlash |
| **07** | **Kirim bo‘limi — backenddan oynagacha** | Kundalik ish | **DONE** | DRAFT->POSTED kirim; migration 000006 (`operation_id`, `supplier_invoice_number`, `paid_amount`, `debt_amount`, `notes`); `ReceivePurchaseService` orqali atomik WAC qoldiq, supplier_ledger credit, ixtiyoriy cash_movements + supplier debit, Payment modeli; kassa huquqi bo'lmagan omborchining to'lov qilishi taqiqlangan (jim qolish naqd to'lov deb olinmaydi); xatolikda to'liq rollback; idempotent retry eski natijani berishi; Livewire `QuickInward` (katalog va ta'minotchi inline modallari bilan qoralama saqlanadi, to'lov paneli, kassa tanlovi); 68/68 testlar (541 assertions) 100% o'tdi. | 08-bosqichni boshlash |
| **08** | **Sotuv bo‘limi — tezkor, mijozli va nasiya** | Kundalik ish | **DONE** | `CreateSaleService` xizmati; migration 000007 (`operation_id`, `paid_amount`, `debt_amount`, `cash_account_id`, `payment_method`, `notes`, `receipt_data`); guest tezkor savdoda faqat to'liq to'lov (mijozsiz DEBT taqiqlangan); mijozli savdoda to'liq/qisman/nasiya; savatda takroriy variant qatorlari bo'lsa barcha dona jamlanib lock tekshirilishi (100 qoldiqdan 60+60 o'tmaydi); kasr dona va manfiy/nol narx taqiqlanishi; tizim narxi vs erkin narx va eski narx versiyasida qayta tasdiq; 60×6500 jami 390 000 / cost 300 000 / paid 140 000 / debt 250 000 / gross 90 000 qabul mezoni to'liq o'tgan; 20 bosish/parallel so'rov bitta chek; mijoz avansi ikkinchi cash emasligi; Livewire OptomPos xatolikda savat saqlanishi va elektron kvitansiya; SaleExecutionAdapterInterface arxitekturasi; 78/78 testlar (581 assertions) 100% o'tdi. | 09-bosqichni boshlash |
| **09** | **Qarzdorliklar va taraflar to‘lovlari** | Kundalik ish | **DONE** | Alohida "Mijozlar bizga qarzdor" va "Biz ta’minotchilarga qarzdormiz" tablari; signed balans (musbat=qarz, manfiy=avans), avans boshqa taraf qarzini yashirmaydi; kartochkalar va ko'chirma (`boshlang'ich + harakatlar = yakuniy ko'chirma` 100%); Asia/Tashkent sekund aniqligida event_time, server_time, actor va tovar olib ketilgan vaqt (`goods_picked_up_at`); atomik `CustomerPaymentService` va `SupplierPaymentService` (kassa bilan bitta tranzaksiya); qarzdan ortiq summa faqat tasdiq bilan avansga o'tishi; keyingi to'lov chek/tovarlarga majburiy bog'lanmasligi va oldingi sotuv foyda/tannarxini o'zgartirmasligi; ta'minotchi to'lovi omborga tegmasligi; kredit limit va to'lov muddati ogohlantirishlari; 88/88 backend testlar (630 assertions), Pint, Vite, Flutter analyze 100% o'tdi. | 10-bosqichni boshlash |
| **10** | **Kassa, xarajat, o‘tkazma va smena** | Kundalik ish | **DONE** | Naqd/karta/bank hisoblari; umumiy atomik cash kontrakti; operatsion xarajatlar (`ExpenseService`), egasi mablag'i (`OwnerFundsService`, draw operatsion xarajat emas va foydani kamaytirmaydi); hisoblararo o'tkazma (`CashTransferService`, savdo/foyda emas); kassa yetarliligi `lockForUpdate` ichida; bitta naqd hisob uchun bitta OPEN smena (`CashSession`), kutilgan naqd balansi, sanalgan naqd, farq sababi; offline qurilmalar kutilganda PROVISIONAL yopilish; closed session guard (yopilgan smenaga savdo/harakat yozilmaydi); farqni yashirin balance overwrite bilan emas, balki ruxsatli farq hujjati bilan rasman tuzatish; 14.3 misoli (500k boshlang'ich naqd, supplier -300k, sale +140k, debt +100k, supplier -50k = 390k naqd, ombor/qarzlar aralashmasligi); Livewire `CashManager` interfeysi; 99/99 testlar (692 assertions), Pint, Vite, Flutter analyze 100% o'tdi. | 11-bosqichni boshlash |
| **11** | **Offline qurilmalar, qoldiq va kredit ajratmalari** | Offline poydevori | **DONE** | Device registration (`devices` jadvali, DEV-0001, UUID, tur, status, oxirgi ko‘rilgan vaqt); HMAC-SHA256 imzolangan muddatli lease (`offline_authorizations`, epoch, token, vaqtli permissions); tovar ajratmasi (`inventory_allocations` va harakatlar daftari, 100 dona = PC 60 / Phone 30 / Free 10 stsenariysi, online savdo o‘z rezervi yoki erkin qoldiqni sarflashi, parallel grant jismoniy qoldiqdan oshmasligi, idempotent iste'mol, bekor bo'lish/uzilish rezervni avtomatik boshqa qurilmaga bermasligi, yo'qolgan qurilmani audit sababi bilan reconciliation qilish); ombordagi brak/qaytarish amallarini faol rezervlardan himoyalash (`ReservedStockProtectionException`); qat'iy mijoz kredit limiti va yangi offline mijozlar uchun umumiy qarz byudjeti; Livewire `DeviceManager` interfeysi; 110/110 testlar (735 assertions), Pint, Vite, Flutter analyze 100% o'tdi. | 12-bosqichni boshlash |
| **13** | **PWA lokal baza va internetsiz sotuv** | Offline PWA | **DONE** | Standalone PWA manifest (`manifest.json`), Service Worker (`sw.js`, app shell cache, network-only offline JSON fallback), `AquaOptomDB` IndexedDB sxemasi (9 ta store), Pure JS/Alpine.js offline POS (`aqua-pos.js`, `aqua-db.js`, Livewire online qoladi), atomik lokal savdo tranzaksiyasi (quota, credit, sale, outbox, draft clearing bitta IndexedDB tranzaksiyada), mijoz UUID, PIN lock, ko‘p tabli BroadcastChannel sinxronizatsiyasi, favqulodda JSON eksport; 7/7 JS unit testlari (Node.js + fake-indexeddb), 7/7 Feature testlari va 129/129 to'liq tizim testlari 100% o'tdi. | 14-bosqichni boshlash |
| **14** | **PWA avtomatik sync va uzilish sinovlari** | Offline PWA | **DONE** | IndexedDB navbatini API bilan ulash: server health tekshirish (`/api/health`, `/api/sync/health`), pending batch push (`/api/sync/push`), ACK'ni lokal atomik yozish (`applyPushResults`), cursor pull (`applyPulledChanges`) va qolgan pending overlay hisobi (`getPendingOverlay`); avtomatik sinxronizatsiya triggerlari (`online`, `visibilitychange`, window `focus`, 30s interval, qo'lda sync tugmasi, Service Worker Background Sync API); ko'p tabli poyga holatini oldini olish uchun multi-tab mutex lock (`acquireSyncLock` / `releaseSyncLock`, 30s stale recovery bilan); asl `operation_id` va payload bilan idempotent replay (tarmoq uzilishi va timeoutda qayta jo'natilganda dublikatsiz `RETRY_SUCCESS`); xavfsiz retention (ACK bo'lgan chek va payloadlar outbox'dan o'chirib yuborilmaydi, status `APPLIED` qilinadi, favqulodda tiklash uchun saqlanadi); offline savdoni bekor qilish (`VOID_SALE` / `CANCEL_SALE`, asl `original_operation_id` ga bog'lanadi, navbatdan o'chirilmaydi, ombor/kassa/mijoz qaytariladi); `NEEDS_REVIEW` holati va tushuntirish modal oynasi; 7/7 JS unit testlari (Node.js + fake-indexeddb), 6/6 Feature testlari, 135/135 to'liq tizim testlari 100% o'tdi. | 15-bosqichni boshlash |
| **16** | **Qaytarish, brak, inventarizatsiya va tuzatish** | Tahlil va nazorat | **DONE** | Original savdo/kirimga bog'langan qisman/to'liq return, sotilgandan ko'p qaytarish yo'qligi, original narx va snapshot tannarx, kasrli rounding yopilishi, brak tovar sotiladigan qoldiqqa qo'shilmasligi; mijoz signed balansi va refund/cash qayta hisobi (naqd va qarzni bir vaqtda kamaytirmaslik); ta'minotchi qaytarishida commercial credit va WAC chiqimi farqi; brak faqat WAC tannarx yo'qotishi (kassa harakati/xarajati emas); inventarizatsiya prepare->freeze ACK->sanash->apply sikli, uzilgan qurilma freeze guard (`DeviceFreezePendingException`), ortiqcha/kamomad tuzatish daftari, posted hujjatlar o'zgarmasligi (`DocumentImmutableException`); 16/16 Feature testlari (57 assertions) va 164/164 to'liq backend testlari (1064 assertions) 100% o'tdi. | 17-bosqichni boshlash |
| **17** | **Savdo tarixi, hisobotlar va eksportlar** | Tahlil va nazorat | **DONE** | Bugun/kecha/hafta/oy/sana-oraliq/yil+oy (UTC saqlash, Asia/Tashkent kun chegaralari); dastlabki paid/debt snapshot bilan keyingi to'lov aralashmasligi, soxta invoice paid allocation taqiqlanishi (sentabr savdosi 100k qarz va oktabr 100k qarz to'lovi ikki davrda aniq ajratilishi, cash/debt double-count yo'qligi); umumiy hisobotlar so'rov kontrakti (Savdo tahlili, P&L, Kirim/Ta'minotchi, Ombor qoldig'i/Valuation ledgerdan, Taraf ko'chirmalari, Kassa/Smenalar, Xodimlar, Qurilma sync); paginatsiya va eksport totals 100% bir xilligi; CSV formula inyeksiyasidan himoya ('=, +, -, @, \t, \r); ruxsatli (/exports/download/{uuid}) CSV va DomPDF oqimli eksport; 12/12 Feature testlari va 176/176 to'liq backend testlari 100% o'tdi. | 18-bosqichni boshlash |
| **18** | **Dashboard, Admin va real vaqt yangilanishlari** | Boshqaruv | **DONE** | Rolga mos boshqaruv paneli (flow vs as-of balansi, ochiq smenalar, past qoldiq, qarzlar, offline to'liqlik belgisi); Admin boshqaruvi (foydalanuvchilar, ruxsatlar, tizim sozlamalari, qurilmalar va oflayn ajratmalar, audit jurnali, NEEDS_REVIEW nizolari va ularni sababli hal qilish); Reverb/Echo xususiy kanallari (`private-store.updates`, `private-user.{id}`); Flutter uchun event invalidation API (`/api/events/cursor`); 12/12 Feature testlari va 188/188 to'liq backend testlari 100% o'tdi. | 19-bosqichni boshlash |
| **19** | **Telegram orqali ko‘rish, kirim, sotuv va to‘lov** | Telegram | **DONE** | Webhook xavfsiz secret headeri; Telegram chat ID'ni ruxsatli xodimga bog'lash (`/link`); ko'p qatorli savdo va kirim yaratish (draft, tovar/hajm/taraf inline qo'shish, to'lov turi, tasdiqlash); qarz to'lash va kassa balansi; ruxsatli bildirishnomalar tarqatish outbox orqali; 8/8 Feature testlari va 196/196 to'liq backend testlari 100% o'tdi. | 20-bosqichni boshlash |
| **20** | **Flutter Android: kirish va online biznes oynalari** | Mobil ilova | **DONE** | Mavjud `mobile/` loyihasi arxitekturaga moslashtirildi; Sanctum sessiya va rolga mos navigatsiya; Dashboard, Katalog, Kassa/POS, Kirim, Qarzlar, Kalkulyator, Savdo tarixi, Hisobotlar, Admin Web ko'rinishlari; inline tovar/hajm/taraf yaratish; narxlar va pullar integer tiyinlarda; xatoliklar typed `ApiException` bilan; 6/6 Flutter testlari (100%) va 196/196 backend testlari 100% o'tdi. | 21-bosqichni boshlash |
| **21** | **Flutter offline savdo, sync va reliz paketi** | Mobil ilova | **DONE** | SQLite lokal katalog/qoldiq/ajratma/navbat modeli (`AppDatabase`, 10 jadval, v1->v2 migratsiya saqlanishi); atomik lokal confirm (`confirmSaleOffline`, ajratmadan ayirish, `#OFF-...` vaqtinchalik chek, `sync_queue` PENDING); PWA/Backend bilan 100% bir xil kanonik SHA-256 fingerprint (`PayloadFingerprint`); worker lease lock va restart/duplicate-worker dedup; user isolation (sessiya almashganda navbat xavfsiz ajratilgan); offline customer UUID parent mapping; ACK saqlash va `NEEDS_REVIEW` klassifikatsiyasi; void/cancel tuzatish amali; Android ruxsatlari, release signing konfigi va release APK build (`app-release.apk`, 55.5MB); 19/19 Flutter testlari va 196/196 backend testlari 100% o'tdi. | 22-bosqichni boshlash |
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

## 13. 08-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence)

1. **Sotuv Jarayoni va Qabul Mezonlari Sinovi:**
   - Qabul mezoni bo‘yicha sinov: 60 dona × 6500 so‘m = 390 000 so‘m savdo summasi (`total_amount`), 300 000 so‘m WAC tannarx (`total_cost`), 140 000 so‘m hozir to‘langan pul (`paid_amount`), 250 000 so‘m yangi nasiya qarz (`debt_amount`) va 90 000 so‘m yalpi foyda (`gross_profit`).
   - WAC tannarx snapshot: Har bir `sale_items` qatori o‘sha paytdagi WAC o‘rtacha tannarxi (5000 so‘m) bilan saqlandi, ombor qoldig‘ida 40 dona va 200 000 so‘m qoldiq qiymat qoldi.
   - Mijoz qarzi: Mijozning signed balansiga to‘liq 390 000 so‘m qarz (Debit) qo‘shildi, 140 000 so‘m to‘lov (Credit) ayirildi va yakuniy qarz 250 000 so‘m bo‘ldi.
   - Kassa hisobi: Kassaga faqat hozir olingan haqiqiy 140 000 so‘m kirim qilindi.
2. **Qat'iy Biznes Rad Etishlari (Rejections):**
   - **Mijozsiz DEBT:** Noma'lum xaridorga nasiyaga savdo qilish taqiqlandi (`OperationValidationException: GUEST_DEBT_NOT_ALLOWED`).
   - **Manfiy yoki Nol Narx:** `0` yoki manfiy narxda savdo qilish rad etildi (`INVALID_SALE_PRICE`).
   - **Kasr Dona:** Butun bo‘lmagan miqdor (masalan, 2.5 dona) taqiqlandi (`INVALID_QUANTITY`).
   - **100 qoldiqdan 60 + 60 o‘tmasligi:** Savatda bir xil variant takror qatorlarda kelsa yoki concurrent tranzaksiyalarda kelganda barcha dona jamlanib `lockForUpdate()` orqali tekshirildi (120 > 100 rad etildi; 60 o‘tgandan so‘ng qolgan 40 dan ikkinchi 60 rad etildi).
   - **Tizim Narxi Yo‘q Holat:** Variantda tizim narxi belgilanmagan bo‘lsa tasodifiy default narx olinmasdan rad etildi (`SYSTEM_PRICE_NOT_SET`).
   - **Online Eski Narx Versiyasida Qayta Tasdiq:** Narx versiyasi o‘zgarganda xaridordan qayta tasdiqlash so‘raldi (`PRICE_VERSION_MISMATCH`).
3. **Idempotency va 20 Takroriy Bosish Sinovi:**
   - Bir xil `operation_id` bilan 20 marta ketma-ket yuborilgan so‘rov faqat 1 ta chek yaratdi, ombor, mijoz qarzi va kassa faqat 1 marta o‘zgardi.
4. **Mijozning Mavjud Avansi (Avans ikkinchi cash emas):**
   - Xaridorning oldingi -100 000 so‘m avansi bo‘lganda, 65 000 so‘mlik savdo hisobiga qarz yozildi (-35 000 so‘m qoldi), kassa hisobiga esa soxta ikkinchi marta pul tushumi yozilmadi.
5. **Livewire OptomPos Oynasi va Qoralama Saqlanishi:**
   - Xatolik yuz berganda (masalan, mijozsiz nasiya qilishga urinilganda) savat qoralamasi buzilmay saqlanib qoldi ("xatoda savat qoladi"). Mijoz tanlangach savdo yakunlandi va elektron kvitansiya ko‘rsatildi.
6. **POS Adapter Arxitekturasi:**
   - `SaleExecutionAdapterInterface` va `OnlineSaleAdapter` yaratildi, keyingi bosqichlardagi offline sync adapteri uchun arxitektura to‘liq ajratildi.
7. **Avtomatlashtirilgan Test Natijalari (Verification Evidence):**
   - `php artisan test --filter=SaleOperationTest`: **10 passed (40 assertions, duration 4.8s)**.
   - `php artisan test --filter=BeverageCrmCoreTest`: **8 passed (66 assertions, duration 4.9s)**.
   - `php artisan test`: **78 passed out of 78 tests (581 assertions, duration 16.7s)**.
   - `vendor/bin/pint --test`: **PASSED** (0 style issues).
   - `npm run build`: **0 errors (built in 787ms)**.
   - `flutter analyze`: **No issues found! (ran in 2.5s)**.

---

## 14. 09-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence)

1. **Alohida Tablar va Signed Balans Qoidalari:**
   - "Mijozlar bizga qarzdor" va "Biz ta’minotchilarga qarzdormiz" alohida tablar qilib ajratildi.
   - Signed balans qat'iy saqlandi: musbat balans — qarz (`DEBT`), manfiy balans — avans (`ADVANCE`).
   - Bir mijozning avansi boshqa mijozning qarzini agregatlarda hech qachon yashirmaydi (`max(0)` taqiqlangan).
2. **Hisob Ko‘chirmasi (Statement) Formulaning 100% Mosligi:**
   - `StatementService` mijoz va ta'minotchi uchun hisob ko'chirmasini to'liq shakllantiradi: `boshlang'ich qoldiq + jami debit - jami credit = yakuniy qoldiq`.
   - Har bir harakat o'zgarishi running balance bilan hisoblanadi.
3. **Asia/Tashkent Vaqt Aniqligi va Audit:**
   - Hujjatlar va ko'chirmadagi barcha vaqtlar `Asia/Tashkent` vaqt mintaqasida sekund aniqligida (`Y-m-d H:i:s`) ko'rsatiladi.
   - `event_time` (hodisa vaqti), `server_time` (yozilgan vaqt), `actor` (kim bajargan) va ixtiyoriy `goods_picked_up_at` (tovar olib ketilgan vaqt) to'liq audit qilinadi.
   - Soxta CCTV integratsiyasi yo'q — faqat aniq vaqt ma'lumotlari.
4. **Umumiy Atomik To‘lov Xizmatlari:**
   - `CustomerPaymentService`: Mijoz qarz to'lovi kassa kirimi (`cash_movements` IN) va mijoz krediti (`customer_ledger` CREDIT) bilan bitta `TransactionalOperationService` tranzaksiyasida atomik bajariladi.
   - `SupplierPaymentService`: Ta'minotchi qarzi to'lovi kassa chiqimi (`cash_movements` OUT) va ta'minotchi debiti (`supplier_ledger` DEBIT) bilan atomik bajariladi. Kassa mablag'i yetarli bo'lmasa to'liq rollback bo'ladi (`INSUFFICIENT_CASH`).
   - Ta'minotchi to'lovi ombor qoldig'i yoki tovar harakatlariga MUTLAQO tegmaydi.
5. **Ortiqcha To‘lov va Avans Tasdig‘i:**
   - Agar to'lov summasi joriy qarzdan oshsa, xatolik beriladi (`EXCESS_PAYMENT_REQUIRES_ADVANCE_CONFIRMATION`).
   - Foydalanuvchi `confirm_excess_advance = true` deb aniq tasdiqlagan taqdirdagina to'lov qabul qilinadi va mijoz/ta'minotchi balansida toza manfiy avans yoziladi.
6. **Keyingi To‘lovning Yalpi Foydaga Ta'sir Qilmasligi:**
   - 250 000 so‘m qarz bo'yicha keyinchalik kiritilgan 100 000 so‘m to'lov faqat mijoz balansini kamaytiradi (qarz 150 000 so‘mga tushadi).
   - Oldingi savdoning yalpi foydasi (`gross_profit` 90 000 so‘m) va tannarxi (`total_cost` 300 000 so‘m) mutlaqo o'zgarmaydi. To'lov tovarlarga/cheklarga majburiy bog'lanmaydi.
7. **Kredit Limit va Kelishilgan To‘lov Muddati:**
   - Mijoz va ta'minotchi kartalariga `credit_limit` va `payment_due_date` qo'shildi. Muddati o'tgan qarzlar uchun qizil indikatorlar va filtrlash imkoniyati yaratildi.
8. **Livewire DebtsManager Oynasi:**
   - Responsive interfeys: Mijozlar va ta'minotchilar ro'yxati, qidiruv, holat filtrlari (qarz/avans/muddati o'tgan).
   - Tezkor to'lov modal oynasi (to'liq to'lash, 50%, 25% tugmalari bilan).
   - Ortiqcha summa kiritilganda ogohlantirish va avansga o'tkazish checkboxi.
   - Hisob ko'chirmasi (Statement drawer) va davr filtrlari.
   - Kredit limit va muddatlarni tahrirlash modali.
9. **Avtomatlashtirilgan Test Natijalari (Verification Evidence):**
   - `php artisan test --filter=DebtsAndPartyPaymentsTest`: **10 passed (49 assertions, duration 3.2s)**.
   - `php artisan test`: **88 passed out of 88 tests (630 assertions, duration 31.3s)**.
   - `vendor/bin/pint --test`: **PASSED** (0 style issues).
   - `npm run build`: **0 errors (built in 4.73s)**.
   - `flutter analyze`: **No issues found! (ran in 57.5s)**.

---

## 15. 10-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence)

1. **Naqd, Karta va Bank Hisoblari & Umumiy Pul Kontrakti:**
   - `cash_accounts` jadvali: Naqd pul (`CASH`), Karta/Terminal (`CARD`), Bank hisob-raqami (`BANK`).
   - Umumiy cash kontrakti operatsion xarajatlar, hisoblararo o'tkazma, egasi mablag'i, savdo/qarz tushumlari va qaytarishlarga moslashtirildi.
   - Pul chiqimlarida qoldiq yetarliligi `lockForUpdate()` tranzaksiyasi ichida tekshiriladi (`InsufficientCashException`).
2. **Operatsion Xarajatlar (`ExpenseService`):**
   - Ruxsat etilgan toifalar: `RENT`, `SALARY`, `TRANSPORT`, `UTILITIES`, `UNLOADING`, `OTHER`.
   - `operation_id` orqali to'liq idempotentsiya: takroriy so'rov eski natijani qaytaradi, kassa puli ikki marta yechilmaydi, boshqa ma'lumot bilan 409 Conflict.
   - `expenses` va `payments` jadvallari, `AuditLog` va `OutboxEvent` atomik shakllanadi.
3. **Hisoblararo O'tkazma (`CashTransferService`):**
   - Transfer faqat hisoblararo pul harakati bo'lib, yangi savdo, tushum yoki operatsion xarajat EMAS.
   - Chiqim hisobidan yechilib, kirim hisobiga qo'shiladi; deadlockdan himoyalanish uchun hisoblar ID lari saralangan tartibda qulflanadi.
   - Idempotentsiya tekshirildi (takroriy so'rovda ikki marta yechilmaydi).
4. **Egasi Mablag'i (`OwnerFundsService`) va Foydadan Ajratish:**
   - Egasi mablag' kiritishi (`OWNER_DEPOSIT` / capital injection) savdo tushumi emas.
   - Egasi mablag' chiqarishi (`OWNER_DRAW` / draw) operatsion xarajat emas va sotuv yalpi foydasini kamaytirmaydi (`Expense` modeli yaratilmaydi).
5. **14.3 Misoli — Aniq Ketma-ketlik va Hisoblarning To'liq Ajratilishi:**
   - Boshlang'ich naqd: 500 000 so'm.
   - Ta'minotchiga to'lov: −300 000 so'm -> kassa: 200 000 so'm, ta'minotchi qarzi: 450 000 so'm.
   - Mijozga savdo (60 dona × 6 500 = 390 000 so'm, tannarx 300 000 so'm, yalpi foyda 90 000 so'm), to'langan: +140 000 so'm -> kassa: 340 000 so'm, mijoz qarzi: 250 000 so'm, ombor qoldig'i: 90 dona / 450 000 so'm.
   - Mijoz qarz to'lovi: +100 000 so'm -> kassa: 440 000 so'm, mijoz qarzi: 150 000 so'm.
   - Ta'minotchiga to'lov: −50 000 so'm -> kassa: **390 000 so'm**, ta'minotchi qarzi: 400 000 so'm.
   - Yakuniy naqd kassa: aniq **390 000 so'm**! Mijoz qarzi (150 000), ta'minotchi qarzi (400 000) va ombor qiymati (450 000) naqdga mutlaqo aralashmaydi.
6. **Kassa Smenasi (`CashSession`) Intizomi:**
   - **Bitta naqd hisob uchun bitta OPEN smena:** Bir vaqtda ikkinchi smena ochish dastur va PostgreSQL darajasida (`unique_open_cash_session_per_account`) taqiqlandi (`ANOTHER_SESSION_ALREADY_OPEN`).
   - **Closed Session Guard:** Yopilgan smenaga yangi savdo yoki pul operatsiyasi yozish qat'iy bloklandi (`CLOSED_SESSION_CANNOT_ACCEPT_OPERATIONS`).
   - **Provisional yopilish:** Offline qurilmalar sinxronizatsiyasi kutilayotganda smena `PROVISIONAL` holatida yopiladi va ma'lumotlar kelguncha kutadi.
7. **Kassa Farqi va Ruxsatli Farq Hujjati:**
   - Kassa sanalganda farq aniqlansa, sababsiz yopish rad etiladi (`DIFFERENCE_REASON_REQUIRED`).
   - Smena yopilganda kassa balansi yashirincha overwrite qilinmaydi (`balance_after` o'zgarmasdan turadi).
   - Farq faqat vakolatli xodim (egasi/admin) tomonidan tasdiqlangandan keyin qonuniy `DIFFERENCE_SURPLUS` yoki `DIFFERENCE_SHORTAGE` pul daftari harakati bilan to'g'rilanadi.
8. **Livewire `CashManager` Interfeysi:**
   - Hisoblar kartalari (Naqd, Karta, Bank) va jami pul mablag'i.
   - Ochiq smena holati, ochilgan vaqti, kutilgan naqd summasi va smenani yopish oynasi.
   - Tezkor amallar: Xarajat qilish, Hisoblararo o'tkazma, Egasi mablag'i (kiritish/chiqarish), Smena ochish/yopish.
   - Pul harakatlari daftari (filtrlar, `Asia/Tashkent` sekund aniqligi, kirim/chiqim/qoldiq) va Smenalar tarixi jadvallari.
9. **Avtomatlashtirilgan Test Natijalari (Verification Evidence):**
   - `php artisan test --filter=CashSessionAndMovementsTest`: **11 passed (62 assertions, duration 2.8s)**.
   - `php artisan test`: **99 passed out of 99 tests (692 assertions, duration 21.0s)**.
   - `vendor/bin/pint --test`: **PASSED** (0 style issues).
   - `npm run build`: **0 errors (built in 550ms)**.
   - `flutter analyze`: **No issues found! (ran in 2.1s)**.

---

## 16. 11-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence)

1. **Qurilmalar Ro‘yxatga Olinishi (`DeviceService` & `devices` jadvali):**
   - **Migratsiya:** `2026_10_03_000010_create_devices_and_allocations_tables.php` yaratildi. `device_seq` PostgreSQL sequence orqali parallel so‘rovlarda xavfsiz `DEV-0001` formatidagi inson o‘qiy oladigan kodlar shakllanadi.
   - Har bir qurilma uchun unikal `device_uuid`, `device_type` (`PC`, `MOBILE`, `TABLET`, `POS_TERMINAL`), `status` (`ACTIVE`, `REVOKED`, `LOST`), tayinlangan foydalanuvchi (`assigned_user_id`), oxirgi IP va heartbeat vaqtlari qayd etildi.
   - Bloklangan yoki yo'qolgan qurilmalar yangi operatsiyalarni bajara olmaydi (`DeviceRevokedException`).

2. **Imzolangan Muddatli Ruxsat Guvohnomasi (`OfflineLeaseService`):**
   - HMAC-SHA256 imzosi server maxfiy kaliti (`config('app.key')`) orqali kanonik JSON ma'lumotlari asosida shakllantiriladi.
   - Muddat (`valid_from`, `expires_at`), ruxsatlar to'plami (`permissions`), token (`lease_token`) va qurilma epoxasi (`current_lease_epoch`) to'liq imzo bilan muhrlanadi.
   - Imzo soxtalashtirilganda `verifyLease` darhol `SIGNATURE_MISMATCH` qaytaradi.
   - Epoxa oshirilganda yoki lease revoke qilinganda eski ruxsatlar bekor bo'ladi.
   - **Muhim arxitektura qoidasi:** Revoke yoki Expiry yangi savdoni to'xtatadi, ammo ushbu vaqtdan oldin qurilmada lokal yaratilgan pending savdoni rad etmaydi (`validateOperationPermitted`).

3. **100 Dona = PC 60 / Phone 30 / Free 10 Stsenariysi (`InventoryAllocationService`):**
   - **Fizik qoldiq va sotish huquqi ajratmasi (rezerv) qat'iy ajratildi:** Omborda 100 dona tovar bo'lsa, PC ga 60 dona va Phone ga 30 dona ajratilganda jismoniy qoldiq 100, jami rezerv 90 va erkin sotish mumkin bo'lgan erkin qoldiq aniq 10 donani tashkil qiladi.
   - **Erkin qoldiq himoyasi:** Online savdo o'z rezervi bo'lmasa, faqat erkin qoldiqdan sarflaydi. 10 dona erkin qoldiq bo'lganda 15 dona sotish so'rovi `InsufficientFreeStockException` bilan bloklanadi.
   - **Qurilma o'z rezervidan sotishi:** PC 60 dona sotganda o'z ajratmasidan sarflaydi (`consumed_quantity = 60`), fizik qoldiq 90 bo'ladi.
   - **Qurilma rezervidan ortiq sota olmasligi:** 30 dona ajratmasi bor telefon 35 dona sotishga uringanda `InsufficientAllocationException` bilan bloklanadi.
   - **Parallel grantlar jismoniy qoldiqdan osha olmaydi:** Omborda mavjud bo'lganidan ortiq tovar qurilmalarga rezerv qilib tarqatilmaydi (`InsufficientStockForAllocationException`).

4. **Idempotent Iste'mol va Baza Butunligi:**
   - Bir xil `operation_id` bilan bir nechta marta sarflash chaqirilganda rezerv takroriy kamaytirilmaydi; harakatlar daftari (`inventory_allocation_movements`) bitta yozuvni saqlaydi.

5. **Avtomatik Tiklanmaslik Qoidasi (Lease Expiry / Offline / Reinstall):**
   - Qurilmaning internetdan uzilishi, muddati tugashi yoki dasturni qayta o'rnatishi unga berilgan tovar rezervini avtomatik ravishda bekor qilib boshqa qurilmaga bermaydi (chunki fizik tovar yo'lda yoki mashinada sotilgan bo'lishi mumkin).
   - Rezervlar tovar do'konga qaytarilgandagina rasman qaytariladi (`returnAllocation`).

6. **Yo'qolgan Qurilmani Qo'lda Rekonsilyatsiya Qilish (`manualReconcileLostDevice`):**
   - Agar qurilma yo'qolsa, do'kon egasi ruxsati bilan majburiy sabab kiritilib, qoldiqlar audit logi bilan rekonsilyatsiya qilinadi.

7. **Ombor Harakatlarining Faol Rezervlardan Himoyalanishi:**
   - Ombordagi brakka chiqarish yoki ta'minotchiga qaytarish kabi qoldiqni kamaytiruvchi amallar faol rezervlar chegarasini buzolmaydi (`validateStockReductionAllowed` -> `ReservedStockProtectionException`).

8. **Mijoz Kredit Limiti va Yangi Mijozlar Qarz Byudjeti (`CreditAllocationService`):**
   - `is_strict_credit_limit = true` bo'lgan mijozlar uchun qurilmalarga berilgan kredit limit ajratmalari va online nasiyalar umumiy limitdan oshmasligi tekshiriladi (`InsufficientCreditAllocationException`).
   - Qurilmalarga yangi ro'yxatdan o'tmagan offline mijozlarga sotish uchun alohida umumiy nasiya byudjeti (`new_customer_debt_budget`) ajratiladi.

9. **Livewire `DeviceManager` Interfeysi:**
   - Qurilmalar ro'yxati (kodi, nomi, turi, egasi, oxirgi IP va faollik vaqti).
   - Qurilmani ro'yxatga olish, bloklash (revoke), yo'qolgan deb belgilash (lost).
   - Muddatli Lease berish modali (ruxsatlar tanlovi, muddat soatlarda).
   - Tovar rezervi ajratish (Variant, Ombor, Miqdor) va qaytarish modallari.
   - Kredit limiti ajratish modali.
   - Yo'qolgan qurilmani rasmiy audit sababi bilan rekonsilyatsiya qilish oynasi.
   - Admin sahifasiga (`resources/views/pages/admin.blade.php`) to'liq ulandi.

10. **Avtomatlashtirilgan Test Natijalari (Verification Evidence):**
    - `php artisan test --filter=OfflineDevicesAndAllocationsTest`: **11 passed (43 assertions, duration 4.3s)**.
    - `php artisan test`: **110 passed out of 110 tests (735 assertions, duration 26.8s)**.
    - `vendor/bin/pint --test`: **PASSED** (0 style issues).
    - `npm run build`: **0 errors (built in 1.01s)**.
    - `flutter analyze`: **No issues found! (ran in 2.7s)**.

---

## 17. 12-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence)

1. **Versiyalangan Device Bootstrap (`POST /api/sync/bootstrap`):**
   - Faol qurilma uchun imzolangan muddatli lease token (`OfflineLeaseService`), ruxsatlar snapshot (`permissions`), biriktirilgan ombor (`assigned_warehouse`), faol tovar ajratmalari (`stock_allocations`), mijoz kredit ajratmalari (`credit_allocations`), serverning eng so'nggi kursori (`latest_cursor`) va rasmiy server vaqti (`server_time_iso`) berilishi tasdiqlandi.

2. **Commit-Order Cursor Pull & Change Feed (`GET /api/sync/pull`):**
   - `sync_change_log` jadvali orqali `BIGSERIAL` commit-order tartibida qat'iy kursor boshqaruvi (`last_cursor`). Kech commit past ID'ni yo'qotadigan max-ID polling muammosi yo'q; tranzaksiyaviy advisory lock orqali ketma-ketlik kafolatlangan.
   - Entitet versiyalari (`version`) va tombstones (`is_tombstone: true`).
   - Sezgir moliyaviy ma'lumotlar (`cost_price`, `average_cost`) huquqi bo'lmagan sotuvchi/kassir qurilmalaridan yashiriladi (faqat OWNER, ADMIN, FINANCE_VIEWER ko'ra oladi).
   - Sahifalash (`limit`, `next_cursor`, `has_more`) va so'nggi kursor xotirasi (`device_cursors`).

3. **Batch Push va Mustaqil Natijalar Kontrakti (`POST /api/sync/push`):**
   - Bir nechta amallar (`CREATE_CUSTOMER`, `CREATE_SALE`, `CUSTOMER_PAYMENT`) bitta paketda kelganda har biriga mustaqil tranzaksiya ochiladi (Per-item transaction isolation). Bitta amal xato qilsa (masalan, noma'lum mijoz), boshqa muvaffaqiyatli amallar commit qilinadi va to'xtab qolmaydi.
   - Natija kontraktida har bir amal bo'yicha: `status` (`APPLIED`, `RETRY_SUCCESS`, `CONFLICT`, `NEEDS_REVIEW`, `FAILED`), `server_document_id`, `server_document_number`, `entity_type`, `entity_id`, `error_code`, `message`.

4. **Offline Mijoz Bog'liqligi va Automerge Taqiqlanishi:**
   - Qurilmada yangi yaratilgan mijoz (`CREATE_CUSTOMER` payload'ida `client_uuid`) bazaga yangi mijoz sifatida yoziladi.
   - Xuddi shu paketdagi keyingi savdo (`customer_client_uuid`) ushbu mijozning server ID siga bog'lanadi.
   - Serverda ayni telefon raqamli boshqa mijoz mavjud bo'lsa ham avtomatik merge qilinmaydi (har bir taraf mustaqil qoladi).

5. **Idempotent Replay va Timeout Himoyasi:**
   - Tarmoq uzilishi yoki client timeout bo'lganda bir xil `operation_id` va bir xil kanonik payload (`PayloadFingerprint::compute`) takroriy yuborilsa, `RETRY_SUCCESS` qaytadi va avvalgi hujjat raqami/ID saqlanadi; tovar ombordan ikkinchi marta kamaymaydi.
   - Ayni shu `operation_id` boshqa ma'lumot bilan yuborilsa, `409 Conflict` (`PAYLOAD_MISMATCH`) qaytariladi.

6. **Stale Narx va Posting Tannarxi (WAC):**
   - Offline vaqtda kelishilgan sotuv narxi (`sale_price`) qat'iy saqlanadi (serverdagi joriy narx o'zgargan bo'lsa ham).
   - Savdoning yakuniy tannarxi (`total_cost`) va realizatsiya qilingan foydasi (`gross_profit`) serverga qabul qilingan paytdagi joriy WAC qoldiq asosida hisoblanadi.

7. **Uch Xil Vaqtning Saqlanishi:**
   - `device_created_at` (qurilmada savdo qilingan vaqt), `received_at` (serverga push kelgan vaqt), `posted_at` (server hisob kitobiga yozilgan vaqt) ajratib qayd etiladi.

8. **Kech Kelgan Smena va Rezerv Cheklovlarida Yozuv Tashlab Yuborilmasligi (`NEEDS_REVIEW`):**
   - Yopilgan kassa smenasiga kech kelgan naqd savdo, yoki tovar/kredit rezervidan oshib ketgan amallar aslo o'chirilmaydi yoki e'tiborsiz qoldirilmaydi.
   - Amal `sync_conflicts` jadvaliga `status: NEEDS_REVIEW`, aniq xato kodi (`LATE_CLOSED_SESSION`, `INSUFFICIENT_ALLOCATION`, `CUSTOMER_NOT_FOUND`) va asl xom payload (`raw_payload`) bilan to'liq saqlanadi.

9. **Admin Nizolarni Hal Qilish API:**
   - `GET /api/sync/conflicts` (barcha yoki holat bo'yicha nizolarni ko'rish).
   - `POST /api/sync/conflicts/{id}/resolve` (`APPROVED_OVERRIDE` — admin ruxsati bilan o'tkazish; `REJECT` — rad etish) izohlar va audit logi bilan.
   - `GET /api/sync/status/{operation_id}` (ixtiyoriy operatsiyaning hozirgi server holati: PROCESSED, CONFLICT, NEEDS_REVIEW, NOT_FOUND).

10. **Avtomatlashtirilgan Test Natijalari (Verification Evidence):**
    - `php artisan test tests/Feature/SyncProtocolAndConflictTest.php`: **12 passed out of 12 tests (106 assertions, duration 5.7s)**.
    - `php artisan test`: **122 passed out of 122 tests (841 assertions, duration 32.8s)**.
    - `vendor/bin/pint`: **0 issues** (barcha fayllar PSR-12/Laravel standartida).
    - `npm run build`: **0 errors (built in 652ms)**.
    - `flutter analyze`: **No issues found! (ran in 1.5s)**.

---

## 18. 13-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence)

1. **Installable PWA va Service Worker App Shell:**
   - `manifest.json`: Web App Manifest (`name: AquaOptom CRM - Offline POS`, `display: standalone`, `theme_color: #0284c7`, `start_url: /pos?source=pwa`, SVG va 192/512 PNG ikonkalari).
   - `sw.js`: Service Worker kesh strategiyasi — App Shell (`/pos`, `/offline.html`, manifest, icons, Vite assets) uchun Network-First kesh zaxirasi bilan; `/api/*` so‘rovlari uchun esa Network-Only strategiyasi va tarmoq yo‘qligida 503 `OFFLINE_DEVICE` JSON javobi.
   - Livewire online qoladi; offline POS to‘liq alohida mustaqil Alpine.js / Pure JS qatlamida (`resources/views/pages/pos-offline.blade.php`, `resources/js/offline/aqua-db.js`, `resources/js/offline/aqua-pos.js`) qurildi. Server-render cache offline POS deb aldanmaydi.

2. **IndexedDB Lokal Baza Sxemasi (`AquaOptomDB`, versiya 1):**
   - 9 ta obyektlar do‘koni (Object Stores) yaratildi:
     1. `device_lease` (`keyPath: "id"`) — imzolangan ruxsatnoma va token;
     2. `stock_allocations` (`keyPath: "variant_id"`) — tovar kvotalari (`allocated_quantity`, `consumed_quantity`, `remaining_quantity`);
     3. `credit_allocations` (`keyPath: "customer_id"`) — mijoz nasiya limitlari (`remaining_credit_limit`);
     4. `catalog` (`keyPath: "variant_id"`) — tovarlar, hajmlar, narxlar (tannarx yashirilgan);
     5. `customers` (`keyPath: "id"`) — mijozlar ro‘yxati (server id yoki client uuid bilan);
     6. `cart_draft` (`keyPath: "id"`) — lokal savat qoralamasi va barqaror `operation_id`;
     7. `sales` (`keyPath: "operation_id"`) — lokal tasdiqlangan savdo cheklari;
     8. `sync_outbox` (`keyPath: "operation_id"`, index: `status`) — serverga jo‘natilishi kerak bo‘lgan navbat;
     9. `meta` (`keyPath: "key"`) — oxirgi kursor, server vaqti va sozlamalar.

3. **Atomik Lokal Tranzaksiya (`executeSaleTransaction`):**
   - Yagona IndexedDB `readwrite` tranzaksiyasi ichida 6 ta do‘kon (`stock_allocations`, `credit_allocations`, `sales`, `sync_outbox`, `cart_draft`, `meta`):
     - Tovar kvotasi yetarliligi tekshiriladi va `remaining_quantity` kamaytiriladi (`consumed_quantity` oshiriladi);
     - Agar savdoda qarz (`debt_amount > 0`) bo‘lsa, mijozning `credit_allocations` limiti tekshiriladi va kamaytiriladi;
     - Savdo hujjati `sales` do‘koniga yoziladi (`status: LOCAL_COMMITTED`);
     - Serverga jo‘natish uchun `sync_outbox` ga `CREATE_SALE` yozuvi qo‘shiladi (`status: PENDING`);
     - Joriy savat qoralamasi (`cart_draft`) tozalab tashlanadi.
   - Bitta amal xato qilsa (masalan, kvota yetmasa) tranzaksiya to‘liq bekor bo‘ladi va outboxga hech narsa yozilmaydi.

4. **Kvota va Kredit Limitidan Oshmaslik Himoyasi:**
   - Qurilmaga berilgan qoldiqdan ortiq tovar sotishga urinilganda IndexedDB tranzaksiyasi `INSUFFICIENT_STOCK_ALLOCATION` xatosi bilan to‘xtatiladi.
   - Belgilangan kredit limitidan ortiq nasiyaga sotishga urinilganda tranzaksiya `INSUFFICIENT_CREDIT_ALLOCATION` xatosi bilan to‘xtatiladi.

5. **Offline Yangi Mijoz va UUID Bog‘liqligi:**
   - Internetsiz vaqtda yangi mijoz yaratilganda unga barqaror `crypto.randomUUID()` beriladi va `customers` do‘koniga qo‘shiladi (`is_offline: true`).
   - `sync_outbox` ga `CREATE_CUSTOMER` amali qo‘shiladi.
   - Shu mijozga qilingan savdo ushbu `client_uuid` orqali bog‘lanadi (serverga yetganda Prompt 12 da yaratilgan protokol bo‘yicha avval mijoz, so‘ng savdo biriktiriladi).

6. **Butun So‘m va Taxminiy Tannarx:**
   - Barcha pul hisob-kitoblari butun so‘mda (`integer`).
   - Kassir interfeysida sezgir tannarx ko‘rinmaydi, kvitansiyada va ma’lumotlarda tannarx `~serverda aniqlanadi` deb belgilanadi.

7. **Storage Quota, PIN Qulflash va Favqulodda Eksport:**
   - `navigator.storage.estimate()` orqali disk to‘lishini kuzatish va xavf tug‘ilganda ogohlantirish.
   - Kassir vaqtincha ketganda ekran 4 xonali PIN kod bilan qulflanadi (`pinLocked: true`).
   - Brauzer xotirasi buzilganda yoki zudlik bilan serverga o‘tkazish kerak bo‘lganda barcha navbatdagi amallarni JSON fayl sifatida yuklab olish (`exportPendingData()` -> `aquaoptom_offline_backup_*.json`).

8. **Avtomatlashtirilgan Test Natijalari (Verification Evidence):**
   - Node.js Fake-IndexedDB Unit Testlari (`backend/tests/pwa-indexeddb-test.cjs`):
     - `AquaOptomDB initialization & schema creation`: **PASSED**
     - `Cart draft save, retrieve & persistent operation_id`: **PASSED**
     - `Atomic sale transaction (stock quota, outbox, cart cleared)`: **PASSED**
     - `Stock quota protection (blocks sale exceeding allocated quantity)`: **PASSED**
     - `Credit limit protection (blocks debt exceeding remaining credit)`: **PASSED**
     - `Offline new customer creation with client UUID & outbox`: **PASSED**
     - `Emergency disaster recovery export of pending outbox`: **PASSED**
     - **7/7 testlar 100% muvaffaqiyatli o‘tdi.**
   - Feature Testlari (`backend/tests/Feature/OfflinePwaAndPosTest.php`): **7 passed (52 assertions, duration 2.1s)**.
   - To‘liq Backend Testlari: **129 passed out of 129 tests (893 assertions, duration 26.9s)**.
   - `vendor/bin/pint --format agent`: **PASSED** (0 style issues).
   - `npm run build`: **0 errors (built in 517ms)**.
   - `flutter analyze`: **No issues found! (ran in 2.3s)**.

---

## 19. 14-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence)

1. **Server Health va Push Reversal/Void Protokoli:**
   - **Endpointlar:** `GET /api/health` (ochiq ping), `GET /api/sync/health` (Sanctum/qurilma bilan server vaqti va holati).
   - **`SyncPushService::handleVoidSale`:**
     - `VOID_SALE` yoki `CANCEL_SALE` amali kelganda `original_operation_id` bo'yicha sotuv topiladi;
     - Agar savdo allaqachon serverda bo'lsa (`COMPLETED`), ombor harakati orqali tovarlar to'liq qaytariladi (`InventoryLedgerService::recordMovement` turi `SALE_RETURN`);
     - Mijozning qarzi qaytariladi (`customer_ledger` da qarama-qarshi yozuv, `current_debt` kamaytiriladi);
     - Agar savdoda naqd pul olingan bo'lsa, kassadan pul chiqariladi (`CashAccount::balance` kamaytiriladi va harakat yoziladi);
     - Sotuv holati `CANCELLED` qilinadi, `AuditLog` ga `SALE_CANCEL` kiritiladi va `operation_results` ga `APPLIED` qayd etiladi;
     - Agar savdo hali serverga kelmasdan bekor qilingan bo'lsa, `OperationResult` ga yoziladi va serverga keyinchalik kelishi kutilgan savdoni bloklaydi;
     - Takroriy jo'natilganda (idempotent replay) `RETRY_SUCCESS` qaytadi va ombor/kassa ikkinchi marta buzilmaydi.

2. **Lokal Offline Mexanizmlar (`aqua-db.js`, `aqua-sync.js`, `aqua-pos.js`):**
   - **Multi-Tab Mutex Lock (`acquireSyncLock` / `releaseSyncLock`):**
     - Bir necha ochiq tab yoki worker parallel push qilib poyga holati (race condition) keltirib chiqarmasligi uchun `sync_lock` store'da qulf olinadi;
     - Agar qulf egasi qotib qolsa yoki tab kutilmaganda yopilsa, 30 soniyadan oshgan lock avtomatik ravishda stale deb topilib boshqa tabga beriladi (`recovery`).
   - **Pending Batch Push va Xavfsiz Retention (`applyPushResults`):**
     - ACK kelganda chek yoki operatsiya IndexedDB `sync_outbox` dan **o'chirib tashlanmaydi**;
     - Statusi `APPLIED` ga o'zgartiriladi va `applied_at` belgilanadi;
     - Bu ofatdan tiklash (disaster recovery) va mahalliy audit tarixi uchun saqlanadi.
   - **Incremental Cursor Pull (`applyPulledChanges`):**
     - Server kursoridan olingan o'zgarishlar (`CATALOG_UPDATED`, `CUSTOMER_UPDATED`, `ALLOCATION_CHANGED`) lokal IndexedDB bazasiga atomik tarzda qo'llaniladi va `last_cursor` yangilanadi.
   - **Remaining Pending Overlay (`getPendingOverlay`):**
     - Hali serverga jo'natilmagan (pending) operatsiyalar tahlil qilinib, haqiqiy ko'rsatiladigan qoldiq va mijoz qarzi overlay sifatida to'g'ri hisoblanadi.
   - **Offline Void Protokoli (`voidOfflineSale`):**
     - Foydalanuvchi internetsiz savdoni bekor qilganda, u navbatdan o'chirilmaydi;
     - Lokal `stock_allocations` va `credit_allocations` ga tovar va limit qaytariladi;
     - `sales` jadvalida status `CANCELLED` bo'ladi;
     - Outbox ga `VOID_SALE` turi bilan yangi operatsiya navbatga qo'yiladi (`original_operation_id` bilan).

3. **Foydalanuvchi Interfeysi Yangilanishlari (`pos-offline.blade.php`, `aqua-pos.js`):**
   - **Sync Status & Last Sync Time:** Yuqori paneldagi holat indikatori (Online/Offline, aylanish animatsiyasi, oxirgi muvaffaqiyatli sinxronizatsiya vaqti).
   - **Outbox Navbati va Modal Oyna:** Yuborilmagan cheklar soni ko'rsatilgan tugma, bosilganda barcha navbatdagi amallar, ularning statusi (`PENDING`, `APPLIED`, `NEEDS_REVIEW`) va server izohi ko'rinadi.
   - **Offline Bekor Qilish Modali:** Har bir lokal savdoni sababini ko'rsatgan holda bekor qilish oynasi.
   - **Muddatli Ruxsatnoma (Lease) Ogohlantirish Paneli:** Agar lease muddati tugashiga 1 soatdan kam qolgan bo'lsa yoki tugagan bo'lsa, banner orqali serverga ulanish zarurligi eslatiladi.
   - **Avtomatik Triggerlar:** Tarmoq paydo bo'lganda (`online`), ilova ochilganda (`visibilitychange`), oyna fokuslanganda (`focus`), davriy 30 soniyali taymer va Service Worker background sync orqali sinxronizatsiya chaqiriladi.

4. **Avtomatlashtirilgan Test Natijalari (Verification Evidence):**
   - **Node.js Sync Protocol Unit Testlari (`backend/tests/pwa-sync-protocol-test.cjs`):**
     - `Test 1: Multi-tab mutex lock acquisition and stale lock recovery`: **PASS**
     - `Test 2: Pending push and atomic ACK processing with safe retention`: **PASS**
     - `Test 3: Timeout handling and idempotent replay without duplicates`: **PASS**
     - `Test 4: Incremental cursor pull and delta feed application`: **PASS**
     - `Test 5: Remaining pending overlay calculation`: **PASS**
     - `Test 6: Offline void/cancellation: original retained, correction queued`: **PASS**
     - `Test 7: NEEDS_REVIEW status handling in outbox`: **PASS**
     - **Barcha 7/7 ta sinov 100% muvaffaqiyatli o'tdi.**
   - **Node.js Prompt 13 Regressiya Testlari (`backend/tests/pwa-indexeddb-test.cjs`):**
     - **7/7 testlar 100% muvaffaqiyatli o'tdi.**
   - **Feature Testlari (`backend/tests/Feature/PwaAutoSyncAndDisruptionTest.php`):**
     - `test_server_health_endpoints_accessible`: **PASS**
     - `test_server_void_sale_reverses_inventory_customer_debt_and_cash`: **PASS**
     - `test_server_void_sale_idempotent_replay`: **PASS**
     - `test_void_sale_before_posted_records_safely`: **PASS**
     - `test_node_js_sync_protocol_tests_pass`: **PASS**
     - `test_push_operation_with_needs_review_retains_status`: **PASS**
     - **6/6 passed (28 assertions, duration 3.3s)**.
   - **To'liq Backend Testlari:** **135 passed out of 135 tests (921 assertions, duration 29.2s)**.
   - **Laravel Pint:** `vendor/bin/pint --test`: **PASSED** (0 style issues).
   - **Vite Build:** `npm run build`: **0 errors (built in 639ms)**.
   - **Flutter Analyze:** `flutter analyze`: **No issues found! (ran in 1.6s)**.

---

## 20. 15-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence)

1. **Ombor Qoldiqlari va Server Qidiruvi (`InventoryStockService`):**
   - **Paginationdan oldin server qidiruvi:** Nom, kod, SKU, shtrix-kod, va hajmlar bo'yicha to'liq PostgreSQL qidiruvi;
   - **Filtrlar majmuasi:** Hajmlar (multi-select), min/max qoldiq diapazoni, threshold filtri (`quantity <= minimum_stock`), nol qoldiq (`zero_only` / `non_zero`), sotuv narxi mavjudligi (`has_price` / `no_price`), arxiv holati (`active` / `archived` / `all`), sekin sotiladigan tovarlar (`slow_moving` — so'nggi 30 kunda sotuv bo'lmagan);
   - **Narrow filterdagi yig'indi butun bazaning filtrlangan natijasi:** `getAggregatedTotals` orqali butun filtrlangan query bo'yicha umumiy agregatlar (faqat bitta sahifa emas): jami turlar, jami fizik dona, erkin dona, ajratilgan dona, tannarx qiymati, tizim sotuv qiymati, kam qoldiq soni, narxsizlar soni;
   - **Tannarx va qiymat rol bo'yicha:** `view_cost_price` ruxsati bor xodim (masalan egasi) tannarx va jami ombor qiymatini ko'radi; ruxsati yo'q xodim (kassir/omborchi) uchun bu qiymatlar `null` bo'lib yashiriladi;
   - **Qoldiqlar tabaqalanishi:** Fizik qoldiq, sotish uchun erkin miqdor, qurilmalarga ajratilgan miqdor va eskirgan offline snapshot (`stale_allocation` — lease muddati o'tgan yoki 24 soatdan beri aloqaga chiqmagan qurilma) aniq farqlanadi.

2. **Variant Kartasi va Manba Hujjatga Bog'langan Harakat Tarixi:**
   - **Tafsilot:** Variant kodi, SKU, shtrix-kod, jismoniy/erkin/ajratilgan qoldiqlar, qurilmalar bo'yicha zaxira ajratmalari jadvali (qurilma kodi, ajratilgan, sarflangan, qaytarilgan, qolgan, sync vaqti, lease holati);
   - **Harakatlar daftari (`inventory_movements`):**
     - Harakat vaqti sekund aniqligida Asia/Tashkent formatida (`Y-m-d H:i:s`);
     - Harakat turi: Kirim, Sotuv, Sotuv qaytarildi, Ta'minotchiga qaytarildi, Brak, Boshlang'ich qoldiq, Tuzatish;
     - O'zgarish donasi (`+` / `-`), o'zgarishdan keyingi qoldiq va qiymat;
     - Manba hujjatga to'g'ridan-to'g'ri bog'liqlik: Kirim nakladnoy raqami va ta'minotchi nomi, Sotuv cheki raqami va mijoz nomi, mas'ul xodim.

3. **Interaktiv Rentabellik Kalkulyatori (`InventoryCalculatorService`):**
   - **Multi-select:** Mahsulotlar va litr/hajm checkboxlari, "Barchasini tanlash" va "Tozalash";
   - **Ko'rsatkichlar:** Mavjud jami dona (erkin va ajratilgan bilan), Jami tannarx qiymati (WAC asosida, faqat `view_cost_price` bilan), Tizim sotuv qiymati, Kutilayotgan yalpi foyda (`expected_gross_profit`), Kutilayotgan marja (%);
   - **Nomlanish qoidasi:** Potensial foyda hech qachon haqiqiy savdo foydasi yoki cash/kassa deb nomlanmaydi; qat'iy "Kutilayotgan yalpi foyda" deb yuritiladi;
   - **Narxsiz variantlar:** Narxi yo'q variantlar nol narxga tenglashtirilmaydi, sun'iy salbiy foyda keltirib chiqarmaydi; ular alohida ajratilib ko'rsatiladi va ogohlantirish beriladi;
   - **Taxminiy narx simulyatsiyasi:** Foydalanuvchi variantlar uchun vaqtinchalik narx kiritib real-vaqtda kutilayotgan foydani simulyatsiya qilishi mumkin; bu katalogdagi narxni o'zgartirmaydi;
   - **Katalogga saqlash ruxsati:** Simulyatsiya narxlarini tizim narxi sifatida saqlash faqat `manage_prices` ruxsatiga ega xodimga ruxsat etiladi; saqlanganda `PriceHistory` jurnali yuritiladi va variant versiyasi oshiriladi.

4. **Kam Qoldiq (Threshold) Hodisasi:**
   - Qoldiq minimal chegaradan past bo'lganda `App\Events\LowStockDetected` hodisasi qayd etiladi (variant, joriy qoldiq, chegara, ombor).

5. **Foydalanuvchi Interfeysi (`pages/inventory.blade.php`, `StockManager`):**
   - Livewire 3 `StockManager` komponenti orqali reaktiv 3 ta tab: "Ombor Qoldiqlari", "Interaktiv Kalkulyator", "Tovar Kirimi (QuickInward)";
   - `/calculator` va `/inward` yo'naltirishlari to'g'ridan-to'g'ri tegishli tabga olib boradi.

6. **Avtomatlashtirilgan Test Natijalari (Verification Evidence):**
   - **PHPUnit Feature Testlari (`tests/Feature/InventoryStockAndCalculatorTest.php`):**
     - `test_stock_list_server_search_before_pagination`: **PASS**
     - `test_stock_list_filters_threshold_zero_unpriced_archived_slow_moving`: **PASS**
     - `test_narrow_filter_aggregates_across_whole_filtered_database_not_just_current_page`: **PASS**
     - `test_role_based_cost_price_and_value_visibility`: **PASS**
     - `test_calculator_fanta_all_vs_one_liter_vs_two_volumes_from_real_rows`: **PASS** (Fanta 0.5L vs 1.0L vs barchasi aniq rowsdan)
     - `test_unpriced_variants_are_isolated_and_not_forced_to_zero_price`: **PASS**
     - `test_simulation_prices_recalculate_without_modifying_catalog_and_saving_requires_permission`: **PASS**
     - `test_owner_can_save_simulation_prices_to_catalog`: **PASS**
     - `test_physical_free_allocated_and_stale_allocation_separation`: **PASS**
     - `test_low_stock_detected_event_dispatched`: **PASS**
     - `test_variant_movements_history_with_source_documents_and_tashkent_time`: **PASS**
     - `test_livewire_stock_manager_renders_and_switches_tabs`: **PASS**
     - `test_livewire_stock_manager_calculator_and_modal_actions`: **PASS**
     - **13/13 testlar 100% muvaffaqiyatli o'tdi (86 assertions, duration 4.8s).**
   - **Regressiya va Asosiy Sinovlar:**
     - `BeverageCrmCoreTest.php`: **8/8 testlar 100% PASS** (66 assertions).
     - `AuthAndAccessControlTest.php`: **11/11 testlar 100% PASS** (103 assertions).
     - Node.js Sync Protocol: **7/7 testlar 100% PASS**.
     - Node.js IndexedDB: **7/7 testlar 100% PASS**.
   - **To'liq Backend Testlari:** **148 passed out of 148 tests (1007 assertions, duration 35.4s)**.
   - **Laravel Pint:** `vendor/bin/pint --test`: **PASSED** (0 style issues).
   - **Vite Build:** `npm run build`: **0 errors (built in 785ms)**.
   - **Flutter Analyze:** `flutter analyze`: **No issues found! (ran in 2.4s)**.

---

## 21. Ochiq Qolgan Biznes Qarorlari va Cheklovlar

1. **Eski Demo Testlarni Bosqichma-bosqich Almashtirish Rejasi:**
   - `BeverageCrmCoreTest.php` to‘liq yangi kirim va sotuv xizmatlariga moslashtirildi (8/8 passed).
   - Flutter `test/widget_test.dart` dagi default counter testi Prompt 20 da haqiqiy CRM kirish va savdo ekranlari widget testlariga almashtiriladi.
2. **Offline qoldiq va kredit ajratish siyosati:** Prompt 11 doirasida to'liq amalga oshirildi.
3. **Server Sync API va Nizolar protokoli:** Prompt 12 doirasida to'liq amalga oshirildi.
4. **PWA Offline Baza va Savdo:** Prompt 13 doirasida to'liq amalga oshirildi.
5. **PWA Avtomatik Sync va Uzilish Sinovlari:** Prompt 14 doirasida to'liq amalga oshirildi.
6. **Ombor Qoldiqlari va Interaktiv Kalkulyator:** Prompt 15 doirasida to'liq amalga oshirildi.
7. **Qaytarish, brak va inventarizatsiya auditi:** Prompt 16 doirasida to'liq amalga oshirildi.
8. **Flutter ilovasining birinchi relizdagi roli:** PWA birinchi relizda barcha qurilmalarda ishga tushadi; Flutter Android parallel ravishda ishlab chiqilmoqda.

---

## 22. 16-Bosqich Tekshiruv Buyruqlari va Natijalari (Verification Evidence — Prompt 16)

Prompt 16 bo'yicha sotuv qaytarishlari (Sale Returns), ta'minotchiga qaytarishlar (Supplier Returns), brak va yaroqsiz tovar chiqimlari (Damage Records) hamda inventarizatsiya va sanash muvofiqlashtirishlari (Inventory Audits & Adjustments) to'liq ishlab chiqildi va qabul mezonlari asosida sinovdan o'tkazildi.

### 1. Bajarilgan Ishlar va Arxitektura Invariantlari:
1. **Sotuv Qaytarish va Refund (`SaleReturnService`):**
   - **Original savdoga bog'liqlik:** Qisman va to'liq qaytarishlar `sale_id` va `sale_item_id` ga bog'lanadi;
   - **Cheklov:** Sotilgan sondan ko'p qaytarish taqiqlanadi (`CannotReturnMoreThanSoldException`); takroriy qaytarishlar ilgari qaytarilgan miqdorni inobatga oladi;
   - **Asl narx va tannarx:** Qaytarishda asl sotuv narxi (`sale_items.unit_price`) va asl xarid tannarxi snapshot'i (`sale_items.purchase_cost_snapshot`) ishlatiladi;
   - **Kasrli yaxlitlashni yopish:** Qisman qaytarishlarda kasrli yaxlitlash qoldiqlari oxirgi donalar qaytganda 100% yopiladi;
   - **Brak tovarlar karantini:** Qaytarilgan mahsulot yaroqsiz/shikastlangan bo'lsa (`is_damaged == true`), u sotiladigan ombor qoldig'iga (`inventory_balances`) qo'shilmaydi;
   - **Mijoz qarzi va kassa anti-double-decrement:** Naqd qaytarish (`refund_amount`) va qarzni kamaytirish (`debt_reduction`) o'zaro to'g'ri balanslanadi (`debt_reduction = total_amount - refund_amount`). Agar kassa hisobida naqd pul yetarli bo'lmasa, tranzaksiya to'liq bekor qilinadi (`InsufficientCashException`).

2. **Ta'minotchiga Qaytarish (`SupplierReturnService`):**
   - **Asl kirimga bog'liqlik:** `purchase_id` va `purchase_item_id` ga bog'lanadi; kirim miqdoridan ortiq qaytarish taqiqlanadi (`CannotReturnMoreThanPurchasedException`);
   - **Tijoriy kredit:** Ta'minotchi oldidagi qarzimiz asl xarid narxida kamaytiriladi (`supplier_ledger` DEBIT);
   - **WAC chiqimi va farq:** Ombordan tovar joriy WAC qiymatida chiqim qilinadi; xarid narxi va WAC o'rtasidagi farq `cost_discrepancy` sifatida auditlanadi;
   - **Qurilma rezervlari himoyasi:** Qurilmalarga ajratilgan faol rezervlar hisobiga tovar qaytarish taqiqlanadi (`ReservedStockProtectionException`).

3. **Brak va Yaroqsiz Tovar Chiqimi (`DamageDisposalService`):**
   - **Tannarx yo'qotishi:** Brak chiqimi joriy WAC tannarxi bo'yicha ombor daftari orqali hisobdan chiqariladi (`movement_type = 'DAMAGE'`);
   - **Kassa daxlsizligi:** Brak chiqimi hech qachon kassa operatsion xarajati yoki kassa harakati hisoblanmaydi (`cash_movements` ga umuman tegilmaydi);
   - **Rezerv nazorati:** Faol zaxira ajratmalariga ega tovarlarni erkin qoldiqdan ortiq brakka chiqarish taqiqlanadi.

4. **Inventarizatsiya va Muvofiqlashtirish (`InventoryAuditService`):**
   - **Bosqichli sikl:** `PREPARE` (tizimdagi qoldiqlar va WAC snapshot qilinadi, qurilmalarga freeze so'rovi beriladi) → `FREEZE ACK` (qurilmalar muzlatishni tasdiqlaydi) → `COUNTING` (amaldagi qoldiqlar sanaladi) → `APPLY` (muvofiqlashtiriladi);
   - **Uzilgan qurilmalar freeze nazorati:** Agar faol rezervga ega offline qurilma freeze tasdig'ini bermagan bo'lsa (`freeze_ack_at == null`), auditni yakuniy tasdiqlash to'xtatiladi (`DeviceFreezePendingException`), faqat majburiy (force) tasdiq orqali o'tkazish mumkin;
   - **Sababli tuzatish:** Ortiqcha tovar `ADJUSTMENT_IN`, kamomad esa `ADJUSTMENT_OUT` harakati bilan daftarga yoziladi;
   - **O'zgarmaslik (Immutability):** Tasdiqlangan (posted/completed) audit va qaytarish hujjatlarini o'chirib yuborish qat'iy taqiqlanadi (`DocumentImmutableException`).

5. **Foydalanuvchi Interfeysi (`pages/inventory.blade.php`, `StockManager`):**
   - Livewire `StockManager` komponentiga "Qaytarishlar" (`returns`), "Brak & Yaroqsiz" (`damages`), "Inventarizatsiya" (`audits`) tablari va to'liq interaktiv modallar qo'shildi.

---

### 2. O'zgargan va Yaratilgan Fayllar:
- **Migratsiya:**
  - `backend/database/migrations/2026_10_04_000012_create_returns_damages_and_inventory_audits_tables.php` (8 ta yangi jadval, PostgreSQL sequencelari, device freeze ustunlari).
- **Modellar va Bog'liqliklar:**
  - `backend/app/Models/SaleReturn.php` & `SaleReturnItem.php`
  - `backend/app/Models/PurchaseReturn.php` & `PurchaseReturnItem.php`
  - `backend/app/Models/DamageRecord.php` & `DamageItem.php`
  - `backend/app/Models/InventoryAudit.php` & `InventoryAuditItem.php`
  - `backend/app/Models/Device.php` (freeze timestamps)
  - `backend/app/Models/Sale.php`, `SaleItem.php`, `Purchase.php`, `PurchaseItem.php` (returns relationships)
- **Hujjat Raqami Generatorlari:**
  - `backend/app/Services/Operations/DocumentNumberGenerator.php` (`nextSaleReturnNumber`, `nextPurchaseReturnNumber`, `nextDamageNumber`, `nextAuditNumber`).
- **Biznes Servislari:**
  - `backend/app/Services/Inventory/SaleReturnService.php`
  - `backend/app/Services/Inventory/SupplierReturnService.php`
  - `backend/app/Services/Inventory/DamageDisposalService.php`
  - `backend/app/Services/Inventory/InventoryAuditService.php`
  - `backend/app/Services/Ledger/InventoryAllocationService.php` (epoch fallback)
- **Maxsus Istisnolar (Exceptions):**
  - `backend/app/Services/Ledger/Exceptions/CannotReturnMoreThanSoldException.php`
  - `backend/app/Services/Ledger/Exceptions/CannotReturnMoreThanPurchasedException.php`
  - `backend/app/Services/Ledger/Exceptions/DeviceFreezePendingException.php`
  - `backend/app/Services/Ledger/Exceptions/DocumentImmutableException.php`
- **UI & Routing:**
  - `backend/app/Livewire/Inventory/StockManager.php`
  - `backend/resources/views/livewire/inventory/stock-manager.blade.php`
  - `backend/routes/web.php`
- **Testlar:**
  - `backend/tests/Feature/ReturnsDamagesAndAdjustmentsTest.php` (16 ta keng qamrovli test).

---

### 3. Tekshiruv Buyruqlari va Test Natijalari (Verification Evidence):
1. **Prompt 16 Feature Testlari:**
   - Buyruq: `php artisan test tests/Feature/ReturnsDamagesAndAdjustmentsTest.php`
   - Natija: **16/16 testlar 100% PASS** (57 assertions, duration 12.4s).
     - `test_cannot_return_more_than_sold_quantity`: PASS
     - `test_sale_return_restores_inventory_and_reduces_customer_debt_correctly`: PASS
     - `test_sale_return_with_cash_refund_does_not_double_deduct_debt_and_cash`: PASS
     - `test_sale_return_fails_when_drawer_has_insufficient_cash`: PASS
     - `test_damaged_returned_items_are_quarantined_and_not_added_to_sellable_stock`: PASS
     - `test_partial_returns_correctly_close_out_fractional_cost_rounding_on_final_unit`: PASS
     - `test_sale_return_idempotency_via_operation_id`: PASS
     - `test_cannot_return_more_than_purchased_quantity_to_supplier`: PASS
     - `test_supplier_return_reduces_payable_and_records_wac_outflow_with_discrepancy`: PASS
     - `test_supplier_return_cannot_exceed_free_stock_when_device_has_active_allocations`: PASS
     - `test_damage_disposal_records_wac_inventory_loss_without_touching_cash`: PASS
     - `test_damage_disposal_cannot_exceed_free_stock_under_device_allocation`: PASS
     - `test_inventory_audit_lifecycle_and_device_freeze_guard`: PASS
     - `test_inventory_audit_applies_surplus_and_deficit_adjustments`: PASS
     - `test_posted_returns_damages_and_audits_cannot_be_deleted`: PASS
     - `test_livewire_stock_manager_returns_damages_and_audit_ui`: PASS
2. **To'liq Backend Test Suite:**
   - Buyruq: `php artisan test`
   - Natija: **164/164 testlar 100% PASS** (1064 assertions, duration 53.8s, 0 failures, 0 errors).
3. **PWA & Offline Sinxronizatsiya Sinovlari:**
   - `node backend/tests/pwa-sync-protocol-test.cjs`: **7/7 testlar 100% PASS**.
   - `node backend/tests/pwa-indexeddb-test.cjs`: **7/7 testlar 100% PASS**.
4. **Kod Sifat Tekshiruvi:**
   - `vendor/bin/pint --test`: **PASSED** (0 style issues).
   - `flutter analyze`: **No issues found!** (ran in 2.6s).
   - `npm run build`: **Vite assets built in 930ms** (0 errors).

---
*16-bosqich muvaffaqiyatli yakunlandi. Keyingi bosqich: Prompt 17.*

---

## 23. Prompt 17 — Savdo tarixi, hisobotlar va eksportlar (Bajarildi)

### 1. Arxitektura Qarorlari va Bajarilgan Ishlar:
1. **Sana va Vaqt Chegaralari (`ReportPeriod`):**
   - Barcha ma'lumotlar bazasi so'rovlari UTC vaqtida bajariladi, biroq kun, hafta, oy va yil chegaralari `Asia/Tashkent` (+05:00) mahalliy vaqt mintaqasi bo'yicha to'liq `00:00:00` dan `23:59:59.999999` gacha hisoblanadi.
   - Qo'llab-quvvatlanadigan presetlar: `today`, `yesterday`, `this_week`, `this_month`, `last_month`, `this_year`, `month` (masalan, `2026-09`), `custom` (`from_date` va `to_date`).
2. **Savdo Tarixi va To'lovlar Taqsimoti Qoidasi:**
   - Dastlabki savdo cheki snapshotidagi to'langan va nasiya miqdorlari (`paid_amount`, `debt_amount`) keyingi umumiy mijoz to'lovlari bilan aslo aralashtirilmaydi.
   - Soxta invoice-level allocation to'qilmaydi.
   - **Qabul mezonining asosiy talabi:** Sentabrda rasmiylashtirilgan 100 000 so'mlik nasiya savdo va oktabrda kelib to'langan 100 000 so'mlik qarz to'lovi ikki alohida davrda to'liq ajratildi. Naqd pul va qarz hech qachon takror (double-count) hisoblanmaydi.
3. **Boshqaruv Hisobotlarining Umumiy So'rov Kontrakti (`ReportQueryService`):**
   - **Savdo Tahlili (Sales Summary):** Brutto/sof tushum, POS naqd/karta/bank, cheklar soni, o'rtacha chek, tovarlar va variantlar kesimidagi sotuv, kunlik trend dinamikasi.
   - **Yalpi va Operatsion Natija (Profit & Loss):** Sof savdo, WAC tannarx (COGS — yaroqli qaytarishlar chegirilgan), Yalpi foyda va marja %, Operatsion xarajatlar (`cash_movements` da `type = 'EXPENSE'`), Brak yo'qotishi (`DamageRecord` posted loss), Sof operatsion foyda va rentabellik %. **Egasi pul yechishi (`OWNER_WITHDRAWAL` / `OWNER_DRAW`) operatsion xarajat hisoblanmaydi!** `view_cost_price` ruxsati bo'lmagan xodim uchun tannarx va foyda to'liq yashiriladi (masking).
   - **Kirim va Ta'minotchilar (Purchases):** Kirim qilingan partiyalar, to'langan va qarz, ta'minotchiga qaytarishlar krediti, sof kirim.
   - **Tarixiy Ombor Qoldig'i (Stock & Valuation):** Qat'iy buxgalteriya qoidasi: Davr boshi va yakuni qoldiqlari hozirgi jonli snapshotdan emas, balki rasmiy ombor harakatlari daftari (`inventory_movements`) bo'yicha retrospektiv hisoblanadi.
   - **Taraf Ko'chirmalari (Customer & Supplier Statements):** Davr boshi signed balansi, debetlar, kreditlar, davr yakuni balansi hamda xronologik harakatlar daftari.
   - **Kassa va Smenalar (Cash & Sessions):** Hisoblar bo'yicha boshlang'ich kassa, kirim, chiqim, yakuniy kassa hamda kassa smenalari farqlari (`discrepancy`).
   - **Xodimlar (Staff Performance):** Savdo soni, jami tushum, naqd va nasiya ulushi.
   - **Qurilmalar va Sinxronizatsiya (Device Sync):** Qurilma turi, statusi, ajratilgan va sarflangan tovar kvotasi, oxirgi aloqa vaqti.
4. **Paginatsiya va Eksport Totals 100% Bir Xilligi:**
   - 35 ta savdo yoki ko'p sahifali ro'yxat bo'lganda, sahifa 1 dagi paginator (20 ta element) va tepasidagi KPI summary kartalari butun 35 ta qatorning umumiy summasini jamlaydi.
   - Eksport qilinganda (CSV/PDF) barcha 35 ta qator to'liq eksport qilinadi, ko'p sahifali data yo'qolmaydi.
5. **Eksport Xavfsizligi va Formula In'eksiyasidan Himoyalash (CSV/Formula Injection Guard):**
   - OWASP xavfsizlik standartiga muvofiq, jadval kataklarida `=`, `+`, `-`, `@`, `\t`, `\r` bilan boshlangan har qanday matn spreadsheet tomonidan formula sifatida bajarilib ketmasligi uchun oldiga bitta `'` (single quote) qo'yilib zararsizlantirildi (`escapeFormula`).
   - Barcha eksport fayllari `storage/app/exports` yopiq papkasida saqlanadi va bevosita public URL orqali ochilmaydi.
   - Yuklab olish faqat autentifikatsiyalangan va `export_reports` ruxsatiga ega xodimlar uchun `/exports/download/{uuid}` himoyalangan marshrut orqali amalga oshiriladi (begona yoki ruxsatsiz foydalanuvchilar uchun 403 Forbidden).
   - CSV eksporti UTF-8 BOM (`\xEF\xBB\xBF`) bilan yoziladi va metadata sarlavhasiga ega.
   - PDF eksporti `barryvdh/laravel-dompdf` orqali formatlangan chiroyli hisobot ko'rinishida generatsiya qilinadi.
6. **Foydalanuvchi Interfeysi (Livewire):**
   - `SalesHistory` Livewire komponenti (`pages/sales-history.blade.php`).
   - `ReportDashboard` 8 ta interaktiv tabga ega Livewire komponenti (`pages/reports.blade.php`).

---

### 2. Yaratilgan va O‘zgartirilgan Fayllar:
- **Ma'lumotlar Bazasi va Ruxsatlar:**
  - `backend/database/migrations/2026_10_04_000013_create_report_exports_table.php`
  - `backend/database/seeders/RoleAndPermissionSeeder.php` (`export_reports` ruxsati OWNER va ADMIN rollariga biriktirildi)
- **Modellar va Xizmatlar:**
  - `backend/app/Models/ReportExport.php`
  - `backend/app/Models/Sale.php` (`created_at` fillable ro'yxatiga qo'shildi)
  - `backend/app/Services/Reports/ReportPeriod.php`
  - `backend/app/Services/Reports/ReportQueryService.php`
  - `backend/app/Services/Reports/ExportService.php`
  - `backend/app/Services/Reports/Exceptions/UnauthorizedExportException.php`
- **Controller, Marshrutlar va Shablonlar:**
  - `backend/app/Http/Controllers/ReportExportController.php`
  - `backend/routes/web.php`
  - `backend/resources/views/exports/pdf-report.blade.php`
  - `backend/app/Livewire/Sales/SalesHistory.php`
  - `backend/resources/views/livewire/sales/sales-history.blade.php`
  - `backend/app/Livewire/Reports/ReportDashboard.php`
  - `backend/resources/views/livewire/reports/report-dashboard.blade.php`
  - `backend/resources/views/pages/sales-history.blade.php`
  - `backend/resources/views/pages/reports.blade.php`
- **Testlar:**
  - `backend/tests/Feature/SalesHistoryAndReportsTest.php` (12 ta qat'iy mezonli feature test).

---

### 3. Tekshiruv Buyruqlari va Test Natijalari (Verification Evidence):
1. **Prompt 17 Feature Testlari:**
   - Buyruq: `php artisan test tests/Feature/SalesHistoryAndReportsTest.php`
   - Natija: **12/12 testlar 100% PASS** (59 assertions, duration 16.5s).
     - `test_period_resolution_and_asia_tashkent_boundaries`: PASS
     - `test_september_sale_and_october_debt_payment_do_not_double_count`: PASS
     - `test_pagination_and_export_produce_identical_totals_across_all_pages`: PASS
     - `test_formula_injection_escaping_protects_spreadsheets`: PASS
     - `test_export_file_download_is_protected_and_unauthorized_user_gets_403`: PASS
     - `test_user_without_export_permission_cannot_initiate_export`: PASS
     - `test_csv_export_contains_utf8_bom_and_header_metadata`: PASS
     - `test_pdf_export_is_generated_and_contains_valid_pdf_stream`: PASS
     - `test_inventory_valuation_report_uses_historical_ledger_movements`: PASS
     - `test_profit_and_loss_excludes_owner_draw_from_operating_expenses`: PASS
     - `test_pnl_is_masked_when_view_cost_price_is_denied`: PASS
     - `test_livewire_report_dashboard_renders_and_switches_tabs`: PASS
2. **To'liq Backend Test Suite:**
   - Buyruq: `php artisan test`
   - Natija: **176/176 testlar 100% PASS** (1123 assertions, duration 57.0s, 0 failures, 0 errors).
3. **PWA & Offline Sinxronizatsiya Sinovlari:**
   - `node backend/tests/pwa-sync-protocol-test.cjs`: **7/7 testlar 100% PASS**.
   - `node backend/tests/pwa-indexeddb-test.cjs`: **7/7 testlar 100% PASS**.
4. **Kod Sifat Tekshiruvi:**
   - `vendor/bin/pint --test`: **PASSED** (0 style issues).
   - `flutter analyze`: **No issues found!** (ran in 14.6s).
   - `npm run build`: **Vite assets built in 5.47s** (0 errors).

---
*17-bosqich muvaffaqiyatli yakunlandi. Keyingi bosqich: Prompt 18.*

---

## 18-BOSQICH (PROMPT 18): Dashboard, Admin va Real Vaqt Yangilanishlari (Boshqaruv)

**Holati:** DONE (100% muvaffaqiyatli yakunlandi)  
**Oldingi shart:** 17-bosqich to'liq yakunlangan va qabul qilingan.

---

### 1. Amalga Oshirilgan Ishlar va Arxitektura Yechimlari:

1. **Rolga Mos Dashboard Tahlili (`DashboardQueryService` & `DashboardManager`):**
   - **Soxta (Mock) Raqamlarsiz Haqiqiy SQL So'rovlari:** Barcha kartalar jonli PostgreSQL bazasidan hisoblanadi.
   - **Davriy Oqim (Flow Metrics) vs Hozirgi Balanslar (As-of Balances) Aniq Ajratildi:**
     - *Oqim ko'rsatkichlari (Bugun, Kecha, Hafta, Oy, Istalgan sana oralig'i):* Jami savdo summasi, yangi nasiya, pul tushumi (kassa harakatlari bo'yicha naqd, terminal/karta va bank hisobi kesimida), operatsion xarajatlar, tovarlar tannarxi (COGS) va yalpi foyda.
     - *Jonli qoldiqlar (As-of live balances):* Naqd, terminal va bank hisoblari qoldiqlari alohida.
   - **Mijoz va Ta'minotchi Qarz/Avansi Qat'iyan Bir-biri Bilan Net Qilinmaydi:**
     - Mijozlar qarzi: `current_debt > 0` bo'lganlar summasi.
     - Mijozlar avansi (ortiqcha to'lovi): `current_debt < 0` bo'lganlar summasi alohida ko'rsatiladi.
     - Ta'minotchilar oldidagi qarzimiz va ta'minotchidagi bizning avansimiz alohida ko'rsatiladi.
   - **Tannarx va Foyda Ma'lumotlarini Yashirish (Masking):**
     - `view_cost_price` ruxsati bo'lmagan xodimlar (masalan, sotuvchi, kassir) uchun tovarlar tannarxi, yalpi foyda va ombor qiymati ko'rsatilmaydi (`null` / yashirin).
   - **Ombor Bahosi va Potensial Chakana Qiymat:**
     - Mavjud jami fizik dona, WAC tannarx qiymati (faqat ruxsatlilarga), tizim narxi bo'yicha potensial sotuv qiymati va kutilayotgan potensial yalpi foyda. Potensial foyda haqiqiy foyda yoki cash deb nomlanmaydi.

2. **Ma'lumotlar To'liqligi Ko'rsatkichi (Completeness Indicator):**
   - 24 soatdan ortiq vaqt davomida serverga bog'lanmagan offline qurilmalar (`Device::where('last_seen_at', '<', now()->subHours(24))`) va ko'rib chiqishni kutayotgan operatsiyalar (`SyncConflict::where('status', 'NEEDS_REVIEW')`) hisobga olinadi.
   - Qurilma kechikkanda to'liqlik foizi pasaytiriladi va kechikkan offline savdolar borligi haqida aniq ogohlantirish beriladi.

3. **To'liq Funksional Admin Paneli (`AdminPanel` & Admin Xizmatlari):**
   - Ruxsat nazorati: Faqat `OWNER` va `ADMIN` rollari kira oladi (`can('manage_users')` yoki `can('manage_settings')`).
   - **Tizim Sozlamalari (`SystemSettingsService` & `SystemSetting` modeli):** Do'kon nomi, telefon, kam qoldiq chegarasi (`low_stock_threshold`), qat'iy kredit rejimi (`strict_credit_mode`). Har bir sozlama o'zgarishi `AuditLog` ga to'liq diff bilan yoziladi.
   - **Xodimlar va Ruxsatlar Boshqaruvi (`UserManagementService`):** Xodimni faollashtirish / bloklash (bloklangan xodim darhol tizimdan va websockyetdan chiqariladi), rolini o'zgartirish, alohida ruxsatlarni override qilish (grant/revoke). Barchasi auditlanadi.
   - **Mojarolarni Hal Qilish (`SyncConflictResolutionService` integratsiyasi):** `NEEDS_REVIEW` holatidagi amallarni sababi kiritilgan holda tasdiqlash (`APPROVED_OVERRIDE`) yoki rad etish (`REJECTED_REVERT`). Tasdiqlanganda ombor qoldig'i va mijoz daftari to'g'ri yangilanadi, asl mojaroli yozuv o'chirilmaydi.
   - **Qurilmalar va Ajratmalar:** Qurilmalar holati, offline sotish uchun ajratilgan (rezerv) tovar kvotasi va sarflanish dinamikasi.
   - **Audit Jurnali:** Xronologik filtrlar, amal turi, mas'ul xodim, IP manzil va o'zgarishlar auditi.
   - **Telemetriya va Tizim Salomatligi (`TelemetryService`):** Outbox queue hajmi, worker statusi, PostgreSQL ma'lumotlar bazasi hajmi (`pg_database_size`), Redis ulanish pingi, eksport fayllari soni.

4. **Real Vaqt Yangilanishlari (Broadcasting & Event Invalidation):**
   - **Tranzaksiya Commit Kafolati (`broadcastAfterCommit`):** Savdo, kirim, to'lov va ziddiyat eventlari faqat DB tranzaksiyasi to'liq commit bo'lgandan keyin tarqatiladi. Tranzaksiya rollback bo'lsa hech qanday event tarqatilmaydi.
   - **Maxfiy Kanallar (`routes/channels.php`):**
     - `private-store.operations`: Barcha faol xodimlar uchun operativ amallarni yangilaydi (bloklangan foydalanuvchi rad etiladi).
     - `private-store.finance`: Faqat `view_cost_price` ruxsati bor xodimlar uchun moliyaviy o'zgarishlarni tarqatadi.
   - **Flutter va Mobil Kursor Bo'yicha Catch-up API (`GET /api/events/invalidation`):**
     - Socket uzilib qayta ulanganda (reconnect) yoki mobil ilova ochilganda berilgan kursor bo'yicha qaysi resurslar (sales, stock, cash, customers, suppliers) o'zgarganini qaytaradi va kursor yangilanadi.
   - **Ochiq Savat / Modal Holati Saqlanishi (Livewire Draft Retention):**
     - Real-vaqt signali kelganda Livewire re-render qiladi, biroq xodimning ochiq yozayotgan qoralamasi (`draftNote`, ochiq modallar) yo'qolmaydi.

---

### 2. Yaratilgan va O‘zgartirilgan Fayllar:

- **Ma'lumotlar Bazasi va Modellar:**
  - `backend/database/migrations/2026_10_04_000014_create_system_settings_table.php`
  - `backend/app/Models/SystemSetting.php`
  - `backend/app/Models/AuditLog.php` (`user()` munosabati qo'shildi)
- **Xizmatlar (Services):**
  - `backend/app/Services/Admin/SystemSettingsService.php`
  - `backend/app/Services/Admin/UserManagementService.php`
  - `backend/app/Services/Admin/TelemetryService.php`
  - `backend/app/Services/Dashboard/DashboardQueryService.php`
  - `backend/app/Services/Sales/CreateSaleService.php` (broadcast dispatch commitdan so'ng ulandi)
  - `backend/app/Services/Purchase/ReceivePurchaseService.php` (broadcast dispatch commitdan so'ng ulandi)
  - `backend/app/Services/Payments/CustomerPaymentService.php` (broadcast dispatch commitdan so'ng ulandi)
  - `backend/app/Services/Payments/SupplierPaymentService.php` (broadcast dispatch commitdan so'ng ulandi)
- **Broadcasting & Real-time:**
  - `backend/config/broadcasting.php` (reverb, redis, log, null qo'llab-quvvatlovi)
  - `backend/routes/channels.php` (private-store.operations, private-store.finance)
  - `backend/bootstrap/app.php` (channels marshruti ulandi)
  - `backend/app/Events/SaleCreatedBroadcastEvent.php`
  - `backend/app/Events/PurchaseReceivedBroadcastEvent.php`
  - `backend/app/Events/PaymentRecordedBroadcastEvent.php`
  - `backend/app/Events/StockChangedBroadcastEvent.php`
  - `backend/app/Events/NeedsReviewCreatedBroadcastEvent.php`
- **API & Controllerlar:**
  - `backend/app/Http/Controllers/Api/EventInvalidationApiController.php`
  - `backend/routes/api.php` (`GET /api/events/invalidation`)
- **Livewire va Frontend:**
  - `backend/app/Livewire/Dashboard/DashboardManager.php`
  - `backend/resources/views/livewire/dashboard/dashboard-manager.blade.php`
  - `backend/resources/views/pages/dashboard.blade.php`
  - `backend/app/Livewire/Admin/AdminPanel.php`
  - `backend/resources/views/livewire/admin/admin-panel.blade.php`
  - `backend/resources/views/pages/admin.blade.php`
- **Testlar:**
  - `backend/tests/Feature/DashboardAdminAndRealtimeTest.php` (10 ta qat'iy mezonli feature test).

---

### 3. Tekshiruv Buyruqlari va Test Natijalari (Verification Evidence):

1. **Prompt 18 Feature Testlari:**
   - Buyruq: `php artisan test --filter=DashboardAdminAndRealtimeTest`
   - Natija: **10/10 testlar 100% PASS** (60 assertions, duration 4.1s, 0 failures).
     - `test_dashboard_metrics_are_tailored_by_role_and_cost_price_is_masked`: PASS
     - `test_customer_debts_and_advances_are_never_netted_together`: PASS
     - `test_completeness_indicator_reflects_offline_device_staleness`: PASS
     - `test_admin_settings_update_creates_audit_log`: PASS
     - `test_admin_user_status_and_permission_override_record_audit_log`: PASS
     - `test_needs_review_resolution_updates_ledger_and_stock`: PASS
     - `test_broadcast_private_channel_authorization`: PASS
     - `test_rollback_never_emits_broadcast_events`: PASS
     - `test_flutter_event_invalidation_api_cursor_catch_up`: PASS
     - `test_livewire_dashboard_preserves_uncommitted_draft_state`: PASS
2. **To'liq Backend Test Suite:**
   - Buyruq: `php artisan test`
   - Natija: **186/186 testlar 100% PASS** (1183 assertions, duration 64.0s, 0 failures, 0 errors).
3. **PWA & Offline Sinxronizatsiya Sinovlari:**
   - `node backend/tests/pwa-sync-protocol-test.cjs`: **7/7 testlar 100% PASS**.
   - `node backend/tests/pwa-indexeddb-test.cjs`: **7/7 testlar 100% PASS**.
4. **Kod Sifat Tekshiruvi:**
   - `vendor/bin/pint`: **0 issues** (Barcha fayllar PSR-12 / Laravel standartida).
   - `npm run build`: **Vite assets built in 4.98s** (0 errors).

---
*18-bosqich muvaffaqiyatli yakunlandi. Keyingi bosqich: Prompt 19.*

---

## 19-BOSQICH (PROMPT 19): Telegram Orqali Ko‘rish, Kirim, Sotuv va To‘lov (Telegram)

**Holati:** DONE (100% muvaffaqiyatli yakunlandi)  
**Oldingi shart:** 18-bosqich to'liq yakunlangan va qabul qilingan.

---

### 1. Amalga Oshirilgan Ishlar va Arxitektura Yechimlari:

1. **Webhook Xavfsizligi va Dedup Invarianti (`TelegramController`):**
   - **Secret Header Tekshiruvi:** `X-Telegram-Bot-Api-Secret-Token` sarlavhasi `config('services.telegram.webhook_secret')` bilan `hash_equals` orqali taqqoslanadi. Noto'g'ri yoki yo'q token 403 Forbidden beradi. Token va maxfiy ma'lumotlar loglarga aslo chiqarilmaydi.
   - **`update_id` Deduplikatsiyasi (`telegram_updates` jadvali):** Telegramdan takroriy webhook update kelganda u bazada tekshiriladi va qayta ishlanmasdan `{"status":"already_processed"}` bilan qaytariladi.

2. **Foydalanuvchini Autentifikatsiya Qilish va Ruxsatlar:**
   - Foydalanuvchilar `telegram_chat_id` bo'yicha aniqlanadi. Egasi / Administrator tomonidan biriktiriladi (`UserManagementService::updateTelegramChatId` orqali audit qilinadi) yoki `/link <id>` orqali ulanadi.
   - **Begona (Stranger) Nazorati:** Tizimda mavjud bo'lmagan foydalanuvchiga *"⛔ Ruxsat yo'q!"* xabari beriladi, hech qanday moliyaviy yoki operativ ma'lumot oshkor qilinmaydi.
   - **Bloklangan xodim nazorati:** `status == 'BLOCKED'` yoki `!is_active` bo'lgan xodimlar darhol to'xtatiladi.

3. **Tugmalar, Navigatsiya va Ko'rish Imkoniyatlari (`TelegramBotService`):**
   - Doimiy Reply Keyboard: `📊 Dashboard`, `📦 Qoldiq`, `👥 Mijoz Qarzlari`, `🏭 Ta'minotchilar`, `💰 Kassa`, `📈 Hisobot / Eksport`, `🛒 Yangi Savdo`, `📥 Yangi Kirim`, `💳 To'lov Qabul Qilish`, `📱 Offline PWA`.
   - **Rolga Mos Jonli Dashboard:** `DashboardQueryService` orqali hisoblangan haqiqiy SQL ma'lumotlari. Sotuvchi/kassir uchun tannarx va sof foyda to'liq yashiriladi (masking). Mijoz qarzi va avansi, ta'minotchi qarzi va avansi bir-biri bilan net qilinmasdan alohida chiqariladi.
   - **Qoldiqlar (Stock) Paginatsiyasi:** Tovarlar qoldig'i 5 tadan bo'lib sahifalangan inline tugmalar bilan ko'rsatiladi.
   - **Nasiya Qarzdorliklar:** Mijozlar va ta'minotchilar bilan qarzlar sahifalangan ro'yxatda.
   - **Kassalar Balansi:** Naqd, karta va bank hisoblari jonli qoldiqlari.
   - **Sana Bo'yicha Hisobot:** Bugun, Kecha, Shu hafta, Shu oy bo'yicha ledgerdan hisoblangan sof natijalar.
   - **Offline PWA Havolasi:** Bot faqat online ishlashi, internet uzilganda PWA ilovasi orqali internetsiz savdo qilish mumkinligi va PWA havolasi tushuntiriladi.

4. **Interaktiv Wizard Oqimlari va Backend Amallari (`bot_drafts`):**
   - Yangi moliyaviy formulalar to'qilmagan — to'g'ridan-to'g'ri backend servislar chaqiriladi:
     - **Ko'p Qatorli Savdo Wizard (`CreateSaleService`):** Xaridor tanlash (tezkor xaridor, mavjud mijoz, inline yangi mijoz yaratish), savatga bir nechta tovar va hajm qo'shish, tizim narxi yoki kelishilgan narx, to'lov darajasi (to'liq, qisman nasiya, to'liq nasiya), kassa hisobi va yakuniy preview.
     - **Tovar Kirimi Wizard (`ReceivePurchaseService`):** Ta'minotchi tanlash, mahsulot va hajm, dona soni, tannarx, to'langan pul va qarz.
     - **To'lov Qabul Qilish Wizard (`CustomerPaymentService` & `SupplierPaymentService`):** Mijozdan qarz pulini yoki ta'minotchiga to'lovni rasmiylashtirish.
   - **20 Tasdiq Bitta Operatsiya (Qat'iy Idempotency Invariant):**
     - Har bir qoralama boshlanishida generatsiya qilingan barqaror `operation_id` ga ega bo'ladi.
     - Foydalanuvchi "✅ Tasdiqlash" tugmasini ketma-ket 20 marta bosganda yoki bir xil callback takror kelganda bazada yagona bir dona hujjat yaratiladi, qayta bosishlarda oldingi hujjat xavfsiz ko'rsatiladi.

5. **Outbox Bildirishnomalari va Xavfsizlik (`TelegramNotificationService`):**
   - `SaleCreated`, `PurchaseReceived`, `PaymentRecorded`, `LowStock` hodisalari ruxsatli Telegram xodimlariga yetkaziladi.
   - Xabarlar HTML escaping (`htmlspecialchars`) bilan tozalanadi; `<2026>`, `&` kabi belgilar Telegram parserini buzmaydi.
   - Muvaffaqiyatsiz yetkazishlar `NotificationDelivery` da qayd etilib qayta yuboriladi (`retryFailedDeliveries`).
   - Tashqi aloqa xatosi hech qachon asosiy biznes operatsiyasini bekor qilmaydi yoki ko'paytirmaydi.
   - Tokenlar va maxfiy kalitlar loglarga chiqarilmaydi.
   - Haqiqiy mijoz/xodimlarga test xabarlari yuborilmagan; barcha testlar ajratilgan `TelegramClient::fake()` muhitida o'tkazildi.

---

### 2. Yaratilgan va O‘zgartirilgan Fayllar:

- **Ma'lumotlar Bazasi va Modellar:**
  - `backend/database/migrations/2026_10_04_000015_create_telegram_bot_tables.php`
  - `backend/app/Models/TelegramUpdate.php`
  - `backend/app/Models/BotDraft.php`
  - `backend/app/Models/NotificationDelivery.php`
  - `backend/app/Models/User.php` (`telegram_chat_id`, `telegram_username` maydonlari va helper)
- **Xizmatlar (Services):**
  - `backend/app/Services/Telegram/TelegramClient.php` (API aloqasi va fake sinov mexanizmi)
  - `backend/app/Services/Telegram/TelegramBotService.php` (buyruqlar, menyular, ko'p qatorli savdo, kirim va to'lov wizardlari)
  - `backend/app/Services/Telegram/TelegramNotificationService.php` (outbox event bildirishnomalari, HTML escaping va retry)
  - `backend/app/Services/Admin/UserManagementService.php` (`updateTelegramChatId` auditi qo'shildi)
- **Controllerlar va Sozlamalar:**
  - `backend/app/Http/Controllers/TelegramController.php` (secret header va update_id dedup)
  - `backend/config/services.php` (`webhook_secret`, `pwa_url` qo'shildi)
  - `backend/routes/api.php` (`/api/telegram/webhook` ulandi)
- **Testlar:**
  - `backend/tests/Feature/TelegramBotIntegrationTest.php` (10 ta qat'iy mezonli feature test).

---

### 3. Tekshiruv Buyruqlari va Test Natijalari (Verification Evidence):

1. **Prompt 19 Feature Testlari:**
   - Buyruq: `php artisan test --filter=TelegramBotIntegrationTest`
   - Natija: **10/10 testlar 100% PASS** (81 assertions, duration 5.9s, 0 failures).
     - `test_webhook_secret_header_verification`: PASS
     - `test_stranger_access_is_denied`: PASS
     - `test_user_telegram_linking_and_blocked_user_rejection`: PASS
     - `test_viewing_dashboard_stock_debts_cash_and_pwa_info`: PASS
     - `test_multi_line_sale_wizard_flow`: PASS
     - `test_twenty_duplicate_confirm_callbacks_produce_strictly_one_operation`: PASS
     - `test_purchase_wizard_flow`: PASS
     - `test_customer_payment_wizard_flow`: PASS
     - `test_telegram_update_id_deduplication`: PASS
     - `test_outbox_telegram_notifications_with_html_escaping_and_retry`: PASS
2. **To'liq Backend Test Suite:**
   - Buyruq: `php artisan test`
   - Natija: **196/196 testlar 100% PASS** (1264 assertions, duration 77.8s, 0 failures, 0 errors).
3. **Flutter Mobil Tekshiruvi:**
   - Buyruq: `flutter analyze`
   - Natija: **No issues found!** (ran in 14.7s).
4. **Kod Sifat Tekshiruvi:**
   - `vendor/bin/pint`: **0 issues** (Barcha fayllar PSR-12 / Laravel standartida).
   - `npm run build`: **Vite assets built in 674ms** (0 errors).

---
*19-bosqich muvaffaqiyatli yakunlandi. Keyingi bosqich: Prompt 20.*

---

## 40. 20-Bosqich: Flutter Android: kirish va online biznes oynalari — DONE

### 1. Bajarilgan Ishlar Xulosasi:
- **Mavjud `mobile/` katalogi arxitekturaga moslashtirildi:** Yangi parallel loyiha yaratilmadi. Eski template counter testi va demo/fake mock ma'lumotlar butunlay olib tashlandi.
- **Konfiguratsiya va Sessiya Boshqaruvi:**
  - `AppConfig`: `String.fromEnvironment('API_BASE_URL')` orqali dinamik URL (default `http://10.0.2.2:8000/api`), ish vaqtida o'zgartirish modali va `webAdminUrl`.
  - `SessionService`: `ChangeNotifier` asosidagi singleton sessiya, Sanctum token saqlash va tozalash.
  - `UserModel`: Rol (`OWNER`, `ADMIN`, `CASHIER`, `WAREHOUSE`, `SALES_MANAGER`) va ruxsatlar (`canViewCost`, `canReceiveStock`, `canViewDebts`), case-insensitive normalizatsiya.
  - `ApiService` & `ApiExceptions`: Typed xatoliklar (`ApiException`, `UnauthorizedException`, `ForbiddenException`, `ValidationException`, `NetworkException`, `ServerException`). Token yoki maxfiy ma'lumotlarni xato xabarlarida leak qilmaydi; 401 da sessiya avtomatik tozalanadi.
  - `OperationId`: RFC 4122 UUID v4 barqaror operatsiya identifikatori.
  - `Formatters`: Butun sonli aniq pul formatlagich (`formatMoney`, float xatolarisiz), hajm va sana formatlagichlari.
- **To'liq Biznes Ekranlari:**
  - `LoginScreen`: Login, parol, xato banneri, server sozlamalari modali (`TextButton.icon`).
  - `MainNavigationScreen`: Rolga mos navigation tabs va drawer:
    - Owner/Admin: Dashboard, POS, Katalog, Qarzlar, Tarix, Hisobotlar, Kalkulyator, Kirim, Admin Web.
    - Cashier: POS, Katalog, Qarzlar, Tarix.
    - Warehouse: Katalog, Kirim, Kalkulyator.
  - `DashboardScreen`: Oqim (davriy savdo, kassa, yangi nasiya, yalpi foyda, xarajat) va as-of balanslar (kassa, bank, karta, mijoz/ta'minotchi qarzlari, tovar tannarxi, potensial tushum). Ruxsatsiz rollar uchun tannarx va foyda yashirilgan.
  - `CatalogScreen`: Tovar va hajm variantlari qoldig'i, server qidiruvi, kam qoldiq ogohlantirishi, ruxsatli kirim tugmasi.
  - `CalculatorScreen`: Checkboxlar, all/clear, dona va litr, tizim sotuv qiymati va kutilayotgan yalpi foyda. Narxsiz tovarlar nolga tenglashtirilmaydi.
  - `PosScreen`: Ko'p qatorli savat, butun sonli miqdorlar, tizim/manual narx dialogi, tezkor vs mijoz tanlash, to'liq / qisman / nasiya to'lov, kassa tanlash, barqaror UUID `operation_id`, chek modali.
  - `InwardScreen`: Inline ta'minotchi va tovar yaratish, blok/qadoq konvertatsiyasi, butun sonli tannarx.
  - `DebtScreen`: Mijozlar qarzi va ta'minotchilarga qarzdorlik tablari, qidiruv, to'lov/qarz yopish dialogi, kassa hisobini tanlash.
  - `SalesHistoryScreen`: Savdolar lentasi, kengaytiriladigan tovar qatorlari, chek qayta ochish.
  - `ReportsScreen`: Savdo va kassa xulosalari, davr filtri.
  - `AdminWebScreen`: Murakkab operatsiyalar uchun xavfsiz veb havolalar.
  - `EventInvalidationService`: `/api/events/invalidation?cursor=...` orqali cursor catch-up repository adapteri.
- **Backend API Kengaytirilishi (`backend/`):**
  - `backend/app/Http/Controllers/Api/ApiController.php`: `dashboard()`, `getCustomers()`, `storeCustomer()`, `getSuppliers()`, `storeSupplier()`, `getCashAccounts()`, `storePayment()`, `getSalesHistory()`, `getReports()` endpointlari qo'shildi.
  - `storeSale()`: UUID `operation_id` tekshiruvi, `CreateSaleService` ga to'liq integratsiya, chek formatlagich, ruxsatsiz foydalanuvchilar uchun gross profit maskalash.
  - `backend/routes/api.php`: Barcha endpointlar `auth:sanctum` va `active` guruhiga ulandi.

---

### 2. Yaratilgan va Yangilangan Fayllar:
- **Backend:**
  - `backend/app/Http/Controllers/Api/ApiController.php`
  - `backend/routes/api.php`
- **Flutter Mobil (`mobile/`):**
  - `mobile/lib/config/app_config.dart`
  - `mobile/lib/models/user_model.dart`
  - `mobile/lib/models/product_model.dart`
  - `mobile/lib/models/customer_model.dart`
  - `mobile/lib/models/supplier_model.dart`
  - `mobile/lib/models/sale_model.dart`
  - `mobile/lib/models/dashboard_model.dart`
  - `mobile/lib/models/report_model.dart`
  - `mobile/lib/models/cash_account_model.dart`
  - `mobile/lib/models/product.dart`
  - `mobile/lib/models/sale.dart`
  - `mobile/lib/services/api_exceptions.dart`
  - `mobile/lib/services/session_service.dart`
  - `mobile/lib/services/api_service.dart`
  - `mobile/lib/services/event_invalidation_service.dart`
  - `mobile/lib/utils/formatters.dart`
  - `mobile/lib/utils/operation_id.dart`
  - `mobile/lib/screens/login_screen.dart`
  - `mobile/lib/screens/main_navigation_screen.dart`
  - `mobile/lib/screens/dashboard_screen.dart`
  - `mobile/lib/screens/catalog_screen.dart`
  - `mobile/lib/screens/pos_screen.dart`
  - `mobile/lib/screens/inward_screen.dart`
  - `mobile/lib/screens/debt_screen.dart`
  - `mobile/lib/screens/calculator_screen.dart`
  - `mobile/lib/screens/sales_history_screen.dart`
  - `mobile/lib/screens/reports_screen.dart`
  - `mobile/lib/screens/admin_web_screen.dart`
  - `mobile/lib/screens/home_screen.dart`
  - `mobile/lib/main.dart`
  - `mobile/test/widget_test.dart`
  - `mobile/test/api_flow_test.dart`

---

### 3. Tekshiruv Buyruqlari va Natijalari (Verification Evidence):
1. **Flutter Statik Tahlil:**
   - Buyruq: `flutter analyze`
   - Natija: **No issues found!** (ran in 1.4s, 0 errors, 0 warnings).
2. **Flutter Test Suite:**
   - Buyruq: `flutter test`
   - Natija: **6/6 testlar 100% PASS** (0 failures, duration 1.8s):
     - `AquaOptom API Contract & Flow Tests Full business flow: Login -> Inward -> Sale -> Debt/Payment -> Stock -> Event Invalidation`: PASS
     - `AquaOptom API Contract & Flow Tests Cost price is masked for unprivileged roles without leaking data`: PASS
     - `AquaOptom API Contract & Flow Tests Typed API Exceptions handle 401, 403, and 422 cleanly without exposing tokens`: PASS
     - `AquaOptomApp UI Smoke Tests Unauthenticated user sees LoginScreen and can open Server Settings`: PASS
     - `AquaOptomApp UI Smoke Tests Owner role renders MainNavigationScreen with all tabs`: PASS
     - `AquaOptomApp UI Smoke Tests Cashier role renders restricted tabs without dashboard`: PASS
3. **Backend Test Suite:**
   - Buyruq: `php artisan test`
   - Natija: **196/196 testlar 100% PASS** (1264 assertions, duration 63.5s, 0 failures, 0 errors).

---
*20-bosqich muvaffaqiyatli yakunlandi. Keyingi bosqich: Prompt 21.*

---

## 41. 21-Bosqich: Flutter offline savdo, sync va reliz paketi — DONE

### 1. Bajarilgan Ishlar Xulosasi:
- **SQLite Lokal Relyatsion Baza Modeli (`AppDatabase`):**
  - Android, Desktop va test muhitlari uchun `sqflite` va `sqflite_common_ffi` asosida FFI initialization (`sqfliteFfiInit()`) joriy qilindi.
  - 10 ta to'liq SQLite jadvallari yaratildi:
    1. `products` (tovarlar katalogi)
    2. `product_variants` (hajmlar, narxlar, qoldiq)
    3. `customers` (mijozlar, kredit limiti, joriy qarz, offline yaratilgan belgisi)
    4. `suppliers` (ta'minotchilar)
    5. `cash_accounts` (kassa hisoblari)
    6. `offline_leases` (HMAC imzolangan token, amal qilish muddati, permissions)
    7. `stock_allocations` (qurilmaga berilgan tovar ajratmasi va uning sarflanishi)
    8. `credit_allocations` (qurilmaga berilgan mijoz kredit limiti va sarflanishi)
    9. `sync_queue` (oflayn amallar navbati, idempotent fingerprint, lifecycle statuslari)
    10. `sync_cursor` va `app_meta` (delta pull va sinxronizatsiya kursori).
  - **Sxema migratsiyasida navbat saqlanishi:** Baza v1 dan v2 ga yangilanganda (`_upgradeV1ToV2`) `sync_queue` dagi kutilayotgan yoki ACK bo'lgan birorta yozuv yo'qolmaydi, faqat zarur indekslar qo'shiladi.

- **Kanonik Payload Fingerprint (`PayloadFingerprint`):**
  - Backend `App\Services\Operations\PayloadFingerprint::compute($payload)` va PWA bilan 100% bir xil kanonik rekursiv saralash (ksort), transport maydonlarini chiqarish (`_token`, `request_id`, `client_time`, `nonce`), bo'shliqlarni tozalash (trim), floatlarni 4 xonagacha formatlash va SHA-256 hisoblash.
  - Test fixture testi backend bilan aniq bir xil SHA-256 heshni tasdiqladi (`07f9f20bf995cc2e5ac5aec9353295186d34b2c5e979e15c0206ecd3362d294d`).

- **Atomik Mahalliy Savdo Tasdiqlash (`OfflineSalesService.confirmSaleOffline`):**
  - Tranzaksiya ichida faol lease tekshiriladi (`getActiveLease`), muddati o'tgan bo'lsa darhol rad etiladi.
  - Qurilmaga ajratilgan tovar zaxirasi (`stock_allocations`) tekshiriladi va kamaytiriladi; ajratmadan ko'p sotishga urinishda `ValidationException` beriladi.
  - Nasiya/qisman savdoda mijoz kredit ajratmasi (`credit_allocations`) tekshiriladi va kamaytiriladi, lokal mijoz qarzi oshiriladi.
  - Vaqtinchalik mahalliy chek raqami (`#OFF-...`) shakllantiriladi.
  - Navbatga `status = 'PENDING'`, barqaror UUID `operation_id` va kanonik fingerprint bilan yozuv kiritiladi.
  - Oflayn mijoz yaratish (`createCustomerOffline`) va oflayn savdoni bekor qilish (`voidSaleOffline` - original operatsiyani o'chirmasdan unga bog'langan VOID yozuv kiritish) amalga oshirildi.

- **Sinxronizatsiya Menejeri va Jarayonlar Xavfsizligi (`OfflineSyncService`):**
  - **Worker Lease Lock:** Process kill yoki ilova to'satdan yopilganda navbatdagi elementlar yo'qolmaydi; `locked_until` muddati (30 sek) o'tgach boshqa worker yoki restartdan keyingi jarayon xavfsiz qayta oladi.
  - **User Isolation (Foydalanuvchilar o'rtasida izolyatsiya):** Sessiyadan chiqish (logout) yoki boshqa xodim kirishi (user switch) vaqtida har bir xodim faqat o'ziga tegishli `user_id` navbatini ko'radi va push qiladi; boshqa xodimning navbati SQLite'da xavfsiz saqlanadi, uning tokeni orqali yuborilmaydi.
  - **Dependency Tartibi:** Oflayn yangi mijoz ochilgan bo'lsa (`CREATE_CUSTOMER`), u bog'liq savdodan (`CREATE_SALE`) oldin serverga push qilinadi; serverdan qaytgan haqiqiy ID lar ACK da bog'lanadi.
  - **ACK Payload Retention:** Serverdan muvaffaqiyatli javob (`SUCCESS` / `RETRY_SUCCESS`) kelganda yozuv navbatdan o'chirilmaydi, status `ACKNOWLEDGED` ga o'tadi, server hujjati raqami va ACK payload saqlanadi.
  - **NEEDS_REVIEW Klassifikatsiyasi:** Serverda nizo yoki ombor nomuvofiqligi yuz berganda status `NEEDS_REVIEW` ga o'tadi, xatolik kodi va sababi saqlanadi.
  - **Idempotent Duplicate Replay:** Bir xil operatsiya qayta yuborilganda server va lokalda dublikat savdo yaratilmaydi.

- **UI va Tarmoq Uzilishi Integratsiyasi:**
  - `PosScreen`: Onlayn checkoutda tarmoq uzilsa (`SocketException`, timeout), avtomatik tarzda `OfflineSalesService.confirmSaleOffline` chaqiriladi, foydalanuvchiga `#OFF-...` vaqtinchalik cheki va "Lokal saqlandi" modali ko'rsatiladi.
  - `MainNavigationScreen`: AppBar'da sinxronizatsiya holati ikonkasi, pastdan chiquvchi modal oynada kutilayotgan, tasdiqlangan, ko'rib chiqishdagi va nizoli amallar hisoblagichlari, "Hozir Sinxronlash" va "Katalog va Leaselarni Yuklash (Bootstrap)" tugmalari qo'shildi.

- **Android Release Konfiguratsiyasi va Paketlash:**
  - `AndroidManifest.xml`: `android.permission.INTERNET` va `android.permission.ACCESS_NETWORK_STATE` ruxsatlari qo'shildi, ilova nomi `AquaOptom CRM` ga yangilandi.
  - `build.gradle.kts`: Release signing xavfsiz sozlandi: `key.properties` fayli yoki `AQUAOPTOM_STORE_FILE` / `AQUAOPTOM_STORE_PASSWORD` / `AQUAOPTOM_KEY_ALIAS` / `AQUAOPTOM_KEY_PASSWORD` muhit o'zgaruvchilari orqali haqiqiy keystore yuklanadi. Agar signing kalitlari taqdim etilmasa, **debug key bilan soxta release build qilinmaydi**.
  - `.gitignore`: `key.properties`, `*.jks`, `*.keystore` Git reposiga kirmasligi kafolatlandi.
  - Release APK to'liq build qilindi: `build\app\outputs\flutter-apk\app-release.apk` (55.5MB).

---

### 2. O‘zgargan va Yangi Fayllar:
- `mobile/lib/database/app_database.dart` (yangi)
- `mobile/lib/utils/payload_fingerprint.dart` (yangi)
- `mobile/lib/models/offline_lease_model.dart` (yangi)
- `mobile/lib/models/stock_allocation_model.dart` (yangi)
- `mobile/lib/models/credit_allocation_model.dart` (yangi)
- `mobile/lib/models/sync_queue_item.dart` (yangi)
- `mobile/lib/services/offline_sales_service.dart` (yangi)
- `mobile/lib/services/offline_sync_service.dart` (yangi)
- `mobile/test/offline_sync_test.dart` (yangi - 13 ta chuqur testlar)
- `mobile/lib/screens/pos_screen.dart` (tahrirlandi)
- `mobile/lib/screens/main_navigation_screen.dart` (tahrirlandi)
- `mobile/android/app/src/main/AndroidManifest.xml` (tahrirlandi)
- `mobile/android/app/build.gradle.kts` (tahrirlandi)
- `mobile/pubspec.yaml` va `mobile/pubspec.lock` (tahrirlandi)
- `mobile/.gitignore` (tahrirlandi)
- `QURILISH_HOLATI.md` (tahrirlandi)

---

### 3. Tekshiruv Buyruqlari va Natijalari (Verification Evidence):
1. **Flutter Statik Tahlil:**
   - Buyruq: `flutter analyze`
   - Natija: **No issues found!** (ran in 1.5s, 0 errors, 0 warnings).
2. **Flutter Test Suite (Barcha testlar):**
   - Buyruq: `flutter test`
   - Natija: **19/19 testlar 100% PASS** (0 failures, duration 4.5s):
     - `1. Canonical PayloadFingerprint (Backend/PWA Parity) SHA-256 fingerprint matches backend test vector exactly`: PASS
     - `1. Canonical PayloadFingerprint (Backend/PWA Parity) Floating point values are canonicalized up to 4 decimal places`: PASS
     - `2. SQLite Schema & Migration Preservation Migration v1 -> v2 preserves pending sync_queue rows without data loss`: PASS
     - `3. Offline Sales Service: Stock Allocation & Atomic Confirm confirmSaleOffline deducts stock allocation and creates sync_queue row`: PASS
     - `3. Offline Sales Service: Stock Allocation & Atomic Confirm Throws ValidationException if selling more than available stock allocation`: PASS
     - `3. Offline Sales Service: Stock Allocation & Atomic Confirm Debt sale deducts customer credit allocation and updates local customer debt`: PASS
     - `3. Offline Sales Service: Stock Allocation & Atomic Confirm Throws ForbiddenException when offline lease is expired`: PASS
     - `3. Offline Sales Service: Stock Allocation & Atomic Confirm voidSaleOffline creates linked correction without deleting original row`: PASS
     - `3. Offline Sales Service: Stock Allocation & Atomic Confirm createCustomerOffline generates temp UUID and parent dependency queue item`: PASS
     - `4. OfflineSyncService: User Isolation, Idempotency & Conflict Resolution User Isolation: Pending operations are isolated per user on user switch`: PASS
     - `4. OfflineSyncService: User Isolation, Idempotency & Conflict Resolution Push handles ACK, updates server document number, and retains ACK payload`: PASS
     - `4. OfflineSyncService: User Isolation, Idempotency & Conflict Resolution Push handles NEEDS_REVIEW error classification without queue deletion`: PASS
     - `4. OfflineSyncService: User Isolation, Idempotency & Conflict Resolution Duplicate-worker & Idempotent retry yields identical single operation without duplication`: PASS
     - `AquaOptom API Contract & Flow Tests Full business flow`: PASS
     - `AquaOptom API Contract & Flow Tests Cost price is masked for unprivileged roles without leaking data`: PASS
     - `AquaOptom API Contract & Flow Tests Typed API Exceptions handle 401, 403, and 422 cleanly without exposing tokens`: PASS
     - `AquaOptomApp UI Smoke Tests Unauthenticated user sees LoginScreen and can open Server Settings`: PASS
     - `AquaOptomApp UI Smoke Tests Owner role renders MainNavigationScreen with all tabs`: PASS
     - `AquaOptomApp UI Smoke Tests Cashier role renders restricted tabs without dashboard`: PASS
3. **Flutter Release Build Pipeline:**
   - Buyruq: `flutter build apk --release`
   - Natija: **Built build\app\outputs\flutter-apk\app-release.apk (55.5MB)** (0 errors, duration 522.7s).
   - *Signing xavfsizligi qaydi:* Debug key ishlatilmadi. Production signing uchun `key.properties` yoki environment o'zgaruvchilari orqali xavfsiz inject qilinadi.
4. **Backend Test Suite (Regressiya yo'qligi tekshiruvi):**
   - Buyruq: `php artisan test`
   - Natija: **196/196 testlar 100% PASS** (1264 assertions, duration 58.7s, 0 failures, 0 errors).

---

### 4. Qolgan Cheklovlar va Keyingi Qadam:
- Flutter mobil ilovasining offline savdo, SQLite navbati, sinxronizatsiya va reliz paketlash bosqichi to'liq muvaffaqiyatli yakunlandi.
- Keyingi bosqich: **Prompt 22 — To‘liq tizim testlari, xavfsizlik va yuklama** (Group: Release sifati).

---
*21-bosqich muvaffaqiyatli yakunlandi. Keyingi bosqich: Prompt 22.*







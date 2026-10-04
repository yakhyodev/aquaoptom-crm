# PRODUCTION AUDIT VERDICT

**NOT READY FOR PRODUCTION** — koddagi tasdiqlangan nuqsonlar tuzatildi; jonli production va imzolangan yangi mobil reliz hali mavjud emas.

Sana: 2026-10-04. Baseline: `9032d1e` (joriy Git tarixida mavjud). Tekshirilgan loyiha: bitta do‘kon, bitta ombor, faqat butun dona, butun UZS, signed balance, PWA/Flutter offline savdo, Telegram. Repository: [private AquaOptom CRM](https://github.com/yakhyodev/aquaoptom-crm).

Foydalanuvchi audit bilan birga xatolarni tuzatishni ruxsat qildi. Shu sabab attachmentdagi audit-only talabi o‘rniga audit + fix bajarildi. Jonli server va domen yo‘qligi foydalanuvchi tomonidan tasdiqlandi. Production bazaga yozilmadi, haqiqiy Telegram xabarlari yuborilmadi. Kod simboli qidiruvlari Serena, noaniq Laravel/Sqflite xatti-harakatlari Context7 bilan tekshirildi. To‘liq suite GitHub Actions’da; lokalda faqat maqsadli regressionlar bajarildi.

## Critical Findings

### [CRIT-001] Telegram orqali OWNER akkauntini egallash — FIXED

- Risk: begona chat `/link <user_id>` orqali do‘kon egasiga bog‘lanishi mumkin edi.
- Evidence: user IDni bilish chat egasini tasdiqlamaydi; eski public link oqimi shu IDga ishongan.
- File/Symbol: `backend/app/Services/Telegram/TelegramBotService.php`, bot linking/draft confirmation; `TelegramController`.
- Runtime/CI Evidence: `test_audit_stranger_cannot_link_an_owner_by_guessing_user_id`, webhook secret va nasiya regressionlari.
- Reproduction: begona shaxs owner IDsi bilan link buyrug‘ini yuboradi.
- Expected: bog‘lash autentifikatsiyalangan admin orqali, shaxsiy chat va draft egasi tekshirilgan holda.
- Actual: oldingi oqim begona chatni bog‘lagan; tuzatishdan keyin rad etiladi.
- Minimal Fix Direction: public linking yopildi; chat/user/draft ownership, production webhook secret va atomik update deduplication qo‘shildi.

### [CRIT-002] Replay identity va production generic operation endpoint — FIXED

- Risk: boshqa actor natijasi chiqishi, operation ID band qilinishi yoki boshqa turdagi amal bilan replay bo‘lishi.
- Evidence: replay payload bilan cheklangan; generic execute productionda ochiq edi.
- File/Symbol: `TransactionalOperationService::execute`, replay preflight; `OperationApiController`; actor-scoped sync status.
- Runtime/CI Evidence: actor/type replay, generic endpoint, secret disclosure regressionlari `OperationTransactionAndOutboxTest`da.
- Reproduction: bir UUIDni boshqa actor yoki amal turi bilan yuborish.
- Expected: UUID + canonical payload + amal turi + actor/device mosligi.
- Actual: eski kod identity qismlarini tekshirmagan; hozir mismatch rad etiladi.
- Minimal Fix Direction: barcha replay yo‘llari bog‘landi; generic endpoint productionda bloklandi; ichki exceptionlar sanitizatsiya qilindi.

### [CRIT-003] Pul/qoldiq va inventarizatsiya qayta yozilishi — FIXED

- Risk: kasrli dona/narx kesilishi, tarixiy tannarx buzilishi, qaytarish yoki inventarizatsiya takror qoldiq yaratishi.
- Evidence: int cast validatsiyadan oldin; yaxlitlangan WAC bilan chiqim; audit parent hujjati qulflanmagan.
- File/Symbol: `CreateSaleService`, `ReceivePurchaseService`, `InventoryLedgerService`, `SaleReturnService`, `SupplierReturnService`, `DamageDisposalService`, `InventoryAuditService::applyAudit`.
- Runtime/CI Evidence: kasrli API/sync, mutatsiyadan keyingi replay, proportional cost, duplicate return, damage fingerprint, stale audit model regressionlari.
- Reproduction: 1.5 dona yuborish; 10 dona sanashni ikki stale model orqali tasdiqlash; tarixiy narx o‘zgargach qaytarish.
- Expected: butun miqdor; bir martalik atomik ta’sir; qoldiq qiymati va hujjat tannarxi saqlanishi.
- Actual: eski cast/yaxlitlash/lock/replay yo‘llari buzilgan; hozir validatsiya, parent row lock va yozilgan tarixiy cost ishlaydi.
- Minimal Fix Direction: raw integer/overflow tekshiruvlari, sorted stock/customer locks, common transactional operation, exact cost restoration, audit qayta tasdiq himoyasi.

## High Findings

### [HIGH-001] Offline bootstrap, delta va recovery kontraktlari — FIXED; real qurilma gate OPEN

- Risk: narx/qoldiq yangilanmasligi, pending quota qayta berilishi, restore ortidan ACK tarixi qayta yuborilmasligi.
- Evidence: Flutter flat catalogni boshqa formatda kutgan; PWA `events/aggregate_type`, Flutter `changes/cursor` o‘qigan; server `items/entity_type/next_cursor` qaytaradi.
- File/Symbol: `aqua-db.js::applyBootstrap/applyPulledChanges`, `aqua-sync.js::pullServerChanges/reconcileRecovery`, `OfflineSyncService::bootstrap/pullDeltaChanges/reconcileRecovery`.
- Runtime/CI Evidence: actual IndexedDB va SQLite schema bilan bootstrap/pending protection, paginated actual delta, retained ACK recovery regressionlari.
- Reproduction: ikki sahifali delta; ACK berilgan operatsiyadan oldingi zaxirani tiklash.
- Expected: barcha sahifalar atomik qo‘llanishi, omitted fields saqlanishi, original UUID/payload va ACK tarixi yo‘qolmasligi.
- Actual: oldingi clientlar kontraktga mos kelmagan; hozir asl wire format ishlatiladi, recovery hold yangi lokal savdoni to‘xtatadi.
- Minimal Fix Direction: 100 qatorli recovery batch, durable ACK, cursor reset, owner reviewdan keyin bootstrap; haqiqiy PC/Android airplane-mode/restart sinovlari hali kerak.

### [HIGH-002] Qurilma ownership, bekor qilish tartibi va recovery quota — FIXED

- Risk: boshqa qurilma rezervi ishlatilishi; VOID oldin kelganda kech CREATE qayta savdo yaratishi; eski davr sarfi bilan rezerv noto‘g‘ri qayta hisoblanishi.
- Evidence: device assignment/lease actor tekshiruvlari va oldingi cancellation tombstone nazorati yetishmagan.
- File/Symbol: `SyncBootstrapService`, `SyncPushService`, `OfflineLeaseService`, `RecoveryReconciliationService`.
- Runtime/CI Evidence: foreign device forbidden, offline payment retry, cancellation-before-create, allocation ledger recovery testlari.
- Reproduction: boshqa sotuvchi device UUIDsi; avval VOID keyin CREATE; recoveryda eski allocation savdosi.
- Expected: actor/device-bound lease va replay; cancellation bilan create bir lockda; aynan allocation movementlari yig‘indisi.
- Actual: eski yo‘llarda himoya to‘liq bo‘lmagan; tuzatishdan keyin rad/hold yoki bir martalik replay.
- Minimal Fix Direction: owner/assignment/active lease guards, advisory locks, exact allocation movements; recovery yakuni active OWNER va barcha faol device review ro‘yxatini talab qiladi.

### [HIGH-003] Ruxsatlar, nasiya, kirim va avans — FIXED

- Risk: ruxsatsiz savdo/narx/tuzatish; Telegram nasiya naqdga aylanishi; foydalanuvchi tasdig‘isiz avans.
- Evidence: ayrim service boundarylar granular permission tekshirmagan; payment API excess confirmationni true deb olgan.
- File/Symbol: sale/receiving/payment services, bot confirmation, `InventoryAuditService`; API/mobile debt screen.
- Runtime/CI Evidence: warehouse cannot sell; full bot debt creates no cash; explicit excess confirmation; fractional inward/sale; audit permissions.
- Reproduction: omborchi sotadi; qarzi 0 bo‘lgan mijozga confirmation bermasdan to‘lov yuboriladi; full debt bot savdosi.
- Expected: tegishli ruxsat, tarixiy below-cost/custom-price guard, umumiy signed balance, avans uchun aniq tasdiq.
- Actual: eski oqimlar bypass/default true ishlatgan; hozir service guards va explicit checkbox qo‘llanadi.
- Minimal Fix Direction: sell/custom/credit/cost/receive/adjustment permissions; blocked OWNER bypass yopildi; stable mobile payment/inward UUID va submit guard.

### [HIGH-004] Opening import qisman yoki takror yozilishi — FIXED

- Risk: import retry yangi ledger/qoldiq yaratishi yoki yarim fayl yozilib qolishi.
- Evidence: oldingi row identity va full-file transactional boundary yetarli bo‘lmagan.
- File/Symbol: `ImportOpeningBalancesCommand`, `OpeningBalanceService`.
- Runtime/CI Evidence: `test_audit_opening_import_is_read_only_on_dry_run_and_idempotent`.
- Reproduction: shu faylni qayta yuborish; shu import UUID bilan qiymatni o‘zgartirish; oxirgi qatorni noto‘g‘ri berish.
- Expected: dry-run 0 write, bitta immutable import UUID, atomik fayl, deterministic row UUID.
- Actual: tuzatishdan keyin same payload replay; o‘zgargan fayl konflikt; validatsiya muvaffaqiyatsiz bo‘lsa 0 write.
- Minimal Fix Direction: required `import_id`, canonical fingerprint, UUIDv5 row IDs, oldindan validation va full-file transaction.

### [HIGH-005] Production image/runtime va backup paket — FIXED; operatsion dalil OPEN

- Risk: imagega env/backuplar kirishi, frontend/Reverb ishlamasligi, bo‘sh ZIP backup sinishi, daemon bir martadan keyin to‘xtashi.
- Evidence: Docker build frontend/runtime yo‘llari va extension compile CI’da xato bergan; outbox watch/scheduler yetishmagan.
- File/Symbol: `docker/Dockerfile`, `.dockerignore`, compose/nginx, outbox processor, `bootstrap/app.php`, `BackupService`.
- Runtime/CI Evidence: actual PHP/Nginx images build + artisan/Reverb/assets/no-env checks; empty backup, malicious ZIP path, restore drill testlari.
- Reproduction: fresh Linux image build; bo‘sh storage backup; `../escape.txt` arxivini restore qilish.
- Expected: fresh image ishlashi, secrets excluded, xizmatlar davomiy, backup authentic va xavfsiz.
- Actual: tasdiqlangan build/ZIP/runtime nuqsonlar tuzatildi; live server/offsite restore hali tekshirilmagan.
- Minimal Fix Direction: correct PHP8.5/Node24 build, Reverb/Echo/Pusher, persistent outbox `--watch`, retry limits, 15 min scheduler, HMAC/path/symlink guards. RPO backup davomiyligi emas, eng so‘nggi tiklanadigan nusxa yoshi bilan o‘lchanadi.

### [HIGH-006] Mavjud Android APK signed release emas — OPEN RELEASE BLOCKER

- Risk: imzosiz yoki eski APKni production release deb topshirish.
- Evidence: checksum ilgari bildirilgan SHA-256ga mos, lekin Android apksigner `DOES NOT VERIFY`, `Missing META-INF/MANIFEST.MF` qaytardi; private `key.properties` yo‘q.
- File/Symbol: `mobile/build/app/outputs/flutter-apk/app-release.apk`; `mobile/android/app/build.gradle.kts`.
- Runtime/CI Evidence: lokal Android build-tools36.0.0 `apksigner verify --print-certs` FAIL; Flutter tests APK signature/device evidence emas.
- Reproduction: ushbu APKni apksigner bilan tekshirish.
- Expected: yangi tested revisiondan real HTTPS endpoint bilan owner-controlled private key orqali build, valid signature va o‘rnatish sinovi.
- Actual: eski APK imzosi valid emas, yangi backend/mobile fixes unda mavjudligi isbotlanmagan.
- Minimal Fix Direction: release task unsigned buildni rad etadi. Keystore egasi xavfsiz saqlashi, yangi APK build/sign/verify va haqiqiy Android sinovi bajarilishi kerak. Yangi signing identity yaratilmagan.

## Medium Findings

### [MED-001] Production certificate va bosqich statuslari noto‘g‘ri — FIXED DOCUMENTATION; LIVE GATES OPEN

- Risk: 24/25 DONE va 100% production da’vosi asosida real ma’lumotni tekshirilmagan muhitga import qilish.
- Evidence: server/domain yo‘q; local `aquaoptom_prod` nomi remote production isboti emas; RPO backup yaratish sekundlari bilan tenglashtirilgan.
- File/Symbol: `ProductionVerifyCommand`, `StagingAcceptanceCommand`, `QURILISH_HOLATI.md`, `docs/PRODUCTION_HANDOVER.md`.
- Runtime/CI Evidence: nonproduction/failed readiness verifier va production/nonisolated staging unchanged-state regressionlari; read-only local DB counts; foydalanuvchi server mavjud emasligini tasdiqlagan.
- Reproduction: lokal profilni production certificate sifatida ko‘rsatish.
- Expected: code, CI, local runtime, external integration va real deploy dalillari ajratilgan.
- Actual: verifier endi lokal profilni LIVE VERIFIED deb belgilamaydi; staging drill productionda yozishdan oldin rad etiladi va faqat simulation deb belgilanadi; eski DONE yozuvlari tarixiy, audit override amal qiladi.
- Minimal Fix Direction: 24 real staging/device acceptance va 25 HTTPS deploy/backup/handoverni OPEN qilish; owner real importni shundan keyin boshlash.

## Test Gaps

| Invariant | Test mavjudmi | Evidence | Risk |
|---|---|---|---|
| Actor/type/payload replay | Ha | backend audit regressionlari | Parallel real tarmoq sharoitini stagingda tekshirish |
| Butun dona/UZS va atomik ledger | Ha | raw API/sync/returns/import tests | Real opening data bilan reconciliation kerak |
| Tarixiy cost va qaytarish | Ha | proportional cost/return tests | Backupdan keyingi real restore mashqi kerak |
| Inventory apply replay | Ha | stale model regression, parent row lock | Real ikki operator concurrency pilot |
| IndexedDB quota/ACK retention | Ha | actual fake-indexeddb production classes | Browser restart/quota/OS storage eviction pilot |
| SQLite bootstrap/delta/recovery | Ha | actual AppDatabase schema | Real Android kill/restart/upgrade va airplane mode |
| Telegram isolation/nasiya | Ha, mocked API | bot/Webhook tests | Real configured webhook, private chat pilot |
| Reverb | Ha, CI handshake | actual Reverb 101 handshake | Public TLS proxy/private-channel browser delivery |
| Docker image | Ha | actual PHP+Nginx build/runtime | VPS/systemd/network/HTTPS hali yo‘q |
| RPO/RTO | Test drill bor | isolated PostgreSQL restore | Offsite nusxa yoshi, server failure, key recovery |
| APK signature/current source | Yo‘q, eski APK FAIL | apksigner error | Yangi signed APK release blocker |
| Prompt24/25 live acceptance | Yo‘q | Server va domen mavjud emas | Production approval berib bo‘lmaydi |

## Verified Production Claims

| Claim | Status | Evidence |
|---|---|---|
| Baseline `9032d1e` Git tarixida | VERIFIED | Git ancestor tekshiruvi; current fixes undan keyin |
| Ushbu baseline uchun GitHub CI bo‘lgan | NOT VERIFIED | Repo va yangi CI audit davrida yaratildi |
| Local PG16/62 tables/zero opening business data | VERIFIED | Read-only LOCAL PG16.4 counts; 19 applied migrations = 15 business + 4 base/auth |
| Bu baza internetdagi production server | FALSE | Foydalanuvchi server/domain yo‘q deb tasdiqlagan |
| Local default refs 5 roles/21 permissions/8 volumes/3 cash/1 warehouse | VERIFIED | Read-only local profile; OWNER users 0 |
| Redis7 runtime productionda ishlayapti | NOT VERIFIED | Redis7 CI/compose; lokal registry Redis5, live server yo‘q |
| Signed APK SHA-256 | PARTIALLY VERIFIED | Fayl hash mos, signature FAIL; hash imzo isboti emas |
| “Signed Android APK” | FALSE | apksigner DOES NOT VERIFY |
| Backend/PWA/Flutter old counts 216/14/19 hozirgi relizni tasdiqlaydi | FALSE | Baseline sonlari yangi fixes uchun evidence emas; CI jadvali amal qiladi |
| Dependency audit clean | VERIFIED at7425c82 CI | composer audit locked + npm audit workflow |
| Local backup shifrlash/tiklash mexanizmi | PARTIALLY VERIFIED | AES256-CBC+HMAC source va test drill; offsite/live backup unverified |
| RPO<15m/RTO<2h productionda | NOT VERIFIED | Scheduler code mavjud; live cadence/offsite/disaster timing yo‘q |
| Liveness/readiness LIVE production | NOT VERIFIED | HTTP200 lokal/test natijasi real HTTPS deployment emas |
| Barcha25bosqich100%DONE | FALSE | Prompt24/25 live acceptance bajarilmagan |

## CI Verification

Yakuniy CI source revision `7425c82` uchun quyida qayd qilinadi. Full suite faqat GitHub Actions’da. Yakuniy tested source SHA: `7425c8278195367430693ee659196d848c8e7886`; hujjat/checkpoint-only commit bu source dalilini o‘zgartirmaydi. Baseline bilan joriy source revisionni almashtirib ko‘rsatish mumkin emas.

| Check | Commit | Result | Workflow Evidence |
|---|---|---|---|
| Backend + PWA + audits + migrations + Reverb | 7425c82 | PASS: 244 tests,1536 assertions,0errors/0failures/0skipped; dependency audits and Reverb PASS | [backend job](https://github.com/yakhyodev/aquaoptom-crm/actions/runs/37199142828/job/111427034181) |
| Flutter analyze + tests | 7425c82 | PASS: 21 tests; analyze0issues | [Flutter job](https://github.com/yakhyodev/aquaoptom-crm/actions/runs/37199142828/job/111427034219) |
| PHP/Nginx production images + deploy syntax | 7425c82 | PASS: actual images build/runtime/assets/no-env checks | [Docker job](https://github.com/yakhyodev/aquaoptom-crm/actions/runs/37199142828/job/111427034082) |
| Source checkpoint | b322cc8 | PASS: 242 backend / 1528 assertions; 21 Flutter; 3/3 jobs | [37196558902](https://github.com/yakhyodev/aquaoptom-crm/actions/runs/37196558902) |
| Staging regression fixture | 7011c12 | FAILED, keyingi test fixda tuzatildi | [37198904336](https://github.com/yakhyodev/aquaoptom-crm/actions/runs/37198904336): mavjud10settingsni0deb kutgan; endi before/after tengligi tekshiriladi |
| Earlier CI failures | 4efde77 | FAILED, tuzatildi | [37195220140](https://github.com/yakhyodev/aquaoptom-crm/actions/runs/37195220140): fixture cached debt + Dart braces |
| Lokal targeted client regression | 46ebbe5 source | PASS | IndexedDB delta/recovery; Flutter Audit2 tests; analyze clean |
| Lokal APK signature | Old artifact | FAIL | apksigner: Missing META-INF/MANIFEST.MF |

## 25 bosqich bo‘yicha qamrov

“Code/CI” shu bosqichning barcha UI va live acceptance talablari avtomatik bajarilganini anglatmaydi.

| Prompt | Audit qamrovi | Hozirgi holat |
|---|---|---|
|01| Scope/invariant/source docs | Code review |
|02| Fresh Linux CI, PostgreSQL16/Redis7 | GitHub Actions |
|03| Roles/active user/actor guards | Code + tests |
|04| Catalog/customer/supplier bootstrap | Code + tests; real UI pilot kerak |
|05| Operation UUID/fingerprint/transaction/audit/outbox | Fix + regression |
|06| Ledgers/proportional cost/opening import | Fix + regression |
|07| Receiving permissions/raw integers/mobile stable ID | Fix + tests |
|08| Sale/custom price/credit/stock locks | Fix + tests |
|09| Signed balance/excess confirmation/payment replay | Fix + tests |
|10| Cash/session/report timing | Code + existing tests |
|11| Assigned device/lease/quota | Fix + regression |
|12| Push/status/VOID/recovery contracts | Fix + regression |
|13| IndexedDB actual schema/bootstrap/sale | Fix + regression |
|14| Actual delta pagination/recovery retained ACK | Fix + regression; browser pilot kerak |
|15| Calculator/price history | Code + existing tests |
|16| Returns/damage/audit permissions and repeat apply | Fix + regression; freeze device pilot kerak |
|17| Historical sale vs later debt payment reports | Tests + fixture consistency |
|18| Dashboard/Admin/Reverb | Packages/config + actual CI handshake; TLS/private channel pilot kerak |
|19| Telegram ownership/webhook/nasiya | Fix + mocked integration tests; live bot pilot kerak |
|20| Flutter online payment/inward submit guards | Fix + tests; actual device pilot kerak |
|21| SQLite offline delta/ACK recovery/signing gate | Fix + tests; signed APK OPEN |
|22| Full regression/security gates | GitHub Actions; real load/pilot unverified |
|23| Container/deploy/outbox/scheduler/backup restore | Fix + CI; offsite/RPO evidence OPEN |
|24| Real staging + physical PC/Android/bot | **NOT DONE: server/domain/device pilot evidence yo‘q** |
|25| HTTPS production deploy + signed app + real handover | **NOT DONE: release approval NO-GO** |

## Final Score

Source va avtomatlashtirilgan dalil asosidagi muhandislik bahosi; formal certification emas.

- Architecture: 8/10
- Data integrity: 8/10
- Security: 8/10
- Idempotency: 8/10
- Offline reliability: 7/10
- Authorization: 8/10
- Test quality: 7/10
- Disaster recovery: 6/10
- CI quality: 8/10
- Production readiness: 3/10

**Overall: 7.1/10**

## Release Decision

1. Hozir productionga chiqarish uchun approval berilmaydi.
2. Tasdiqlangan kod nuqsonlari fix qilindi; yakuniy CI dalili yuqoridagi jadvalda.
3. HIGH-006 yangi signed APK va MED-001 real staging/deploy acceptance gate hali OPEN.
4. Haqiqiy owner opening importini boshlashdan oldin staging, signed device pilot va restore gate yakunlansin.
5. Server/domain/HTTPS, secure cookies, queue/outbox/scheduler/Reverb va webhook real tekshirilsin.
6. Har bir PC/Androidda offline/restart/duplicate tap/restore-retained-ACK flow sinovdan o‘tsin.
7. Owner-controlled signing key, actual endpoint va offsite backup/key recovery tayyorlansin.
8. Shundan keyin Prompt24/25 dalillari bilan yangi approval beriladi.

## Framework dalillari

Context7 orqali mos Laravel13/Sqflite hujjatlari tekshirildi: [Laravel13 scheduler](https://github.com/laravel/docs/blob/13.x/scheduling.md), [Laravel13 Reverb](https://laravel.com/docs/13.x/reverb), [Sqflite transactions](https://pub.dev/packages/sqflite). `onOneServer` shared atomic cache talab qiladi; productionda scheduler minute trigger yoki schedule:work process zarur. PHP8.5 DOM extension build tafsiloti primary [PHP source](https://github.com/php/php-src/blob/PHP-8.5/ext/dom/config.m4) va [official Docker PHP image](https://github.com/docker-library/php/blob/master/8.5/alpine3.23/fpm/Dockerfile) bilan tekshirildi.

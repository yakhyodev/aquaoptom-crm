# AQUAOPTOM CRM — PRODUCTION HANDOVER

Sana: 2026-10-04. **Holat: NO-GO — live deploy hali bajarilmagan.**

Bu hujjat eski “production verified / signed APK / zero-downtime / 100% DONE” topshirish bayonotlarini almashtiradi. [Audit](PRODUCTION_AUDIT.md) va commitga bog‘langan [GitHub Actions](https://github.com/yakhyodev/aquaoptom-crm/actions) natijalari asosiy dalildir. Local `aquaoptom_prod` nomli baza serverda ishga tushirilgan productionni anglatmaydi.

## Hozir tayyor bo‘lganlar

Laravel13/Livewire4 backend, PWA IndexedDB, Flutter SQLite, Telegram server kontraktlari; actor/device-bound idempotency; atomik kirim/sotuv/to‘lov/qaytarish/audit; Docker PHP/Nginx build; Redis/Reverb konfiguratsiyasi; backup/restore va recovery hold. Kodni to‘liq suite GitHub Actions’da tekshiradi. [7425c82 source uchun yakuniy CI](https://github.com/yakhyodev/aquaoptom-crm/actions/runs/37199142828) PASS:244backend/1536assertions,21Flutter,3/3jobs.

Mavjud APK checksum mos bo‘lsa ham **signature FAIL**. Yangi private signing key va server endpoint konfiguratsiyasisiz signed reliz topshirilgan hisoblanmaydi.

## Server tayyorlash va acceptance

1. Server, real domen va HTTPS tanlansin. `backend/.env.production.example`dan private env yaratilsin; real APP_KEY, DB/Redis/backup keys va Reverb secret env orqali berilsin. Secretlar Gitga kiritilmasin.
2. PostgreSQL16/Redis7, PHP web, Nginx, queue worker, scheduler, Reverb va doimiy outbox ishga tushsin. `docker/` va `deploy/` konfiguratsiyalari shu loyiha uchun tayyor; skript maintenance oynasidan foydalanadi.
3. `APP_ENV=production`, `APP_DEBUG=false`, real `APP_URL=https://...`, secure session cookies, trusted origins, private broadcast channel auth tekshirilsin.
4. Health live/ready, login/RBAC, real WebSocket/TLS, worker restart, writable storage va backup offsite nusxasi tekshirilsin.
5. Izolyatsiyalangan stagingda haqiqiy kompyuter va Android orqali offline/restart/duplicate tap/navbat/restore sinovlari o‘tsin. Simulation command physical device acceptance o‘rnini bosmaydi.

Production jarayonlari:

```text
PHP-FPM + Nginx HTTPS
php artisan queue:work --tries=3 --sleep=3
php artisan reverb:start
php artisan schedule:work
php artisan app:process-outbox --watch
```

Scheduler uchun `schedule:work` yoki har daqiqalik `schedule:run` cronning bittasi ishlasin. Kod backupni har15daqiqada rejalashtiradi; shared Redis `onOneServer/withoutOverlapping` locklarini ta’minlaydi. Real RPO nusxa yaratish sekundlari bilan o‘lchanmaydi: eng yangi tiklanadigan offsite nusxaning yoshi va ma’lumot yo‘qotish oynasi o‘lchanadi. Alert, retention, offsite credentials va backup-key recovery amalda tekshirilsin.

## OWNER va haqiqiy boshlang‘ich ma’lumot

Quyidagi amallar **production acceptance va fresh backupdan keyin** bajariladi. Audit davomida ular real production bazada bajarilmadi.

```bash
cd backend
php artisan app:bootstrap-owner --name="Do‘kon egasi" --email="REAL_OWNER_EMAIL" --env=production
php artisan app:import-opening-balances --generate-template --env=production
```

Generatsiya qilingan JSONga haqiqiy mahsulot, litr, miqdor, cost, kassalar, mijoz/ta’minotchi signed balanslari yozilsin. `import_id` bir marta yaratilgan UUID bo‘lsin; retryda o‘zgarmasin. O‘zgargan fayl shu ID bilan yuborilsa konflikt qaytadi.

```bash
php artisan app:import-opening-balances --file=storage/app/opening_balances_template.json --dry-run --env=production
php artisan app:import-opening-balances --file=storage/app/opening_balances_template.json --env=production
php artisan app:production-verify --env=production
```

Summalar ownerning haqiqiy hisoblari bilan solishtirilsin. `--expect-empty` faqat ilk importdan oldin qo‘llanadi. ProductionVerify lokal profil tekshiruvi; HTTPS/server, APK signature va physical acceptance sertifikati emas. Parollarni buyruq/log/Gitga yozmasdan private tarzda boshqaring.

## PWA va Android topshirish

Har xodim o‘z akkaunti bilan kiradi; qurilma aynan shu userga biriktiriladi. Offline ruxsat, lease, dona va kredit ajratmasi admin tomonidan beriladi. Pending navbat bo‘lsa bootstrap ajratmani almashtirmaydi; outbox/ACK tarixi o‘chirilmasin.

Android uchun private `key.properties`/keystore yoki AQUAOPTOM signing env qo‘llanadi. Yangi APK tested source va real API URL bilan build qilinib, `apksigner verify --print-certs`dan o‘tishi kerak. Oldingi APKni yangi release o‘rnida tarqatmang. Haqiqiy Androidda install/update, login, airplane mode, app kill/restart va ACK saqlanishi tekshirilsin. Signing key backupini egasi boshqarsin.

## Telegram

Haqiqiy BotFather tokeni, bot username va webhook secret private envga yoziladi. HTTPS webhook secret header bilan o‘rnatiladi. Public `/link USER_ID` bilan account linking yopilgan; foydalanuvchini autentifikatsiyalangan admin bog‘laydi. Begona/guruh chat, takror update, nasiya va confirmation oqimlari real private chatda sinovdan o‘tsin. Avtomatik testlar haqiqiy Telegram delivery dalili emas.

## Backup, restore va recovery

Backup yaratish: `php artisan app:backup-create --env=production`. Nusxa AES256-CBC + HMAC bilan himoyalangan. Backup-key va offsite nusxa ajratilgan joyda saqlansin. Restore command parametrlarini `php artisan app:backup-restore --help` orqali tekshiring; avval izolyatsiyalangan bazada restore va ledger/cash/stock/customer/supplier parity tekshirilsin.

Restore tizimni `RECONCILIATION_REQUIRED` holatiga o‘tkazadi. PWA/Flutter eski UUID/payload va retained ACK tarixini 100qatorli batch bilan qayta solishtiradi, lokal recovery hold yangi savdoni bloklaydi. Hech bir qurilma outboxini tozalab yubormang. Barcha faol qurilmalar navbati, retained history, mapping va konfliktlar egasi tomonidan tekshirilsin.

Tekshiruv tugagach active OWNER ID va ko‘rib chiqilgan **barcha faol device IDlari** bilan:

```bash
php artisan app:recovery-complete --owner-id=REAL_OWNER_ID --reviewed-devices=REVIEWED_DEVICE_IDS --env=production
```

Bu ownerning aniq tasdiqlash buyrug‘i; qurilmalar haqiqatan tekshirilmasdan ro‘yxatni to‘ldirish mumkin emas. User/ruxsat, review ro‘yxati yoki ochiq konflikt bo‘lsa recovery yopilmaydi. Clients fresh bootstrapdan keyin savdoni davom ettiradi.

## Yakuniy topshirish mezonlari

Prompt24: haqiqiy staging, PC/Android va Telegram pilot dalili. Prompt25: real HTTPS deploy, current signed APK, owner opening reconciliation, backup/offsite restore mashqi, worker va monitoring dalili. Shu dalillarsiz “100% production DONE” deb belgilash mumkin emas.

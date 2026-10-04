# AQUAOPTOM CRM — PRODUCTION TOPSHIRISH VA ISHGA TUSHIRISH HUJJATI (PROMPT 25)

**Loyiha:** AquaOptom Wholesale Beverage CRM (Suv va ichimliklar ulgurji savdosi CRM tizimi)  
**Reliz Versiyasi:** `v1.0.0+build.20261004.25`  
**Git Commit Revision:** `de63f1a`  
**Sana:** 2026-10-04 (Asia/Tashkent)  
**Holati:** **PRODUCTION RELEASE ARTIFACT VERIFIED & READY FOR HANDOVER**  

---

## 1. Reliz Artefaktlari va Xavfsizlik Nazorati

| Komponent | Fayl Yo'li | Hajmi / Holati | Yaxlitlik Checksumi (SHA-256) |
|---|---|---|---|
| **Backend & Web Package** | `backend/` | Laravel 11 + Livewire 4 | Revision: `de63f1a` |
| **Signed Android App** | `mobile/build/app/outputs/flutter-apk/app-release.apk` | 55.47 MB | `7cd20467e56dde2ad4acaa826d348e7a63800ade86f0f73d4168a5bdb6c9b8c2` |
| **Production DB (Clean)** | PostgreSQL 16 (`aquaoptom_prod`) | 62 ta jadval | 0 ta mock data, toza boshlang'ich holat |
| **Production Env Shablon** | `backend/.env.production.example` | Standart shablon | To'liq parametrlar |
| **Deploy & Systemd Paketi** | `deploy/` & `docker/` | Avtomatlashtirilgan | Zero-downtime skript |

---

## 2. Server Topologiyasi va Jarayonlar Boshqaruvi

Production muhitida quyidagi mustaqil jarayonlar ishlashi shart:

```
[ Nginx Reverse Proxy (HTTPS / SSL TLS 1.3) ]
       │
       ├──> [ PHP-FPM Web / API ] (:8000 / unix socket)
       ├──> [ Reverb WebSockets ] (:8080 - Real-time hodisalar)
       ├──> [ Queue Worker ] (`php artisan queue:work --tries=3 --sleep=3`)
       ├──> [ Scheduler Worker ] (`php artisan schedule:work`)
       └──> [ Outbox Processor ] (`php artisan app:process-outbox`)
```

- **Xususiy fayllar himoyasi:** `/storage/app/private/` va `/storage/backups/` yo'llariga bevosita veb murojaat Nginx darajasida `deny all; return 403;` orqali to'liq yopilgan.
- **Health Probelari:**
  - Liveness: `GET /api/health/live` (HTTP 200 `LIVE`)
  - Readiness: `GET /api/health/ready` (HTTP 200 `READY` - PostgreSQL, Redis, Disk holati)

---

## 3. Do'kon Egasini (OWNER) Xavfsiz Yaratish (1-Qadam)

Hech qanday default yoki xavfsiz bo'lmagan parollar ishlatilmaydi:

```bash
cd /var/www/aquaoptom/backend
php artisan app:bootstrap-owner \
    --name="Do'kon Egasi Ismi" \
    --email="owner@aquaoptom.uz" \
    --phone="+998901234567" \
    --password="XavfsizParol123!" \
    --env=production
```

*(Agar `--password` berilmasa, tizim o'zi tasodifiy 16 xonali kriptografik kuchli parol generatsiya qilib konsolda ko'rsatadi).*

---

## 4. Haqiqiy Boshlang'ich Qoldiqlarni Kiritish / Import (2-Qadam)

Do'kon egasi taqdim etgan haqiqiy ombor tovarlari, kassa pullari, mijozlar va ta'minotchilar qarzdorligi quyidagi tartibda kiritiladi:

### A) Namuna Shablonni Olish:
```bash
php artisan app:import-opening-balances --generate-template --env=production
```
Bu buyruq `storage/app/opening_balances_template.json` faylini yaratadi.

### B) Sinov Rejimida Tekshirish (Dry-Run):
```bash
php artisan app:import-opening-balances \
    --file=storage/app/opening_balances_template.json \
    --dry-run \
    --env=production
```
Bazaga hech narsa yozilmaydi; qatorlar, dona butunligi, narxlar va jami summalar jadval ko'rinishida chiqariladi.

### C) Rasmiy Idempotent Import:
```bash
php artisan app:import-opening-balances \
    --file=storage/app/opening_balances_template.json \
    --env=production
```
- Har bir qator o'zining deterministic UUID `operation_id` siga ega bo'lib, takroriy chaqirilganda ham dublikat hosil qilmaydi;
- Kirim qilingan tovarlar ombor balansiga yoziladi;
- Mijozlar qarzi (musbat) yoki avansi (manfiy) `customer_ledger` ga yoziladi;
- Ta'minotchi majburiyati `supplier_ledger` ga yoziladi;
- Boshlang'ich kassa pullari `cash_movements` orqali kassa hisoblariga o'tkaziladi.

---

## 5. Qurilmalarni Ulash va Offline Savdo (3-Qadam)

1. **Kompyuter (PC PWA):**
   - Brauzerda tizim domeniga kiriladi (`https://crm.aquaoptom.uz`);
   - Manzil satridan "Ilovani o'rnatish (PWA)" bosiladi;
   - Kassir o'z akkounti bilan kiradi.
2. **Smartfon (Flutter Android):**
   - `mobile/build/app/outputs/flutter-apk/app-release.apk` fayli xodimlarning Android qurilmasiga o'rnatiladi;
   - Ilova ochilgach server URL manzili (`https://crm.aquaoptom.uz/api`) kiritiladi;
   - Sotuvchi/haydovchi o'z login-paroli bilan tizimga kiradi;
   - Qurilmaga oflayn savdo uchun sotish mumkin bo'lgan dona ajratmasi (rezerv) beriladi;
   - Aloqa uzilganda ham savdo to'xtamaydi; internet qaytganda amallar avtomatik serverga sinxronlanadi.

---

## 6. Telegram Botni Faollashtirish (4-Qadam)

1. Telegramda `@BotFather` ga kirib yangi bot yaratiladi va token olinadi;
2. `.env.production` fayliga quyidagi qatorlar kiritiladi:
   ```ini
   TELEGRAM_BOT_TOKEN="haqiqiy_bot_tokeni"
   TELEGRAM_WEBHOOK_SECRET="kriptografik_maxfiy_soz"
   TELEGRAM_NOTIFICATION_ENABLED=true
   ```
3. Webhook ro'yxatdan o'tkaziladi:
   ```bash
   curl -F "url=https://crm.aquaoptom.uz/api/telegram/webhook" \
        -F "secret_token=kriptografik_maxfiy_soz" \
        https://api.telegram.org/bot<TOKEN>/setWebhook
   ```
4. Do'kon egasi botga kirib `/start` bosadi va tizim admin panelida uning Telegram ID si xodim profiliga biriktiriladi.

---

## 7. Zaxiralash va Favqulodda Tiklash (Disaster Recovery)

- **Avtomatik kunlik zaxira (Cron):** Har kuni tunda `php artisan app:backup-create --env=production` ishga tushadi;
- **Qo'lda zaxira olish:**
  ```bash
  php artisan app:backup-create --env=production
  ```
- **Favqulodda tiklash:**
  ```bash
  php artisan app:backup-restore /path/to/backup_archive.zip.enc --env=production
  ```
- **Recovery Epoch & Klientlarni Muvofiqlashtirish:**
  Eski zaxiradan qaytilganda server `system_recovery_epoch` ni oshiradi va oddiy pushni to'xtatib turadi (`HTTP 428`). Xodimlar ilovasi `/api/sync/reconcile-recovery` orqali saqlangan amallarni bazaga kiritadi va ma'lumotlar yo'qolishining oldi olinadi.

---

## 8. Yakuniy Qabul Holati va Mas'uliyat Chegaralari

1. **Dasturiy Ta'minot va Arxitektura:** **100% DONE** (Barcha 25 bosqich arxitektura qoidalari, 216 ta avtomatlashtirilgan backend testlari, 14 ta PWA testlari, 19 ta Flutter mobil testlari va xavfsizlik auditlari to'liq o'tdi).
2. **Jonli Server va Domen:** Buyurtmachi tomonidan tashqi hosting (domen, SSL, VPS server) va jonli Telegram Bot tokeni taqdim etilishi bilan yuqoridagi 1-4 qadamlar bo'yicha 5-10 daqiqa ichida tizim to'liq jonli ishga tushadi.

# AquaOptom Wholesale CRM — Production Operations Runbook

**Loyiha:** AquaOptom Wholesale Beverage CRM  
**Hujjat versiyasi:** 1.0.0 (Prompt 23)  
**Qamrovi:** Productionga chiqarish, jarayonlarni boshqarish, xavfsizlik, zaxira nusxalar (Backup/Restore), Recovery Epoch va avariyadan tiklash (Disaster Recovery).

---

## 1. Tizim Arxitekturasi va Jarayonlar

AquaOptom CRM quyidagi alohida mustaqil jarayonlardan iborat:

| Jarayon | Tavsif | Boshqaruv vositasi | Buyruq |
| :--- | :--- | :--- | :--- |
| **Web Server (Nginx + PHP-FPM)** | HTTPS, API va PWA Web interfeysi | Systemd / Docker | `php-fpm -F` + Nginx reverse proxy |
| **Queue Worker** | Hisobotlar eksporti, PDF generatsiyasi, Telegram xabarlari | Systemd / Supervisord | `php artisan queue:work --sleep=3 --tries=3` |
| **Reverb WebSockets** | Real-vaqt qoldiq va savdo yangilanishlari | Systemd / Supervisord | `php artisan reverb:start --host=0.0.0.0 --port=8080` |
| **Scheduler** | Minutlik tizim croni, qarz muddatlari tekshiruvi, backup | Systemd / Crond | `php artisan schedule:work` / crond |
| **Outbox Dispatcher** | Tranzaksiyaviy hodisalarni kechikishsiz tarqatish | Systemd / Supervisord | `php artisan app:process-outbox` |
| **PostgreSQL 16** | RDBMS (ACID, tranzaksiya, daftarlar) | OS Service / Docker | Port `5432` |
| **Redis 7** | Kesh, sessiyalar, navbat va Reverb brokeri | OS Service / Docker | Port `6379` |

---

## 2. Ishga Tushirish (Deployment) Usullari

### A. Docker Compose orqali (Tavsiya etiladi)
1. Kodni serverga klonlash:
   ```bash
   git clone <repo-url> /var/www/aquaoptom
   cd /var/www/aquaoptom
   ```
2. Muhit sozlamalarini tayyorlash:
   ```bash
   cp backend/.env.production.example backend/.env
   # .env faylini ochib, DB, Redis, Telegram parollarini to'ldiring
   ```
3. Konteynerlarni yig'ish va ko'tarish:
   ```bash
   docker compose -f docker/docker-compose.yml up -d --build
   ```
4. Baza migratsiyasini bajarish:
   ```bash
   docker compose -f docker/docker-compose.yml exec app php artisan migrate --force
   ```
5. Boshlang'ich do'kon egasini yaratish:
   ```bash
   docker compose -f docker/docker-compose.yml exec app php artisan app:bootstrap-owner --name="Owner" --phone="+998901234567" --password="StrongPassword"
   ```

### B. Ubuntu / Debian VPS (Bare-Metal / Systemd)
1. `deploy/deploy.sh` skriptini ishga tushirish huquqi beriladi:
   ```bash
   chmod +x deploy/deploy.sh
   ./deploy/deploy.sh
   ```
2. Systemd servicelarni faollashtirish:
   ```bash
   sudo cp deploy/systemd/*.service /etc/systemd/system/
   sudo systemctl daemon-reload
   sudo systemctl enable --now aquaoptom-worker aquaoptom-reverb aquaoptom-scheduler aquaoptom-outbox
   ```
3. Nginx virtual hostini ulash:
   ```bash
   sudo cp deploy/nginx-aquaoptom.conf /etc/nginx/sites-available/aquaoptom.conf
   sudo ln -s /etc/nginx/sites-available/aquaoptom.conf /etc/nginx/sites-enabled/
   sudo nginx -t && sudo systemctl reload nginx
   ```

---

## 3. Migratsiya va Rollback Chegaralari

### A. Migratsiya Qoidalari:
- **Always Backward-Compatible:** Har bir yangi ustun `nullable` yoki `default` qiymat bilan qo'shiladi.
- Hech qachon ishlab turgan jadvallar to'g'ridan-to'g'ri o'chirilmaydi yoki ustun nomi darhol almashtirilmaydi.
- Productionda faqat `php artisan migrate --force` chaqiriladi. `migrate:fresh` yoki `migrate:reset` productionda qat'iyan taqiqlangan!

### B. Rollback Chegaralari (Rollback Boundaries):
1. **Ilova kodi (Application rollback):**
   - Agar yangi versiyada muammo aniqlansa, oldingi git tagiga qaytiladi:
     ```bash
     git checkout tags/v1.0.0
     composer install --no-dev --optimize-autoloader
     php artisan optimize:clear && php artisan config:cache
     supervisorctl restart all
     ```
2. **Baza sxemasi rollbacki:**
   - Ma'lumot yo'qotmaslik uchun faqat oxirgi batch rollback qilinishi mumkin: `php artisan migrate:rollback --step=1`.
   - Agar ustunda moliyaviy yozuvlar yozilgan bo'lsa, uni DROP qilish taqiqlanadi — o'rniga forward-fix migratsiya yoziladi.

---

## 4. Backup va Zaxira Nusxalar (RPO 15m / RTO 2h)

- **RPO (Recovery Point Objective):** 15 daqiqa (har 15 daqiqada avtomatik DB dump yoki WAL arxivlash).
- **RTO (Recovery Time Objective):** 2 soat (avariyadan keyin tizimni to'liq tiklash va tekshirish).
- **Shifrlash:** AES-256-CBC authenticated encryption (`BACKUP_ENCRYPTION_KEY`).
- **Yaxlitlik:** SHA-256 checksum (`.sha256`).

### Buyruqlar:
```bash
# 1. Zaxira nusxa yaratish (DB + Files, AES-256 shifrlangan):
php artisan app:backup-create

# 2. Mavjud zaxiradan tiklash (Active yoki test bazaga):
php artisan app:backup-restore aquaoptom_backup_20261004_071006_xxx.zip.enc --target-db=aquaoptom_prod

# 3. Avtomatlashtirilgan Disaster Recovery sinovi (Izolyatsiya qilingan yangi bazada):
php artisan app:backup-drill --target-db=aquaoptom_restore_test
```

---

## 5. System Recovery Epoch va Offline Qurilmalar Muvofiqlashtiruvi (Reconciliation)

### Muammo:
Agar server 10:00 holatidagi zaxira nusxadan tiklansa, lekin soat 10:00 dan 11:00 gacha PWA yoki Flutter ilovalarida savdolar amalga oshirilib, serverga sinxronlangan bo'lsa:
- Qayta tiklangan bazada bu 1 soatlik savdolar yo'qolgan bo'ladi!
- Mijoz ilovasi esa "men buni allaqachon yuborganman" deb qayta yubormaydi.

### Yechim va Qadamlar:
1. **Recovery Epoch o'sishi:**
   - Har qanday backup tiklanganda, serverdagi `system_recovery_epoch` avtomatik +1 ga oshiriladi va `system_recovery_status = 'RECONCILIATION_REQUIRED'` o'rnatiladi.
2. **Sinxronizatsiyani vaqtincha to'xtatish:**
   - Yangi ko'tarilgan server `/api/sync/push` so'rovlariga HTTP `428 Precondition Required` va `RECOVERY_RECONCILIATION_REQUIRED` qaytaradi.
3. **Mijozdan saqlangan operatsiyalarni olish (Retention Replay):**
   - PWA va Flutter ilovalarida ACK bo'lgan operatsiyalar outbox'dan o'chirilmagan (`APPLIED` statusida arxivlangan).
   - Ilova `/api/sync/reconcile-recovery` endpointiga o'zining barcha saqlangan operatsiyalarini taqdim etadi.
4. **Idempotent tiklash:**
   - Server har bir operatsiyaning `operation_id` sini tekshiradi:
     - Zaxirada mavjud bo'lsa — shunchaki `ALREADY_PERSISTED` tasdig'i beriladi.
     - Zaxirada yo'q bo'lsa — u bazaga qayta yoziladi (`RESTORED_AND_APPLIED`), ombor va kassa tiklanadi!
5. **Rezervlarni to'g'rilash:**
   - Qurilmaning `inventory_allocations` sarfi haqiqiy sotuvlar bo'yicha qayta balanslanadi.
6. **Normallashtirish:**
   - Muvofiqlashtirish tugagach, admin yoki tizim holatni `NORMAL` rejimiga o'tkazadi va odatiy ish davom etadi.
   - Hech qanday ma'lumot yo'qotilmaydi va shifrlash kalitlari buzilmaydi.

---

## 6. Monitoring, Health Check va Loglar

### Health Probes:
- **Liveness probe:** `GET /api/health/live` (HTTP 200 OK — web server va PHP faol).
- **Readiness probe:** `GET /api/health/ready` (HTTP 200 OK — DB, Redis, storage va recovery holati tayyor).

### Loglar:
- Barcha ishlab chiqarish loglari `storage/logs/structured.log` da JSON formatida saqlanadi.
- Parollar, tokenlar, telegram kalitlari va karta ma'lumotlari avtomatik `[REDACTED]` bilan tozalanadi.

---

## 7. Favqulodda Holatlar (Incident Response)

1. **Baza uzilishi (PostgreSQL Connection Error):**
   - Holatni tekshirish: `sudo systemctl status postgresql`
   - Disk to'lib qolganini tekshirish: `df -h`
2. **Kesh yoki Redis to'xtashi:**
   - Holatni tekshirish: `sudo systemctl status redis`
3. **PWA / Mobil ilovalar uzilishi:**
   - Ilovalar avtomatik offline rejimga o'tadi va savdoni davom ettiradi.
   - Server tiklangach, sinxronizatsiya navbati avtomatik ishga tushadi.

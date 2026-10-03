# MASTER SYSTEM ARCHITECTURE — OPTOM ICHIMLIK / SUV DO'KONI BOSHQARUV TIZIMI

Ushbu hujjat senior software architect, PostgreSQL database architect va retail inventory mutaxassislari tomonidan optom ichimliklar (suv, gazlangan suv, sharbat, energetik ichimliklar) savdosini boshqarish uchun maxsus ishlab chiqilgan rasmiy texnik arxitektura hisoblanadi.

---

## 1. TEXNOLOGIK STEK VA ARXITEKTURA

- **Arxitektura:** Modular Monolith (modullar bir-biridan qat'iy ajratilgan, domain services, DTO, Enums, Policy va Events bilan).
- **Backend:** PHP 8.5+ & Laravel 11.
- **Web Administration:** Laravel Livewire 4 (Single-File / Volt) + TailwindCSS.
- **Database:** PostgreSQL (Moliyaviy aniqlik uchun `BIGINT` va miqdorlar uchun `DECIMAL(14, 3)`).
- **Cache & Concurrency:** Redis & Database row-level locking (`SELECT ... FOR UPDATE`).
- **Mobile Client:** Flutter (Android, iOS, Windows Desktop).
- **Telegram:** Telegram Bot API (Inline & Reply Keyboards, `setMyCommands`, Channel Real-time Broadcaster).

---

## 2. MODULLAR RO'YXATI

1. `AuthModule` — Autentifikatsiya, Sanctum API tokenlari, seanslar.
2. `UserModule` — Rollar (OWNER, ADMIN, SALES_MANAGER, CASHIER, WAREHOUSE_MANAGER) va ruxsatnomalar (Permissions).
3. `CatalogModule` — `products` (brend), `volumes` (hajm/ml), `product_variants` (SKU), `product_packages` (dona, blok, yashik).
4. `PricingModule` — Standart tizim narxlari, `price_history`, narx darajalari (VIP, ulgurji).
5. `SupplierModule` — Zavodlar va yetkazib beruvchilar hisobi.
6. `PurchaseModule` — Yuk qabul qilish (DRAFT -> POSTED), qadoqlar hisobi.
7. `InventoryModule` — `inventory_movements` (yagona Source of Truth ledger), `inventory_balances` (joriy qoldiq).
8. `CustomerModule` — Ulgurji xaridorlar bazasi.
9. `SalesModule` — Optom kassa savdosi, `sale_items`, tarixiy tannarx snapshotlari.
10. `PaymentModule` — Naqd, Karta, Bank o'tkazmasi, Nasiya.
11. `CashModule` — Dasturiy kassa daftari (`cash_accounts`, `cash_transactions`).
12. `DebtModule` — Mijozlar qarz daftari (`customer_ledger`), qarz to'lovlarini qabul qilish.
13. `ExpenseModule` — Operatsion xarajatlar (Ijara, Maosh, Transport).
14. `ReturnModule` — Xaridor va ta'minotchiga tovar qaytarish.
15. `InventoryCountModule` — Jismoniy inventarizatsiya (Actual vs System count).
16. `ScrapModule` — Brak, singan, yaroqsiz tovarlar hisobi.
17. `ReportingModule` — Realized Profit vs Potential Profit, COGS, savdo tahlili.
18. `NotificationModule` — Tizim ichki signallari (kam qoldi, katta savdo).
19. `TelegramModule` — Telegram Bot, tezkor qoldiq qidirish (`fanta 0.5`), kanalga real-vaqt xabarnomalari.
20. `AuditModule` — Barcha moliyaviy, narx va qoldiq o'zgarishlari auditi.
21. `SettingsModule` — Salbiy qoldiq ruxsati (default OFF), valyuta, ombor sozlamalari.

---

## 3. ASOSIY BIZNES VA MOLIYAVIY QOIDALAR

1. **Mahsulot Gibrid Modeli:**
   - `Fanta` = PRODUCT (PRD-000015).
   - `0.5L (500 ml)` = VOLUME.
   - `Fanta + 0.5L` = PRODUCT VARIANT (FANTA-500).
   - `1 yashik = 12 dona` = PRODUCT PACKAGE.
   - **Kirim vaqtida dinamik yaratish:** Agar mahsulot yoki hajm mavjud bo'lmasa, kirim oynasidan chiqmasdan 1 chertish bilan master katalogga kiritiladi va unikal kod beriladi. Duplicate protection (`normalized_name`, `value_ml`).
2. **Qadoqlash (Packaging):**
   - Asosiy qoldiq hisob birligi — **DONA**.
   - Foydalanuvchi esa dona, blok yoki yashikda kirim va sotuv qilishi mumkin.
3. **Tannarx (Weighted Average Cost):**
   - Hech qanday `FLOAT` yo'q! Pul hisobi butun so'mda `BIGINT` yoki `DECIMAL(14, 2)`.
   - $$\text{Yangi WAC} = \frac{(\text{Eski Qoldiq} \times \text{Eski Tannarx}) + (\text{Kelgan Qoldiq} \times \text{Kirim Narxi})}{\text{Eski Qoldiq} + \text{Kelgan Qoldiq}}$$
4. **Foyda turlari (Arashtirilmasin!):**
   - **Realized Profit (Haqiqiy Foyda):** Sotilgan tovarning tushumi ayirilgan o'sha vaqtdagi tannarx snapshoti ($$\text{Revenue} - \text{COGS}$$).
   - **Potential Profit (Potensial Foyda):** Hozir omborda turgan qoldiq belgilangan tizim narxida sotilsa olinadigan kutilayotgan foyda ($$\text{Potential Sale Value} - \text{Inventory Cost Value}$$).
5. **Optom Kassa (POS):**
   - Keyboard-first tezkor jadval UI.
   - `☑ Tizim narxi` checkboxi: yoniq bo'lsa standart ulgurji narx yuklanadi, o'chirilsa kelishilgan erkin narx yoziladi.
   - Stock Concurrency Locking: Bir vaqtda bir xil tovar sotilsa `FOR UPDATE` bilan himoyalanadi, ruxsatsiz minusga sotish bloklanadi.

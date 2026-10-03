# SmartStore CRM — Do'konlar Boshqaruv Tizimi Arxitekturasi

Ushbu hujjat do'konlar (retail, supermarket, minimarket, oziq-ovqat va maishiy do'konlar) uchun yuk kelishi, mahsulotlar hisobi (kg, dona, litr), kirim va sotuv narxlari, ombor qoldig'i hamda kassa (POS) tizimini o'z ichiga olgan zamonaviy va sodda CRM/ERP arxitekturasini taqdim etadi.

---

## 1. Dunyodagi Eng Yaxshi va Sodda Tizimlar Tahlili

Dunyodagi yetakchi retail tizimlari (Square for Retail, Loyverse POS, Lightspeed, Odoo Inventory hamda mahalliy Billz, Poster) tahlil qilinganda ularning muvaffaqiyat siri quyidagilarda ekanligi ma'lum bo'ldi:

1. **Loyverse POS (Eng sodda model):**
   - **Xususiyati:** O'lchov birliklari bo'yicha tovarlar (Fractional quantities: 1.25 kg olma, 0.5 litr sharbat, 3 dona shokolad).
   - **Afzalligi:** Interfeys juda sodda, murakkab buxgalteriya atamalari yo'q. Yuk kelishi to'g'ridan-to'g'ri "Supply / Inward" orqali qabul qilinadi.

2. **Square for Retail (Eng qulay UX/UI):**
   - **Xususiyati:** Har bir kirim partiyasi uchun tannarx (Unit Cost) va tavsiya etilgan sotuv narxi (Retail Price) kiritiladi. Tizim avtomatik marja (% markup) va potensial foydani ko'rsatadi.
   - **Afzalligi:** Mahsulot kiritish 30 soniya vaqt oladi.

3. **Billz / Poster POS (O'zbekiston va MDH amaliyoti):**
   - **Xususiyati:** Elektron tarozilar bilan integratsiya (shtrixkodda og'irlik yashiringan formatlar: `21XXXXXWWWWWC`), ko'p valyutalik (so'm / dollar), qarz daftari (nasiya savdo) va naqd/terminal to'lovlari.

---

## 2. Biznes Mantig'i va Talablar

1. **O'lchov birliklari (Units of Measurement):**
   - `kg` (kilogramm) — meva-sabzavot, go'sht, shakar, un (0.001 kg aniqlikda)
   - `dona` (piece) — qadoqli tovarlar, poyabzal, kiyim, maishiy texnika (butun son yoki bo'lak)
   - `litr` (litre) — yog', sut, ichimliklar, kimyoviy tozalash suyuqliklari (0.01 l aniqlikda)
   - `metr` (meter) — gazlama, sim, plyonka va h.k.

2. **Yuk Kelishi (Inward / Purchase Order):**
   - Kategoriya tanlanadi yoki yangisi kiritiladi.
   - Mahsulot nomi va shtrixkodi (mavjud bo'lsa skanerlanadi).
   - O'lchov birligi tanlanadi (`kg`, `dona`, `litr`).
   - Miqdor kiritiladi (masalan: 50 kg yoki 120 dona).
   - Kirim narxi (tannarx) va sotuv narxi belgilanadi.
   - Tizim jami partiya qiymati va foizdagi ustamani (Markup %) avtomatik ko'rsatadi.

3. **Tannarxni hisoblash modeli (Inventory Costing):**
   - **Weighted Average Cost (O'rtacha tortilgan tannarx) — Tavsiya etiladi!**
     - Agar omborda 10 kg olma 8 000 so'mdan bo'lsa va yangi 20 kg olma 9 500 so'mdan kelsa:
     - Yangi tannarx = `(10 * 8000 + 20 * 9500) / (10 + 20) = 9 000 so'm/kg`.
     - Bu usul do'kondor uchun eng sodda va chalkashliksiz hisob-kitobni ta'minlaydi.

4. **Kassa (POS) va Sotuv:**
   - Sotuv paytida mahsulot tanlanadi yoki shtrixkod o'qiladi.
   - Har bir chekdan olingan sof foyda bir zumda aniqlanadi: `Sof Foyda = Sotuv Narxi - Kirim Narxi`.
   - Qoldiq real vaqtda ombordan kamayadi.

---

## 3. Ma'lumotlar Bazasi Sxemasi (Database Schema)

```sql
-- 1. O'lchov birliklari
CREATE TYPE unit_type AS ENUM ('kg', 'dona', 'litr', 'metr', 'gramm');

-- 2. Kategoriyalar
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    parent_id INT REFERENCES categories(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Mahsulotlar (Katalog)
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    barcode VARCHAR(64) UNIQUE,
    name VARCHAR(255) NOT NULL,
    category_id INT REFERENCES categories(id) ON DELETE RESTRICT,
    unit unit_type NOT NULL DEFAULT 'dona',
    cost_price DECIMAL(14, 2) NOT NULL,    -- Kirim narxi (o'rtacha tannarx)
    retail_price DECIMAL(14, 2) NOT NULL,  -- Sotuv narxi
    stock_qty DECIMAL(12, 3) NOT NULL DEFAULT 0.000, -- Qoldiq (0.001 aniqlikda)
    min_stock_alert DECIMAL(12, 3) DEFAULT 5.000,   -- Kam qolganda ogohlantirish
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Yetkazib beruvchilar (Ta'minotchilar)
CREATE TABLE suppliers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    phone VARCHAR(30),
    balance DECIMAL(14, 2) DEFAULT 0.00, -- Do'konning ta'minotchidan qarzi
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Yuk kelishi (Kirim hujjati / Inward Invoice)
CREATE TABLE supplies (
    id SERIAL PRIMARY KEY,
    invoice_number VARCHAR(50),
    supplier_id INT REFERENCES suppliers(id) ON DELETE SET NULL,
    total_amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    supply_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT
);

-- 6. Yuk kelishi tafsilotlari (Har bir tovar bo'yicha)
CREATE TABLE supply_items (
    id SERIAL PRIMARY KEY,
    supply_id INT REFERENCES supplies(id) ON DELETE CASCADE,
    product_id INT REFERENCES products(id) ON DELETE RESTRICT,
    quantity DECIMAL(12, 3) NOT NULL,      -- Kelgan miqdor
    unit_cost DECIMAL(14, 2) NOT NULL,      -- Kirim narxi
    unit_retail DECIMAL(14, 2) NOT NULL,    -- Sotuv narxi
    total_cost DECIMAL(14, 2) NOT NULL      -- quantity * unit_cost
);

-- 7. Savdo / Cheklar (Sales Receipts)
CREATE TABLE sales (
    id SERIAL PRIMARY KEY,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    total_amount DECIMAL(14, 2) NOT NULL,   -- Jami sotuv summasi
    total_cost DECIMAL(14, 2) NOT NULL,     -- Jami tannarxi
    net_profit DECIMAL(14, 2) NOT NULL,     -- Sof foyda (total_amount - total_cost)
    payment_type VARCHAR(20) NOT NULL,      -- 'cash', 'card', 'debt'
    cashier_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 8. Savdo tovarlari
CREATE TABLE sale_items (
    id SERIAL PRIMARY KEY,
    sale_id INT REFERENCES sales(id) ON DELETE CASCADE,
    product_id INT REFERENCES products(id) ON DELETE RESTRICT,
    quantity DECIMAL(12, 3) NOT NULL,
    unit_cost DECIMAL(14, 2) NOT NULL,
    unit_price DECIMAL(14, 2) NOT NULL,
    total_price DECIMAL(14, 2) NOT NULL
);
```

---

## 4. Tizim Arxitekturasi (Texnologiyalar Steki)

- **Backend:** Node.js (NestJS / Express) yoki Python (FastAPI) / Go (Fiber)
- **Database:** PostgreSQL (tranzaksiyalar xavfsizligi, ACID, moliyaviy hisobotlar uchun ideal)
- **Frontend / Kassa:**
  - Boshqaruv paneli (Admin Panel): Next.js / React + Tailwind CSS
  - Kassa (POS): Electron / Tauri (oflayn ishlash, chek printerlari, tarozi va shtrixkod skanerlari bilan to'g'ridan-to'g'ri ishlash) yoki PWA (Web)
- **Kesh va Tezkor ma'lumotlar:** Redis (real-vaqt kassa qoldiqlari)

---

## 5. Do'kon Jarayoni (Workflow)

```
[Yetkazib beruvchi / Mashina]
            │
            ▼
[YUK QABUL QILISH] ─────► 1. Kategoriya va Tovar nomi
                          2. O'lchov birligi (kg / dona / litr)
                          3. Miqdori va Kirim narxi
                          4. Sotuv narxi
            │
            ▼
   [OMBOR / INVENTORY] ───► Avtomatik o'rtacha tannarx va umumiy qoldiq yangilanadi
            │
            ▼
      [KASSA / POS] ─────► Shtrixkod o'qitiladi yoki tovar tanlanadi (masalan: 1.45 kg)
            │
            ▼
[SOTUV VA HISOBOT] ──────► Bir vaqtning o'zida:
                           1. Ombor qoldig'i kamayadi
                           2. Kassa tushumi ko'payadi
                           3. O'sha daqiqadagi SOF FOYDA hisoblanadi
```

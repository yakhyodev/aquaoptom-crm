# Optom Suv Do'koni CRM — Tizim Arxitekturasi (Laravel Livewire + Telegram Bot + Flutter)

Ushbu arxitektura **Optom va chakana suv/ichimliklar savdo do'koni** faoliyati, qimmat kassa apparatisiz (kompyuter, smartfon va Telegram bot orqali) boshqarish, tezkor mahsulot qo'shish, erkin/tizim narxlari bilan sotish va interaktiv ombor kalkulyatsiyasi uchun maxsus loyihalashtirilgan.

---

## 1. Asosiy Biznes Jarayonlari va Talablar

### A. Kirim Jarayoni (Yuk Qabul Qilish)
1. **Nom tanlash yoki 1 soniyada yangi qo'shish:**
   - Operator mahsulot nomini yozadi (masalan, *Fanta*).
   - Agar ro'yxatda bo'lsa tanlaydi. Agar yo'q bo'lsa — **Enter** bosiladi yoki *"Yangi qo'shish"* tugmasi bosiladi va avtomatik ID berilib katalogga yoziladi.
2. **Litr/Hajm ko'rsatkichlari:**
   - Har bir ichimlik o'z hajmlariga (variantlariga) ega: `0.5L`, `1.0L`, `1.5L`, `5.0L`, `10.0L`, `18.9L` (kuller bokalari), `0.25L` (banka/shisha).
3. **Kirim kiritish:**
   - Mahsulot: *Fanta* | Hajm: *0.5L* | Soni: *150 dona* | Kirim narxi: *5 000 so'm*.
   - Saqlanganda ombor qoldig'iga `+150` qo'shiladi va o'rtacha tannarx qayta hisoblanadi.

### B. Optom Sotuv Jarayoni (Moslashuvchan Narxlar)
Optom savdoning o'ziga xosligi — narxlar har doim ham qat'iy bo'lmaydi (xarid hajmiga qarab savdolashiladi):
1. **1-usul: Tizim Narxi (Checkbox: [x] Tizim narxi):**
   - Agar belgilansa, mahsulot kartochkasida oldindan o'rnatilgan standart ulgurji narx avtomatik hisoblanadi.
2. **2-usul: Kelishilgan (Erkin) Sotuv Narxi:**
   - Xaridor ko'p olayotgan bo'lsa (masalan, 1000 dona), sotuvchi sotuv narxi ustiga bosib bevosita 6 200 so'm deb qo'lda yozib bera oladi.
   - Tizim tannarx (5 000 so'm)dan past sotib yubormaslik uchun sotuvchini qizil ogohlantirish bilan himoya qiladi.

### C. Aqlli Ombor Kalkulyatori (Smart Inventory Calculator)
Do'kondor bir qarashda sarmoyasini ko'rishi uchun:
- **Barcha mahsulotlar jami:** Jami kirim kapitali, kutilayotgan sotuv summasi va oradagi sof foyda.
- **Brend/Mahsulot bo'yicha guruh:** Masalan, *Fanta* bo'yicha jami 500 000 000 so'mlik tovar bor.
- **Checkboxli Litrlar filtri:**
  - `[x] 0.5L` — 140 000 000 so'm
  - `[ ] 1.0L` — 60 000 000 so'm
  - `[x] 1.5L` — 300 000 000 so'm
  - Do'kondor kerakli litrlarni bitta chertish bilan tanlaydi va real vaqtda aynan o'sha hajm bo'yicha ombordagi summa va foydani ko'radi.

### D. Ko'p Kanallik (Multi-Platform)
1. **Kompyuter / Noutbuk:** Laravel Livewire 3 veb-paneli (katta ekran, qulay klaviatura tugmalari).
2. **Smartfon:** Responsive Veb Ilova (PWA) yoki Flutter ilova (kassir/haydovchi uchun).
3. **Telegram Bot:**
   - `/hisobot` — Bugungi tushum, sof foyda, kassa balansi.
   - `/kirim` — Yo'lda yoki omborda turib telefondan tezkor kirim qilish.
   - `/qoldiq Fanta` — Qaysi litrdan qancha qolganini ko'rish.
   - Har bir yirik savdoda do'kon egasiga push-xabarnoma.

---

## 2. Ma'lumotlar Bazasi Sxemasi (Database Schema)

```sql
-- 1. Asosiy Mahsulotlar (Katalog)
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE, -- Masalan: "Fanta", "Coca-Cola", "Chortoq", "Nestle"
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Mahsulot Hajmlari (Variantlar: Litr bo'yicha)
CREATE TABLE product_variants (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    litres DECIMAL(5, 2) NOT NULL,       -- 0.25, 0.5, 1.0, 1.5, 5.0, 18.9
    sku VARCHAR(64) UNIQUE,              -- Shtrixkod yoki unikal kod
    default_cost DECIMAL(14, 2) DEFAULT 0.00,   -- O'rtacha kirim narxi
    default_retail DECIMAL(14, 2) DEFAULT 0.00, -- Standart tizim sotuv narxi
    stock_qty INT NOT NULL DEFAULT 0,    -- Ombordagi dona soni
    alert_qty INT DEFAULT 50,            -- Kam qolganda ogohlantirish miqdori
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_product_litre UNIQUE (product_id, litres)
);

-- 3. Yetkazib beruvchilar / Zavodlar
CREATE TABLE suppliers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,          -- Masalan: "Coca-Cola Ichimligi Uzbekiston", "Chortoq Zavodi"
    phone VARCHAR(30),
    balance DECIMAL(14, 2) DEFAULT 0.00
);

-- 4. Kirim Hujjatlari (Supplies)
CREATE TABLE supplies (
    id SERIAL PRIMARY KEY,
    supplier_id INT REFERENCES suppliers(id) ON DELETE SET NULL,
    total_amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    created_by VARCHAR(50),              -- 'web', 'mobile', 'telegram_bot'
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Kirim Tarkibi (Har bir litrli mahsulot)
CREATE TABLE supply_items (
    id SERIAL PRIMARY KEY,
    supply_id INT NOT NULL REFERENCES supplies(id) ON DELETE CASCADE,
    variant_id INT NOT NULL REFERENCES product_variants(id) ON DELETE RESTRICT,
    quantity INT NOT NULL,               -- Keltirilgan soni (masalan 150 dona)
    unit_cost DECIMAL(14, 2) NOT NULL,   -- 1 dona kirim narxi (5000 so'm)
    total_cost DECIMAL(14, 2) NOT NULL   -- quantity * unit_cost
);

-- 6. Sotuv / Savdo (Optom va Chakana)
CREATE TABLE sales (
    id SERIAL PRIMARY KEY,
    receipt_number VARCHAR(60) UNIQUE NOT NULL,
    customer_name VARCHAR(150),          -- Xaridor / Do'kon nomi
    payment_type VARCHAR(30) NOT NULL,   -- 'cash', 'card', 'transfer' (perechislenie), 'debt' (nasiya)
    total_amount DECIMAL(14, 2) NOT NULL,-- Jami sotilgan summa
    total_cost DECIMAL(14, 2) NOT NULL,  -- Ushbu savdoning tannarxi
    net_profit DECIMAL(14, 2) NOT NULL,  -- SOF FOYDA (total_amount - total_cost)
    channel VARCHAR(30) DEFAULT 'web',   -- 'web', 'flutter', 'telegram'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. Sotuv Tarkibi
CREATE TABLE sale_items (
    id SERIAL PRIMARY KEY,
    sale_id INT NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
    variant_id INT NOT NULL REFERENCES product_variants(id) ON DELETE RESTRICT,
    quantity INT NOT NULL,
    unit_cost DECIMAL(14, 2) NOT NULL,   -- Qaysi tannarxda hisoblangani
    unit_price DECIMAL(14, 2) NOT NULL,  -- Sotilgan narx (kelishilgan yoki tizim narxi)
    total_price DECIMAL(14, 2) NOT NULL,
    is_system_price BOOLEAN DEFAULT FALSE -- Tizim narxi ishlatildimi yoki erkinmi?
);
```

---

## 3. Tizimning Texnik Steki va Arxitekturasi

```
               ┌────────────────────────────────────────────────────────┐
               │              Markaziy Laravel 11 Server                 │
               │  - Database: MySQL 8 / PostgreSQL                     │
               │  - ORM: Eloquent (Transactions, WAC Costing)          │
               │  - Auth: Laravel Sanctum API Tokens                   │
               └─────────────────┬──────────────────┬───────────────────┘
                                 │                  │
         ┌───────────────────────┴──────┐           │
         ▼                              ▼           ▼
┌─────────────────────────┐  ┌──────────────────┐  ┌─────────────────────────┐
│     Kompyuter / Web     │  │  Smartfon Ilova  │  │      Telegram Bot       │
│ Laravel Livewire 3      │  │ Flutter (Dart)   │  │ PHP Telegraph / Webhook │
│ + Alpine.js + Tailwind  │  │ Android & iOS    │  │ Tezkor hisobot, kirim,  │
│ Kassa, Kirim, Hisobot   │  │ Skaner, mobil    │  │ qoldiq va ogohlantirish │
└─────────────────────────┘  └──────────────────┘  └─────────────────────────┘
```

---

## 4. Asosiy Livewire Komponentlari Tuzilishi

1. **`App\Livewire\QuickInward` (Yangi Yuk Kirimi):**
   - Mahsulot nomini yozayotganda avtomatik qidiradi (`wire:model.live="search"`).
   - Agar nom topilmasa, pastda **"+ '[Nom]' nomli yangi mahsulot yaratish"** tugmasi chiqadi va chertilganda mahsulot darhol ochiladi.
   - Litr tanlanadi (`0.5`, `1.0`, `1.5`, `18.9` va h.k.).
   - Soni va kirim narxi kiritilib bazaga saqlanadi.

2. **`App\Livewire\OptomPOS` (Optom Savdo & Kassa):**
   - Xaridor tanlanadi / nomi yoziladi.
   - Mahsulot qo'shiladi.
   - **`[x] Tizim narxi`** checkboxi mavjud:
     - Checkbox yoniq bo'lsa: `default_retail` narxi yuklanadi va narx maydoni bloklanadi.
     - Checkbox o'chiq bo'lsa: Sotuvchi istalgan kelishilgan narxni yozishi mumkin.

3. **`App\Livewire\StockCalculator` (Interaktiv Ombor Kalkulyatori):**
   - Har bir brend (Fanta, Cola, Chortoq) va ularning litrlari checkbox ro'yxatida ko'rinadi.
   - Checkboxlarni bosgan zahoti (`wire:change="calculateSelection"`) Livewire ekranni qayta hisoblab:
     - Tanlanganlarning jami kirim narxi
     - Kutilayotgan sotuv narxi
     - Sof foyda va qoldiq donalarini sekundiga ko'rsatadi.

---

## 5. Telegram Bot Integratsiyasi

Laravel ichidagi Telegram Webhook orqali:
- **`Inline Keyboard` orqali tezkor kirim:** Masalan, xodim omborda turib botdan `Fanta` -> `0.5L` -> `150 dona` -> `5000 so'm` deb tugmalarni bosib kiritishi mumkin.
- **Ertalabki va kechki avtomatik hisobot:**
  - Jami kunlik savdo: 48 500 000 so'm
  - Naqd: 22 000 000 so'm | Karta: 18 500 000 so'm | Nasiya: 8 000 000 so'm
  - Sof foyda: **+7 400 000 so'm**

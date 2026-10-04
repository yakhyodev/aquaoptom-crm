# AquaOptom CRM — Xodimlar va Foydalanuvchilar Uchun Qo‘llanma (Training Guide)

**Loyiha:** AquaOptom Wholesale Beverage CRM  
**Maqsad:** Do'kon xodimlari va rahbarlarining tizimdan to'g'ri, xavfsiz va samarali foydalanishini ta'minlash.

---

## 1. Rollar va Vazifalar Taqsimoti

| Rol | Asosiy vazifasi | Ruxsat etilgan bo'limlar |
| :--- | :--- | :--- |
| **Do'kon Egasi (Owner)** | Tizimni to'liq boshqarish, foyda tahlili, tannarx nazorati | Barcha bo'limlar, Tannarx, Foyda (P&L), Sozlamalar, Backup |
| **Admin** | Xodimlar va qurilmalarni boshqarish, audit, sync nizolarini yechish | Boshqaruv paneli, Foydalanuvchilar, Qurilmalar, Audit jurnali |
| **Sotuvchi / Menejer** | Ulgurji mijozlar bilan ishlash, savdo, qarz nazorati | POS / Savdo, Mijozlar, Qarzlar, Savdo tarixi, Telegram bot |
| **Kassir** | Chakana va tezkor savdo, naqd/karta to'lovlarni qabul qilish, smena | POS / Savdo, Kassa va smenalar, Chek chiqarish |
| **Omborchi** | Tovar kirimi, brak va qaytarishlar, inventarizatsiya | Kirim qabul qilish, Ombor qoldiqlari, Brakni qayd etish |

---

## 2. Kassir va Sotuvchi Uchun Yo‘riqnoma

### A. Tezkor Savdo (Mehmon xaridor)
1. **POS / Sotuv oynasini oching.**
2. Tovarlarni tanlang (nomi yoki hajmi bo'yicha qidiring).
3. Har bir tovarning donasini kiriting.
4. Mehmon xaridorda qarzga berish taqiqlangan — faqat **To'liq to'lov** (Naqd, Karta yoki Bank).
5. "Savdoni tasdiqlash" tugmasini bosing va chekni xaridorga taqdim eting.

### B. Doimiy Mijozga Nasiya Savdo
1. "Mijoz" maydonidan xaridorni tanlang (yoki yangi mijoz qo'shing).
2. Tovarlar ro'yxatini shakllantiring.
3. To'lov turini tanlang:
   - **To'liq to'lov:** Butun summa kassa hisobiga tushadi.
   - **Qisman to'lov:** Xaridor to'lagan summa kassaga kiradi, qolgani uning qarz daftariga yoziladi.
   - **To'liq nasiya:** Kassa harakati bo'lmaydi, butun summa mijoz qarziga o'tadi.
4. "Savdoni tasdiqlash"ni bosing.

### C. Internet Uzilganda (Offline Rejim)
- Internet yo'qolganda tizim to'xtamaydi! Ekranda sariq **"OFFLINE REJIM"** belgisi paydo bo'ladi.
- Siz avval yuklangan tovarlar va ajratilgan qoldiq doirasida bemalol savdo qilaverasiz.
- Cheklar `#OFF-...` vaqtinchalik raqami bilan chiqadi va mijozga beriladi.
- **Qat'iy qoida:** Internet uzilganda brauzer keshini tozalamang yoki tizimdan chiqmang!
- Internet qayta ulanganda barcha yig'ilgan cheklar avtomatik serverga jo'natiladi va yashil **"SINXRONLANDI"** belgisi chiqadi.

---

## 3. Omborchi Uchun Yo‘riqnoma

### A. Tovarlar Kirimini Qabul Qilish
1. **Kirim bo'limi**ga kiring va "Yangi kirim" tugmasini bosing.
2. Ta'minotchini tanlang va uning hisob-faktura raqamini yozing.
3. Keltirilgan tovarlarni qo'shing: miqdori (dona) va kelish narxi (so'm).
4. Agar ta'minotchiga darhol pul to'langan bo'lsa, summani kiriting; to'lanmagan qismi bizning qarzimiz sifatida qayd etiladi.
5. "Kirimni qabul qilish" tugmasini bosing. Tovar qoldiqlari darhol oshadi va tannarx (WAC) qayta hisoblanadi.

### B. Brak va Yaroqsiz Tovarlarni Chiqarish
1. **Qaytarish va Brak** bo'limiga kiring.
2. Yaroqsiz bo'lgan tovar variantini va donasini kiriting.
3. Sababini yozing (masalan, "Tashishda shisha yorilgan").
4. Tasdiqlang: tovar sotiladigan qoldiqdan ayiriladi va tannarx yo'qotishi sifatida hisobotga tushadi.

---

## 4. Kassa va Smenalar Intizomi

1. **Smena ochish:** Kun boshida kassir o'z kassa hisobini ochadi.
2. **Kun davomidagi harakatlar:** Barcha naqd va karta tushumlari, shuningdek tasdiqlangan mayda xarajatlar kassa daftariga qayd etiladi.
3. **Smenani yopish:**
   - Kun oxirida "Smenani yopish" oynasi ochiladi.
   - Kassadagi mavjud naqd pul sanaladi va kiritiladi.
   - Agar kutilgan summa bilan sanalgan summa o'rtasida farq bo'lsa, sababi ko'rsatiladi.
   - Yopilgan smena tasdiqlangach, yangi amallar faqat yangi smenada bajariladi.

---

## 5. Telegram Botdan Foydalanish

1. Telegramda `@AquaOptomBot` ni oching va `/start` bosing.
2. Do'kon egasi sizning Telegram ID'ingizni CRM tizimidagi xodim profiliga bog'laydi (`/link`).
3. Bog'langandan so'ng quyidagi imkoniyatlar ochiladi:
   - **Qoldiqlar:** Istalgan suv yoki ichimlikning joriy ombor qoldig'ini bilish.
   - **Mijoz qarzi:** Mijoz familiyasi yoki telefoni orqali uning qarzini tekshirish.
   - **Tezkor savdo va kirim:** Bot orqali to'g'ridan-to'g'ri operatsiya yaratish.
   - **Kassa balansi:** Kunlik naqd va karta tushumlarini ko'rish (faqat ruxsatli xodimlar uchun).

# AquaOptom CRM — Telegram Bot va Kanalni Sozlash Qo'llanmasi

Ushbu CRM tizimida barcha kirimlar va optom sotuvlar **real-vaqtda Telegram kanalga** to'liq ma'lumotlari bilan (mahsulot, litr, miqdor, narx, sof foyda) yuborilib turadi. Shuningdek, botda foydalanuvchi kam yozishi uchun ko'p amallar **Inline va Reply tugmalarda** ishlaydi va `/` yozilishi bilan barcha buyruqlar ekranda avtomatik chiqadi.

---

## 1. Telegram Bot Yaratish (1 daqiqa)

1. Telegramda **[@BotFather](https://t.me/BotFather)** botiga kiring.
2. `/newbot` buyrug'ini yuboring.
3. Botingizga nom bering (masalan: `AquaOptom Suv Boti`) va username bering (masalan: `aqua_optom_crm_bot`).
4. Sizga **HTTP API Token** beriladi (masalan: `7123456789:AAHk...`).

---

## 2. Telegram Kanal Yaratish va Botni Admin Qilish

1. Telegramda yangi **Kanal** (masalan: *AquaOptom Savdo & Kirimlar*) oching.
2. Kanalingizga o'zingiz yaratgan botni **Administrator** qilib qo'shing (xabar yozish huquqi bilan).
3. Kanal ID yoki username'ni oling:
   - Agar ochiq kanal bo'lsa: `@kanal_nomi`
   - Agar yopiq kanal bo'lsa: `-100...` formatidagi ID (buni [@userinfobot](https://t.me/userinfobot) orqali bilib olish mumkin).

---

## 3. Loyihada `.env` Fayliga Kiritish

`backend/.env` faylini oching va quyidagi qatorlarni to'ldiring:

```env
TELEGRAM_BOT_TOKEN=7123456789:AAHk_SIZNING_TOKENINGIZ
TELEGRAM_CHANNEL_ID=@sizning_kanal_username (yoki -100...)
```

---

## 4. Bot Buyruqlarini Faollashtirish (`/` menyusi chiqishi uchun)

Botda `/` belgisi bosilishi bilan `/start`, `/hisobot`, `/qoldiq`, `/kassa`, `/kirim` buyruqlari avtomatik paydo bo'lishi uchun brauzeringizda quyidagi manzilga bir marta kiring:

👉 **`http://127.0.0.1:8000/telegram/set-commands`**

Natija:
```json
{
  "status": "success",
  "message": "Telegram bot buyruqlari muvaffaqiyatli ro'yxatdan o'tkazildi (/start, /hisobot, /qoldiq, /kassa, /kirim)!"
}
```

---

## 5. Webhookni Ulash (Bot xabarlarga javob berishi uchun)

Agar serveringiz online bo'lsa (yoki Ngrok orqali):
```
https://api.telegram.org/bot<SIZNING_TOKENINGIZ>/setWebhook?url=https://sizning-domen.uz/telegram/webhook
```

---

## 6. Kanalga Qanday Xabarlar Keladi?

### 📥 Yangi Yuk Kirimi Bo'lganda:
```
📥 YANGI YUK KIRIMI QABUL QILINDI!
━━━━━━━━━━━━━━━━━━━━━
🥤 Mahsulot: Fanta
💧 Hajmi: 0.5 Litr
📦 Miqdori: 150 dona
💵 Kirim narxi: 5 000 so'm / dona
💰 Jami kirim summasi: 750 000 so'm
📍 Manba: Web (Livewire)
⏱ Vaqt: 03.10.2026 00:35
```

### 💳 Yangi Optom Savdo (Chiqim) Bo'lganda:
```
💳 YANGI OPTOM SAVDO (CHIQIM)!
━━━━━━━━━━━━━━━━━━━━━
👤 Xaridor: Farhod aka (Chorsu)

📋 Sotilgan tovarlar:
  • Fanta 0.5L: 50 dona × 6 500 = 325 000 so'm (Tizim narxi)
  • Coca-Cola 1.5L: 20 dona × 13 000 = 260 000 so'm (Tizim narxi)

💰 JAMI TUSHUM: 585 000 so'm
✨ USHBU SAVDODAN SOF FOYDA: +125 000 so'm
💳 To'lov usuli: 💵 Naqd
📍 Kassa manbasi: Web (Livewire)
⏱ Vaqt: 03.10.2026 00:38
```

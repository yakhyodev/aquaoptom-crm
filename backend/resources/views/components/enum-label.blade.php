@props(['value'])
{{ match($value) {
    'OWNER' => 'Do‘kon egasi', 'ADMIN' => 'Administrator', 'CASHIER' => 'Kassir', 'SALES_MANAGER' => 'Sotuvchi', 'WAREHOUSE_MANAGER' => 'Omborchi',
    'CASH' => 'Naqd pul', 'CARD' => 'Karta', 'BANK' => 'Bank', 'FULL' => 'To‘liq to‘lov', 'PARTIAL' => 'Qisman to‘lov', 'DEBT' => 'Nasiya', 'UNPAID' => 'To‘lanmagan',
    'SALE_PAYMENT' => 'Sotuvdan tushgan pul', 'CUSTOMER_PAYMENT' => 'Mijoz qarzini to‘ladi', 'SUPPLIER_PAYMENT' => 'Yetkazuvchiga to‘lov', 'EXPENSE' => 'Do‘kon xarajati',
    'TRANSFER_IN' => 'Boshqa hisobdan o‘tkazildi', 'TRANSFER_OUT' => 'Boshqa hisobga o‘tkazildi', 'OWNER_DEPOSIT' => 'Egadan pul olindi', 'OWNER_WITHDRAWAL', 'OWNER_DRAW' => 'Egaga pul berildi',
    'OPENING_BALANCE' => 'Boshlang‘ich qoldiq', 'REFUND' => 'Pul qaytarildi', 'ADJUSTMENT' => 'Hisob tuzatildi',
    'PAYMENT_IN' => 'Pul kirimi', 'PAYMENT_OUT' => 'Pul chiqimi',
    'SALE' => 'Sotuv', 'PURCHASE' => 'Mahsulot kirimi', 'PAYMENT' => 'To‘lov', 'COMPLETED' => 'Yakunlangan', 'CANCELLED' => 'Bekor qilingan',
    'PARTIALLY_REFUNDED' => 'Qisman qaytarilgan', 'FULLY_REFUNDED', 'REFUNDED' => 'Qaytarilgan',
    'ACTIVE' => 'Faol', 'INACTIVE' => 'O‘chirilgan', 'REVOKED' => 'Ulana olmaydi', 'EXPIRED' => 'Muddati tugagan', 'SUSPENDED' => 'Vaqtincha to‘xtatilgan',
    'OPEN' => 'Ish kuni ochiq', 'CLOSED' => 'Ish kuni yopilgan', 'NEEDS_REVIEW' => 'Tekshirish kerak', 'PENDING_APPROVAL' => 'Tasdiqlash kutilmoqda', 'APPROVED' => 'Tasdiqlangan', 'REJECTED' => 'Rad etilgan',
    'PWA', 'WEB' => 'Kompyuter brauzeri', 'ANDROID' => 'Android telefon', 'IOS' => 'iPhone',
    default => $value,
} }}

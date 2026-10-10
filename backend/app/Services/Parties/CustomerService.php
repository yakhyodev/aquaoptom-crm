<?php

namespace App\Services\Parties;

use App\Exceptions\CannotDeleteReferencedRecordException;
use App\Models\Customer;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    /**
     * Mijoz yaratish yoki yangilash.
     *
     * Biznes qoidalari:
     * - Ism majburiy.
     * - Telefon foydali aloqa maydoni, lekin hamma mijoz uchun mavjud deb majbur qilinmaydi.
     * - Telefon bo'lmasa mijoz ismi bilan birga do'kon nomi yoki manzil talab qilinadi.
     * - Telefon yoki o'xshash ism bilan avtomatik taraf merge yo'q.
     * - Offline mijoz uchun barqaror UUID (idempotent retry).
     */
    public function createCustomer(array $data, ?int $createdBy = null): Customer
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Mijoz ismi kiritilishi shart.']);
        }

        $phone = ! empty($data['phone']) ? trim($data['phone']) : null;
        $storeName = ! empty($data['store_name']) ? trim($data['store_name']) : null;
        $address = ! empty($data['address']) ? trim($data['address']) : null;

        // Arxitektura talabi: Telefon bo'lmasa, ism bilan birga do'kon nomi yoki manzil talab qilinadi!
        if (empty($phone) && empty($storeName) && empty($address)) {
            throw ValidationException::withMessages([
                'phone' => 'Telefon ko\'rsatilmaganda mijozning do\'kon nomi yoki manzili kiritilishi shart!',
            ]);
        }

        // Offline barqaror UUID tekshiruvi (idempotent retry):
        if (! empty($data['uuid'])) {
            $existingByUuid = Customer::where('uuid', $data['uuid'])->first();
            if ($existingByUuid) {
                return $existingByUuid;
            }
            $uuid = $data['uuid'];
        } else {
            $uuid = (string) Str::uuid();
        }

        return Customer::create([
            'uuid' => $uuid,
            'name' => $name,
            'phone' => $phone,
            'store_name' => $storeName,
            'address' => $address,
            'debt_limit' => (int) ($data['debt_limit'] ?? 0),
            'current_debt' => (int) ($data['current_debt'] ?? 0),
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Telefon raqami bo'yicha ogohlantirish (avtomatik merge yo'q, lekin do'konga signal)
     */
    public function checkPhoneDuplicate(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $count = Customer::where('phone', trim($phone))->count();
        if ($count > 0) {
            return "Ushbu telefon raqami ({$phone}) bilan allaqachon {$count} ta mijoz mavjud. Tizim avtomatik birlashtirmaydi, alohida saqlanadi.";
        }

        return null;
    }

    /**
     * Mijoz qidiruvi:
     * Natija ko'rinishi: "Akmal — Bahor Market — tel: +998901234567 — Chilonzor"
     */
    public function search(string $query, int $limit = 20)
    {
        $term = trim($query);
        if ($term === '') {
            return Customer::where('status', 'active')
                ->orderBy('name')->orderBy('id')
                ->limit($limit)
                ->get();
        }

        $customers = Customer::where('status', 'active');
        foreach (preg_split('/\s+/u', $term) as $word) {
            $customers->where(function ($query) use ($word) {
                $query->where('name', 'ilike', "%{$word}%")
                    ->orWhere('store_name', 'ilike', "%{$word}%")
                    ->orWhere('phone', 'like', "%{$word}%")
                    ->orWhere('address', 'ilike', "%{$word}%");
                if (preg_match('/^[\d+()\-]+$/', $word)) {
                    $digits = preg_replace('/\D/', '', $word);
                    if ($digits !== '') {
                        $query->orWhereRaw("regexp_replace(phone, '[^0-9]', '', 'g') LIKE ?", ["%{$digits}%"]);
                    }
                }
            });
        }

        return $customers
            ->orderBy('name')->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Mijozni arxivlash
     */
    public function archiveCustomer(Customer $customer): bool
    {
        return $customer->update(['status' => 'archived']);
    }

    /**
     * Tarixda ishlatilgan mijozni o'chirib bo'lmaydi
     */
    public function deleteCustomer(Customer $customer): bool
    {
        if ($customer->hasHistoricalRecords()) {
            throw new CannotDeleteReferencedRecordException(
                "Ushbu mijoz savdo yoki qarz daftarlarida mavjud, uni o'chirib bo'lmaydi! Uni arxivlash mumkin."
            );
        }

        return (bool) $customer->delete();
    }
}

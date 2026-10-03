<?php

namespace App\Services\Parties;

use App\Exceptions\CannotDeleteReferencedRecordException;
use App\Models\Supplier;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function createSupplier(array $data, ?int $createdBy = null): Supplier
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Ta\'minotchi nomi kiritilishi shart.']);
        }

        if (! empty($data['uuid'])) {
            $existing = Supplier::where('uuid', $data['uuid'])->first();
            if ($existing) {
                return $existing;
            }
            $uuid = $data['uuid'];
        } else {
            $uuid = (string) Str::uuid();
        }

        return Supplier::create([
            'uuid' => $uuid,
            'name' => $name,
            'company_name' => ! empty($data['company_name']) ? trim($data['company_name']) : null,
            'phone' => ! empty($data['phone']) ? trim($data['phone']) : null,
            'address' => ! empty($data['address']) ? trim($data['address']) : null,
            'balance' => (int) ($data['balance'] ?? 0),
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
            'created_by' => $createdBy,
        ]);
    }

    public function search(string $query, int $limit = 20)
    {
        $term = trim($query);
        if ($term === '') {
            return Supplier::where('status', 'active')
                ->latest()
                ->limit($limit)
                ->get();
        }

        return Supplier::where(function ($q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
                ->orWhere('company_name', 'ilike', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('address', 'ilike', "%{$term}%");
        })
            ->where('status', 'active')
            ->limit($limit)
            ->get();
    }

    public function archiveSupplier(Supplier $supplier): bool
    {
        return $supplier->update(['status' => 'archived']);
    }

    public function deleteSupplier(Supplier $supplier): bool
    {
        if ($supplier->hasHistoricalRecords()) {
            throw new CannotDeleteReferencedRecordException(
                "Ushbu ta'minotchi kirim hujjatlarida ishlatilgan, uni o'chirib bo'lmaydi! Uni arxivlash mumkin."
            );
        }

        return (bool) $supplier->delete();
    }
}

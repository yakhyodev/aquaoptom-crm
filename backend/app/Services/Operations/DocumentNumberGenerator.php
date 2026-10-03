<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;

class DocumentNumberGenerator
{
    /**
     * Sotuv hujjati raqami: INV-YYYY-000001 (Concurrency safe sequence)
     */
    public static function nextSalesInvoiceNumber(): string
    {
        return static::next('INV', 'sales_invoice_seq');
    }

    public static function nextSaleInvoiceNumber(): string
    {
        return static::nextSalesInvoiceNumber();
    }

    /**
     * Kirim hujjati raqami: PUR-YYYY-000001
     */
    public static function nextPurchaseInvoiceNumber(): string
    {
        return static::next('PUR', 'purchases_invoice_seq');
    }

    /**
     * Pul to'lovi hujjati raqami: PAY-YYYY-000001
     */
    public static function nextPaymentNumber(): string
    {
        return static::next('PAY', 'payments_number_seq');
    }

    /**
     * Qaytarish hujjati raqami: RET-YYYY-000001
     */
    public static function nextReturnNumber(): string
    {
        return static::next('RET', 'returns_number_seq');
    }

    /**
     * Boshlang'ich qoldiq hujjati raqami: OPN-YYYY-000001
     */
    public static function nextOpeningNumber(): string
    {
        return static::next('OPN', 'opening_doc_seq');
    }

    /**
     * Umumiy ketma-ketlik raqamini xavfsiz generatsiya qilish (max(id)+1 ishlatilmaydi)
     */
    public static function next(string $prefix, string $sequenceName): string
    {
        $year = date('Y');

        if (DB::getDriverName() === 'pgsql') {
            $seq = DB::select("SELECT nextval('{$sequenceName}') as seq")[0]->seq;

            return sprintf('%s-%s-%06d', $prefix, $year, $seq);
        }

        // Fallback for non-postgres environments (testing mocks)
        $random = mt_rand(1, 999999);

        return sprintf('%s-%s-%06d', $prefix, $year, $random);
    }
}

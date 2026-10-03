<?php

namespace App\Services\Ledger\Exceptions;

use App\Services\Operations\Exceptions\OperationValidationException;

class ReservedStockProtectionException extends OperationValidationException
{
    public function __construct(
        string $message = "Ombordan tovar chiqimi amalga oshirilishi mumkin emas! Chiqimdan keyingi qoldiq faol qurilmalarga ajratilgan rezervlar yig'indisidan kam bo'lib qoladi. Avval qurilmalardagi rezervlarni qaytaring.",
        ?string $operationId = null,
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId ?: 'reserved-stock-prot-err',
            message: $message,
            details: $details,
            errorCode: 'RESERVED_STOCK_PROTECTED'
        );
    }
}

<?php

namespace App\Services\Ledger\Exceptions;

use App\Services\Operations\Exceptions\OperationValidationException;

class InsufficientStockException extends OperationValidationException
{
    public function __construct(
        string $message = "Omborda yetarli tovar qoldig'i mavjud emas!",
        ?string $operationId = null,
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId ?: 'stock-val-err',
            message: $message,
            details: $details,
            errorCode: 'INSUFFICIENT_STOCK'
        );
    }
}

<?php

namespace App\Services\Ledger\Exceptions;

use App\Services\Operations\Exceptions\OperationValidationException;

class CannotReturnMoreThanSoldException extends OperationValidationException
{
    public function __construct(
        string $message = "Sotilgan miqdordan ortiq tovar qaytarib bo'lmaydi!",
        ?string $operationId = null,
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId ?: 'cannot-return-more-than-sold',
            message: $message,
            details: $details,
            errorCode: 'RETURN_EXCEEDS_SOLD_QUANTITY'
        );
    }
}

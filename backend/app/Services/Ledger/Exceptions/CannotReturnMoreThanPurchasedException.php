<?php

namespace App\Services\Ledger\Exceptions;

use App\Services\Operations\Exceptions\OperationValidationException;

class CannotReturnMoreThanPurchasedException extends OperationValidationException
{
    public function __construct(
        string $message = "Xarid qilingan miqdordan ortiq tovar ta'minotchiga qaytarib bo'lmaydi!",
        ?string $operationId = null,
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId ?: 'cannot-return-more-than-purchased',
            message: $message,
            details: $details,
            errorCode: 'RETURN_EXCEEDS_PURCHASED_QUANTITY'
        );
    }
}

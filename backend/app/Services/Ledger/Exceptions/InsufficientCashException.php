<?php

namespace App\Services\Ledger\Exceptions;

use App\Services\Operations\Exceptions\OperationValidationException;

class InsufficientCashException extends OperationValidationException
{
    public function __construct(
        string $message = "Kassada yetarli pul mablag'i mavjud emas!",
        ?string $operationId = null,
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId ?: 'cash-val-err',
            message: $message,
            details: $details,
            errorCode: 'INSUFFICIENT_CASH'
        );
    }
}

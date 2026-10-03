<?php

namespace App\Services\Ledger\Exceptions;

use App\Services\Operations\Exceptions\OperationValidationException;

class InsufficientCreditAllocationException extends OperationValidationException
{
    public function __construct(
        string $message = 'Qurilmada yoki mijoz uchun ajratilgan kredit limiti yetarli emas!',
        ?string $operationId = null,
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId ?: 'credit-alloc-err',
            message: $message,
            details: $details,
            errorCode: 'INSUFFICIENT_CREDIT_ALLOCATION'
        );
    }
}

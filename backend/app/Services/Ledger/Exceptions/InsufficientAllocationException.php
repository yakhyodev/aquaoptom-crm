<?php

namespace App\Services\Ledger\Exceptions;

use App\Services\Operations\Exceptions\OperationValidationException;

class InsufficientAllocationException extends OperationValidationException
{
    public function __construct(
        string $message = 'Qurilmada yetarli tovar ajratmasi (rezervi) mavjud emas!',
        ?string $operationId = null,
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId ?: 'alloc-stock-err',
            message: $message,
            details: $details,
            errorCode: 'INSUFFICIENT_STOCK_ALLOCATION'
        );
    }
}

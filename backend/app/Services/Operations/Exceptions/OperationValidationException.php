<?php

namespace App\Services\Operations\Exceptions;

class OperationValidationException extends OperationException
{
    public function __construct(
        string $operationId,
        string $message,
        array $details = [],
        string $errorCode = 'VALIDATION_FAILED'
    ) {
        parent::__construct(
            operationId: $operationId,
            errorCategory: 'validation',
            errorCode: $errorCode,
            message: $message,
            details: $details,
            statusCode: 422
        );
    }
}

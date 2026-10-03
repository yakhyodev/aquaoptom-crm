<?php

namespace App\Services\Operations\Exceptions;

class OperationRetryableException extends OperationException
{
    public function __construct(
        string $operationId,
        string $message = 'Tizim vaqtincha band yoki tarmoq uzilishi. Qayta urinib ko\'ring.',
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId,
            errorCategory: 'retryable',
            errorCode: 'TEMPORARY_RETRYABLE_ERROR',
            message: $message,
            details: $details,
            statusCode: 503
        );
    }
}

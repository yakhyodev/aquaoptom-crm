<?php

namespace App\Services\Devices\Exceptions;

use App\Services\Operations\Exceptions\OperationValidationException;

class LeaseExpiredException extends OperationValidationException
{
    public function __construct(
        string $message = 'Qurilma ruxsat guvohnomasi (lease) muddati tugagan! Yangi ruxsat talab etiladi.',
        ?string $operationId = null,
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId ?: 'lease-exp-err',
            message: $message,
            details: $details,
            errorCode: 'LEASE_EXPIRED'
        );
    }
}

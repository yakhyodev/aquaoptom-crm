<?php

namespace App\Services\Devices\Exceptions;

use App\Services\Operations\Exceptions\OperationValidationException;

class InvalidLeaseException extends OperationValidationException
{
    public function __construct(
        string $message = "Qurilma ruxsat guvohnomasi (lease) imzosi noto'g'ri yoki bekor qilingan!",
        ?string $operationId = null,
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId ?: 'lease-inv-err',
            message: $message,
            details: $details,
            errorCode: 'INVALID_LEASE'
        );
    }
}

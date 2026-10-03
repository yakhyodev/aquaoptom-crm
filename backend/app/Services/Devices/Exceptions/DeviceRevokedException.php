<?php

namespace App\Services\Devices\Exceptions;

use App\Services\Operations\Exceptions\OperationValidationException;

class DeviceRevokedException extends OperationValidationException
{
    public function __construct(
        string $message = 'Qurilma tizimda bloklangan yoki bekor qilingan!',
        ?string $operationId = null,
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId ?: 'dev-revoked-err',
            message: $message,
            details: $details,
            errorCode: 'DEVICE_REVOKED'
        );
    }
}

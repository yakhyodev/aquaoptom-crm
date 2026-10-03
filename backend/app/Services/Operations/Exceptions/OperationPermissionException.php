<?php

namespace App\Services\Operations\Exceptions;

class OperationPermissionException extends OperationException
{
    public function __construct(
        string $operationId,
        string $message = 'Ushbu operatsiyani bajarish uchun ruxsat mavjud emas.',
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId,
            errorCategory: 'permission',
            errorCode: 'PERMISSION_DENIED',
            message: $message,
            details: $details,
            statusCode: 403
        );
    }
}

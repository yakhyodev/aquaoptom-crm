<?php

namespace App\Services\Operations\Exceptions;

class OperationConflictException extends OperationException
{
    public function __construct(
        string $operationId,
        string $message = 'Ushbu operation_id allaqachon boshqa ma\'lumotlar bilan bajarilgan! Operatsiya parametrlari o\'zgargan bo\'lsa yangi operation_id kiritilishi shart.',
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId,
            errorCategory: 'conflict',
            errorCode: 'OPERATION_PAYLOAD_CONFLICT',
            message: $message,
            details: $details,
            statusCode: 409
        );
    }
}

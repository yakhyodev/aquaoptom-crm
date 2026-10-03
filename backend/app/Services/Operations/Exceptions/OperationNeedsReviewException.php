<?php

namespace App\Services\Operations\Exceptions;

class OperationNeedsReviewException extends OperationException
{
    public function __construct(
        string $operationId,
        string $message = 'Ushbu operatsiya tafovut yoki ziddiyat aniqlanganligi sababli rahbariyat/mas\'ul tekshiruvini talab qiladi.',
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId,
            errorCategory: 'needs_review',
            errorCode: 'OPERATION_NEEDS_REVIEW',
            message: $message,
            details: $details,
            statusCode: 422
        );
    }
}

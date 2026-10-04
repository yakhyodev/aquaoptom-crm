<?php

namespace App\Services\Ledger\Exceptions;

use App\Services\Operations\Exceptions\OperationValidationException;

class DocumentImmutableException extends OperationValidationException
{
    public function __construct(
        string $message = "Tasdiqlangan buxgalteriya/ombor hujjati o'chirilishi yoki bevosita tahrirlanishi taqiqlanadi! Barcha tuzatishlar faqat yangi qarama-qarshi operatsiya orqali yoziladi.",
        ?string $operationId = null,
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId ?: 'document-immutable',
            message: $message,
            details: $details,
            errorCode: 'DOCUMENT_IMMUTABLE'
        );
    }
}

<?php

namespace App\Services\Operations\Exceptions;

use Exception;

class OperationException extends Exception
{
    public function __construct(
        public string $operationId,
        public string $errorCategory, // validation, permission, retryable, needs_review, conflict
        public string $errorCode,
        string $message,
        public array $details = [],
        public int $statusCode = 400,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function toResponseArray(): array
    {
        return [
            'success' => false,
            'operation_id' => $this->operationId,
            'error' => [
                'category' => $this->errorCategory,
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'details' => $this->details,
            ],
        ];
    }
}

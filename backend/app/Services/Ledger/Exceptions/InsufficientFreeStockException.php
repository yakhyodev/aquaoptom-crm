<?php

namespace App\Services\Ledger\Exceptions;

use App\Services\Operations\Exceptions\OperationValidationException;

class InsufficientFreeStockException extends OperationValidationException
{
    public function __construct(
        string $message = "Omborda yetarli erkin tovar qoldig'i mavjud emas! Qoldiqning bir qismi boshqa qurilmalarga rezerv qilingan.",
        ?string $operationId = null,
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId ?: 'free-stock-val-err',
            message: $message,
            details: $details,
            errorCode: 'INSUFFICIENT_FREE_STOCK'
        );
    }
}

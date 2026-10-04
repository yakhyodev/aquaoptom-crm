<?php

namespace App\Services\Ledger\Exceptions;

use App\Services\Operations\Exceptions\OperationValidationException;

class DeviceFreezePendingException extends OperationValidationException
{
    public function __construct(
        string $message = "Inventarizatsiyani yakunlash uchun faol ajratmaga ega uzilgan qurilmalar muzlatish tasdig'ini (freeze ACK) kuting yoki maxsus ruxsat bilan tasdiqlang.",
        ?string $operationId = null,
        array $details = []
    ) {
        parent::__construct(
            operationId: $operationId ?: 'device-freeze-pending',
            message: $message,
            details: $details,
            errorCode: 'DEVICE_FREEZE_PENDING'
        );
    }
}

<?php

namespace App\Services\Sync\Exceptions;

use Exception;

class RecoveryReconciliationRequiredException extends Exception
{
    public function __construct(
        string $message,
        public int $recoveryEpoch,
        public ?string $recoveryWatermark = null,
        int $code = 428
    ) {
        parent::__construct($message, $code);
    }
}

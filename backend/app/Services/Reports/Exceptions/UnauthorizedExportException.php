<?php

namespace App\Services\Reports\Exceptions;

use Exception;

class UnauthorizedExportException extends Exception
{
    protected $message = 'Hisobotlarni eksport qilish huquqi mavjud emas.';
}

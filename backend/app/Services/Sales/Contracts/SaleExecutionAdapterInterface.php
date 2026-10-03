<?php

namespace App\Services\Sales\Contracts;

use Illuminate\Support\Fluent;

interface SaleExecutionAdapterInterface
{
    /**
     * Savdoni qabul qilish va bajarish.
     */
    public function processSale(array $saleData): Fluent;
}

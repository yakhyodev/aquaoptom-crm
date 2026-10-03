<?php

namespace App\Services\Sales\Adapters;

use App\Services\Sales\Contracts\SaleExecutionAdapterInterface;
use App\Services\Sales\CreateSaleService;
use Illuminate\Support\Fluent;

class OnlineSaleAdapter implements SaleExecutionAdapterInterface
{
    public function __construct(
        protected CreateSaleService $createSaleService
    ) {}

    public function processSale(array $saleData): Fluent
    {
        $sale = $this->createSaleService->execute(
            customerId: $saleData['customer_id'] ?? null,
            items: $saleData['items'] ?? [],
            operationId: $saleData['operation_id'] ?? null,
            paidAmount: (int) ($saleData['paid_amount'] ?? 0),
            cashAccountId: isset($saleData['cash_account_id']) ? (int) $saleData['cash_account_id'] : null,
            paymentType: $saleData['payment_type'] ?? 'CASH',
            paymentMethod: $saleData['payment_method'] ?? 'CASH',
            notes: $saleData['notes'] ?? null,
            warehouseId: isset($saleData['warehouse_id']) ? (int) $saleData['warehouse_id'] : null,
            userId: isset($saleData['user_id']) ? (int) $saleData['user_id'] : null,
            source: $saleData['source'] ?? 'web'
        );

        return new Fluent([
            'sale_id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'total_amount' => $sale->total_amount,
            'paid_amount' => $sale->paid_amount,
            'debt_amount' => $sale->debt_amount,
            'total_cost' => $sale->total_cost,
            'gross_profit' => $sale->gross_profit,
            'customer_name' => $sale->customer_name,
            'receipt_data' => $sale->receipt_data,
            'sale' => $sale,
        ]);
    }
}

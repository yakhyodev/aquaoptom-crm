<?php

namespace App\Services\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Carbon\Carbon;

class ReconcileInventoryService
{
    /**
     * Ledger va Balance orasidagi nomuvofiqlikni tekshirish va to'g'irlash
     *
     * @param  bool  $fixDiscrepancies  Agar true bo'lsa balance ni ledger ga tenglashtiradi
     * @return array Aniqlangan nomuvofiqliklar ro'yxati
     */
    public function execute(bool $fixDiscrepancies = false): array
    {
        $discrepancies = [];
        $variants = ProductVariant::with('product', 'volume')->get();
        $warehouses = Warehouse::all();

        foreach ($warehouses as $wh) {
            foreach ($variants as $variant) {
                // 1. Ledger (Source of Truth) yig'indisi
                $ledgerSum = (float) InventoryMovement::where('product_variant_id', $variant->id)
                    ->where('warehouse_id', $wh->id)
                    ->sum('quantity');

                // 2. Cached Balance
                $balance = InventoryBalance::where('product_variant_id', $variant->id)
                    ->where('warehouse_id', $wh->id)
                    ->first();

                $currentBalance = $balance ? (float) $balance->quantity : 0.0;

                // 3. Taqqoslash
                if (abs($ledgerSum - $currentBalance) > 0.001) {
                    $item = [
                        'variant_id' => $variant->id,
                        'product_name' => $variant->product->name,
                        'volume' => $variant->volume->name ?? 'N/A',
                        'warehouse' => $wh->name,
                        'ledger_quantity' => $ledgerSum,
                        'balance_quantity' => $currentBalance,
                        'diff' => $ledgerSum - $currentBalance,
                    ];

                    if ($fixDiscrepancies) {
                        if (! $balance) {
                            $balance = new InventoryBalance;
                            $balance->product_variant_id = $variant->id;
                            $balance->warehouse_id = $wh->id;
                            $balance->average_cost = 0;
                        }
                        $balance->quantity = $ledgerSum;
                        $balance->updated_at = Carbon::now();
                        $balance->save();
                        $item['fixed'] = true;
                    }

                    $discrepancies[] = $item;
                }
            }
        }

        return $discrepancies;
    }
}

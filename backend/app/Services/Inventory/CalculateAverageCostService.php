<?php

namespace App\Services\Inventory;

class CalculateAverageCostService
{
    /**
     * Weighted Average Cost (WAC) formulasi
     *
     * @param  float|string  $currentStock  Ombordagi joriy qoldiq (dona)
     * @param  int  $currentAverageCost  Hozirgi o'rtacha tannarx (so'm)
     * @param  float|string  $incomingQuantity  Yangi kelgan miqdor (dona)
     * @param  int  $incomingUnitCost  Yangi kelgan tovarning 1 dona kirim narxi (so'm)
     * @return int Yangi o'rtacha tannarx (butun so'mda)
     */
    public function execute($currentStock, int $currentAverageCost, $incomingQuantity, int $incomingUnitCost): int
    {
        $currentStock = (float) $currentStock;
        $incomingQuantity = (float) $incomingQuantity;

        if ($currentStock <= 0) {
            return $incomingUnitCost;
        }

        $oldTotalCost = $currentStock * $currentAverageCost;
        $newIncomingCost = $incomingQuantity * $incomingUnitCost;
        $newTotalStock = $currentStock + $incomingQuantity;

        if ($newTotalStock <= 0) {
            return $incomingUnitCost;
        }

        return (int) round(($oldTotalCost + $newIncomingCost) / $newTotalStock);
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Inventory\ReconcileInventoryService;
use Illuminate\Console\Command;

class ReconcileInventoryCommand extends Command
{
    protected $signature = 'inventory:reconcile {--fix : Nomuvofiqlik aniqlansa balanceni to\'g\'irlash}';

    protected $description = 'Inventory ledger (Source of Truth) va Inventory balance nomuvofiqligini tekshirish';

    public function handle(ReconcileInventoryService $service): int
    {
        $fix = (bool) $this->option('fix');
        $this->info('Ombor audit tekshiruvi boshlandi...');

        $discrepancies = $service->execute($fix);

        if (empty($discrepancies)) {
            $this->info('✓ Hech qanday nomuvofiqlik topilmadi. Barcha harakatlar (Movements) va Qoldiqlar (Balances) 100% mos!');

            return 0;
        }

        $this->warn('⚠️  '.count($discrepancies).' ta nomuvofiqlik aniqlandi:');
        $tableData = [];
        foreach ($discrepancies as $d) {
            $tableData[] = [
                $d['product_name'].' '.$d['volume'],
                $d['warehouse'],
                $d['ledger_quantity'],
                $d['balance_quantity'],
                $d['diff'],
                isset($d['fixed']) && $d['fixed'] ? 'TO\'G\'IRLANDI' : 'FARQ BOR',
            ];
        }

        $this->table(['Mahsulot', 'Ombor', 'Ledger (Harakatlar)', 'Balance (Joriy)', 'Farq', 'Holat'], $tableData);

        if (! $fix) {
            $this->comment("Farqlarni avtomatik to'g'irlash uchun buyruqqa --fix kalitini qo'shing: php artisan inventory:reconcile --fix");
        }

        return 1;
    }
}

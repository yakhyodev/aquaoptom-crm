<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class BackupDrillCommand extends Command
{
    protected $signature = 'app:backup-drill 
                            {--target-db=aquaoptom_restore_test : Isolated database name for restore drill}
                            {--keep : Keep the isolated restore database after verification}';

    protected $description = 'Perform an automated end-to-end backup and restore drill into an isolated database';

    public function handle(BackupService $backupService): int
    {
        $this->info('=== AquaOptom CRM: Automated Backup & Disaster Recovery Drill ===');
        $drillStart = microtime(true);
        $isolatedDb = $this->option('target-db');

        // 1. Zaxira nusxa yaratish
        $this->line('1. Creating full encrypted backup...');
        $backup = $backupService->createBackup(['encrypt' => true]);
        $this->info("   -> Created: {$backup['file_name']} (Checksum: ".substr($backup['checksum'], 0, 16).'...)');

        // 2. Izolyatsiya qilingan yangi bazaga tiklash (Restore Drill)
        $this->line("2. Restoring backup into isolated database '{$isolatedDb}'...");
        $restore = $backupService->restoreBackup($backup['file_path'], [
            'target_db' => $isolatedDb,
            'restore_files' => false,
        ]);
        $this->info("   -> Restored successfully into '{$isolatedDb}'!");

        // 3. Ma'lumotlar va ledger yaxlitligini tekshirish
        $this->line('3. Verifying ledger balances and financial parity...');

        $config = config('database.connections.pgsql');
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '5432';
        $username = $config['username'] ?? 'postgres';
        $password = $config['password'] ?? '';

        $isolatedPdo = new PDO("pgsql:host={$host};port={$port};dbname={$isolatedDb}", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Jadvallarni sanash
        $sourceTables = (int) DB::selectOne("SELECT count(*) as cnt FROM pg_tables WHERE schemaname = 'public'")->cnt;
        $restoredTables = (int) $isolatedPdo->query("SELECT count(*) as cnt FROM pg_tables WHERE schemaname = 'public'")->fetch(PDO::FETCH_OBJ)->cnt;

        // Foydalanuvchilar
        $sourceUsers = (int) DB::table('users')->count();
        $restoredUsers = (int) $isolatedPdo->query('SELECT count(*) as cnt FROM users')->fetch(PDO::FETCH_OBJ)->cnt;

        // Mahsulotlar
        $sourceProducts = (int) DB::table('products')->count();
        $restoredProducts = (int) $isolatedPdo->query('SELECT count(*) as cnt FROM products')->fetch(PDO::FETCH_OBJ)->cnt;

        // Ombor qoldig'i jami summasi
        $sourceStockVal = (int) (DB::table('inventory_balances')->sum('total_value') ?? 0);
        $restoredStockVal = (int) ($isolatedPdo->query('SELECT COALESCE(SUM(total_value), 0) as val FROM inventory_balances')->fetch(PDO::FETCH_OBJ)->val ?? 0);

        // Kassa harakatlari summasi
        $sourceCash = (int) (DB::table('cash_movements')->sum('amount') ?? 0);
        $restoredCash = (int) ($isolatedPdo->query('SELECT COALESCE(SUM(amount), 0) as val FROM cash_movements')->fetch(PDO::FETCH_OBJ)->val ?? 0);

        // Mijozlar sof qarzi (debit - credit)
        $sourceCustDebt = (int) (DB::table('customer_ledger')->selectRaw('COALESCE(SUM(debit - credit), 0) as val')->value('val') ?? 0);
        $restoredCustDebt = (int) ($isolatedPdo->query('SELECT COALESCE(SUM(debit - credit), 0) as val FROM customer_ledger')->fetch(PDO::FETCH_OBJ)->val ?? 0);

        // Ta'minotchilar sof qarzi (credit - debit)
        $sourceSuppDebt = (int) (DB::table('supplier_ledger')->selectRaw('COALESCE(SUM(credit - debit), 0) as val')->value('val') ?? 0);
        $restoredSuppDebt = (int) ($isolatedPdo->query('SELECT COALESCE(SUM(credit - debit), 0) as val FROM supplier_ledger')->fetch(PDO::FETCH_OBJ)->val ?? 0);

        $drillDurationSec = round(microtime(true) - $drillStart, 2);

        $this->table(
            ['Metrika', 'Asosiy Baza', 'Tiklagan Baza (Isolated)', 'Holat'],
            [
                ['Jadvallar soni', $sourceTables, $restoredTables, $sourceTables === $restoredTables ? 'MATCH' : 'MISMATCH'],
                ['Foydalanuvchilar', $sourceUsers, $restoredUsers, $sourceUsers === $restoredUsers ? 'MATCH' : 'MISMATCH'],
                ['Mahsulotlar', $sourceProducts, $restoredProducts, $sourceProducts === $restoredProducts ? 'MATCH' : 'MISMATCH'],
                ['Ombor qiymati (so\'m)', number_format($sourceStockVal), number_format($restoredStockVal), $sourceStockVal === $restoredStockVal ? 'MATCH' : 'MISMATCH'],
                ['Kassa harakatlari (so\'m)', number_format($sourceCash), number_format($restoredCash), $sourceCash === $restoredCash ? 'MATCH' : 'MISMATCH'],
                ['Mijozlar daftari (so\'m)', number_format($sourceCustDebt), number_format($restoredCustDebt), $sourceCustDebt === $restoredCustDebt ? 'MATCH' : 'MISMATCH'],
                ['Ta\'minotchilar daftari (so\'m)', number_format($sourceSuppDebt), number_format($restoredSuppDebt), $sourceSuppDebt === $restoredSuppDebt ? 'MATCH' : 'MISMATCH'],
            ]
        );

        $allMatch = ($sourceTables === $restoredTables)
            && ($sourceUsers === $restoredUsers)
            && ($sourceProducts === $restoredProducts)
            && ($sourceStockVal === $restoredStockVal)
            && ($sourceCash === $restoredCash)
            && ($sourceCustDebt === $restoredCustDebt)
            && ($sourceSuppDebt === $restoredSuppDebt);

        // 4. Tozalash (agar --keep bo'lmasa)
        if (! $this->option('keep')) {
            $this->line("4. Cleaning up isolated test database '{$isolatedDb}'...");
            unset($isolatedPdo); // disconnect
            DB::statement("DROP DATABASE IF EXISTS \"{$isolatedDb}\";");
            $this->info('   -> Cleaned up successfully.');
        }

        if (! $allMatch) {
            AuditLog::create(['action' => 'RESTORE_DRILL_FAILED', 'new_values' => ['backup_id' => $backup['backup_id']], 'created_at' => now()]);
            $this->error('DRILL FAILED: Financial or schema mismatch between source and restored database!');

            return self::FAILURE;
        }

        $this->info('=== RESTORE DRILL CERTIFICATE: PASSED 100% ===');
        AuditLog::create(['action' => 'RESTORE_DRILL_PASSED', 'new_values' => [
            'backup_id' => $backup['backup_id'], 'duration_seconds' => $drillDurationSec,
            'target_db' => $isolatedDb, 'database_parity' => true, 'files_restored' => false,
        ], 'created_at' => now()]);
        $this->line("Drill Duration: {$drillDurationSec} seconds (RTO Target: < 2 hours)");
        $this->line('RPO depends on the age of the latest verified offsite backup; this drill checks database restore parity.');
        $this->line('Ledger Parity: All inventory, cash, customer and supplier ledgers match 100%.');

        return self::SUCCESS;
    }
}

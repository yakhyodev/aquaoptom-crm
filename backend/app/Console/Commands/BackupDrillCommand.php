<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        if (! preg_match('/^aquaoptom_restore_[a-z0-9_]{1,40}$/', $isolatedDb)
            || $isolatedDb === config('database.connections.pgsql.database')
            || DB::selectOne('SELECT 1 AS present FROM pg_database WHERE datname = ?', [$isolatedDb])) {
            $this->error('Restore drill requires a new isolated aquaoptom_restore_* database. Existing databases are protected.');

            return self::FAILURE;
        }

        $backup = null;
        $isolatedPdo = null;
        $downloaded = null;
        $isolationStarted = false;
        $filesTarget = $backupService->getBackupStoragePath().'/drill_files_'.Str::uuid();
        try {
            // 1. Zaxira nusxa yaratish
            $this->line('1. Creating full encrypted backup...');
            $backup = $backupService->createBackup(['encrypt' => true]);
            $this->info("   -> Created: {$backup['file_name']} (Checksum: ".substr($backup['checksum'], 0, 16).'...)');

            $archivePath = $backup['file_path'];
            $offsiteVerified = false;
            if (config('backup.offsite_disk')) {
                if (($backup['offsite']['status'] ?? '') !== 'COPIED') {
                    throw new \RuntimeException('Verified offsite copy is required for the drill.');
                }
                $disk = Storage::disk(config('backup.offsite_disk'));
                $downloaded = $backupService->getBackupStoragePath().'/drill_download_'.Str::uuid().'.enc';
                $stream = $disk->readStream($backup['offsite']['path']);
                $output = fopen($downloaded, 'wb');
                try {
                    if (! is_resource($stream) || ! is_resource($output) || stream_copy_to_stream($stream, $output) === false) {
                        throw new \RuntimeException('Offsite backup download failed.');
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    if (is_resource($output)) {
                        fclose($output);
                    }
                }
                if (! hash_equals($backup['checksum'], hash_file('sha256', $downloaded))) {
                    throw new \RuntimeException('Downloaded offsite backup checksum mismatch.');
                }
                $archivePath = $downloaded;
                $offsiteVerified = true;
            }

            // 2. Izolyatsiya qilingan yangi bazaga tiklash (Restore Drill)
            $this->line("2. Restoring backup into isolated database '{$isolatedDb}'...");
            $isolationStarted = true;
            $restore = $backupService->restoreBackup($archivePath, [
                'target_db' => $isolatedDb,
                'restore_files' => true,
                'files_target' => $filesTarget,
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

            if (! $allMatch) {
                AuditLog::create(['action' => 'RESTORE_DRILL_FAILED', 'new_values' => ['backup_id' => $backup['backup_id']], 'created_at' => now()]);
                $this->error('DRILL FAILED: Financial or schema mismatch between source and restored database!');

                return self::FAILURE;
            }

            $this->info('=== RESTORE DRILL CERTIFICATE: PASSED 100% ===');
            AuditLog::create(['action' => 'RESTORE_DRILL_PASSED', 'new_values' => [
                'backup_id' => $backup['backup_id'], 'duration_seconds' => $drillDurationSec,
                'target_db' => $isolatedDb, 'database_parity' => true, 'files_restored' => true, 'offsite_verified' => $offsiteVerified,
            ], 'created_at' => now()]);
            $this->line("Drill Duration: {$drillDurationSec} seconds (RTO Target: < 2 hours)");
            $this->line('RPO depends on the age of the latest verified offsite backup; this drill checks database restore parity.');
            $this->line('Ledger Parity: All inventory, cash, customer and supplier ledgers match 100%.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            AuditLog::create(['action' => 'RESTORE_DRILL_FAILED', 'new_values' => ['backup_id' => $backup['backup_id'] ?? null,
                'target_db' => $isolatedDb, 'exception_type' => $exception::class], 'created_at' => now()]);
            $this->error('Restore drill failed. Check server logs; the live store was not restored.');
            report($exception);

            return self::FAILURE;
        } finally {
            unset($isolatedPdo);
            if (! $this->option('keep') && $isolationStarted) {
                try {
                    DB::statement("DROP DATABASE IF EXISTS \"{$isolatedDb}\";");
                } catch (\Throwable $cleanupException) {
                    AuditLog::create(['action' => 'RESTORE_DRILL_FAILED', 'new_values' => ['target_db' => $isolatedDb, 'cleanup_failed' => true], 'created_at' => now()]);
                    report($cleanupException);

                    return self::FAILURE;
                }
                if (File::exists($filesTarget)) {
                    File::deleteDirectory($filesTarget);
                }
            }
            if ($downloaded) {
                File::delete($downloaded);
            }
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupRestoreCommand extends Command
{
    protected $signature = 'app:backup-restore 
                            {file : Path or filename of the backup file}
                            {--target-db= : Target database to restore into (default: active db)}
                            {--key= : Decryption key}
                            {--restore-files : Also restore application files}
                            {--force : Force restore without interactive confirmation}';

    protected $description = 'Restore a backup into specified database and update recovery epoch';

    public function handle(BackupService $backupService): int
    {
        $file = $this->argument('file');
        if (! file_exists($file)) {
            $defaultPath = $backupService->getBackupStoragePath() . '/' . $file;
            if (file_exists($defaultPath)) {
                $file = $defaultPath;
            } else {
                $this->error("Backup file not found: {$file}");
                return self::FAILURE;
            }
        }

        $targetDb = $this->option('target-db') ?: config('database.connections.pgsql.database');
        $isCurrentDb = ($targetDb === config('database.connections.pgsql.database'));

        if ($isCurrentDb && ! $this->option('force')) {
            if (! $this->confirm("WARNING: You are about to restore into the ACTIVE database '{$targetDb}'. Are you sure?")) {
                $this->info('Restoration cancelled.');
                return self::SUCCESS;
            }
        }

        $this->info("Restoring backup from: " . basename($file) . " into '{$targetDb}'...");

        $result = $backupService->restoreBackup($file, [
            'target_db' => $targetDb,
            'key' => $this->option('key'),
            'restore_files' => (bool) $this->option('restore-files'),
        ]);

        $this->info("Restore completed successfully!");
        $this->line("Target Database: {$result['target_database']}");
        $this->line("Recovery Epoch: {$result['recovery_epoch']}");
        $this->line("Watermark: {$result['watermark_timestamp']}");
        $this->line("Recovery Status: {$result['recovery_status']}");

        return self::SUCCESS;
    }
}

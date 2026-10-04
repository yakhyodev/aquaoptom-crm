<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupCreateCommand extends Command
{
    protected $signature = 'app:backup-create 
                            {--no-encrypt : Do not encrypt the backup bundle}
                            {--key= : Custom encryption key}';

    protected $description = 'Create a full encrypted backup of PostgreSQL database and application files';

    public function handle(BackupService $backupService): int
    {
        $this->info('Starting full system backup...');
        $encrypt = ! $this->option('no-encrypt');
        $key = $this->option('key');

        $result = $backupService->createBackup([
            'encrypt' => $encrypt,
            'key' => $key,
            'user_id' => null,
        ]);

        $this->info("Backup successfully created!");
        $this->line("File: {$result['file_name']}");
        $this->line("Size: " . round($result['size_bytes'] / 1024, 2) . " KB");
        $this->line("SHA-256: {$result['checksum']}");
        $this->line("Encrypted: " . ($result['is_encrypted'] ? 'YES (AES-256-CBC)' : 'NO'));
        $this->line("RPO Target: {$result['manifest']['rpo_target']}");
        $this->line("RTO Target: {$result['manifest']['rto_target']}");

        return self::SUCCESS;
    }
}

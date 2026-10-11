<?php

namespace App\Services\Backup;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupService
{
    public const RPO_TARGET = '15 minutes';

    public const RTO_TARGET = '2 hours';

    /**
     * Backup papkasi yo'li
     */
    public function getBackupStoragePath(): string
    {
        $path = storage_path('app/backups');
        if (! File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        return $path;
    }

    /**
     * Shifrlash kalitini olish
     */
    public function getEncryptionKey(?string $customKey = null): string
    {
        $key = $customKey ?: (config('backup.encryption_key') ?: config('app.key'));
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return hash('sha256', $key, true); // 32-byte binary key
    }

    /**
     * Yangi to'liq zaxira nusxa (Database + Files) yaratish
     */
    public function createBackup(array $options = []): array
    {
        $backupId = (string) Str::uuid();
        $timestamp = Carbon::now('UTC')->format('Ymd_His');
        $watermark = Carbon::now('UTC')->toIso8601String();
        $tmpDir = $this->getBackupStoragePath().'/tmp_'.$backupId;
        File::makeDirectory($tmpDir, 0755, true);

        try {
            // 1. Database dump olish
            $dumpFile = $tmpDir.'/db_dump.sql';
            $this->dumpDatabase($dumpFile);

            // 2. Fayllarni arxivlash
            $filesZip = $tmpDir.'/files.zip';
            $filesCount = $this->archiveFiles($filesZip);

            // 3. Jadvallar va ma'lumotlar hajmini aniqlash
            $tablesCount = count(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"));

            // 4. Manifest yaratish
            $manifest = [
                'backup_id' => $backupId,
                'created_at' => $watermark,
                'app_name' => config('app.name', 'AquaOptom CRM'),
                'app_version' => config('app.version', '1.0.0'),
                'rpo_target' => self::RPO_TARGET,
                'rto_target' => self::RTO_TARGET,
                'recovery_epoch' => (int) SystemSetting::get('system_recovery_epoch', 1),
                'watermark_timestamp' => $watermark,
                'database_name' => config('database.connections.pgsql.database'),
                'tables_count' => $tablesCount,
                'files_count' => $filesCount,
                'files_checksums' => collect(['private', 'public'])->flatMap(function ($directory) {
                    $path = storage_path('app/'.$directory);
                    if (! File::exists($path)) {
                        return [];
                    }

                    return collect(File::allFiles($path))->mapWithKeys(fn ($file) => [$directory.'/'.str_replace('\\', '/', $file->getRelativePathname()) => hash_file('sha256', $file->getRealPath())]);
                })->all(),
            ];
            File::put($tmpDir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // 5. Yagona ZIP arxivga birlashtirish
            $bundleZipPath = $this->getBackupStoragePath()."/aquaoptom_backup_{$timestamp}_{$backupId}.zip";
            $zip = new ZipArchive;
            if ($zip->open($bundleZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException("ZIP fayl yaratib bo'lmadi: {$bundleZipPath}");
            }
            $zip->addFile($dumpFile, 'db_dump.sql');
            $zip->addFile($filesZip, 'files.zip');
            $zip->addFile($tmpDir.'/manifest.json', 'manifest.json');
            $zip->close();

            $finalPath = $bundleZipPath;
            $isEncrypted = $options['encrypt'] ?? true;

            // 6. Shifrlash (AES-256-CBC with HMAC)
            if ($isEncrypted) {
                $encPath = $bundleZipPath.'.enc';
                $key = $this->getEncryptionKey($options['key'] ?? null);
                $this->encryptFile($bundleZipPath, $encPath, $key);
                File::delete($bundleZipPath);
                $finalPath = $encPath;
            }

            // 7. SHA-256 Checksum hisoblash va saqlash
            $checksum = hash_file('sha256', $finalPath);
            File::put($finalPath.'.sha256', $checksum);

            AuditLog::create([
                'user_id' => $options['user_id'] ?? null,
                'action' => 'BACKUP_CREATED',
                'auditable_type' => self::class,
                'auditable_id' => null,
                'new_values' => [
                    'backup_id' => $backupId,
                    'file_name' => basename($finalPath),
                    'size_bytes' => filesize($finalPath),
                    'encrypted' => $isEncrypted,
                    'checksum' => $checksum,
                    'watermark' => $watermark,
                ],
                'created_at' => now(),
            ]);

            $offsite = $this->copyToOffsite($finalPath, $checksum, $isEncrypted);

            return [
                'success' => true,
                'backup_id' => $backupId,
                'file_path' => $finalPath,
                'file_name' => basename($finalPath),
                'size_bytes' => filesize($finalPath),
                'is_encrypted' => $isEncrypted,
                'checksum' => $checksum,
                'manifest' => $manifest,
                'offsite' => $offsite,
            ];
        } catch (\Throwable $exception) {
            Log::error('Backup creation failed.', ['exception_type' => $exception::class]);
            AuditLog::create(['action' => 'BACKUP_FAILED', 'new_values' => ['exception_type' => $exception::class], 'created_at' => now()]);
            throw $exception;
        } finally {
            if (File::exists($tmpDir)) {
                File::deleteDirectory($tmpDir);
            }
        }
    }

    public function copyToOffsite(string $filePath, string $checksum, bool $encrypted): array
    {
        $diskName = config('backup.offsite_disk');
        if (! $diskName) {
            return ['status' => 'NOT_CONFIGURED'];
        }
        $stream = null;
        $remoteStream = null;
        try {
            if (! $encrypted || ! str_ends_with($filePath, '.enc') || ! hash_equals($checksum, hash_file('sha256', $filePath))) {
                throw new \RuntimeException('Only verified encrypted backups may leave the server.');
            }
            $disk = Storage::disk($diskName);
            $prefix = 'aquaoptom/'.app()->environment().'/';
            $remotePath = $prefix.basename($filePath);
            $stream = fopen($filePath, 'rb');
            if (! $disk->put($remotePath, $stream, ['visibility' => 'private'])
                || ! $disk->put($remotePath.'.sha256', $checksum, ['visibility' => 'private'])
                || $disk->size($remotePath) !== filesize($filePath)) {
                throw new \RuntimeException('Backup upload verification failed.');
            }
            $remoteStream = $disk->readStream($remotePath);
            if (! is_resource($remoteStream)) {
                throw new \RuntimeException('Uploaded backup cannot be read back.');
            }
            $hash = hash_init('sha256');
            hash_update_stream($hash, $remoteStream);
            if (! hash_equals($checksum, hash_final($hash))) {
                throw new \RuntimeException('Uploaded backup checksum mismatch.');
            }
            AuditLog::create(['action' => 'BACKUP_OFFSITE_COPIED', 'new_values' => [
                'disk' => $diskName, 'path' => $remotePath, 'checksum' => $checksum,
                'size_bytes' => filesize($filePath),
            ], 'created_at' => now()]);
            $cutoff = now()->subDays(config('backup.retention_days'));
            foreach (AuditLog::where('action', 'BACKUP_OFFSITE_COPIED')->where('created_at', '<', $cutoff)->get() as $old) {
                $path = $old->new_values['path'] ?? '';
                if (($old->new_values['disk'] ?? '') !== $diskName || ! str_starts_with($path, $prefix)
                    || ! preg_match('/^aquaoptom_backup_\d{8}_\d{6}_[a-f0-9-]{36}\.zip\.enc$/', basename($path))) {
                    continue;
                }
                if ($disk->delete([$path, $path.'.sha256'])) {
                    $localPath = $this->getBackupStoragePath().'/'.basename($path);
                    if (is_file($localPath) && isset($old->new_values['checksum'])
                        && hash_equals($old->new_values['checksum'], hash_file('sha256', $localPath))) {
                        File::delete([$localPath, $localPath.'.sha256']);
                    }
                    $old->update(['action' => 'BACKUP_OFFSITE_EXPIRED']);
                }
            }

            return ['status' => 'COPIED', 'path' => $remotePath];
        } catch (\Throwable $exception) {
            Log::error('Offsite backup copy failed; local backup retained.', ['exception_type' => $exception::class]);
            AuditLog::create(['action' => 'BACKUP_OFFSITE_FAILED', 'new_values' => ['exception_type' => $exception::class], 'created_at' => now()]);

            return ['status' => 'FAILED'];
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_resource($remoteStream)) {
                fclose($remoteStream);
            }
        }
    }

    /**
     * Zaxira nusxani tiklash (Restore Drill / Recovery)
     */
    protected function validateArchiveEntries(ZipArchive $zip): void
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = str_replace('\\', '/', $zip->getNameIndex($index));
            $opsys = 0;
            $attributes = 0;
            $zip->getExternalAttributesIndex($index, $opsys, $attributes);
            if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name) || in_array('..', explode('/', $name), true) || (($attributes >> 16) & 0170000) === 0120000) {
                throw new \RuntimeException('Unsafe backup archive path.');
            }
        }
    }

    public function restoreBackup(string $archivePath, array $options = []): array
    {
        if (! File::exists($archivePath)) {
            throw new \InvalidArgumentException("Zaxira fayli topilmadi: {$archivePath}");
        }

        // 1. Checksum tekshirish (agar mavjud bo'lsa)
        $checksumFile = $archivePath.'.sha256';
        if (File::exists($checksumFile)) {
            $expectedChecksum = trim(File::get($checksumFile));
            $actualChecksum = hash_file('sha256', $archivePath);
            if ($expectedChecksum !== $actualChecksum) {
                throw new \RuntimeException('Zaxira nusxa yaxlitligi buzilgan (Checksum mos kelmadi)!');
            }
        }

        $tmpDir = $this->getBackupStoragePath().'/restore_tmp_'.Str::uuid();
        File::makeDirectory($tmpDir, 0755, true);

        try {
            $zipPath = $archivePath;

            // 2. Agar fayl shifrlangan bo'lsa, deshifrlash
            if (str_ends_with($archivePath, '.enc')) {
                $decryptedZip = $tmpDir.'/decrypted.zip';
                $key = $this->getEncryptionKey($options['key'] ?? null);
                $this->decryptFile($archivePath, $decryptedZip, $key);
                $zipPath = $decryptedZip;
            }

            // 3. Arxivni ochish
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException("Zaxira arxivini ochib bo'lmadi!");
            }
            $this->validateArchiveEntries($zip);
            if (! $zip->extractTo($tmpDir)) {
                throw new \RuntimeException('Backup extraction failed.');
            }
            $zip->close();

            // 4. Manifestni o'qish
            $manifestPath = $tmpDir.'/manifest.json';
            if (! File::exists($manifestPath)) {
                throw new \RuntimeException('Zaxira paketida manifest.json topilmadi!');
            }
            $manifest = json_decode(File::get($manifestPath), true);

            // 5. Maqsadli bazani aniqlash va tiklash
            $targetDb = $options['target_db'] ?? config('database.connections.pgsql.database');
            $filesTarget = $options['files_target'] ?? storage_path('app');
            if (($options['restore_files'] ?? false) && $targetDb !== config('database.connections.pgsql.database')) {
                $allowedRoot = str_replace('\\', '/', $this->getBackupStoragePath()).'/drill_files_';
                if (! str_starts_with(str_replace('\\', '/', $filesTarget), $allowedRoot)
                    || str_contains($filesTarget, '..') || File::exists($filesTarget)) {
                    throw new \RuntimeException('Isolated restore requires a new drill_files_* directory.');
                }
            }
            $this->ensureTargetDatabaseExists($targetDb);

            $dumpFile = $tmpDir.'/db_dump.sql';
            if (! File::exists($dumpFile)) {
                throw new \RuntimeException('Zaxira paketida db_dump.sql topilmadi!');
            }

            if (($options['restore_files'] ?? false) && File::exists($tmpDir.'/files.zip')) {
                $preflight = new ZipArchive;
                if ($preflight->open($tmpDir.'/files.zip') !== true) {
                    throw new \RuntimeException('Invalid backup file archive.');
                }
                try {
                    $this->validateArchiveEntries($preflight);
                } finally {
                    $preflight->close();
                }
            }
            $this->restoreDatabaseDump($dumpFile, $targetDb);

            // 6. Fayllarni tiklash (agar so'ralgan bo'lsa)
            $restoreFiles = $options['restore_files'] ?? false;
            if ($restoreFiles && File::exists($tmpDir.'/files.zip')) {
                $filesZip = new ZipArchive;
                if ($filesZip->open($tmpDir.'/files.zip') === true) {
                    File::ensureDirectoryExists($filesTarget);
                    try {
                        if (! $filesZip->extractTo($filesTarget)) {
                            throw new \RuntimeException('Backup files could not be restored.');
                        }
                    } finally {
                        $filesZip->close();
                    }
                    foreach ($manifest['files_checksums'] ?? [] as $relativePath => $checksum) {
                        $restoredPath = $filesTarget.'/'.$relativePath;
                        if (! File::exists($restoredPath) || ! hash_equals($checksum, hash_file('sha256', $restoredPath))) {
                            throw new \RuntimeException('Restored file checksum mismatch.');
                        }
                    }
                }
            }

            // 7. Agar joriy aktiv bazaga tiklangan bo'lsa, Recovery Epoch va Watermarkni yangilash
            $isCurrentDb = ($targetDb === config('database.connections.pgsql.database'));
            $newEpoch = ((int) ($manifest['recovery_epoch'] ?? 1)) + 1;

            if ($isCurrentDb) {
                SystemSetting::set('system_recovery_epoch', $newEpoch, $options['user_id'] ?? null, 'Restored from backup');
                SystemSetting::set('system_recovery_watermark', $manifest['watermark_timestamp'] ?? now()->toIso8601String(), $options['user_id'] ?? null, 'Restored backup watermark');
                SystemSetting::set('system_recovery_status', 'RECONCILIATION_REQUIRED', $options['user_id'] ?? null, 'Pending offline client reconciliation');
            }

            AuditLog::create([
                'user_id' => $options['user_id'] ?? null,
                'action' => 'BACKUP_RESTORED',
                'auditable_type' => self::class,
                'auditable_id' => null,
                'new_values' => [
                    'backup_id' => $manifest['backup_id'] ?? 'unknown',
                    'target_database' => $targetDb,
                    'recovery_epoch' => $newEpoch,
                    'watermark' => $manifest['watermark_timestamp'] ?? null,
                ],
                'created_at' => now(),
            ]);

            return [
                'success' => true,
                'backup_id' => $manifest['backup_id'] ?? null,
                'target_database' => $targetDb,
                'recovery_epoch' => $newEpoch,
                'watermark_timestamp' => $manifest['watermark_timestamp'] ?? null,
                'recovery_status' => $isCurrentDb ? 'RECONCILIATION_REQUIRED' : 'ISOLATED_TEST',
                'manifest' => $manifest,
            ];
        } finally {
            if (File::exists($tmpDir)) {
                File::deleteDirectory($tmpDir);
            }
        }
    }

    /**
     * PostgreSQL bazani pg_dump orqali dump qilish
     */
    protected function dumpDatabase(string $outputFile): void
    {
        $config = config('database.connections.pgsql');
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '5432';
        $database = $config['database'];
        $username = $config['username'] ?? 'postgres';
        $password = $config['password'] ?? '';

        $pgDumpPath = $this->findPgBinary('pg_dump');

        $env = ['PGPASSWORD' => $password];
        $command = [
            $pgDumpPath,
            '-h', $host,
            '-p', (string) $port,
            '-U', $username,
            '-d', $database,
            '-F', 'p', // Plain SQL format
            '--no-owner',
            '--no-acl',
            '-f', $outputFile,
        ];

        $process = new Process($command, null, $env, null, 120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('pg_dump xatolik berdi: '.$process->getErrorOutput());
        }
    }

    /**
     * PostgreSQL dumpni psql orqali tiklash
     */
    protected function restoreDatabaseDump(string $dumpFile, string $targetDb): void
    {
        $config = config('database.connections.pgsql');
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '5432';
        $username = $config['username'] ?? 'postgres';
        $password = $config['password'] ?? '';

        $psqlPath = $this->findPgBinary('psql');

        $env = ['PGPASSWORD' => $password];
        $command = [
            $psqlPath,
            '-h', $host,
            '-p', (string) $port,
            '-U', $username,
            '-d', $targetDb,
            '-f', $dumpFile,
        ];

        $process = new Process($command, null, $env, null, 120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('psql restore xatolik berdi: '.$process->getErrorOutput());
        }
    }

    /**
     * Maqsadli bazani mavjudligini tekshirish yoki yaratish
     */
    protected function ensureTargetDatabaseExists(string $dbName): void
    {
        $config = config('database.connections.pgsql');
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '5432';
        $username = $config['username'] ?? 'postgres';
        $password = $config['password'] ?? '';

        $psqlPath = $this->findPgBinary('psql');
        $env = ['PGPASSWORD' => $password];

        // Bazani tekshirish
        $checkProc = new Process([
            $psqlPath, '-h', $host, '-p', (string) $port, '-U', $username,
            '-d', 'postgres', '-tAc', "SELECT 1 FROM pg_database WHERE datname='{$dbName}'",
        ], null, $env);
        $checkProc->run();

        if (trim($checkProc->getOutput()) !== '1') {
            $createProc = new Process([
                $psqlPath, '-h', $host, '-p', (string) $port, '-U', $username,
                '-d', 'postgres', '-c', "CREATE DATABASE \"{$dbName}\";",
            ], null, $env);
            $createProc->run();

            if (! $createProc->isSuccessful()) {
                throw new \RuntimeException("Maqsadli baza ('{$dbName}') yaratib bo'lmadi: ".$createProc->getErrorOutput());
            }
        }
    }

    /**
     * Fayllarni arxivlash (storage/app/private va attachments)
     */
    protected function archiveFiles(string $outputZip): int
    {
        $zip = new ZipArchive;
        if ($zip->open($outputZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return 0;
        }

        $zip->addEmptyDir('private');
        $zip->addEmptyDir('public');

        $filesCount = 0;
        $privatePath = storage_path('app/private');
        if (File::exists($privatePath)) {
            foreach (File::allFiles($privatePath) as $file) {
                $zip->addFile($file->getRealPath(), 'private/'.$file->getRelativePathname());
                $filesCount++;
            }
        }

        $publicPath = storage_path('app/public');
        if (File::exists($publicPath)) {
            foreach (File::allFiles($publicPath) as $file) {
                $zip->addFile($file->getRealPath(), 'public/'.$file->getRelativePathname());
                $filesCount++;
            }
        }

        $zip->close();

        return $filesCount;
    }

    /**
     * Faylni AES-256-CBC bilan shifrlash
     */
    protected function encryptFile(string $sourcePath, string $destPath, string $key): void
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $data = File::get($sourcePath);
        $ciphertext = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $iv.$ciphertext, $key, true);

        // Format: IV (16 bytes) + HMAC (32 bytes) + Ciphertext
        File::put($destPath, $iv.$hmac.$ciphertext);
    }

    /**
     * Faylni AES-256-CBC dan deshifrlash
     */
    protected function decryptFile(string $sourcePath, string $destPath, string $key): void
    {
        $raw = File::get($sourcePath);
        $ivLen = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($raw, 0, $ivLen);
        $hmac = substr($raw, $ivLen, 32);
        $ciphertext = substr($raw, $ivLen + 32);

        $expectedHmac = hash_hmac('sha256', $iv.$ciphertext, $key, true);
        if (! hash_equals($expectedHmac, $hmac)) {
            throw new \RuntimeException("Zaxira faylini deshifrlashda xatolik: HMAC autentifikatsiyasi xato (Kalit noto'g'ri)!");
        }

        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new \RuntimeException('Deshifrlashda xatolik yuz berdi!');
        }

        File::put($destPath, $decrypted);
    }

    /**
     * PostgreSQL utilitalarini topish
     */
    protected function findPgBinary(string $binary): string
    {
        $paths = [
            "D:\\tools\\pgsql\\bin\\{$binary}.exe",
            "C:\\tools\\pgsql\\bin\\{$binary}.exe",
            "C:\\Program Files\\PostgreSQL\\16\\bin\\{$binary}.exe",
            $binary,
        ];

        foreach ($paths as $path) {
            if (File::exists($path) || $path === $binary) {
                return $path;
            }
        }

        return $binary;
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\Admin\TelemetryService;
use App\Services\Backup\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupOffsiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_encrypted_copy_and_checksum_are_private_and_old_owned_copies_expire(): void
    {
        config(['backup.offsite_disk' => 'offsite-test', 'backup.retention_days' => 14]);
        $disk = Storage::fake('offsite-test');
        $prefix = 'aquaoptom/testing/';
        $old = $prefix.'aquaoptom_backup_20260101_120000_'.Str::uuid().'.zip.enc';
        $disk->put($old, 'old');
        $disk->put($old.'.sha256', 'old-checksum');
        $disk->put('customer-uploads/keep.enc', 'keep');
        foreach ([$old, 'customer-uploads/keep.enc'] as $path) {
            AuditLog::create(['action' => 'BACKUP_OFFSITE_COPIED', 'new_values' => ['disk' => 'offsite-test', 'path' => $path], 'created_at' => now()->subDays(15)]);
        }
        $file = storage_path('app/aquaoptom_backup_20261011_120000_'.Str::uuid().'.zip.enc');
        file_put_contents($file, 'encrypted-test-bytes');
        try {
            $checksum = hash_file('sha256', $file);
            $result = app(BackupService::class)->copyToOffsite($file, $checksum, true);
            $this->assertSame('COPIED', $result['status']);
            $this->assertSame(file_get_contents($file), $disk->get($result['path']));
            $this->assertSame($checksum, $disk->get($result['path'].'.sha256'));
            $this->assertSame('private', $disk->getVisibility($result['path']));
            $disk->assertMissing($old);
            $disk->assertMissing($old.'.sha256');
            $disk->assertExists('customer-uploads/keep.enc');
        } finally {
            unlink($file);
        }
    }

    public function test_unencrypted_or_corrupt_backup_is_never_uploaded_and_local_file_is_retained(): void
    {
        config(['backup.offsite_disk' => 'offsite-test']);
        $disk = Storage::fake('offsite-test');
        $file = storage_path('app/refused-'.Str::uuid().'.enc');
        file_put_contents($file, 'local-backup');
        try {
            $service = app(BackupService::class);
            $this->assertSame('FAILED', $service->copyToOffsite($file, hash_file('sha256', $file), false)['status']);
            $this->assertSame('FAILED', $service->copyToOffsite($file, str_repeat('0', 64), true)['status']);
            $this->assertFileExists($file);
            $this->assertSame([], $disk->allFiles());
            $this->assertDatabaseCount('audit_logs', 2);
        } finally {
            unlink($file);
        }
    }

    public function test_a_new_local_backup_does_not_hide_an_offsite_copy_failure(): void
    {
        config(['backup.offsite_disk' => 'offsite-test']);
        AuditLog::create(['action' => 'BACKUP_OFFSITE_FAILED', 'created_at' => now()->subMinute()]);
        AuditLog::create(['action' => 'BACKUP_CREATED', 'created_at' => now()]);
        $status = app(TelemetryService::class)->getTelemetry()['backup'];
        $this->assertTrue($status['needs_attention']);
        $this->assertSame('Hali ko‘chirilmagan', $status['last_offsite_at']);
        $this->assertSame('Hali tekshirilmagan', $status['last_drill_at']);
    }
}

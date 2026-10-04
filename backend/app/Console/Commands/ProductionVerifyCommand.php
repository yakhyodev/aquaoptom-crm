<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\HealthController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProductionVerifyCommand extends Command
{
    protected $signature = 'app:production-verify {--expect-empty : Require an unused opening database}';

    protected $description = 'Read-only local release profile checks; does not certify a live deployment or APK signature';

    public function handle(): int
    {
        $this->info('================================================================================');
        $this->info('   AQUAOPTOM CRM — PRODUCTION RELEASE VERIFICATION & HANDOVER (PROMPT 25)');
        $this->info('================================================================================');

        if (! $this->laravel->environment('production') || config('app.debug') || ! str_starts_with((string) config('app.url'), 'https://')) {
            $this->error('Production profile requires APP_ENV=production, APP_DEBUG=false and HTTPS APP_URL.');

            return self::FAILURE;
        }
        $activeDb = DB::connection()->getDatabaseName();
        $this->info("1. Ma'lumotlar Bazasi & Topologiya:");
        $this->line("   - Faol Baza: {$activeDb}");
        $tableCount = DB::selectOne("SELECT count(*) as cnt FROM information_schema.tables WHERE table_schema = 'public'")->cnt;
        $this->line("   - Jadvallar soni: {$tableCount} ta (Kutilgan: 62)");

        if ($tableCount < 62) {
            $this->error('   XATOLIK: Jadvallar soni yetarli emas!');

            return self::FAILURE;
        }

        // 2. Toza Baza Intizomi (Soxta ma'lumotlar yo'qligi)
        $this->info("\n2. Toza Baza Intizomi (Zero Mock Data Guarantee):");
        $saleCount = DB::table('sales')->count();
        $customerCount = DB::table('customers')->count();
        $productCount = DB::table('products')->count();
        $custLedgerCount = DB::table('customer_ledger')->count();
        $cashMovCount = DB::table('cash_movements')->count();
        $this->line("   - Sotuvlar soni (sales): {$saleCount} ta (Kutilgan: 0)");
        $this->line("   - Xaridorlar soni (customers): {$customerCount} ta (Kutilgan: 0)");
        $this->line("   - Mahsulotlar soni (products): {$productCount} ta (Kutilgan: 0)");
        $this->line("   - Mijozlar qarz yozuvlari (customer_ledger): {$custLedgerCount} ta (Kutilgan: 0)");
        $this->line("   - Kassa harakatlari (cash_movements): {$cashMovCount} ta (Kutilgan: 0)");

        if ($this->option('expect-empty') && ($saleCount > 0 || $customerCount > 0 || $productCount > 0 || $custLedgerCount > 0 || $cashMovCount > 0)) {
            $this->error('   XATOLIK: Baza bo‘sh emas. Yozuvlar soni ularning soxta ekanini isbotlamaydi.');

            return self::FAILURE;
        }

        // 3. Ma'lumotnoma Jadvallari (Reference Catalogs)
        $this->info("\n3. Standart Ma'lumotnomalar (Reference Catalogs):");
        $rolesCount = DB::table('roles')->count();
        $permissionsCount = DB::table('permissions')->count();
        $volumesCount = DB::table('volumes')->count();
        $cashAccountsCount = DB::table('cash_accounts')->count();
        $warehouseCount = DB::table('warehouses')->count();
        $this->line("   - Rollar: {$rolesCount} ta, Huquqlar: {$permissionsCount} ta");
        $this->line("   - Standart hajmlar: {$volumesCount} ta");
        $this->line("   - Kassa hisoblari: {$cashAccountsCount} ta");
        $this->line("   - Asosiy ombor: {$warehouseCount} ta");

        // 4. Health Probelari (Liveness & Readiness)
        $this->info("\n4. Health Probelari (Liveness & Readiness Probes):");
        $healthController = app(HealthController::class);
        $liveness = $healthController->liveness();
        $readiness = $healthController->readiness(new Request);
        $isLive = $liveness->getStatusCode() === 200;
        $isReady = $readiness->getStatusCode() === 200;
        $this->line('   - Liveness probe: '.($isLive ? 'HTTP 200 LIVE' : 'FAIL'));
        $this->line('   - Readiness probe: '.($isReady ? 'HTTP 200 READY' : 'FAIL'));

        if (! $isLive || ! $isReady) {
            $this->error('Local health checks failed; release profile verification failed.');

            return self::FAILURE;
        }

        // 5. APK file checksum does not prove signature or source revision.
        $this->info("\n5. Mobil Ilova Artefakti (Android APK file (signature and source revision NOT VERIFIED)):");
        $apkPath = base_path('../mobile/build/app/outputs/flutter-apk/app-release.apk');
        $hasApk = File::exists($apkPath);
        if ($hasApk) {
            $apkSizeMb = round(filesize($apkPath) / (1024 * 1024), 2);
            $sha256 = hash_file('sha256', $apkPath);
            $sha1 = hash_file('sha1', $apkPath);
            $this->line("   - Fayl: {$apkPath}");
            $this->line("   - Hajmi: {$apkSizeMb} MB");
            $this->line("   - SHA-256: {$sha256}");
            $this->line("   - SHA-1:   {$sha1}");
        } else {
            $this->warn("   - APK fayli topilmadi: {$apkPath}");
        }

        // 6. Xulosa
        $this->info("\n================================================================================");
        $this->info('   LOCAL PROFILE CHECKS PASSED — LIVE PRODUCTION NOT VERIFIED');
        $this->info('================================================================================');
        $this->line('Versiya: '.config('app.version', '1.0.0'));
        $this->line('Arxitektura: AquaOptom V2 (PostgreSQL + Redis); APK signing requires separate verification');
        $this->line('These checks do not prove public HTTPS, workers, real devices, bot delivery, backup RPO/RTO or a deployed release.');

        return self::SUCCESS;
    }
}

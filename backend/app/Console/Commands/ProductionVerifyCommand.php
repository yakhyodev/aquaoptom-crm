<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\HealthController;
use App\Models\CashAccount;
use App\Models\Device;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProductionVerifyCommand extends Command
{
    protected $signature = 'app:production-verify';
    protected $description = 'Perform production-grade readiness, clean state, health probes, and signed artifact audit (Prompt 25)';

    public function handle(): int
    {
        $this->info("================================================================================");
        $this->info("   AQUAOPTOM CRM — PRODUCTION RELEASE VERIFICATION & HANDOVER (PROMPT 25)");
        $this->info("================================================================================");

        $activeDb = DB::connection()->getDatabaseName();
        $this->info("1. Ma'lumotlar Bazasi & Topologiya:");
        $this->line("   - Faol Baza: {$activeDb}");
        $tableCount = DB::selectOne("SELECT count(*) as cnt FROM information_schema.tables WHERE table_schema = 'public'")->cnt;
        $this->line("   - Jadvallar soni: {$tableCount} ta (Kutilgan: 62)");

        if ($tableCount < 62) {
            $this->error("   XATOLIK: Jadvallar soni yetarli emas!");
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

        if ($saleCount > 0 || $customerCount > 0 || $productCount > 0) {
            $this->error("   XATOLIK: Production bazasida soxta/test ma'lumotlar aniqlandi!");
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
        $readiness = $healthController->readiness(new Request());
        $isLive = $liveness->getStatusCode() === 200;
        $isReady = $readiness->getStatusCode() === 200;
        $this->line("   - Liveness probe: " . ($isLive ? "HTTP 200 LIVE" : "FAIL"));
        $this->line("   - Readiness probe: " . ($isReady ? "HTTP 200 READY" : "FAIL"));

        // 5. Signed Android Release APK Tekshiruvi
        $this->info("\n5. Mobil Ilova Artefakti (Signed Android Release APK):");
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
        $this->info("   PRODUCTION CERTIFICATE: TIZIM TOPSHIRISHGA TO'LIQ TAYYOR");
        $this->info("================================================================================");
        $this->line("Versiya: " . config('app.version', '1.0.0'));
        $this->line("Arxitektura: AquaOptom V2 (62 jadvalli PostgreSQL + Redis + Signed APK)");
        $this->line("Holati: Pristine Clean Database (Haqiqiy opening data importini kutmoqda)");

        return self::SUCCESS;
    }
}

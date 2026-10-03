<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. PostgreSQL sequences for document numbering (Concurrency-safe)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE SEQUENCE IF NOT EXISTS sales_invoice_seq START 1');
            DB::statement('CREATE SEQUENCE IF NOT EXISTS purchases_invoice_seq START 1');
            DB::statement('CREATE SEQUENCE IF NOT EXISTS payments_number_seq START 1');
            DB::statement('CREATE SEQUENCE IF NOT EXISTS returns_number_seq START 1');
        }

        // 2. Operatsiyalar va natijalar daftari (Idempotency and Operation Results)
        Schema::create('operation_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->string('operation_type', 64)->index();
            $table->string('payload_fingerprint', 64)->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('device_id', 64)->nullable()->index();
            $table->string('source', 32)->default('web');
            $table->string('status', 32)->default('PROCESSED')->index();
            $table->unsignedInteger('expected_version')->nullable();
            $table->json('result_payload')->nullable();
            $table->string('error_category', 32)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
        });

        // 3. Outbox hodisalar navbati (Transactional Outbox Pattern)
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->uuid('operation_id')->nullable()->index();
            $table->string('event_name', 100)->index();
            $table->string('aggregate_type', 100)->index();
            $table->string('aggregate_id', 64)->index();
            $table->json('payload');
            $table->string('status', 32)->default('PENDING')->index();
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(5);
            $table->text('last_error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 4. AuditLogs jadvaliga operation_id qo'shish
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('operation_id')->nullable()->index()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('operation_id');
        });

        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('operation_results');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS sales_invoice_seq');
            DB::statement('DROP SEQUENCE IF EXISTS purchases_invoice_seq');
            DB::statement('DROP SEQUENCE IF EXISTS payments_number_seq');
            DB::statement('DROP SEQUENCE IF EXISTS returns_number_seq');
        }
    }
};

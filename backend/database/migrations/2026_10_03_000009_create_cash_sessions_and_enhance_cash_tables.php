<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. cash_sessions (Kassa smenalari)
        if (! Schema::hasTable('cash_sessions')) {
            Schema::create('cash_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('session_number', 50)->unique();
                $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
                $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
                $table->string('status', 30)->default('OPEN'); // OPEN, CLOSED, PROVISIONAL
                $table->timestamp('opened_at')->useCurrent();
                $table->bigInteger('opening_balance')->default(0);
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->bigInteger('expected_closing_balance')->nullable();
                $table->bigInteger('actual_closing_balance')->nullable();
                $table->bigInteger('difference')->default(0); // actual - expected
                $table->text('difference_reason')->nullable();
                $table->string('difference_status', 30)->default('NONE'); // NONE, PENDING_APPROVAL, APPROVED, REJECTED
                $table->foreignId('difference_approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('difference_approved_at')->nullable();
                $table->boolean('has_pending_offline_sync')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['cash_account_id', 'status']);
            });

            // PostgreSQL partial unique index: Bitta naqd hisob uchun bitta OPEN smena
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS unique_open_cash_session_per_account ON cash_sessions (cash_account_id) WHERE status = 'OPEN';");
            }
        }

        // 2. cash_movements jadvaliga cash_session_id qo'shish
        Schema::table('cash_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_movements', 'cash_session_id')) {
                $table->foreignId('cash_session_id')->nullable()->after('cash_account_id')->constrained('cash_sessions')->nullOnDelete();
            }
        });

        // 3. expenses jadvaliga operation_id va expense_number qo'shish
        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'operation_id')) {
                $table->string('operation_id', 36)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('expenses', 'expense_number')) {
                $table->string('expense_number', 50)->nullable()->unique()->after('operation_id');
            }
        });

        // 4. PostgreSQL sequences for cash sessions and expenses
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE SEQUENCE IF NOT EXISTS cash_session_seq START WITH 1 INCREMENT BY 1 NO CYCLE;');
            DB::statement('CREATE SEQUENCE IF NOT EXISTS expense_seq START WITH 1 INCREMENT BY 1 NO CYCLE;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS expense_seq;');
            DB::statement('DROP SEQUENCE IF EXISTS cash_session_seq;');
            DB::statement('DROP INDEX IF EXISTS unique_open_cash_session_per_account;');
        }

        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'expense_number')) {
                $table->dropColumn('expense_number');
            }
            if (Schema::hasColumn('expenses', 'operation_id')) {
                $table->dropColumn('operation_id');
            }
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            if (Schema::hasColumn('cash_movements', 'cash_session_id')) {
                $table->dropForeign(['cash_session_id']);
                $table->dropColumn('cash_session_id');
            }
        });

        Schema::dropIfExists('cash_sessions');
    }
};

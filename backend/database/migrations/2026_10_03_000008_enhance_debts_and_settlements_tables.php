<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. sales: tovar olib ketilgan vaqt (kamera/CCTV auditi uchun ixtiyoriy alohida timestamp)
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'goods_picked_up_at')) {
                $table->timestamp('goods_picked_up_at')->nullable()->after('completed_at');
            }
        });

        // 2. customers: kelishilgan to'lov sanasi (payment_due_date)
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'payment_due_date')) {
                $table->date('payment_due_date')->nullable()->after('debt_limit');
            }
        });

        // 3. suppliers: kelishilgan to'lov sanasi va kredit limiti
        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'payment_due_date')) {
                $table->date('payment_due_date')->nullable()->after('balance');
            }
            if (! Schema::hasColumn('suppliers', 'credit_limit')) {
                $table->bigInteger('credit_limit')->default(0)->after('payment_due_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'credit_limit')) {
                $table->dropColumn('credit_limit');
            }
            if (Schema::hasColumn('suppliers', 'payment_due_date')) {
                $table->dropColumn('payment_due_date');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'payment_due_date')) {
                $table->dropColumn('payment_due_date');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'goods_picked_up_at')) {
                $table->dropColumn('goods_picked_up_at');
            }
        });
    }
};

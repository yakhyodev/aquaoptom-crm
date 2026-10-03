<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->uuid('operation_id')->nullable()->unique()->after('id');
            $table->bigInteger('paid_amount')->default(0)->after('total_amount');
            $table->bigInteger('debt_amount')->default(0)->after('paid_amount');
            $table->foreignId('cash_account_id')->nullable()->after('debt_amount')->constrained('cash_accounts')->nullOnDelete();
            $table->string('payment_method', 32)->default('CASH')->after('cash_account_id');
            $table->text('notes')->nullable()->after('source');
            $table->json('receipt_data')->nullable()->after('notes');
            $table->timestamp('completed_at')->nullable()->after('receipt_data');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedInteger('price_version')->nullable()->after('is_system_price');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['price_version']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['cash_account_id']);
            $table->dropColumn([
                'operation_id',
                'paid_amount',
                'debt_amount',
                'cash_account_id',
                'payment_method',
                'notes',
                'receipt_data',
                'completed_at',
            ]);
        });
    }
};

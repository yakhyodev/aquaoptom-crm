<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'operation_id')) {
                $table->string('operation_id', 36)->nullable()->after('id')->index();
            }
            if (! Schema::hasColumn('purchases', 'supplier_invoice_number')) {
                $table->string('supplier_invoice_number', 100)->nullable()->after('invoice_number');
            }
            if (! Schema::hasColumn('purchases', 'paid_amount')) {
                $table->bigInteger('paid_amount')->default(0)->after('total_amount');
            }
            if (! Schema::hasColumn('purchases', 'debt_amount')) {
                $table->bigInteger('debt_amount')->default(0)->after('paid_amount');
            }
            if (! Schema::hasColumn('purchases', 'notes')) {
                $table->text('notes')->nullable()->after('source');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['notes', 'debt_amount', 'paid_amount', 'supplier_invoice_number', 'operation_id'] as $col) {
                if (Schema::hasColumn('purchases', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};

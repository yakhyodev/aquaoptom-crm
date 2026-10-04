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
            DB::statement('CREATE SEQUENCE IF NOT EXISTS purchase_returns_seq START 1');
            DB::statement('CREATE SEQUENCE IF NOT EXISTS damage_records_seq START 1');
            DB::statement('CREATE SEQUENCE IF NOT EXISTS inventory_audits_seq START 1');
        }

        // 2. Devices jadvaliga inventarizatsiya muzlatish ustunlarini qo'shish
        Schema::table('devices', function (Blueprint $table) {
            $table->timestamp('freeze_requested_at')->nullable()->after('is_active');
            $table->timestamp('freeze_acknowledged_at')->nullable()->after('freeze_requested_at');
        });

        // 3. Sale Returns (Mijoz tovar qaytarishi)
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->string('return_number', 64)->unique();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->bigInteger('total_amount')->default(0); // Qaytarilgan tovarlar sotuv qiymati
            $table->bigInteger('total_cost')->default(0); // Qaytarilgan tovarlar tannarx snapshot qiymati
            $table->bigInteger('refund_amount')->default(0); // Kassadan naqd/karta berilgan pul
            $table->bigInteger('debt_deduction_amount')->default(0); // Qarzdan chegirilgan summa (total - refund)
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->nullOnDelete();
            $table->string('refund_payment_method', 32)->nullable(); // CASH, CARD, BANK
            $table->string('status', 32)->default('POSTED')->index(); // POSTED, CANCELLED
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->useCurrent();
            $table->timestamps();
        });

        // 4. Sale Return Items
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->decimal('quantity', 12, 3);
            $table->bigInteger('unit_price'); // Original sotuv narxi
            $table->bigInteger('cost_price'); // Original tannarx snapshot
            $table->bigInteger('line_total'); // quantity * unit_price (oxirgi donada to'liq yopiladi)
            $table->bigInteger('cost_total'); // quantity * cost_price (oxirgi donada to'liq yopiladi)
            $table->boolean('is_damaged')->default(false); // Agar yaroqsiz bo'lsa, sotiladigan qoldiqqa kirmaydi
            $table->string('condition', 32)->default('SELLABLE'); // SELLABLE, DAMAGED
            $table->timestamps();
        });

        // 5. Purchase Returns (Ta'minotchiga tovar qaytarish)
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->string('return_number', 64)->unique();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->bigInteger('total_credit_amount')->default(0); // Ta'minotchi qarzini kamaytiruvchi summa (xarid narxida)
            $table->bigInteger('total_cost_amount')->default(0); // Ombordan joriy WAC bo'yicha chiqim qiymati
            $table->bigInteger('cost_discrepancy')->default(0); // credit - cost farqi
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->string('status', 32)->default('POSTED')->index();
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->useCurrent();
            $table->timestamps();
        });

        // 6. Purchase Return Items
        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained('purchase_items')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->decimal('quantity', 12, 3);
            $table->bigInteger('unit_cost'); // Xarid paytidagi tannarx
            $table->bigInteger('current_wac'); // Chiqim paytidagi WAC
            $table->bigInteger('line_credit'); // quantity * unit_cost
            $table->bigInteger('line_wac_cost'); // quantity * current_wac
            $table->timestamps();
        });

        // 7. Damage Records (Brak / Yaroqsiz tovar chiqimi — tannarx yo'qotishi, kassa xarajati emas)
        Schema::create('damage_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->string('damage_number', 64)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->bigInteger('total_loss_value')->default(0); // WAC bo'yicha hisoblangan jami yo'qotish
            $table->decimal('total_quantity', 12, 3)->default(0);
            $table->string('status', 32)->default('POSTED')->index();
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->useCurrent();
            $table->timestamps();
        });

        // 8. Damage Items
        Schema::create('damage_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_record_id')->constrained('damage_records')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->decimal('quantity', 12, 3);
            $table->bigInteger('unit_cost'); // WAC tannarxi
            $table->bigInteger('total_cost'); // quantity * unit_cost
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });

        // 9. Inventory Audits (Inventarizatsiya va sanash)
        Schema::create('inventory_audits', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->string('audit_number', 64)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('status', 32)->default('PREPARED')->index(); // PREPARED, COUNTING, COMPLETED, CANCELLED
            $table->string('device_freeze_status', 32)->default('NOT_REQUIRED')->index(); // NOT_REQUIRED, PENDING_ACK, ACKNOWLEDGED, FORCE_CONFIRMED
            $table->decimal('total_expected_qty', 12, 3)->default(0);
            $table->decimal('total_counted_qty', 12, 3)->default(0);
            $table->decimal('total_discrepancy_qty', 12, 3)->default(0);
            $table->bigInteger('total_discrepancy_value')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 10. Inventory Audit Items
        Schema::create('inventory_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_audit_id')->constrained('inventory_audits')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->decimal('expected_quantity', 12, 3);
            $table->decimal('counted_quantity', 12, 3)->nullable();
            $table->decimal('discrepancy_quantity', 12, 3)->nullable();
            $table->bigInteger('unit_cost'); // Audit vaqtidagi WAC
            $table->bigInteger('discrepancy_value')->nullable(); // discrepancy_quantity * unit_cost
            $table->string('reason', 255)->nullable();
            $table->string('status', 32)->default('PENDING'); // PENDING, COUNTED, ADJUSTED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_audit_items');
        Schema::dropIfExists('inventory_audits');
        Schema::dropIfExists('damage_items');
        Schema::dropIfExists('damage_records');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');

        if (Schema::hasColumn('devices', 'freeze_acknowledged_at')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->dropColumn(['freeze_requested_at', 'freeze_acknowledged_at']);
            });
        }
    }
};

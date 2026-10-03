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
        // 1. inventory_balances ga total_value qo'shish (WAC va jami ombor qiymati uchun)
        Schema::table('inventory_balances', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_balances', 'total_value')) {
                $table->bigInteger('total_value')->default(0)->after('average_cost');
            }
        });

        // 2. inventory_movements ga operation_id va balance_after ustunlarini qo'shish
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_movements', 'operation_id')) {
                $table->string('operation_id', 36)->nullable()->after('id')->index();
            }
            if (! Schema::hasColumn('inventory_movements', 'balance_after_quantity')) {
                $table->decimal('balance_after_quantity', 14, 3)->default(0)->after('total_cost');
            }
            if (! Schema::hasColumn('inventory_movements', 'balance_after_value')) {
                $table->bigInteger('balance_after_value')->default(0)->after('balance_after_quantity');
            }
        });

        // 3. customer_ledger ga operation_id va payment_method qo'shish
        Schema::table('customer_ledger', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_ledger', 'operation_id')) {
                $table->string('operation_id', 36)->nullable()->after('id')->index();
            }
            if (! Schema::hasColumn('customer_ledger', 'payment_method')) {
                $table->string('payment_method', 30)->nullable()->after('type'); // CASH, CARD, BANK, OFFSET
            }
        });

        // 4. supplier_ledger (Ta'minotchi signed qarz daftari)
        if (! Schema::hasTable('supplier_ledger')) {
            Schema::create('supplier_ledger', function (Blueprint $table) {
                $table->id();
                $table->string('operation_id', 36)->nullable()->index();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->string('type', 40); // PURCHASE, PAYMENT, RETURN, ADJUSTMENT, OPENING_BALANCE
                $table->string('payment_method', 30)->nullable(); // CASH, CARD, BANK, OFFSET
                $table->bigInteger('debit')->default(0); // Bizning to'lovimiz (qarz kamayishi)
                $table->bigInteger('credit')->default(0); // Ta'minotchi tovari (qarz ko'payishi)
                $table->bigInteger('balance_after'); // Musbat = bizning qarzimiz, manfiy = avansimiz
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['supplier_id', 'created_at']);
            });
        }

        // 5. cash_movements (Kassa pul harakatlari daftari)
        if (! Schema::hasTable('cash_movements')) {
            Schema::create('cash_movements', function (Blueprint $table) {
                $table->id();
                $table->string('operation_id', 36)->nullable()->index();
                $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
                $table->string('type', 40); // SALE_PAYMENT, CUSTOMER_PAYMENT, SUPPLIER_PAYMENT, EXPENSE, TRANSFER_IN, TRANSFER_OUT, OWNER_DEPOSIT, OWNER_WITHDRAWAL, REFUND, ADJUSTMENT, OPENING_BALANCE
                $table->string('direction', 10); // IN, OUT
                $table->bigInteger('debit')->default(0); // Kirim (pul ko'payishi)
                $table->bigInteger('credit')->default(0); // Chiqim (pul kamayishi)
                $table->bigInteger('amount');
                $table->bigInteger('balance_after'); // Harakatdan keyingi kassa qoldig'i
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['cash_account_id', 'created_at']);
            });
        }

        // 6. payments (To'lov hujjatlari)
        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->string('payment_number', 50)->unique();
                $table->string('operation_id', 36)->unique();
                $table->string('party_type', 30)->default('NONE'); // CUSTOMER, SUPPLIER, EXPENSE, OWNER, NONE
                $table->unsignedBigInteger('party_id')->nullable();
                $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
                $table->string('payment_type', 40); // CUSTOMER_PAYMENT, SUPPLIER_PAYMENT, EXPENSE, OWNER_DRAW, OWNER_DEPOSIT, REFUND, OPENING_BALANCE
                $table->string('payment_method', 30); // CASH, CARD, BANK
                $table->string('direction', 10); // IN, OUT
                $table->bigInteger('amount');
                $table->text('notes')->nullable();
                $table->string('status', 30)->default('COMPLETED');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['party_type', 'party_id']);
            });
        }

        // 7. opening_balance_documents (Boshlang'ich qoldiq hujjatlari)
        if (! Schema::hasTable('opening_balance_documents')) {
            Schema::create('opening_balance_documents', function (Blueprint $table) {
                $table->id();
                $table->string('document_number', 50)->unique();
                $table->string('operation_id', 36)->unique();
                $table->string('type', 30)->default('BATCH'); // STOCK, CASH, CUSTOMER, SUPPLIER, BATCH
                $table->bigInteger('total_amount')->default(0);
                $table->integer('total_items')->default(0);
                $table->string('status', 30)->default('POSTED');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // 8. opening_doc_seq sequence yaratish (PostgreSQL)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE SEQUENCE IF NOT EXISTS opening_doc_seq START WITH 1 INCREMENT BY 1 NO CYCLE;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS opening_doc_seq;');
        }

        Schema::dropIfExists('opening_balance_documents');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('supplier_ledger');

        Schema::table('customer_ledger', function (Blueprint $table) {
            if (Schema::hasColumn('customer_ledger', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
            if (Schema::hasColumn('customer_ledger', 'operation_id')) {
                $table->dropColumn('operation_id');
            }
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_movements', 'balance_after_value')) {
                $table->dropColumn('balance_after_value');
            }
            if (Schema::hasColumn('inventory_movements', 'balance_after_quantity')) {
                $table->dropColumn('balance_after_quantity');
            }
            if (Schema::hasColumn('inventory_movements', 'operation_id')) {
                $table->dropColumn('operation_id');
            }
        });

        Schema::table('inventory_balances', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_balances', 'total_value')) {
                $table->dropColumn('total_value');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Omborlar (Warehouses)
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // 2. Hajmlar (Volumes: 0.25L, 0.5L, 1L, 1.5L, 2L, 2.25L, 5L, 18.9L)
        Schema::create('volumes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "0.5 L"
            $table->integer('value_ml')->unique(); // 500 (ml) - duplicate oldini olish uchun
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        // 3. Mahsulotlar (Product Master: Fanta, Coca-Cola, Chortoq, Nestle, Flash)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->unique(); // "fanta", "coca-cola" duplicate protection
            $table->string('code')->unique(); // "PRD-000001"
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Mahsulot Variantlari (Product Variants: Fanta + 0.5L, Fanta + 1.5L)
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('volume_id')->constrained()->restrictOnDelete();
            $table->string('sku')->unique(); // "FANTA-500"
            $table->string('barcode')->nullable()->index();
            $table->bigInteger('default_sale_price')->nullable(); // Standart ulgurji tizim narxi (so'm)
            $table->integer('minimum_stock')->default(50);
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'volume_id']);
        });

        // 5. Qadoqlash konfiguratsiyasi (Product Packages: dona=1, blok=6, yashik=12)
        Schema::create('product_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('name'); // "dona", "blok", "yashik", "qadoq"
            $table->integer('units_per_package')->default(1); // 1, 6, 12, 24
            $table->timestamps();

            $table->unique(['product_variant_id', 'name']);
        });

        // 6. Narx Tarixi (Price History)
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->bigInteger('old_price')->nullable();
            $table->bigInteger('new_price');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('changed_at')->useCurrent();
        });

        // 7. Inventory Movements (Source of Truth Ledger - barcha qoldiq harakatlari)
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('movement_type'); // PURCHASE, SALE, SALE_RETURN, PURCHASE_RETURN, DAMAGE, LOSS, ADJUSTMENT_IN, ADJUSTMENT_OUT
            $table->decimal('quantity', 14, 3); // Boshlang'ich hisob birligi: DONA
            $table->bigInteger('unit_cost'); // Weighted average cost yoki kirim tannarx
            $table->bigInteger('total_cost');
            $table->string('reference_type')->nullable(); // Purchase, Sale, Adjustment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_variant_id', 'movement_type']);
        });

        // 8. Inventory Balances (Tezkor o'qish uchun kesh/joriy qoldiq holati)
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 3)->default(0); // Dona
            $table->bigInteger('average_cost')->default(0); // Weighted Average Cost (so'm)
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['product_variant_id', 'warehouse_id']);
        });

        // 9. Yetkazib beruvchilar (Suppliers)
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->bigInteger('balance')->default(0); // Bizning qarzimiz
            $table->timestamps();
        });

        // 10. Kirim (Purchases / Goods Receiving)
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('DRAFT'); // DRAFT, POSTED, CANCELLED
            $table->bigInteger('total_amount')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->string('source')->default('web'); // web, mobile, telegram
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        // 11. Kirim Tarkibi (Purchase Items)
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('product_packages')->nullOnDelete();
            $table->integer('package_quantity')->default(0); // Masalan 15 yashik
            $table->decimal('quantity', 14, 3); // Jami: 180 dona
            $table->bigInteger('unit_cost'); // 1 dona tannarxi (so'm)
            $table->bigInteger('total_cost');
            $table->timestamps();
        });

        // 12. Ulgurji Xaridorlar (Customers)
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->bigInteger('debt_limit')->default(0);
            $table->bigInteger('current_debt')->default(0);
            $table->timestamps();
        });

        // 13. Optom Savdo (Sales)
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('COMPLETED'); // COMPLETED, CANCELLED, VOID
            $table->bigInteger('total_amount'); // Jami sotuv
            $table->bigInteger('total_cost'); // Jami tannarx snapshot
            $table->bigInteger('gross_profit'); // Haqiqiy Yalpi Foyda (Realized Profit)
            $table->string('payment_type')->default('CASH'); // CASH, CARD, BANK, DEBT, MIXED
            $table->string('source')->default('web'); // web, mobile, telegram
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        // 14. Savdo Tarkibi (Sale Items with Historical Cost Snapshot)
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('product_packages')->nullOnDelete();
            $table->integer('package_quantity')->default(0);
            $table->decimal('quantity', 14, 3); // Donada
            $table->bigInteger('sale_price'); // Sotilgan narxi (1 dona)
            $table->bigInteger('purchase_cost_snapshot'); // O'sha paytdagi WAC tannarxi (historical snapshot!)
            $table->bigInteger('line_total'); // quantity * sale_price
            $table->bigInteger('cost_total'); // quantity * purchase_cost_snapshot
            $table->bigInteger('gross_profit'); // line_total - cost_total
            $table->boolean('is_system_price')->default(true);
            $table->timestamps();
        });

        // 15. Dasturiy Kassa Hisoblari (Cash Accounts)
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Asosiy Kassa", "Humo/Uzcard Terminal", "Bank Hisob"
            $table->string('type'); // CASH, CARD, BANK
            $table->bigInteger('balance')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // 16. Kassa Harakatlari (Cash Transactions)
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_account_id')->constrained()->restrictOnDelete();
            $table->string('type'); // SALE_PAYMENT, CUSTOMER_PAYMENT, EXPENSE, WITHDRAWAL, DEPOSIT, REFUND
            $table->bigInteger('amount');
            $table->string('reference_type')->nullable(); // Sale, CustomerPayment, Expense
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 17. Mijoz Qarz Daftari (Customer Ledger)
        Schema::create('customer_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // SALE, PAYMENT, RETURN, ADJUSTMENT
            $table->bigInteger('debit')->default(0); // Qarz ko'payishi (Sale)
            $table->bigInteger('credit')->default(0); // Qarz kamayishi (Payment, Return)
            $table->bigInteger('balance_after'); // Harakatdan keyingi qarz qoldig'i
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'created_at']);
        });

        // 18. Xarajatlar (Expenses)
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // Transport, Oylik, Ijara, Yuk tushirish, Boshqa
            $table->foreignId('cash_account_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        // 19. Audit Logs (Barcha moliyaviy va narx o'zgarishlari auditi)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action'); // PRICE_CHANGE, SALE_CANCEL, PURCHASE_POST, STOCK_ADJUSTMENT, DYNAMIC_PRODUCT_CREATE
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('customer_ledger');
        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('cash_accounts');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('price_history');
        Schema::dropIfExists('product_packages');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('volumes');
        Schema::dropIfExists('warehouses');
    }
};

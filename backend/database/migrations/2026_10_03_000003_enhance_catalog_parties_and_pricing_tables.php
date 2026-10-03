<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. PostgreSQL sequence for concurrency-safe product code generation
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE SEQUENCE IF NOT EXISTS product_code_seq START 1');
        }

        // 2. Volumes: soft deletes
        Schema::table('volumes', function (Blueprint $table) {
            $table->softDeletes()->after('created_by');
        });

        // 3. Product Variants: version for price and variant updates
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('status');
        });

        // 4. Price History: version and reason for auditability
        Schema::table('price_history', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('new_price');
            $table->string('reason')->nullable()->after('changed_at');
        });

        // 5. Customers: stable offline UUID, store name, address, notes, status, created_by, softDeletes
        Schema::table('customers', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('store_name')->nullable()->after('phone');
            $table->string('address')->nullable()->after('store_name');
            $table->string('status')->default('active')->after('current_debt');
            $table->text('notes')->nullable()->after('status');
            $table->unsignedBigInteger('created_by')->nullable()->after('notes');
            $table->softDeletes()->after('updated_at');
        });

        // 6. Suppliers: stable offline UUID, company name, address, notes, status, created_by, softDeletes
        Schema::table('suppliers', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('company_name')->nullable()->after('name');
            $table->string('address')->nullable()->after('phone');
            $table->string('status')->default('active')->after('balance');
            $table->text('notes')->nullable()->after('status');
            $table->unsignedBigInteger('created_by')->nullable()->after('notes');
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['uuid', 'company_name', 'address', 'status', 'notes', 'created_by']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['uuid', 'store_name', 'address', 'status', 'notes', 'created_by']);
        });

        Schema::table('price_history', function (Blueprint $table) {
            $table->dropColumn(['version', 'reason']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('version');
        });

        Schema::table('volumes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS product_code_seq');
        }
    }
};

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
        // 1. PostgreSQL sequence for human-friendly device codes (DEV-001, etc.)
        DB::statement('CREATE SEQUENCE IF NOT EXISTS device_seq START WITH 1 INCREMENT BY 1;');

        // 2. Devices table
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_uuid', 64)->unique(); // Client-generated unique identifier/UUID
            $table->string('device_code', 32)->unique(); // Human-friendly code (DEV-001, etc.)
            $table->string('name');                      // "Kassa Kompyuter 1", "Sotuvchi Ali Telefoni"
            $table->string('device_type', 32)->default('PC'); // PC, MOBILE, TABLET, POS_TERMINAL, OTHER
            $table->string('status', 32)->default('ACTIVE'); // ACTIVE, REVOKED, LOST, RECONCILED
            $table->boolean('is_active')->default(true);
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('current_lease_epoch')->default(1);
            $table->boolean('allow_new_offline_customer_debt')->default(false);
            $table->bigInteger('new_customer_debt_budget')->default(0);
            $table->bigInteger('new_customer_debt_consumed')->default(0);
            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_ip', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_active']);
            $table->index('last_seen_at');
        });

        // 3. Offline Authorizations (Signed Timed Permission Leases / Snapshots)
        Schema::create('offline_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('lease_token', 64)->unique();
            $table->json('permissions'); // array of permitted permission strings
            $table->timestamp('valid_from');
            $table->timestamp('expires_at');
            $table->string('signature', 128); // HMAC-SHA256 signature
            $table->integer('epoch')->default(1);
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'is_revoked', 'expires_at']);
        });

        // 4. Inventory Allocations (Reserved selling right per device & variant)
        Schema::create('inventory_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->integer('allocated_quantity')->default(0); // Total pieces granted
            $table->integer('consumed_quantity')->default(0);  // Total pieces sold/consumed
            $table->integer('returned_quantity')->default(0);  // Returned back to free warehouse stock
            $table->integer('epoch')->default(1);
            $table->string('status', 32)->default('ACTIVE');   // ACTIVE, CLOSED, RECONCILED
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'product_variant_id', 'warehouse_id']);
        });

        // PostgreSQL partial unique index: Only 1 active allocation per device, variant & warehouse
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS unique_active_device_variant_allocation
            ON inventory_allocations (device_id, product_variant_id, warehouse_id)
            WHERE status = 'ACTIVE'
        ");

        // 5. Inventory Allocation Movements (Grant, Consume, Return, Reconcile)
        Schema::create('inventory_allocation_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_allocation_id')->constrained('inventory_allocations')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('operation_id', 36)->unique(); // Idempotency guarantee
            $table->string('movement_type', 32);          // GRANT, CONSUME, RETURN, RECONCILE
            $table->integer('quantity');                  // Whole pieces (dona) > 0
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['inventory_allocation_id', 'movement_type']);
        });

        // 6. Credit Allocations (Credit limit reservation for offline devices)
        Schema::create('credit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->boolean('is_new_customer_budget')->default(false);
            $table->bigInteger('allocated_amount')->default(0); // In integer UZS
            $table->bigInteger('consumed_amount')->default(0);  // In integer UZS
            $table->bigInteger('returned_amount')->default(0);  // In integer UZS
            $table->integer('epoch')->default(1);
            $table->string('status', 32)->default('ACTIVE');    // ACTIVE, CLOSED, RECONCILED
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'customer_id', 'status']);
        });

        // PostgreSQL partial unique indexes for active credit allocations
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS unique_active_device_customer_credit
            ON credit_allocations (device_id, customer_id)
            WHERE status = 'ACTIVE' AND is_new_customer_budget = false
        ");
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS unique_active_device_new_cust_credit
            ON credit_allocations (device_id)
            WHERE status = 'ACTIVE' AND is_new_customer_budget = true
        ");

        // 7. Credit Allocation Movements (Grant, Consume, Return, Reconcile)
        Schema::create('credit_allocation_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_allocation_id')->constrained('credit_allocations')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->string('operation_id', 36)->unique(); // Idempotency guarantee
            $table->string('movement_type', 32);          // GRANT, CONSUME, RETURN, RECONCILE
            $table->bigInteger('amount');                 // In integer UZS > 0
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['credit_allocation_id', 'movement_type']);
        });

        // 8. Enhancements to customers and sales tables
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'is_strict_credit_limit')) {
                $table->boolean('is_strict_credit_limit')->default(false)->after('debt_limit');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'device_id')) {
                $table->foreignId('device_id')->nullable()->after('warehouse_id')->constrained('devices')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'device_id')) {
                $table->dropConstrainedForeignId('device_id');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'is_strict_credit_limit')) {
                $table->dropColumn('is_strict_credit_limit');
            }
        });

        Schema::dropIfExists('credit_allocation_movements');
        Schema::dropIfExists('credit_allocations');
        Schema::dropIfExists('inventory_allocation_movements');
        Schema::dropIfExists('inventory_allocations');
        Schema::dropIfExists('offline_authorizations');
        Schema::dropIfExists('devices');

        DB::statement('DROP SEQUENCE IF EXISTS device_seq;');
    }
};

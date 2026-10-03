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
        // 1. Extend users table with role, status, phone, is_active
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('SALES_MANAGER')->after('email');
            $table->string('status', 20)->default('ACTIVE')->after('role'); // ACTIVE, BLOCKED, INACTIVE
            $table->boolean('is_active')->default(true)->after('status');
            $table->string('phone', 25)->nullable()->unique()->after('is_active');
            $table->foreignId('current_store_id')->nullable()->after('phone');
        });

        // 2. Roles table
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique(); // OWNER, ADMIN, SALES_MANAGER, WAREHOUSE_MANAGER, CASHIER
            $table->string('display_name', 100);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // 3. Permissions table
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('display_name', 100);
            $table->string('category', 50); // catalog, sales, inventory, finance, users, reports
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // 4. Role has permissions pivot
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->unique(['role_id', 'permission_id']);
        });

        // 5. User direct permission overrides (grant or revoke specific permission for a user)
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->boolean('is_granted')->default(true); // true = granted, false = revoked override
            $table->unique(['user_id', 'permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'status', 'is_active', 'phone', 'current_store_id']);
        });
    }
};

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
        // 1. report_exports table
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('report_type', 50); // sales, profit_loss, purchases, inventory, statement, cash, staff
            $table->string('format', 20); // csv, xlsx, pdf
            $table->string('file_name', 255);
            $table->string('file_path', 500)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('status', 30)->default('PENDING'); // PENDING, PROCESSING, COMPLETED, FAILED
            $table->text('error_message')->nullable();
            $table->jsonb('filter_payload')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        // 2. Add 'export_reports' permission if not present
        $now = now();
        $permId = DB::table('permissions')->where('name', 'export_reports')->value('id');
        if (! $permId) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => 'export_reports',
                'display_name' => 'Hisobotlarni eksport qilish',
                'category' => 'reports',
                'description' => 'Excel va PDF formatida hisobotlarni yuklab olish huquqi',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Assign 'export_reports' to OWNER and ADMIN roles
        $ownerRoleId = DB::table('roles')->where('name', 'OWNER')->value('id');
        $adminRoleId = DB::table('roles')->where('name', 'ADMIN')->value('id');

        if ($ownerRoleId && $permId) {
            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $ownerRoleId,
                'permission_id' => $permId,
            ]);
        }

        if ($adminRoleId && $permId) {
            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $adminRoleId,
                'permission_id' => $permId,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};

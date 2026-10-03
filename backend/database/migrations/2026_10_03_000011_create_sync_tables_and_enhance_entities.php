<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Sales jadvaliga vaqtlar qo'shish (device_created_at, received_at, posted_at)
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('device_created_at')->nullable()->after('completed_at');
            $table->timestamp('received_at')->nullable()->after('device_created_at');
            $table->timestamp('posted_at')->nullable()->after('received_at');
        });

        // 2. sync_change_log (Commit-order cursor, change feed, entity versioning & tombstones)
        Schema::create('sync_change_log', function (Blueprint $table) {
            $table->id(); // BIGSERIAL - asosiy commit-order kursor
            $table->string('entity_type', 64)->index(); // PRODUCT, PRODUCT_VARIANT, VOLUME, CUSTOMER, PRICE, etc.
            $table->string('entity_id', 64)->index();
            $table->string('change_type', 32); // CREATED, UPDATED, DELETED
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_tombstone')->default(false)->index();
            $table->json('payload'); // Offline iste'mol uchun to'liq ma'lumotlar
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // 3. sync_conflicts (NEEDS_REVIEW operatsiyalari, late closed sessions, conflict resolution)
        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('operation_id')->index();
            $table->string('operation_type', 64)->index(); // CREATE_SALE, CREATE_CUSTOMER, etc.
            $table->string('status', 32)->default('NEEDS_REVIEW')->index(); // NEEDS_REVIEW, RESOLVED, REJECTED
            $table->timestamp('device_created_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->json('raw_payload'); // Asl mijoz yuborgan xom payload - o'zgarmas saqlanadi!
            $table->string('payload_fingerprint', 64)->nullable();
            $table->string('error_code', 64)->nullable()->index(); // LATE_CLOSED_SESSION, INSUFFICIENT_ALLOCATION, etc.
            $table->text('error_message')->nullable();
            $table->string('resolution_action', 64)->nullable(); // APPROVED_OVERRIDE, REJECTED, RETRIED
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('server_entity_type', 64)->nullable();
            $table->unsignedBigInteger('server_entity_id')->nullable();
            $table->timestamps();
        });

        // 4. device_cursors (Qurilmaning eng so'nggi tortib olgan kursor pozitsiyasi)
        Schema::create('device_cursors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->bigInteger('last_cursor')->default(0);
            $table->timestamp('last_pulled_at')->nullable();
            $table->timestamps();

            $table->unique('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_cursors');
        Schema::dropIfExists('sync_conflicts');
        Schema::dropIfExists('sync_change_log');

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['device_created_at', 'received_at', 'posted_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add telegram columns to users
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('telegram_chat_id')->nullable()->unique()->after('status');
            $table->string('telegram_username', 100)->nullable()->after('telegram_chat_id');
        });

        // 2. Telegram webhook received updates for deduplication
        Schema::create('telegram_updates', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('update_id')->unique();
            $table->bigInteger('chat_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });

        // 3. Conversational wizards draft states
        Schema::create('bot_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->bigInteger('chat_id')->index();
            $table->string('type', 50)->index(); // SALE, PURCHASE, CUSTOMER_PAYMENT, SUPPLIER_PAYMENT, QUICK_CUSTOMER
            $table->string('step', 50); // CURRENT_STEP
            $table->uuid('operation_id')->index();
            $table->json('payload');
            $table->timestamps();
        });

        // 4. Telegram notification deliveries from Outbox
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbox_event_id')->nullable()->constrained('outbox_events')->nullOnDelete();
            $table->bigInteger('chat_id')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 32)->default('telegram');
            $table->string('notification_type', 64)->index();
            $table->text('message_text');
            $table->string('status', 32)->default('PENDING')->index(); // PENDING, SENT, FAILED
            $table->integer('retry_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('bot_drafts');
        Schema::dropIfExists('telegram_updates');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_username']);
        });
    }
};

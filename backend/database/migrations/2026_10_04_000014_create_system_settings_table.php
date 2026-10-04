<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 128)->unique()->index();
            $table->json('value')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });

        // Default store settings seeding
        $defaultSettings = [
            [
                'key' => 'store_name',
                'value' => json_encode("AquaOptom Suv Do'koni"),
                'description' => "Do'kon nomi",
            ],
            [
                'key' => 'store_phone',
                'value' => json_encode('+998712000000'),
                'description' => "Do'kon aloqa telefoni",
            ],
            [
                'key' => 'store_address',
                'value' => json_encode('Toshkent shahri, Chilonzor tumani'),
                'description' => "Do'kon yuridik manzili",
            ],
            [
                'key' => 'timezone',
                'value' => json_encode('Asia/Tashkent'),
                'description' => 'Asosiy vaqt mintaqasi',
            ],
            [
                'key' => 'low_stock_threshold',
                'value' => json_encode(10),
                'description' => 'Kam qoldiq xabarnomasi chegarasi (dona)',
            ],
            [
                'key' => 'strict_credit_mode',
                'value' => json_encode(false),
                'description' => "Qat'iy kredit rejimi (limitdan ortiq nasiya taqiqlanadi)",
            ],
            [
                'key' => 'offline_reconcile_timeout_hours',
                'value' => json_encode(24),
                'description' => 'Offline qurilma aloqasiz qolishining ogohlantirish chegarasi (soat)',
            ],
        ];

        foreach ($defaultSettings as $setting) {
            DB::table('system_settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};

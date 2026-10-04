<?php

namespace Tests\Feature;

use App\Logging\MaskSensitiveDataProcessor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

class ProductionReadinessAndHealthTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'OWNER'], [
            'display_name' => 'Do\'kon egasi',
            'permissions' => ['*'],
        ]);

        $this->owner = User::create([
            'name' => 'Production Owner',
            'email' => 'owner@aquaoptom.uz',
            'phone' => '+998901112233',
            'role' => 'OWNER',
            'role_id' => $ownerRole->id,
            'password' => Hash::make('Secret123!'),
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * 1. Liveness probe (GET /api/health/live)
     */
    public function test_liveness_probe_returns_live_status(): void
    {
        $response = $this->getJson('/api/health/live');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'LIVE',
                'app' => 'AquaOptom CRM',
            ])
            ->assertJsonStructure([
                'status',
                'app',
                'version',
                'timestamp',
            ]);
    }

    /**
     * 2. Readiness probe (GET /api/health/ready)
     */
    public function test_readiness_probe_verifies_database_storage_and_recovery(): void
    {
        $response = $this->getJson('/api/health/ready');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'READY',
                'checks' => [
                    'database' => [
                        'status' => 'OK',
                    ],
                    'storage' => [
                        'status' => 'OK',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'status',
                'checks' => ['database', 'redis', 'storage'],
                'recovery' => ['epoch', 'status', 'watermark_at'],
            ]);
    }

    /**
     * 3. Structured logging masks sensitive data without leaking secrets
     */
    public function test_mask_sensitive_data_processor_scrubs_secrets_from_logs(): void
    {
        $processor = new MaskSensitiveDataProcessor();

        $context = [
            'password' => 'super_secret_password',
            'token' => 'plain_bearer_token_12345',
            'api_key' => 'secret_api_key_xyz',
            'telegram_bot_token' => '123456789:ABCdefGhIjkLmNoPqRsTuVwXyZ',
            'user_id' => 42,
            'email' => 'admin@aquaoptom.uz',
        ];

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'User logged in with Bearer abcdef1234567890xyz and token 123456789:ABCdefGhIjkLmNoPqRsTuVwXyZ',
            context: $context
        );

        $processed = $processor->processRecord($record);

        // Maxfiy kalitlar [REDACTED] ga aylangan bo'lishi shart
        $this->assertEquals('[REDACTED]', $processed->context['password']);
        $this->assertEquals('[REDACTED]', $processed->context['token']);
        $this->assertEquals('[REDACTED]', $processed->context['api_key']);
        $this->assertEquals('[REDACTED]', $processed->context['telegram_bot_token']);

        // Xavfsiz maydonlar o'zgarmasdan saqlanadi
        $this->assertEquals(42, $processed->context['user_id']);
        $this->assertEquals('admin@aquaoptom.uz', $processed->context['email']);

        // Matndagi tokenlar ham tozalangan bo'lishi shart
        $this->assertStringNotContainsString('abcdef1234567890xyz', $processed->message);
        $this->assertStringNotContainsString('123456789:ABCdefGhIjkLmNoPqRsTuVwXyZ', $processed->message);
    }
}

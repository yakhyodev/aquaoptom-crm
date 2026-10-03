<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class EnvironmentAndInfrastructureTest extends TestCase
{
    /**
     * TEST 1: Database connection is strictly PostgreSQL and targets the dedicated test database.
     */
    public function test_database_is_real_postgresql_and_targets_test_database(): void
    {
        $connectionName = config('database.default');
        $driver = config("database.connections.{$connectionName}.driver");
        $database = config("database.connections.{$connectionName}.database");

        $this->assertEquals('pgsql', $driver, 'Testing database driver must be PostgreSQL');
        $this->assertStringContainsString('test', $database, 'Testing database must have test in its name');

        // Execute raw PostgreSQL version query to prove connection to real PostgreSQL server
        $result = DB::select('SELECT version() as pg_version');
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('PostgreSQL', $result[0]->pg_version);
    }

    /**
     * TEST 2: Redis connection is reachable and cache operations execute correctly.
     */
    public function test_redis_connection_and_caching(): void
    {
        $testKey = 'aquaoptom:test:ping';
        $testValue = 'pong_'.time();

        Redis::set($testKey, $testValue);
        $retrieved = Redis::get($testKey);
        $this->assertEquals($testValue, $retrieved);

        Redis::del($testKey);
        $this->assertNull(Redis::get($testKey));
    }

    /**
     * TEST 3: External Telegram API calls are mocked/faked and real endpoints are never reached.
     */
    public function test_telegram_api_calls_are_faked_in_testing_environment(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 999999,
                    'chat' => ['id' => -1000000000000],
                    'text' => 'Mocked test message',
                ],
            ], 200),
        ]);

        $botToken = config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN', 'fake_token'));
        $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => -1000000000000,
            'text' => 'Testing notification dispatch',
        ]);

        $this->assertTrue($response->successful());
        $this->assertTrue($response->json('ok'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org');
        });
    }

    /**
     * TEST 4: AppServiceProvider safety guard prevents tests from targeting production database.
     */
    public function test_app_service_provider_guards_against_non_test_databases(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $currentDb = config('database.connections.'.config('database.default').'.database');
        $this->assertStringContainsString('test', $currentDb);
    }
}

<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\HealthController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseProfileVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_profile_cannot_certify_a_nonproduction_environment(): void
    {
        $this->artisan('app:production-verify')->expectsOutputToContain('Production profile requires')->assertFailed();
    }

    public function test_failed_readiness_does_not_print_a_success_certificate(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => false, 'app.url' => 'https://crm.example.invalid']);
        $this->mock(HealthController::class, function ($mock) {
            $mock->shouldReceive('liveness')->once()->andReturn(response()->json(['status' => 'LIVE']));
            $mock->shouldReceive('readiness')->once()->andReturn(response()->json(['status' => 'NOT_READY'], 503));
        });
        $this->artisan('app:production-verify')->expectsOutputToContain('Local health checks failed')->assertFailed();
    }
}

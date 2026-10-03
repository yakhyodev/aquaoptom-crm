<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test verifying login page is accessible and authenticated root redirects to dashboard.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Unauthenticated access to /login returns 200
        $response = $this->get('/login');
        $response->assertStatus(200);

        // Authenticated access to / redirects to /dashboard
        $user = User::factory()->create();
        $authResponse = $this->actingAs($user)->get('/');
        $authResponse->assertRedirect('/dashboard');
    }
}

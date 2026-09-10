<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_responses_include_correlation_and_security_headers(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('X-Request-ID', 'squadup-test-1234')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertHeader('X-Request-ID', 'squadup-test-1234')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_invalid_correlation_id_is_replaced(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('X-Request-ID', '<invalid>')
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            (string) $response->headers->get('X-Request-ID')
        );
    }

    public function test_readiness_probe_checks_application_dependencies(): void
    {
        $this->getJson('/api/v1/ready')
            ->assertOk()
            ->assertExactJson(['status' => 'ready'])
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_report_submission_has_a_stricter_abuse_limit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (range(1, 10) as $attempt) {
            $this->postJson('/api/v1/reports', [])->assertUnprocessable();
        }

        $this->postJson('/api/v1/reports', [])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }
}

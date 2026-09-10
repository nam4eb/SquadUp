<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\EnsureIdempotent;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiRouteSecurityAuditTest extends TestCase
{
    private const PUBLIC_ROUTES = [
        'api/v1/ready',
        'api/v1/auth/register',
        'api/v1/auth/login',
        'api/v1/auth/forgot-password',
        'api/v1/auth/reset-password',
        'api/v1/auth/oauth/{provider}',
    ];

    public function test_every_non_public_api_route_requires_sanctum(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/') || in_array($route->uri(), self::PUBLIC_ROUTES, true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $this->assertTrue(
                in_array('auth:sanctum', $middleware, true),
                "Route {$route->uri()} must require authenticated Sanctum access."
            );
            $this->assertContains(EnsureIdempotent::class, $middleware, "Route {$route->uri()} must support idempotency.");
        }
    }

    public function test_media_download_requires_a_valid_signature(): void
    {
        $route = Route::getRoutes()->getByName('media.show');

        $this->assertNotNull($route);
        $this->assertContains('signed', $route->gatherMiddleware());
    }
}

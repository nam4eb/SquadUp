<?php

use App\Exceptions\ActivityException;
use App\Exceptions\ChatException;
use App\Exceptions\ClanException;
use App\Exceptions\SocialGraphException;
use App\Http\Middleware\RequestTelemetry;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'prefix' => 'api',
        'middleware' => ['api', 'auth:sanctum'],
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(append: [RequestTelemetry::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ChatException $exception, Request $request): ?Response {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'code' => $exception->errorCode,
                ], $exception->status);
            }

            return null;
        });
        $exceptions->render(function (ClanException $exception, Request $request): ?Response {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'code' => $exception->errorCode,
                ], $exception->status);
            }

            return null;
        });

        $exceptions->render(function (ActivityException $exception, Request $request): ?Response {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'code' => $exception->errorCode,
                ], $exception->status);
            }

            return null;
        });

        $exceptions->render(function (SocialGraphException $exception, Request $request): ?Response {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'code' => $exception->errorCode,
                ], $exception->status);
            }

            return null;
        });

        $exceptions->render(function (ValidationException $exception, Request $request): ?Response {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'The request data is invalid.',
                    'code' => 'VALIDATION_FAILED',
                    'errors' => $exception->errors(),
                ], $exception->status);
            }

            return null;
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request): ?Response {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication is required.',
                    'code' => 'UNAUTHENTICATED',
                ], 401);
            }

            return null;
        });
    })->create();

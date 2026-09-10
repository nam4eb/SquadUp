<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestTelemetry
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);
        $request->attributes->set('request_id', $requestId);
        $startedAt = hrtime(true);
        $profileDatabase = (bool) config('performance.profile_database', false);
        if ($profileDatabase) {
            DB::flushQueryLog();
            DB::enableQueryLog();
        }

        $response = $next($request);
        $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 2);
        $queries = $profileDatabase ? DB::getQueryLog() : [];
        $databaseMs = round(array_sum(array_column($queries, 'time')), 2);
        if ($profileDatabase) {
            DB::disableQueryLog();
            DB::flushQueryLog();
            $response->headers->set('X-DB-Query-Count', (string) count($queries));
            $response->headers->set('Server-Timing', "db;dur={$databaseMs}, app;dur={$durationMs}");
        }
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Frame-Options', 'DENY');

        Log::info('api.request.completed', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'database_ms' => $profileDatabase ? $databaseMs : null,
            'database_query_count' => $profileDatabase ? count($queries) : null,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'ip' => $request->ip(),
        ]);

        return $response;
    }

    private function requestId(Request $request): string
    {
        $candidate = $request->header('X-Request-ID');

        return is_string($candidate) && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $candidate)
            ? $candidate
            : (string) Str::uuid();
    }
}

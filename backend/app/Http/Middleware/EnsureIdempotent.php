<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotent
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $key = trim((string) $request->header('Idempotency-Key'));
        if ($key === '') {
            return $next($request);
        }
        if (mb_strlen($key) > 100) {
            return response()->json([
                'success' => false, 'message' => 'The idempotency key is too long.',
                'code' => 'INVALID_IDEMPOTENCY_KEY',
            ], 422);
        }

        $route = $request->method().' '.$request->path();
        $hash = $this->requestHash($request);
        $stored = IdempotencyKey::query()->where('user_id', $request->user()->id)
            ->where('key', $key)->where('expires_at', '>', now())->first();
        if ($stored) {
            if ($stored->route !== $route || $stored->request_hash !== $hash) {
                return response()->json([
                    'success' => false,
                    'message' => 'This idempotency key was used for a different request.',
                    'code' => 'IDEMPOTENCY_KEY_REUSED',
                ], 409);
            }

            return new JsonResponse(
                json_decode($stored->response_body, true),
                $stored->response_status,
                ['Idempotency-Replayed' => 'true'],
            );
        }

        $response = $next($request);
        if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
            IdempotencyKey::create([
                'user_id' => $request->user()->id,
                'key' => $key,
                'route' => $route,
                'request_hash' => $hash,
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'expires_at' => now()->addDay(),
            ]);
        }

        return $response;
    }

    private function requestHash(Request $request): string
    {
        $files = collect($request->allFiles())->map(
            fn ($file) => is_array($file)
                ? collect($file)->map->hashName()->all()
                : [$file->getClientOriginalName(), $file->getSize(), hash_file('sha256', $file->getRealPath())],
        )->all();

        return hash('sha256', json_encode([$request->all(), $files], JSON_THROW_ON_ERROR));
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $cacheKey = 'health:'.Str::uuid();

        try {
            DB::select('select 1');
            Cache::put($cacheKey, 'ready', 10);
            throw_unless(Cache::get($cacheKey) === 'ready', new \RuntimeException('Cache write check failed.'));

            return response()->json(['status' => 'ready']);
        } catch (Throwable $exception) {
            Log::error('health.readiness.failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['status' => 'unavailable'], 503);
        } finally {
            rescue(fn () => Cache::forget($cacheKey), report: false);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken();
        $currentId = $current instanceof PersonalAccessToken ? $current->id : null;
        $data = $request->user()->tokens()->latest()->get()->map(fn ($token) => [
            'id' => (string) $token->id,
            'device_name' => $token->name,
            'is_current' => $token->id === $currentId,
            'last_used_at' => $token->last_used_at?->toISOString(),
            'expires_at' => $token->expires_at?->toISOString(),
            'created_at' => $token->created_at?->toISOString(),
        ]);

        return response()->json(['data' => $data]);
    }

    public function destroy(string $token, Request $request): JsonResponse
    {
        $session = $request->user()->tokens()->findOrFail($token);
        $current = $request->user()->currentAccessToken();
        $isCurrent = $current instanceof PersonalAccessToken && $session->id === $current->id;
        $session->delete();

        return response()->json([
            'message' => 'Session revoked.',
            'current_session_revoked' => $isCurrent,
        ]);
    }
}

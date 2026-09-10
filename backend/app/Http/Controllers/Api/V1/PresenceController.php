<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PresenceController extends Controller
{
    public function heartbeat(Request $request, PresenceService $presence): JsonResponse
    {
        $validated = $request->validate(['status' => ['sometimes', Rule::in(['online', 'away'])]]);

        return response()->json(['data' => $presence->heartbeat($request->user(), $validated['status'] ?? 'online')]);
    }

    public function offline(Request $request, PresenceService $presence): JsonResponse
    {
        return response()->json(['data' => $presence->offline($request->user())]);
    }

    public function show(User $user, PresenceService $presence): JsonResponse
    {
        return response()->json(['data' => $presence->get($user)]);
    }
}

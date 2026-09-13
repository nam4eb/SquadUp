<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\User;
use App\Services\ReputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserReputationController extends Controller
{
    public function show(User $user, Request $request, ReputationService $service): JsonResponse
    {
        abort_if(Block::query()->between($request->user()->id, $user->id)->exists(), 404);
        $reputation = $user->reputation ?? $service->refresh($user->id);

        return response()->json(['data' => $reputation]);
    }
}

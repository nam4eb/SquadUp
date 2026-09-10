<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Friends\BlockUser;
use App\Actions\Friends\UnblockUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\BlockResource;
use App\Models\Block;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BlockController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return BlockResource::collection(
            Block::query()->with('blocked')->where('blocker_id', $request->user()->id)->latest()->paginate(20),
        );
    }

    public function store(User $user, Request $request, BlockUser $action): BlockResource
    {
        return new BlockResource($action->handle($request->user(), $user));
    }

    public function destroy(User $user, Request $request, UnblockUser $action): JsonResponse
    {
        $action->handle($request->user(), $user);

        return response()->json(['message' => 'User unblocked.']);
    }
}

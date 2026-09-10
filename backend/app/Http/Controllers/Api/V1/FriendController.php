<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Friends\RemoveFriend;
use App\Http\Controllers\Controller;
use App\Http\Resources\FriendshipResource;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FriendController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $friendships = Friendship::query()
            ->with(['lowUser', 'highUser'])
            ->where(fn ($query) => $query->where('user_low_id', $request->user()->id)
                ->orWhere('user_high_id', $request->user()->id))
            ->latest('accepted_at')
            ->paginate(20);

        return FriendshipResource::collection($friendships);
    }

    public function destroy(User $user, Request $request, RemoveFriend $action): JsonResponse
    {
        $action->handle($request->user(), $user);

        return response()->json(['message' => 'Friend removed.']);
    }
}

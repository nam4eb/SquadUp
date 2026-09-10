<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Friends\AcceptFriendRequest;
use App\Actions\Friends\SendFriendRequest;
use App\Actions\Friends\TransitionFriendRequest;
use App\Enums\FriendRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendFriendRequestRequest;
use App\Http\Resources\FriendRequestResource;
use App\Http\Resources\FriendshipResource;
use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FriendRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $direction = $request->validate(['direction' => ['nullable', 'in:incoming,outgoing']])['direction'] ?? 'incoming';
        $column = $direction === 'incoming' ? 'receiver_id' : 'sender_id';
        $requests = FriendRequest::query()
            ->with(['sender', 'receiver'])
            ->where($column, $request->user()->id)
            ->where('status', FriendRequestStatus::Pending)
            ->latest()
            ->paginate(20);

        return FriendRequestResource::collection($requests);
    }

    public function store(SendFriendRequestRequest $request, SendFriendRequest $action): FriendRequestResource
    {
        $friendRequest = $action->handle($request->user(), User::query()->findOrFail($request->validated('receiver_id')));

        return new FriendRequestResource($friendRequest);
    }

    public function accept(FriendRequest $friendRequest, Request $request, AcceptFriendRequest $action): FriendshipResource
    {
        return new FriendshipResource($action->handle($friendRequest, $request->user()));
    }

    public function reject(FriendRequest $friendRequest, Request $request, TransitionFriendRequest $action): FriendRequestResource
    {
        return new FriendRequestResource($action->reject($friendRequest, $request->user()));
    }

    public function cancel(FriendRequest $friendRequest, Request $request, TransitionFriendRequest $action): FriendRequestResource
    {
        return new FriendRequestResource($action->cancel($friendRequest, $request->user()));
    }
}

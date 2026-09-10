<?php

namespace App\Actions\Friends;

use App\Enums\FriendRequestStatus;
use App\Exceptions\SocialGraphException;
use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransitionFriendRequest
{
    public function reject(FriendRequest $request, User $actor): FriendRequest
    {
        return $this->transition($request, $actor, FriendRequestStatus::Rejected, receiverAction: true);
    }

    public function cancel(FriendRequest $request, User $actor): FriendRequest
    {
        return $this->transition($request, $actor, FriendRequestStatus::Cancelled, receiverAction: false);
    }

    private function transition(FriendRequest $request, User $actor, FriendRequestStatus $status, bool $receiverAction): FriendRequest
    {
        return DB::transaction(function () use ($request, $actor, $status, $receiverAction): FriendRequest {
            $request = FriendRequest::query()->lockForUpdate()->findOrFail($request->id);
            $expectedActor = $receiverAction ? $request->receiver_id : $request->sender_id;
            if ($expectedActor !== $actor->id) {
                throw new SocialGraphException('You cannot update this friend request.', 'FRIEND_REQUEST_FORBIDDEN', 403);
            }
            if ($request->status !== FriendRequestStatus::Pending) {
                throw new SocialGraphException('This request is no longer pending.', 'FRIEND_REQUEST_NOT_PENDING');
            }
            $request->update(['status' => $status, 'responded_at' => now()]);

            return $request->refresh()->load(['sender', 'receiver']);
        });
    }
}

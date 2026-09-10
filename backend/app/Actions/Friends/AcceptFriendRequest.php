<?php

namespace App\Actions\Friends;

use App\Enums\FriendRequestStatus;
use App\Exceptions\SocialGraphException;
use App\Models\Block;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use App\Support\UserPair;
use Illuminate\Support\Facades\DB;

class AcceptFriendRequest
{
    public function handle(FriendRequest $request, User $actor): Friendship
    {
        $friendship = DB::transaction(function () use ($request, $actor): Friendship {
            $request = FriendRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->receiver_id !== $actor->id) {
                throw new SocialGraphException('Only the recipient can accept this request.', 'NOT_REQUEST_RECIPIENT', 403);
            }
            if ($request->status !== FriendRequestStatus::Pending) {
                throw new SocialGraphException('This request is no longer pending.', 'FRIEND_REQUEST_NOT_PENDING');
            }
            if (Block::query()->between($request->sender_id, $request->receiver_id)->exists()) {
                throw new SocialGraphException('Interaction is not allowed between these users.', 'USER_BLOCKED', 403);
            }

            [$low, $high] = UserPair::ordered($request->sender_id, $request->receiver_id);
            $friendship = Friendship::query()->firstOrCreate(
                ['pair_key' => $request->pair_key],
                ['user_low_id' => $low, 'user_high_id' => $high, 'accepted_at' => now()],
            );
            $request->update(['status' => FriendRequestStatus::Accepted, 'responded_at' => now()]);

            return $friendship->load(['lowUser', 'highUser']);
        });

        $request->refresh()->load(['sender', 'receiver']);
        $request->sender->notify(new FriendRequestAccepted($request));

        return $friendship;
    }
}

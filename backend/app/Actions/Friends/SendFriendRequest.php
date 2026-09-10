<?php

namespace App\Actions\Friends;

use App\Enums\AccountStatus;
use App\Enums\FriendRequestStatus;
use App\Exceptions\SocialGraphException;
use App\Models\Block;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendRequestReceived;
use App\Support\UserPair;
use Illuminate\Support\Facades\DB;

class SendFriendRequest
{
    public function handle(User $sender, User $receiver): FriendRequest
    {
        if ($sender->is($receiver)) {
            throw new SocialGraphException('You cannot send a friend request to yourself.', 'SELF_FRIEND_REQUEST', 422);
        }
        if ($receiver->account_status !== AccountStatus::Active) {
            throw new SocialGraphException('This user is unavailable.', 'USER_UNAVAILABLE', 404);
        }

        $friendRequest = DB::transaction(function () use ($sender, $receiver): FriendRequest {
            $pairKey = UserPair::key($sender->id, $receiver->id);
            if (Block::query()->between($sender->id, $receiver->id)->exists()) {
                throw new SocialGraphException('Interaction is not allowed between these users.', 'USER_BLOCKED', 403);
            }
            if (Friendship::query()->where('pair_key', $pairKey)->exists()) {
                throw new SocialGraphException('You are already friends.', 'ALREADY_FRIENDS');
            }

            $existing = FriendRequest::query()->where('pair_key', $pairKey)->lockForUpdate()->first();
            if ($existing?->status === FriendRequestStatus::Pending) {
                throw new SocialGraphException('A friend request is already pending.', 'FRIEND_REQUEST_PENDING');
            }

            $request = $existing ?? new FriendRequest(['pair_key' => $pairKey]);
            $request->fill([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'status' => FriendRequestStatus::Pending,
                'responded_at' => null,
            ])->save();

            return $request->load(['sender', 'receiver']);
        });

        $receiver->notify(new FriendRequestReceived($friendRequest));

        return $friendRequest;
    }
}

<?php

namespace App\Actions\Friends;

use App\Enums\FriendRequestStatus;
use App\Exceptions\SocialGraphException;
use App\Models\Block;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;
use App\Support\UserPair;
use Illuminate\Support\Facades\DB;

class BlockUser
{
    public function handle(User $actor, User $target): Block
    {
        if ($actor->is($target)) {
            throw new SocialGraphException('You cannot block yourself.', 'SELF_BLOCK', 422);
        }

        return DB::transaction(function () use ($actor, $target): Block {
            $block = Block::query()->firstOrCreate(['blocker_id' => $actor->id, 'blocked_id' => $target->id]);
            $pairKey = UserPair::key($actor->id, $target->id);
            Friendship::query()->where('pair_key', $pairKey)->delete();
            FriendRequest::query()
                ->where('pair_key', $pairKey)
                ->where('status', FriendRequestStatus::Pending)
                ->update(['status' => FriendRequestStatus::Blocked, 'responded_at' => now(), 'updated_at' => now()]);

            return $block->load('blocked');
        });
    }
}

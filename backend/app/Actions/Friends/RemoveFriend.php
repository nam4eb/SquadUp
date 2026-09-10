<?php

namespace App\Actions\Friends;

use App\Exceptions\SocialGraphException;
use App\Models\Friendship;
use App\Models\User;
use App\Support\UserPair;

class RemoveFriend
{
    public function handle(User $actor, User $friend): void
    {
        $deleted = Friendship::query()->where('pair_key', UserPair::key($actor->id, $friend->id))->delete();
        if ($deleted === 0) {
            throw new SocialGraphException('Friendship not found.', 'FRIENDSHIP_NOT_FOUND', 404);
        }
    }
}

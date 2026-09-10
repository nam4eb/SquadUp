<?php

namespace App\Actions\Friends;

use App\Exceptions\SocialGraphException;
use App\Models\Block;
use App\Models\User;

class UnblockUser
{
    public function handle(User $actor, User $target): void
    {
        $deleted = Block::query()->where(['blocker_id' => $actor->id, 'blocked_id' => $target->id])->delete();
        if ($deleted === 0) {
            throw new SocialGraphException('Block not found.', 'BLOCK_NOT_FOUND', 404);
        }
    }
}

<?php

namespace App\Policies;

use App\Models\Clan;
use App\Models\User;
use App\Support\ClanAuthorizer;
use Illuminate\Auth\Access\Response;

class ClanPolicy
{
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function view(User $user, Clan $clan): Response
    {
        if ($clan->status !== 'active') {
            return Response::denyAsNotFound();
        }
        if ($clan->visibility === 'public' || $clan->owner_id === $user->id) {
            return Response::allow();
        }
        $member = ClanAuthorizer::membership($clan, $user);

        return $member && in_array($member->status->value, ['active', 'invited', 'requested'], true)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}

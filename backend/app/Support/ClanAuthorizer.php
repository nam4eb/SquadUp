<?php

namespace App\Support;

use App\Enums\ClanMemberStatus;
use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\User;

final class ClanAuthorizer
{
    public static function membership(Clan $clan, User $user): ?ClanMember
    {
        return $clan->members()->with('role.permissions')->where('user_id', $user->id)->first();
    }

    public static function allows(Clan $clan, User $user, string $permission): bool
    {
        if ($clan->owner_id === $user->id) {
            return true;
        }
        $member = self::membership($clan, $user);

        return $member?->status === ClanMemberStatus::Active
            && $member->role?->permissions->contains('permission', $permission);
    }
}

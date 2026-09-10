<?php

namespace App\Actions\Clans;

use App\Enums\ClanMemberStatus;
use App\Exceptions\ClanException;
use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\ClanRole;
use App\Models\User;
use App\Notifications\ClanRoleChanged;
use App\Support\ClanAuthorizer;

class AssignClanRole
{
    public function handle(Clan $clan, ClanMember $member, ClanRole $role, User $actor): ClanMember
    {
        if (! ClanAuthorizer::allows($clan, $actor, 'manage_members')) {
            throw new ClanException('You cannot assign clan roles.', 'CLAN_PERMISSION_DENIED', 403);
        }
        if ($member->clan_id !== $clan->id || $role->clan_id !== $clan->id) {
            throw new ClanException('Member or role does not belong to this clan.', 'CLAN_ROLE_MISMATCH', 404);
        }
        if ($member->status !== ClanMemberStatus::Active) {
            throw new ClanException('Only active members can receive a role.', 'CLAN_MEMBER_NOT_ACTIVE', 422);
        }
        if ($member->user_id === $clan->owner_id || $role->slug === 'owner') {
            throw new ClanException('Use ownership transfer for the Owner role.', 'CLAN_OWNER_ROLE_RESTRICTED', 422);
        }
        $member->update(['clan_role_id' => $role->id]);
        $member->load(['user', 'role', 'clan']);
        $member->user->notify(new ClanRoleChanged($member));

        return $member;
    }
}

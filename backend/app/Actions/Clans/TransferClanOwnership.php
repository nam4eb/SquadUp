<?php

namespace App\Actions\Clans;

use App\Enums\ClanMemberStatus;
use App\Exceptions\ClanException;
use App\Models\Clan;
use App\Models\User;
use App\Notifications\ClanRoleChanged;
use Illuminate\Support\Facades\DB;

class TransferClanOwnership
{
    public function handle(Clan $clan, User $owner, User $newOwner): Clan
    {
        return DB::transaction(function () use ($clan, $owner, $newOwner): Clan {
            $clan = Clan::query()->lockForUpdate()->findOrFail($clan->id);
            if ($clan->owner_id !== $owner->id) {
                throw new ClanException('Only the owner can transfer the clan.', 'NOT_CLAN_OWNER', 403);
            }
            $newMembership = $clan->members()->where('user_id', $newOwner->id)
                ->where('status', ClanMemberStatus::Active)->lockForUpdate()->first();
            if (! $newMembership) {
                throw new ClanException('The new owner must be an active member.', 'NEW_CLAN_OWNER_NOT_ACTIVE', 422);
            }
            $oldMembership = $clan->members()->where('user_id', $owner->id)->lockForUpdate()->firstOrFail();
            $ownerRole = $clan->roles()->where('slug', 'owner')->firstOrFail();
            $leaderRole = $clan->roles()->where('slug', 'leader')->firstOrFail();
            $oldMembership->update(['clan_role_id' => $leaderRole->id]);
            $newMembership->update(['clan_role_id' => $ownerRole->id]);
            $clan->update(['owner_id' => $newOwner->id]);
            $newMembership->load(['user', 'role', 'clan']);
            $newOwner->notify(new ClanRoleChanged($newMembership));

            return $clan->load('owner')->loadCount(['members as active_members_count' => fn ($query) => $query->where('status', 'active')]);
        });
    }
}

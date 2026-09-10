<?php

namespace App\Actions\Clans;

use App\Enums\ClanMemberStatus;
use App\Exceptions\ClanException;
use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\User;
use App\Notifications\ClanInvitationReceived;
use App\Support\ClanAuthorizer;
use Illuminate\Support\Facades\DB;

class ClanMembership
{
    public function join(Clan $clan, User $user): ClanMember
    {
        return DB::transaction(function () use ($clan, $user): ClanMember {
            $clan = Clan::query()->lockForUpdate()->findOrFail($clan->id);
            if ($clan->status !== 'active') {
                throw new ClanException('This clan is unavailable.', 'CLAN_UNAVAILABLE', 404);
            }
            $member = $clan->members()->where('user_id', $user->id)->first();
            if ($member?->status === ClanMemberStatus::Banned) {
                throw new ClanException('You are banned from this clan.', 'CLAN_BANNED', 403);
            }
            if ($member && in_array($member->status, [ClanMemberStatus::Active, ClanMemberStatus::Requested], true)) {
                throw new ClanException('You already have an active clan membership.', 'CLAN_MEMBERSHIP_EXISTS');
            }
            $status = match (true) {
                $member?->status === ClanMemberStatus::Invited => ClanMemberStatus::Active,
                $clan->join_policy === 'open' => ClanMemberStatus::Active,
                $clan->join_policy === 'approval' => ClanMemberStatus::Requested,
                default => throw new ClanException('This clan is invite only.', 'CLAN_INVITE_REQUIRED', 403),
            };
            $memberRole = $clan->roles()->where('slug', 'member')->firstOrFail();
            $values = [
                'clan_role_id' => $memberRole->id,
                'status' => $status,
                'joined_at' => $status === ClanMemberStatus::Active ? now() : null,
                'left_at' => null,
            ];
            if ($member) {
                $member->update($values);
            } else {
                $member = $clan->members()->create(['user_id' => $user->id, ...$values]);
            }

            return $member->load(['user', 'role']);
        });
    }

    public function leave(Clan $clan, User $user): void
    {
        if ($clan->owner_id === $user->id) {
            throw new ClanException('The owner must transfer ownership before leaving.', 'CLAN_OWNER_CANNOT_LEAVE', 422);
        }
        $member = $clan->members()->where('user_id', $user->id)->first();
        if (! $member || ! in_array($member->status, [ClanMemberStatus::Active, ClanMemberStatus::Requested, ClanMemberStatus::Invited], true)) {
            throw new ClanException('Active membership was not found.', 'CLAN_MEMBERSHIP_NOT_FOUND', 404);
        }
        $member->update(['status' => ClanMemberStatus::Left, 'left_at' => now()]);
    }

    public function invite(Clan $clan, User $actor, User $invitee): ClanMember
    {
        if (! ClanAuthorizer::allows($clan, $actor, 'invite_members')) {
            throw new ClanException('You cannot invite clan members.', 'CLAN_PERMISSION_DENIED', 403);
        }
        if ($clan->owner_id === $invitee->id) {
            throw new ClanException('This user is already the owner.', 'CLAN_MEMBERSHIP_EXISTS');
        }
        $member = $clan->members()->where('user_id', $invitee->id)->first();
        if ($member?->status === ClanMemberStatus::Banned) {
            throw new ClanException('This user is banned from the clan.', 'CLAN_BANNED', 403);
        }
        if ($member?->status === ClanMemberStatus::Active) {
            throw new ClanException('This user is already a member.', 'CLAN_MEMBERSHIP_EXISTS');
        }
        $role = $clan->roles()->where('slug', 'member')->firstOrFail();
        $values = [
            'clan_role_id' => $role->id, 'invited_by' => $actor->id,
            'status' => ClanMemberStatus::Invited, 'joined_at' => null, 'left_at' => null,
        ];
        if ($member) {
            $member->update($values);
        } else {
            $member = $clan->members()->create(['user_id' => $invitee->id, ...$values]);
        }

        $member->load(['user', 'role', 'clan', 'inviter']);
        $invitee->notify(new ClanInvitationReceived($member));

        return $member;
    }

    public function review(Clan $clan, ClanMember $member, User $actor, bool $accept): ClanMember
    {
        if (! ClanAuthorizer::allows($clan, $actor, 'manage_members')) {
            throw new ClanException('You cannot review clan members.', 'CLAN_PERMISSION_DENIED', 403);
        }
        if ($member->clan_id !== $clan->id || $member->status !== ClanMemberStatus::Requested) {
            throw new ClanException('This membership is not awaiting review.', 'CLAN_MEMBERSHIP_NOT_REQUESTED', 422);
        }
        $member->update([
            'status' => $accept ? ClanMemberStatus::Active : ClanMemberStatus::Removed,
            'joined_at' => $accept ? now() : null,
            'left_at' => $accept ? null : now(),
        ]);

        return $member->load(['user', 'role']);
    }

    public function remove(Clan $clan, ClanMember $member, User $actor, bool $ban): ClanMember
    {
        $permission = $ban ? 'ban_members' : 'remove_members';
        if (! ClanAuthorizer::allows($clan, $actor, $permission)) {
            throw new ClanException('You cannot manage this clan member.', 'CLAN_PERMISSION_DENIED', 403);
        }
        if ($member->clan_id !== $clan->id) {
            throw new ClanException('Membership does not belong to this clan.', 'CLAN_MEMBERSHIP_MISMATCH', 404);
        }
        if ($member->user_id === $clan->owner_id) {
            throw new ClanException('The clan owner cannot be removed.', 'CLAN_OWNER_CANNOT_BE_REMOVED', 422);
        }
        $member->update([
            'status' => $ban ? ClanMemberStatus::Banned : ClanMemberStatus::Removed,
            'left_at' => now(),
        ]);

        return $member->load(['user', 'role']);
    }
}

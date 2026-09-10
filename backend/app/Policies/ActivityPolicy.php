<?php

namespace App\Policies;

use App\Enums\ActivityInvitationStatus;
use App\Enums\ActivityStatus;
use App\Enums\ActivityVisibility;
use App\Models\Activity;
use App\Models\Block;
use App\Models\Friendship;
use App\Models\User;
use App\Support\ClanAuthorizer;
use App\Support\UserPair;
use Illuminate\Auth\Access\Response;

class ActivityPolicy
{
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function view(User $user, Activity $activity): Response
    {
        if ($activity->host_id === $user->id) {
            return Response::allow();
        }
        if (Block::query()->between($user->id, $activity->host_id)->exists()) {
            return Response::denyAsNotFound();
        }
        if (in_array($activity->status, [ActivityStatus::Draft, ActivityStatus::Cancelled, ActivityStatus::Expired], true)) {
            return Response::denyAsNotFound();
        }
        if ($activity->participants()->where('user_id', $user->id)->whereIn('status', ['joined', 'requested', 'waitlisted'])->exists()) {
            return Response::allow();
        }
        if ($activity->invitations()
            ->where('invitee_id', $user->id)
            ->whereIn('status', [ActivityInvitationStatus::Pending, ActivityInvitationStatus::Accepted])
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists()) {
            return Response::allow();
        }
        if ($activity->visibility === ActivityVisibility::Public) {
            return Response::allow();
        }
        if ($activity->visibility === ActivityVisibility::Friends && Friendship::query()->where('pair_key', UserPair::key($user->id, $activity->host_id))->exists()) {
            return Response::allow();
        }
        if ($activity->visibility === ActivityVisibility::Clan) {
            $clan = $activity->clanEvent()->with('clan')->first()?->clan;
            if ($clan && ClanAuthorizer::membership($clan, $user)?->status?->value === 'active') {
                return Response::allow();
            }
        }

        return Response::denyAsNotFound();
    }

    public function manage(User $user, Activity $activity): bool
    {
        if ($activity->host_id === $user->id) {
            return true;
        }
        $clan = $activity->clanEvent()->with('clan')->first()?->clan;

        return $clan && ClanAuthorizer::allows($clan, $user, 'manage_events');
    }
}

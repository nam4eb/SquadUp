<?php

namespace App\Actions\Activities;

use App\Enums\AccountStatus;
use App\Enums\ActivityInvitationStatus;
use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Models\ActivityInvitation;
use App\Models\Block;
use App\Models\User;
use App\Notifications\ActivityInvitationReceived;

class CreateDirectInvitation
{
    public function handle(Activity $activity, User $host, User $invitee, ?string $expiresAt = null): ActivityInvitation
    {
        if ($activity->host_id !== $host->id) {
            throw new ActivityException('Only the host can invite users.', 'NOT_ACTIVITY_HOST', 403);
        }
        if ($invitee->id === $host->id) {
            throw new ActivityException('The host is already participating.', 'ALREADY_PARTICIPATING', 422);
        }
        if ($invitee->account_status !== AccountStatus::Active || Block::query()->between($host->id, $invitee->id)->exists()) {
            throw new ActivityException('This user cannot be invited.', 'USER_UNAVAILABLE', 404);
        }
        if ($activity->participants()->where('user_id', $invitee->id)->whereIn('status', ['joined', 'requested', 'waitlisted'])->exists()) {
            throw new ActivityException('This user is already participating.', 'ALREADY_PARTICIPATING');
        }

        $invitation = ActivityInvitation::query()->updateOrCreate(
            ['activity_id' => $activity->id, 'invitee_id' => $invitee->id],
            [
                'inviter_id' => $host->id,
                'status' => ActivityInvitationStatus::Pending,
                'expires_at' => $expiresAt,
                'responded_at' => null,
            ],
        )->load(['activity', 'inviter', 'invitee']);
        $invitee->notify(new ActivityInvitationReceived($invitation));

        return $invitation;
    }
}

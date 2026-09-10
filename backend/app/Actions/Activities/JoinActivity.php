<?php

namespace App\Actions\Activities;

use App\Enums\ActivityInvitationStatus;
use App\Enums\ActivityParticipantStatus;
use App\Enums\ActivityStatus;
use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\Block;
use App\Models\User;
use App\Notifications\ActivityParticipantJoined;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class JoinActivity
{
    public function handle(Activity $activity, User $user, ?string $password = null): ActivityParticipant
    {
        $participant = DB::transaction(function () use ($activity, $user, $password): ActivityParticipant {
            $activity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            $this->ensureEligible($activity, $user, $password);

            $participant = ActivityParticipant::query()
                ->where('activity_id', $activity->id)
                ->where('user_id', $user->id)
                ->first();
            if ($participant?->status === ActivityParticipantStatus::Banned) {
                throw new ActivityException('You are banned from this activity.', 'ACTIVITY_BANNED', 403);
            }
            if ($participant && in_array($participant->status, [
                ActivityParticipantStatus::Joined,
                ActivityParticipantStatus::Requested,
                ActivityParticipantStatus::Waitlisted,
            ], true)) {
                throw new ActivityException('You already have an active participation record.', 'ALREADY_PARTICIPATING');
            }

            $joinedCount = $activity->participants()->where('status', ActivityParticipantStatus::Joined)->count();
            $status = $activity->require_approval
                ? ActivityParticipantStatus::Requested
                : ActivityParticipantStatus::Joined;
            $waitlistPosition = null;
            if ($joinedCount >= $activity->max_participants) {
                if (! $activity->allow_waitlist) {
                    throw new ActivityException('This activity is full.', 'ACTIVITY_FULL');
                }
                $status = ActivityParticipantStatus::Waitlisted;
                $waitlistPosition = ((int) $activity->participants()->where('status', ActivityParticipantStatus::Waitlisted)->max('waitlist_position')) + 1;
            }

            $values = [
                'role' => 'member',
                'status' => $status,
                'joined_at' => $status === ActivityParticipantStatus::Joined ? now() : null,
                'left_at' => null,
                'waitlist_position' => $waitlistPosition,
            ];
            if ($participant) {
                $participant->update($values);
            } else {
                $participant = $activity->participants()->create(['user_id' => $user->id, ...$values]);
            }

            if ($status === ActivityParticipantStatus::Joined && $joinedCount + 1 >= $activity->max_participants) {
                $activity->update(['status' => ActivityStatus::Full]);
            }
            $activity->invitations()
                ->where('invitee_id', $user->id)
                ->where('status', ActivityInvitationStatus::Pending)
                ->update(['status' => ActivityInvitationStatus::Accepted, 'responded_at' => now()]);

            return $participant->load('user');
        });

        if ($participant->status === ActivityParticipantStatus::Joined) {
            $activity->host->notify(new ActivityParticipantJoined($activity, $user));
        }

        return $participant;
    }

    private function ensureEligible(Activity $activity, User $user, ?string $password): void
    {
        if ($activity->host_id === $user->id) {
            throw new ActivityException('The host is already participating.', 'ALREADY_PARTICIPATING');
        }
        if (! in_array($activity->status, [ActivityStatus::Open, ActivityStatus::Full], true)) {
            throw new ActivityException('This activity is not accepting participants.', 'ACTIVITY_NOT_JOINABLE');
        }
        if ($activity->starts_at->isPast()) {
            throw new ActivityException('This activity has already started.', 'ACTIVITY_STARTED');
        }
        if (Block::query()->between($activity->host_id, $user->id)->exists()) {
            throw new ActivityException('Interaction is not allowed.', 'USER_BLOCKED', 403);
        }
        if ($activity->password_hash !== null && ($password === null || ! Hash::check($password, $activity->password_hash))) {
            throw new ActivityException('The activity password is incorrect.', 'INVALID_ACTIVITY_PASSWORD', 422);
        }
        if ($activity->minimum_age !== null) {
            if ($user->date_of_birth === null) {
                throw new ActivityException('A date of birth is required for this activity.', 'DATE_OF_BIRTH_REQUIRED', 422);
            }
            if ($user->date_of_birth->age < $activity->minimum_age) {
                throw new ActivityException('You do not meet the minimum age.', 'AGE_RESTRICTED', 403);
            }
        }
    }
}

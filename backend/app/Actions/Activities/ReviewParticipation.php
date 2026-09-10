<?php

namespace App\Actions\Activities;

use App\Enums\ActivityParticipantStatus;
use App\Enums\ActivityStatus;
use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewParticipation
{
    public function accept(Activity $activity, ActivityParticipant $participant, User $host): ActivityParticipant
    {
        return DB::transaction(function () use ($activity, $participant, $host): ActivityParticipant {
            $activity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            $participant = ActivityParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            $this->ensureHostAndParticipant($activity, $participant, $host);
            if ($participant->status !== ActivityParticipantStatus::Requested) {
                throw new ActivityException('This participation is not awaiting approval.', 'PARTICIPATION_NOT_REQUESTED');
            }

            $joinedCount = $activity->participants()->where('status', ActivityParticipantStatus::Joined)->count();
            if ($joinedCount >= $activity->max_participants) {
                if (! $activity->allow_waitlist) {
                    throw new ActivityException('This activity is full.', 'ACTIVITY_FULL');
                }
                $position = ((int) $activity->participants()->where('status', ActivityParticipantStatus::Waitlisted)->max('waitlist_position')) + 1;
                $participant->update(['status' => ActivityParticipantStatus::Waitlisted, 'waitlist_position' => $position]);
            } else {
                $participant->update([
                    'status' => ActivityParticipantStatus::Joined,
                    'joined_at' => now(),
                    'waitlist_position' => null,
                ]);
                if ($joinedCount + 1 >= $activity->max_participants) {
                    $activity->update(['status' => ActivityStatus::Full]);
                }
            }

            return $participant->load('user');
        });
    }

    public function reject(Activity $activity, ActivityParticipant $participant, User $host): ActivityParticipant
    {
        return DB::transaction(function () use ($activity, $participant, $host): ActivityParticipant {
            $activity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            $participant = ActivityParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            $this->ensureHostAndParticipant($activity, $participant, $host);
            if (! in_array($participant->status, [ActivityParticipantStatus::Requested, ActivityParticipantStatus::Waitlisted], true)) {
                throw new ActivityException('This participation cannot be rejected.', 'PARTICIPATION_NOT_REVIEWABLE');
            }
            $oldPosition = $participant->waitlist_position;
            $participant->update([
                'status' => ActivityParticipantStatus::Rejected,
                'left_at' => now(),
                'waitlist_position' => null,
            ]);
            if ($oldPosition !== null) {
                $activity->participants()
                    ->where('status', ActivityParticipantStatus::Waitlisted)
                    ->where('waitlist_position', '>', $oldPosition)
                    ->decrement('waitlist_position');
            }

            return $participant->load('user');
        });
    }

    private function ensureHostAndParticipant(Activity $activity, ActivityParticipant $participant, User $host): void
    {
        if ($activity->host_id !== $host->id) {
            throw new ActivityException('Only the host can review participation.', 'NOT_ACTIVITY_HOST', 403);
        }
        if ($participant->activity_id !== $activity->id) {
            throw new ActivityException('Participation does not belong to this activity.', 'PARTICIPATION_MISMATCH', 404);
        }
    }
}

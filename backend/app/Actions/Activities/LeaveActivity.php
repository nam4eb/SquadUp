<?php

namespace App\Actions\Activities;

use App\Enums\ActivityParticipantStatus;
use App\Enums\ActivityStatus;
use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeaveActivity
{
    public function handle(Activity $activity, User $user): void
    {
        DB::transaction(function () use ($activity, $user): void {
            $activity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            if ($activity->host_id === $user->id) {
                throw new ActivityException('The host cannot leave their own activity.', 'HOST_CANNOT_LEAVE', 422);
            }
            $participant = $activity->participants()->where('user_id', $user->id)->first();
            if (! $participant || ! in_array($participant->status, [
                ActivityParticipantStatus::Joined,
                ActivityParticipantStatus::Requested,
                ActivityParticipantStatus::Waitlisted,
            ], true)) {
                throw new ActivityException('Active participation was not found.', 'PARTICIPATION_NOT_FOUND', 404);
            }

            $wasJoined = $participant->status === ActivityParticipantStatus::Joined;
            $oldPosition = $participant->waitlist_position;
            $participant->update([
                'status' => ActivityParticipantStatus::Left,
                'left_at' => now(),
                'waitlist_position' => null,
            ]);

            if ($oldPosition !== null) {
                $activity->participants()
                    ->where('status', ActivityParticipantStatus::Waitlisted)
                    ->where('waitlist_position', '>', $oldPosition)
                    ->decrement('waitlist_position');
            }
            if ($wasJoined) {
                $this->promoteWaitlist($activity);
            }
            $joinedCount = $activity->participants()->where('status', ActivityParticipantStatus::Joined)->count();
            $activity->update(['status' => $joinedCount >= $activity->max_participants ? ActivityStatus::Full : ActivityStatus::Open]);
        });
    }

    private function promoteWaitlist(Activity $activity): void
    {
        $next = $activity->participants()
            ->where('status', ActivityParticipantStatus::Waitlisted)
            ->orderBy('waitlist_position')
            ->first();
        if (! $next) {
            return;
        }
        $next->update([
            'status' => $activity->require_approval
                ? ActivityParticipantStatus::Requested
                : ActivityParticipantStatus::Joined,
            'joined_at' => $activity->require_approval ? null : now(),
            'waitlist_position' => null,
        ]);
        $activity->participants()
            ->where('status', ActivityParticipantStatus::Waitlisted)
            ->whereNotNull('waitlist_position')
            ->decrement('waitlist_position');
    }
}

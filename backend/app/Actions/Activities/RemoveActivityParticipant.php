<?php

namespace App\Actions\Activities;

use App\Enums\ActivityParticipantStatus;
use App\Enums\ActivityStatus;
use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemoveActivityParticipant
{
    public function handle(Activity $activity, ActivityParticipant $participant, User $host, bool $ban): ActivityParticipant
    {
        return DB::transaction(function () use ($activity, $participant, $host, $ban): ActivityParticipant {
            $activity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            $participant = ActivityParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            if ($activity->host_id !== $host->id) {
                throw new ActivityException('Only the host can manage participants.', 'NOT_ACTIVITY_HOST', 403);
            }
            if ($participant->activity_id !== $activity->id) {
                throw new ActivityException('Participation does not belong to this activity.', 'PARTICIPATION_MISMATCH', 404);
            }
            if ($participant->role === 'host') {
                throw new ActivityException('The host cannot be removed.', 'HOST_CANNOT_BE_REMOVED', 422);
            }
            $wasJoined = $participant->status === ActivityParticipantStatus::Joined;
            $participant->update([
                'status' => $ban ? ActivityParticipantStatus::Banned : ActivityParticipantStatus::Removed,
                'left_at' => now(),
                'waitlist_position' => null,
            ]);
            if ($wasJoined) {
                $next = $activity->participants()->where('status', ActivityParticipantStatus::Waitlisted)
                    ->orderBy('waitlist_position')->first();
                if ($next) {
                    $next->update([
                        'status' => $activity->require_approval ? ActivityParticipantStatus::Requested : ActivityParticipantStatus::Joined,
                        'joined_at' => $activity->require_approval ? null : now(),
                        'waitlist_position' => null,
                    ]);
                    $activity->participants()->where('status', ActivityParticipantStatus::Waitlisted)
                        ->whereNotNull('waitlist_position')->decrement('waitlist_position');
                }
            }
            $joined = $activity->participants()->where('status', ActivityParticipantStatus::Joined)->count();
            if (in_array($activity->status, [ActivityStatus::Open, ActivityStatus::Full], true)) {
                $activity->update(['status' => $joined >= $activity->max_participants ? ActivityStatus::Full : ActivityStatus::Open]);
            }

            return $participant->load('user');
        });
    }
}

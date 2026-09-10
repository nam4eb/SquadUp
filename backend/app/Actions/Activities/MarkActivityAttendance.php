<?php

namespace App\Actions\Activities;

use App\Enums\ActivityParticipantStatus;
use App\Enums\ActivityStatus;
use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarkActivityAttendance
{
    public function handle(
        Activity $activity,
        ActivityParticipant $participant,
        User $manager,
        ActivityParticipantStatus $status,
    ): ActivityParticipant {
        return DB::transaction(function () use ($activity, $participant, $manager, $status): ActivityParticipant {
            $activity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            $participant = ActivityParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            if ($participant->activity_id !== $activity->id) {
                throw new ActivityException('Participant does not belong to this activity.', 'PARTICIPANT_ACTIVITY_MISMATCH', 404);
            }
            if (! in_array($activity->status, [ActivityStatus::Ongoing, ActivityStatus::Completed], true)) {
                throw new ActivityException('Attendance can only be marked after the activity starts.', 'ATTENDANCE_NOT_OPEN', 422);
            }
            if (! in_array($participant->status, [ActivityParticipantStatus::Joined, ActivityParticipantStatus::Attended, ActivityParticipantStatus::Absent], true)) {
                throw new ActivityException('This participant is not eligible for attendance.', 'PARTICIPANT_NOT_ATTENDANCE_ELIGIBLE', 422);
            }
            if (! in_array($status, [ActivityParticipantStatus::Attended, ActivityParticipantStatus::Absent], true)) {
                throw new ActivityException('Invalid attendance status.', 'INVALID_ATTENDANCE_STATUS', 422);
            }
            $participant->update([
                'status' => $status,
                'attendance_marked_at' => now(),
                'attendance_marked_by' => $manager->id,
            ]);

            return $participant->load('user');
        });
    }
}

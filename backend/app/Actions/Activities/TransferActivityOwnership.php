<?php

namespace App\Actions\Activities;

use App\Enums\ActivityParticipantStatus;
use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransferActivityOwnership
{
    public function handle(Activity $activity, User $host, User $newHost): Activity
    {
        return DB::transaction(function () use ($activity, $host, $newHost): Activity {
            $activity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            if ($activity->host_id !== $host->id) {
                throw new ActivityException('Only the host can transfer ownership.', 'NOT_ACTIVITY_HOST', 403);
            }
            if ($newHost->id === $host->id) {
                throw new ActivityException('This user is already the host.', 'ALREADY_ACTIVITY_HOST', 422);
            }
            $newHostParticipant = $activity->participants()
                ->where('user_id', $newHost->id)
                ->where('status', ActivityParticipantStatus::Joined)
                ->lockForUpdate()
                ->first();
            if (! $newHostParticipant) {
                throw new ActivityException('The new host must be a joined participant.', 'NEW_HOST_NOT_JOINED', 422);
            }
            $oldHostParticipant = $activity->participants()
                ->where('user_id', $host->id)->lockForUpdate()->firstOrFail();
            $oldHostParticipant->update(['role' => 'member']);
            $newHostParticipant->update(['role' => 'host']);
            $activity->update(['host_id' => $newHost->id]);

            return $activity->load(['host', 'category', 'topic'])
                ->loadCount(['participants as joined_count' => fn ($query) => $query->where('status', 'joined')]);
        });
    }
}

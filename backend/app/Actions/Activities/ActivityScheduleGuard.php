<?php

namespace App\Actions\Activities;

use App\Enums\ActivityStatus;
use App\Exceptions\ActivityException;
use App\Models\Activity;
use Carbon\CarbonInterface;

class ActivityScheduleGuard
{
    public function ensureAvailable(
        string $hostId,
        CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
        ?string $exceptActivityId = null,
    ): void {
        $proposedEnd = $endsAt ?? $startsAt->copy()->addMinute();
        $conflicts = Activity::query()
            ->where('host_id', $hostId)
            ->whereNotIn('status', [ActivityStatus::Cancelled, ActivityStatus::Expired])
            ->when($exceptActivityId, fn ($query) => $query->whereKeyNot($exceptActivityId))
            ->where('starts_at', '<', $proposedEnd)
            ->get(['id', 'starts_at', 'ends_at'])
            ->contains(function (Activity $activity) use ($startsAt): bool {
                $existingEnd = $activity->ends_at ?? $activity->starts_at->copy()->addMinute();

                return $existingEnd->isAfter($startsAt);
            });

        if ($conflicts) {
            throw new ActivityException(
                'You already host another activity during this time.',
                'ACTIVITY_TIME_CONFLICT',
                422,
            );
        }
    }
}

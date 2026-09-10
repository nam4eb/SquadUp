<?php

namespace App\Actions\Activities;

use App\Enums\ActivityStatus;
use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Models\User;
use App\Notifications\ActivityCancelled;
use App\Support\ClanAuthorizer;
use Illuminate\Support\Facades\DB;

class ActivityLifecycle
{
    public function transition(Activity $activity, User $manager, ActivityStatus $target): Activity
    {
        $activity = DB::transaction(function () use ($activity, $manager, $target): Activity {
            $activity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            $clan = $activity->clanEvent()->with('clan')->first()?->clan;
            if ($activity->host_id !== $manager->id && (! $clan || ! ClanAuthorizer::allows($clan, $manager, 'manage_events'))) {
                throw new ActivityException('You cannot manage this activity.', 'ACTIVITY_PERMISSION_DENIED', 403);
            }
            $allowed = match ($activity->status) {
                ActivityStatus::Draft => [ActivityStatus::Open, ActivityStatus::Cancelled],
                ActivityStatus::Open, ActivityStatus::Full => [ActivityStatus::Locked, ActivityStatus::Ongoing, ActivityStatus::Cancelled],
                ActivityStatus::Locked => [ActivityStatus::Open, ActivityStatus::Ongoing, ActivityStatus::Cancelled],
                ActivityStatus::Ongoing => [ActivityStatus::Completed, ActivityStatus::Cancelled],
                default => [],
            };
            if (! in_array($target, $allowed, true)) {
                throw new ActivityException('This lifecycle transition is not allowed.', 'INVALID_ACTIVITY_TRANSITION', 422);
            }
            if ($activity->status === ActivityStatus::Locked && $target === ActivityStatus::Open) {
                $joined = $activity->participants()->where('status', 'joined')->count();
                $target = $joined >= $activity->max_participants ? ActivityStatus::Full : ActivityStatus::Open;
            }
            $activity->update(['status' => $target]);

            return $activity->load(['host', 'category', 'topic', 'sport', 'venue', 'clanEvent.clan'])
                ->loadCount(['participants as joined_count' => fn ($query) => $query->where('status', 'joined')]);
        });

        if ($target === ActivityStatus::Cancelled) {
            $activity->participants()->where('status', 'joined')->with('user')->get()
                ->pluck('user')->filter(fn (User $user) => $user->id !== $manager->id)
                ->each(fn (User $user) => $user->notify(new ActivityCancelled($activity)));
        }

        return $activity;
    }
}

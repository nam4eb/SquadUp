<?php

namespace App\Actions\Activities;

use App\Enums\ActivityStatus;
use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Models\User;
use App\Models\Venue;
use App\Support\ClanAuthorizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UpdateActivity
{
    public function __construct(private readonly ActivityScheduleGuard $scheduleGuard) {}

    public function handle(Activity $activity, User $manager, array $attributes): Activity
    {
        return DB::transaction(function () use ($activity, $manager, $attributes): Activity {
            $activity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            $clan = $activity->clanEvent()->with('clan')->first()?->clan;
            if ($activity->host_id !== $manager->id && (! $clan || ! ClanAuthorizer::allows($clan, $manager, 'manage_events'))) {
                throw new ActivityException('You cannot update this activity.', 'ACTIVITY_PERMISSION_DENIED', 403);
            }
            if (! in_array($activity->status, [ActivityStatus::Draft, ActivityStatus::Open, ActivityStatus::Full, ActivityStatus::Locked], true)) {
                throw new ActivityException('This activity can no longer be edited.', 'ACTIVITY_NOT_EDITABLE', 422);
            }
            User::query()->whereKey($activity->host_id)->lockForUpdate()->firstOrFail();
            $startsAt = Carbon::parse($attributes['starts_at'] ?? $activity->starts_at);
            $endsAtValue = array_key_exists('ends_at', $attributes) ? $attributes['ends_at'] : $activity->ends_at;
            $this->scheduleGuard->ensureAvailable(
                $activity->host_id,
                $startsAt,
                $endsAtValue ? Carbon::parse($endsAtValue) : null,
                $activity->id,
            );
            $joinedCount = $activity->participants()->where('status', 'joined')->count();
            $maxParticipants = (int) ($attributes['max_participants'] ?? $activity->max_participants);
            if ($maxParticipants < $joinedCount) {
                throw new ActivityException('Capacity cannot be lower than the joined participant count.', 'CAPACITY_BELOW_JOINED', 422);
            }

            $password = Arr::pull($attributes, 'password');
            $removePassword = (bool) Arr::pull($attributes, 'remove_password', false);
            if ($venueId = $attributes['venue_id'] ?? null) {
                $venue = Venue::findOrFail($venueId);
                $attributes = [...$attributes,
                    'location_name' => $venue->name,
                    'location_address' => $venue->address,
                    'latitude' => $venue->latitude,
                    'longitude' => $venue->longitude,
                ];
            }
            if ($removePassword) {
                $attributes['password_hash'] = null;
            } elseif ($password !== null) {
                $attributes['password_hash'] = Hash::make($password);
            }
            if ($activity->status !== ActivityStatus::Locked && array_key_exists('max_participants', $attributes)) {
                $attributes['status'] = $joinedCount >= $maxParticipants
                    ? ActivityStatus::Full
                    : ActivityStatus::Open;
            }
            $activity->update($attributes);

            return $activity->load(['host', 'category', 'topic', 'sport', 'venue', 'clanEvent.clan'])
                ->loadCount(['participants as joined_count' => fn ($query) => $query->where('status', 'joined')]);
        });
    }
}

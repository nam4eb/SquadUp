<?php

namespace App\Actions\Activities;

use App\Enums\ActivityParticipantStatus;
use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateActivity
{
    public function __construct(private readonly ActivityScheduleGuard $scheduleGuard) {}

    public function handle(User $host, array $attributes): Activity
    {
        return DB::transaction(function () use ($host, $attributes): Activity {
            User::query()->whereKey($host->id)->lockForUpdate()->firstOrFail();
            $this->scheduleGuard->ensureAvailable(
                $host->id,
                Carbon::parse($attributes['starts_at']),
                isset($attributes['ends_at']) ? Carbon::parse($attributes['ends_at']) : null,
            );
            $password = Arr::pull($attributes, 'password');
            $clanId = Arr::pull($attributes, 'clan_id');
            if ($venueId = $attributes['venue_id'] ?? null) {
                $venue = Venue::findOrFail($venueId);
                $attributes = [...$attributes,
                    'location_name' => $venue->name,
                    'location_address' => $venue->address,
                    'latitude' => $venue->latitude,
                    'longitude' => $venue->longitude,
                ];
            }
            $activity = Activity::create([
                ...$attributes,
                'host_id' => $host->id,
                'status' => ActivityStatus::Open,
                'password_hash' => $password ? Hash::make($password) : null,
            ]);
            $activity->participants()->create([
                'user_id' => $host->id,
                'role' => 'host',
                'status' => ActivityParticipantStatus::Joined,
                'joined_at' => now(),
            ]);
            if ($clanId) {
                $activity->clanEvent()->create([
                    'clan_id' => $clanId,
                    'created_by' => $host->id,
                ]);
            }

            return $activity->refresh()->load(['host', 'category', 'topic', 'sport', 'venue', 'clanEvent.clan'])
                ->loadCount(['participants as joined_count' => fn ($query) => $query->where('status', 'joined')]);
        });
    }
}

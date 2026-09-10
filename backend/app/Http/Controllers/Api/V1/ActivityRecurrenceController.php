<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityParticipantStatus;
use App\Enums\ActivityStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ActivityRecurrenceController extends Controller
{
    public function store(Activity $activity, Request $request)
    {
        Gate::authorize('manage', $activity);
        $validated = $request->validate([
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'interval' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'count' => ['required', 'integer', 'min:2', 'max:52'],
        ]);
        $interval = $validated['interval'] ?? 1;
        $rule = ['frequency' => $validated['frequency'], 'interval' => $interval, 'count' => $validated['count']];
        $copies = DB::transaction(function () use ($activity, $rule): array {
            $activity->update(['recurrence_rule' => $rule, 'recurrence_index' => 0]);
            $duration = $activity->ends_at?->diffInSeconds($activity->starts_at);
            $copies = [];
            for ($index = 1; $index < $rule['count']; $index++) {
                $startsAt = match ($rule['frequency']) {
                    'daily' => $activity->starts_at->copy()->addDays($rule['interval'] * $index),
                    'weekly' => $activity->starts_at->copy()->addWeeks($rule['interval'] * $index),
                    'monthly' => $activity->starts_at->copy()->addMonthsNoOverflow($rule['interval'] * $index),
                };
                $attributes = Arr::except($activity->getAttributes(), [
                    'id', 'created_at', 'updated_at', 'deleted_at', 'starts_at', 'ends_at',
                    'recurrence_parent_id', 'recurrence_index', 'status',
                ]);
                $copy = Activity::create([
                    ...$attributes,
                    'recurrence_parent_id' => $activity->id,
                    'recurrence_index' => $index,
                    'recurrence_rule' => $rule,
                    'starts_at' => $startsAt,
                    'ends_at' => $duration === null ? null : $startsAt->copy()->addSeconds($duration),
                    'status' => ActivityStatus::Open,
                ]);
                $copy->participants()->create([
                    'user_id' => $activity->host_id, 'role' => 'host',
                    'status' => ActivityParticipantStatus::Joined, 'joined_at' => now(),
                ]);
                if ($activity->clanEvent) {
                    $copy->clanEvent()->create([
                        'clan_id' => $activity->clanEvent->clan_id, 'created_by' => $activity->host_id,
                    ]);
                }
                $copies[] = $copy->load(['host', 'category', 'topic', 'clanEvent.clan'])
                    ->loadCount(['participants as joined_count' => fn ($query) => $query->where('status', 'joined')]);
            }

            return $copies;
        });

        return ActivityResource::collection(collect([$activity->refresh(), ...$copies]));
    }
}

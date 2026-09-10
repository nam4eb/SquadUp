<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ClanException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\Clan;
use App\Models\ClanEvent;
use App\Models\ClanLeaderboardSnapshot;
use App\Support\ClanAuthorizer;
use App\Support\ClanMetricsCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClanActivityController extends Controller
{
    public function index(Clan $clan, Request $request): AnonymousResourceCollection
    {
        Gate::authorize('view', $clan);
        $this->requireActiveMember($clan, $request);
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $activities = Activity::query()
            ->whereHas('clanEvent', fn ($query) => $query->where('clan_id', $clan->id))
            ->with(['host', 'category', 'topic', 'clanEvent.clan', 'participants' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->withCount(['participants as joined_count' => fn ($query) => $query->where('status', 'joined')])
            ->orderByDesc('starts_at')
            ->paginate($validated['per_page'] ?? 20);

        return ActivityResource::collection($activities);
    }

    public function statistics(Clan $clan, Request $request): JsonResponse
    {
        Gate::authorize('view', $clan);
        $this->requirePermission($clan, $request, 'view_statistics');
        $data = Cache::remember("clan:{$clan->id}:statistics", now()->addMinutes(5), function () use ($clan): array {
            $base = ClanEvent::query()->where('clan_id', $clan->id)->join('activities', 'activities.id', '=', 'clan_events.activity_id')->whereNull('activities.deleted_at');

            return [
                'total_events' => (clone $base)->count(),
                'completed_events' => (clone $base)->where('activities.status', 'completed')->count(),
                'active_members' => $clan->members()->where('status', 'active')->count(),
                'attendance' => $this->attendanceSummary($clan),
                'by_topic' => (clone $base)
                    ->join('activity_topics', 'activity_topics.id', '=', 'activities.topic_id')
                    ->groupBy('activity_topics.id', 'activity_topics.name')
                    ->select('activity_topics.id', 'activity_topics.name', DB::raw('COUNT(*) as events_count'))
                    ->orderByDesc('events_count')->get(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function leaderboard(Clan $clan, Request $request): JsonResponse
    {
        Gate::authorize('view', $clan);
        $this->requireActiveMember($clan, $request);
        $validated = $request->validate([
            'period' => ['nullable', 'in:all_time,yearly,monthly,weekly'],
            'topic_id' => ['nullable', 'uuid', 'exists:activity_topics,id'],
            'metric' => ['nullable', 'in:events_joined,events_attended,events_created,attendance_rate'],
        ]);
        $period = $validated['period'] ?? 'all_time';
        $metric = $validated['metric'] ?? 'events_joined';
        $topicId = $validated['topic_id'] ?? 'all';
        $version = ClanMetricsCache::version($clan->id);
        $key = "clan:{$clan->id}:leaderboard:v{$version}:{$period}:{$topicId}:{$metric}";
        $rows = Cache::remember($key, now()->addMinutes(5), function () use ($clan, $period, $topicId, $metric, $key): array {
            $snapshot = ClanLeaderboardSnapshot::query()
                ->where('cache_key', $key)->where('expires_at', '>', now())->first();
            if ($snapshot) {
                return $snapshot->payload;
            }
            $rows = $this->rank($clan, $period, $topicId, $metric)->all();
            ClanLeaderboardSnapshot::query()->updateOrCreate(
                ['cache_key' => $key],
                [
                    'clan_id' => $clan->id,
                    'period' => $period,
                    'metric' => $metric,
                    'topic_id' => $topicId === 'all' ? null : $topicId,
                    'payload' => $rows,
                    'generated_at' => now(),
                    'expires_at' => now()->addMinutes(5),
                ],
            );

            return $rows;
        });

        return response()->json(['data' => $rows, 'meta' => compact('period', 'metric') + ['topic_id' => $topicId === 'all' ? null : $topicId]]);
    }

    private function rank(Clan $clan, string $period, string $topicId, string $metric)
    {
        $start = match ($period) {
            'weekly' => Carbon::now()->startOfWeek(),
            'monthly' => Carbon::now()->startOfMonth(),
            'yearly' => Carbon::now()->startOfYear(),
            default => null,
        };
        $query = DB::table('clan_events')->join('activities', 'activities.id', '=', 'clan_events.activity_id')->where('clan_events.clan_id', $clan->id)->whereNull('activities.deleted_at');
        if ($start) {
            $query->where('activities.starts_at', '>=', $start);
        }
        if ($topicId !== 'all') {
            $query->where('activities.topic_id', $topicId);
        }
        if ($metric === 'events_created') {
            $query->join('users', 'users.id', '=', 'activities.host_id')->groupBy('users.id', 'users.display_name')->select('users.id as user_id', 'users.display_name', DB::raw('COUNT(*) as score'));
        } else {
            $query->join('activity_participants', 'activity_participants.activity_id', '=', 'activities.id')
                ->join('users', 'users.id', '=', 'activity_participants.user_id')
                ->groupBy('users.id', 'users.display_name');
            if ($metric === 'events_attended') {
                $query->where('activity_participants.status', 'attended')
                    ->select('users.id as user_id', 'users.display_name', DB::raw('COUNT(*) as score'));
            } elseif ($metric === 'attendance_rate') {
                $query->whereIn('activity_participants.status', ['attended', 'absent'])
                    ->select('users.id as user_id', 'users.display_name', DB::raw("ROUND(100.0 * SUM(CASE WHEN activity_participants.status = 'attended' THEN 1 ELSE 0 END) / COUNT(*), 1) as score"));
            } else {
                $query->whereIn('activity_participants.status', ['joined', 'left', 'removed', 'attended', 'absent'])
                    ->select('users.id as user_id', 'users.display_name', DB::raw('COUNT(*) as score'));
            }
        }

        return $query->orderByDesc('score')->orderBy('users.display_name')->limit(100)->get()->values()->map(fn ($row, $index) => [
            'rank' => $index + 1,
            'user_id' => $row->user_id,
            'display_name' => $row->display_name,
            'score' => $metric === 'attendance_rate' ? (float) $row->score : (int) $row->score,
        ]);
    }

    private function attendanceSummary(Clan $clan): array
    {
        $counts = DB::table('clan_events')
            ->join('activity_participants', 'activity_participants.activity_id', '=', 'clan_events.activity_id')
            ->where('clan_events.clan_id', $clan->id)
            ->whereIn('activity_participants.status', ['attended', 'absent'])
            ->selectRaw("SUM(CASE WHEN activity_participants.status = 'attended' THEN 1 ELSE 0 END) as attended")
            ->selectRaw('COUNT(*) as marked')->first();
        $marked = (int) ($counts->marked ?? 0);
        $attended = (int) ($counts->attended ?? 0);

        return [
            'attended' => $attended,
            'absent' => $marked - $attended,
            'rate' => $marked === 0 ? null : round($attended * 100 / $marked, 1),
        ];
    }

    private function requireActiveMember(Clan $clan, Request $request): void
    {
        if (ClanAuthorizer::membership($clan, $request->user())?->status?->value !== 'active') {
            throw new ClanException('Active clan membership is required.', 'CLAN_MEMBERSHIP_REQUIRED', 403);
        }
    }

    private function requirePermission(Clan $clan, Request $request, string $permission): void
    {
        if (! ClanAuthorizer::allows($clan, $request->user(), $permission)) {
            throw new ClanException('You do not have the required clan permission.', 'CLAN_PERMISSION_DENIED', 403);
        }
    }
}

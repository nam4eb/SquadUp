<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Activities\CreateActivity;
use App\Actions\Activities\TransferActivityOwnership;
use App\Actions\Activities\UpdateActivity;
use App\Enums\ActivityStatus;
use App\Exceptions\ClanException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityRequest;
use App\Http\Requests\UpdateActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\Clan;
use App\Models\User;
use App\Support\ClanAuthorizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class ActivityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'uuid'],
            'topic_id' => ['nullable', 'uuid'],
            'sport_id' => ['nullable', 'uuid'],
            'sport' => ['nullable', 'string', 'max:80'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'skill' => ['nullable', 'integer', 'between:800,2400'],
            'match_format' => ['nullable', 'in:singles,doubles,team,open'],
            'open_slots' => ['nullable', 'boolean'],
            'friends_participating' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'query' => ['nullable', 'string', 'max:120'],
            'near_lat' => ['nullable', 'required_with:near_lng,radius_km', 'numeric', 'between:-90,90'],
            'near_lng' => ['nullable', 'required_with:near_lat,radius_km', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'required_with:near_lat,near_lng', 'numeric', 'min:1', 'max:'.config('explore.max_radius_km', 100)],
        ]);
        $user = $request->user();

        $query = Activity::query()
            ->with([
                'host', 'category', 'topic', 'sport', 'venue',
            ])
            ->addSelect([
                'viewer_participation' => fn ($participant) => $participant
                    ->select('status')
                    ->from('activity_participants')
                    ->whereColumn('activity_participants.activity_id', 'activities.id')
                    ->where('activity_participants.user_id', $user->id)
                    ->limit(1),
            ])
            ->withCount([
                'participants as joined_count' => fn ($query) => $query->where('status', 'joined'),
                'likes', 'comments',
            ])
            ->withExists(['likes as liked_by_me' => fn ($query) => $query->where('user_id', $user->id)])
            ->withExists('conversation as has_chat')
            ->whereNotExists(fn ($hidden) => $hidden
                ->selectRaw('1')
                ->from('hidden_activities')
                ->whereColumn('hidden_activities.activity_id', 'activities.id')
                ->where('hidden_activities.user_id', $user->id))
            ->whereNotExists(fn ($blocked) => $blocked
                ->selectRaw('1')
                ->from('blocks')
                ->where(fn ($pair) => $pair
                    ->where(fn ($forward) => $forward
                        ->where('blocks.blocker_id', $user->id)
                        ->whereColumn('blocks.blocked_id', 'activities.host_id'))
                    ->orWhere(fn ($reverse) => $reverse
                        ->where('blocks.blocked_id', $user->id)
                        ->whereColumn('blocks.blocker_id', 'activities.host_id'))))
            ->where(function ($query) use ($user): void {
                $query->where('host_id', $user->id)
                    ->orWhere('visibility', 'public')
                    ->orWhere(fn ($friends) => $friends
                        ->where('visibility', 'friends')
                        ->whereExists(fn ($friendship) => $friendship
                            ->selectRaw('1')
                            ->from('friendships')
                            ->where(fn ($pair) => $pair
                                ->where(fn ($forward) => $forward
                                    ->where('friendships.user_low_id', $user->id)
                                    ->whereColumn('friendships.user_high_id', 'activities.host_id'))
                                ->orWhere(fn ($reverse) => $reverse
                                    ->where('friendships.user_high_id', $user->id)
                                    ->whereColumn('friendships.user_low_id', 'activities.host_id')))))
                    ->orWhere(fn ($clans) => $clans
                        ->where('visibility', 'clan')
                        ->whereHas('clanEvent', fn ($event) => $event
                            ->whereExists(fn ($member) => $member
                                ->selectRaw('1')
                                ->from('clan_members')
                                ->whereColumn('clan_members.clan_id', 'clan_events.clan_id')
                                ->where('clan_members.user_id', $user->id)
                                ->where('clan_members.status', 'active'))))
                    ->orWhereExists(fn ($invitation) => $invitation
                        ->selectRaw('1')
                        ->from('activity_invitations')
                        ->whereColumn('activity_invitations.activity_id', 'activities.id')
                        ->where('activity_invitations.invitee_id', $user->id)
                        ->whereIn('activity_invitations.status', ['pending', 'accepted'])
                        ->where(fn ($active) => $active
                            ->whereNull('activity_invitations.expires_at')
                            ->orWhere('activity_invitations.expires_at', '>', now())))
                    ->orWhereExists(fn ($participant) => $participant
                        ->selectRaw('1')
                        ->from('activity_participants')
                        ->whereColumn('activity_participants.activity_id', 'activities.id')
                        ->where('activity_participants.user_id', $user->id)
                        ->whereIn('activity_participants.status', ['joined', 'requested', 'waitlisted']));
            })
            ->where(function ($query) use ($user): void {
                $query->where('host_id', $user->id)
                    ->orWhereIn('status', [ActivityStatus::Open, ActivityStatus::Full, ActivityStatus::Locked, ActivityStatus::Ongoing]);
            })
            ->where(fn ($active) => $active->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->when($validated['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when($validated['topic_id'] ?? null, fn ($query, $id) => $query->where('topic_id', $id))
            ->when($validated['sport_id'] ?? null, fn ($query, $id) => $query->where('sport_id', $id))
            ->when($validated['sport'] ?? null, fn ($query, $slug) => $query->whereHas('sport', fn ($sport) => $sport->where('slug', $slug)))
            ->when($validated['date_from'] ?? null, fn ($query, $date) => $query->where('starts_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, $date) => $query->where('starts_at', '<=', $date))
            ->when($validated['skill'] ?? null, fn ($query, $skill) => $query
                ->where(fn ($range) => $range->whereNull('skill_min')->orWhere('skill_min', '<=', $skill))
                ->where(fn ($range) => $range->whereNull('skill_max')->orWhere('skill_max', '>=', $skill)))
            ->when($validated['match_format'] ?? null, fn ($query, $format) => $query->where('match_format', $format))
            ->when($request->boolean('open_slots'), fn ($query) => $query->whereRaw(
                "(select count(*) from activity_participants where activity_participants.activity_id = activities.id and status = 'joined') < activities.max_participants"
            ))
            ->when($request->boolean('friends_participating'), fn ($query) => $query->whereExists(fn ($participant) => $participant
                ->selectRaw('1')->from('activity_participants')
                ->join('friendships', fn ($join) => $join
                    ->on('friendships.user_low_id', '=', 'activity_participants.user_id')
                    ->where('friendships.user_high_id', '=', $user->id)
                    ->orOn(function ($join) use ($user): void {
                        $join->on('friendships.user_high_id', '=', 'activity_participants.user_id')
                            ->where('friendships.user_low_id', '=', $user->id);
                    }))
                ->whereColumn('activity_participants.activity_id', 'activities.id')
                ->where('activity_participants.status', 'joined')))
            ->when(trim($validated['query'] ?? ''), fn ($query, $term) => $query->where(fn ($search) => $search
                ->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('location_name', 'like', "%{$term}%")));

        $perPage = $validated['per_page'] ?? 20;
        if (isset($validated['near_lat'], $validated['near_lng'], $validated['radius_km'])) {
            $latitude = (float) $validated['near_lat'];
            $longitude = (float) $validated['near_lng'];
            $radius = (float) $validated['radius_km'];
            $latitudeDelta = $radius / 111.0;
            $longitudeDelta = $radius / max(1.0, 111.0 * cos(deg2rad($latitude)));
            $driver = $query->getConnection()->getDriverName();
            if ($driver === 'sqlite') {
                $items = $query->whereBetween('latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
                    ->whereBetween('longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta])
                    ->limit(500)->get()
                    ->each(fn (Activity $activity) => $activity->setAttribute('distance_km', $this->distance(
                        $latitude, $longitude, (float) $activity->latitude, (float) $activity->longitude,
                    )))
                    ->filter(fn (Activity $activity) => $activity->distance_km <= $radius)
                    ->sortBy('distance_km')->values();
                $page = LengthAwarePaginator::resolveCurrentPage();
                $activities = new LengthAwarePaginator(
                    $items->forPage($page, $perPage)->values(),
                    $items->count(),
                    $perPage,
                    $page,
                    ['path' => $request->url(), 'query' => $request->query()],
                );

                return ActivityResource::collection($activities);
            }
            $cosine = 'cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))';
            $distanceSql = $driver === 'pgsql'
                ? "6371 * acos(LEAST(1, GREATEST(-1, {$cosine})))"
                : "6371 * acos(min(1, max(-1, {$cosine})))";
            $bindings = [$latitude, $longitude, $latitude];
            $activities = $query->whereBetween('latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
                ->whereBetween('longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta])
                ->whereNotNull(['latitude', 'longitude'])
                ->whereRaw("{$distanceSql} <= ?", [...$bindings, $radius])
                ->selectRaw("activities.*, {$distanceSql} AS distance_km", $bindings)
                ->orderByRaw('distance_km ASC, starts_at ASC')
                ->paginate($perPage);
        } else {
            $activities = $query->orderBy('starts_at')->paginate($perPage);
        }

        return ActivityResource::collection($activities);
    }

    private function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return round(6371 * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    public function store(StoreActivityRequest $request, CreateActivity $action): ActivityResource
    {
        Gate::authorize('create', Activity::class);
        if ($clanId = $request->validated('clan_id')) {
            $clan = Clan::findOrFail($clanId);
            Gate::authorize('view', $clan);
            if (! ClanAuthorizer::allows($clan, $request->user(), 'create_events')) {
                throw new ClanException('You cannot create events for this clan.', 'CLAN_PERMISSION_DENIED', 403);
            }
        }

        return new ActivityResource($action->handle($request->user(), $request->validated()));
    }

    public function show(Activity $activity): ActivityResource
    {
        Gate::authorize('view', $activity);
        $activity->load([
            'host', 'category', 'topic', 'sport', 'venue', 'clanEvent.clan',
            'participants' => fn ($query) => $query->where('user_id', request()->user()->id),
        ])
            ->loadCount([
                'participants as joined_count' => fn ($query) => $query->where('status', 'joined'),
                'likes', 'comments',
            ])
            ->loadExists([
                'likes as liked_by_me' => fn ($query) => $query->where('user_id', request()->user()->id),
                'conversation as has_chat',
            ]);

        return new ActivityResource($activity);
    }

    public function update(Activity $activity, UpdateActivityRequest $request, UpdateActivity $action): ActivityResource
    {
        Gate::authorize('manage', $activity);

        return new ActivityResource($action->handle($activity, $request->user(), $request->validated()));
    }

    public function transfer(Activity $activity, Request $request, TransferActivityOwnership $action): ActivityResource
    {
        Gate::authorize('manage', $activity);
        $validated = $request->validate(['user_id' => ['required', 'uuid', 'exists:users,id']]);

        return new ActivityResource(
            $action->handle($activity, $request->user(), User::findOrFail($validated['user_id'])),
        );
    }
}

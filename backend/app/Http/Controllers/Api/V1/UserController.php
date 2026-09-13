<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Block;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:80'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sport_id' => ['nullable', 'uuid', 'exists:sports,id'],
            'skill_min' => ['nullable', 'integer', 'between:800,2400'],
            'skill_max' => ['nullable', 'integer', 'between:800,2400', 'gte:skill_min'],
            'city' => ['nullable', 'string', 'max:120'],
        ]);
        $query = trim($validated['query'] ?? '');
        $blockedUserIds = Block::query()
            ->where('blocker_id', $request->user()->id)->pluck('blocked_id')
            ->merge(Block::query()->where('blocked_id', $request->user()->id)->pluck('blocker_id'));

        $users = User::query()
            ->with(['sportProfiles' => fn ($profiles) => $profiles
                ->with('sport')->when($validated['sport_id'] ?? null, fn ($query, $id) => $query->where('sport_id', $id))])
            ->where('account_status', AccountStatus::Active)
            ->whereKeyNot($request->user()->id)
            ->whereNotIn('id', $blockedUserIds)
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($nested) use ($query): void {
                    $nested->where('username', 'like', "%{$query}%")
                        ->orWhere('display_name', 'like', "%{$query}%");
                });
            })
            ->when($validated['city'] ?? null, fn ($builder, $city) => $builder
                ->whereRaw('LOWER(default_city) = ?', [mb_strtolower($city)]))
            ->when(
                ($validated['sport_id'] ?? null) || isset($validated['skill_min']) || isset($validated['skill_max']),
                fn ($builder) => $builder->whereHas('sportProfiles', fn ($profiles) => $profiles
                    ->when($validated['sport_id'] ?? null, fn ($q, $id) => $q->where('sport_id', $id))
                    ->when($validated['skill_min'] ?? null, fn ($q, $min) => $q->where('skill_rating', '>=', $min))
                    ->when($validated['skill_max'] ?? null, fn ($q, $max) => $q->where('skill_rating', '<=', $max)))
            )
            ->orderBy('username')
            ->paginate($validated['per_page'] ?? 20);

        return UserResource::collection($users);
    }

    public function show(User $user, Request $request): UserResource
    {
        abort_unless($user->account_status === AccountStatus::Active, 404);
        abort_if(Block::query()->between($request->user()->id, $user->id)->exists(), 404);

        return new UserResource($user);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $user = $request->user();
        $friendIds = Friendship::query()
            ->where('user_low_id', $user->id)->pluck('user_high_id')
            ->merge(Friendship::query()->where('user_high_id', $user->id)->pluck('user_low_id'))
            ->unique();
        $excluded = $friendIds->push($user->id)
            ->merge(Block::where('blocker_id', $user->id)->pluck('blocked_id'))
            ->merge(Block::where('blocked_id', $user->id)->pluck('blocker_id'))
            ->merge(FriendRequest::where('status', 'pending')
                ->where(fn ($query) => $query->where('sender_id', $user->id)->orWhere('receiver_id', $user->id))
                ->get()->flatMap(fn ($item) => [$item->sender_id, $item->receiver_id]))
            ->unique();
        $candidates = User::query()->where('account_status', AccountStatus::Active)
            ->whereNotIn('id', $excluded)->limit(200)->get();
        $candidateFriendships = Friendship::query()
            ->where(function ($query) use ($candidates): void {
                $query->whereIn('user_low_id', $candidates->pluck('id'))
                    ->orWhereIn('user_high_id', $candidates->pluck('id'));
            })->get();
        $data = $candidates->map(function (User $candidate) use ($candidateFriendships, $friendIds, $request, $user): array {
            $candidateFriends = $candidateFriendships
                ->filter(fn ($friendship) => $friendship->user_low_id === $candidate->id || $friendship->user_high_id === $candidate->id)
                ->map(fn ($friendship) => $friendship->user_low_id === $candidate->id
                    ? $friendship->user_high_id : $friendship->user_low_id);
            $mutual = $candidateFriends->intersect($friendIds)->count();
            $sameLocation = $user->location && $candidate->location
                && mb_strtolower($user->location) === mb_strtolower($candidate->location);

            return [
                ...((new UserResource($candidate))->resolve($request)),
                'mutual_friends_count' => $mutual,
                'same_location' => (bool) $sameLocation,
                'suggestion_score' => $mutual * 10 + ($sameLocation ? 3 : 0),
            ];
        })->sortByDesc('suggestion_score')->take(20)->values();

        return response()->json(['data' => $data]);
    }
}

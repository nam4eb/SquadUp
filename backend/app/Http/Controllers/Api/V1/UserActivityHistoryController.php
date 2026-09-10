<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class UserActivityHistoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'scope' => ['nullable', Rule::in(['upcoming', 'completed', 'created', 'joined', 'cancelled'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $user = $request->user();
        $scope = $validated['scope'] ?? 'upcoming';
        $query = Activity::query()
            ->with([
                'host', 'category', 'topic',
                'participants' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->withCount([
                'participants as joined_count' => fn ($query) => $query->where('status', 'joined'),
                'likes', 'comments',
            ])
            ->withExists([
                'likes as liked_by_me' => fn ($query) => $query->where('user_id', $user->id),
                'conversation as has_chat',
            ]);

        match ($scope) {
            'created' => $query->where('host_id', $user->id),
            'joined' => $query->where('host_id', '!=', $user->id)
                ->whereHas('participants', fn ($participant) => $participant
                    ->where('user_id', $user->id)->whereIn('status', ['joined', 'attended', 'absent'])),
            'completed' => $query->where('status', ActivityStatus::Completed)
                ->where(fn ($owned) => $owned->where('host_id', $user->id)
                    ->orWhereHas('participants', fn ($participant) => $participant->where('user_id', $user->id))),
            'cancelled' => $query->where('status', ActivityStatus::Cancelled)
                ->where(fn ($owned) => $owned->where('host_id', $user->id)
                    ->orWhereHas('participants', fn ($participant) => $participant->where('user_id', $user->id))),
            default => $query->where('starts_at', '>=', now())
                ->whereNotIn('status', [ActivityStatus::Cancelled, ActivityStatus::Completed, ActivityStatus::Expired])
                ->where(fn ($owned) => $owned->where('host_id', $user->id)
                    ->orWhereHas('participants', fn ($participant) => $participant
                        ->where('user_id', $user->id)->whereIn('status', ['joined', 'requested', 'waitlisted']))),
        };

        return ActivityResource::collection(
            $query->orderBy($scope === 'upcoming' ? 'starts_at' : 'created_at', $scope === 'upcoming' ? 'asc' : 'desc')
                ->paginate($validated['per_page'] ?? 20),
        );
    }
}

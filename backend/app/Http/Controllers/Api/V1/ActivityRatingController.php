<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityStatus;
use App\Exceptions\ActivityException;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityRating;
use App\Models\User;
use App\Models\Block;
use App\Services\ReputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityRatingController extends Controller
{
    public function index(Activity $activity, Request $request): JsonResponse
    {
        $this->ensureRater($activity, $request->user());
        $ratedIds = ActivityRating::query()->where([
            'activity_id' => $activity->id, 'rater_id' => $request->user()->id,
        ])->pluck('ratee_id');
        $targets = $activity->participants()->with('user')
            ->where(fn ($query) => $query->where('role', 'host')->orWhere('status', 'attended'))
            ->where('user_id', '!=', $request->user()->id)
            ->get()->map(fn ($participant) => [
                'id' => $participant->user->id,
                'display_name' => $participant->user->display_name,
                'avatar_path' => $participant->user->avatar_path,
                'rated' => $ratedIds->contains($participant->user_id),
            ])->values();

        return response()->json(['data' => $targets]);
    }

    public function store(Activity $activity, Request $request, ReputationService $reputation): JsonResponse
    {
        $this->ensureRater($activity, $request->user());
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'sportsmanship' => ['required', 'integer', 'between:1,5'],
            'skill' => ['required', 'integer', 'between:1,5'],
            'reliability' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);
        if ($validated['user_id'] === $request->user()->id) {
            throw new ActivityException('You cannot rate yourself.', 'CANNOT_RATE_SELF', 422);
        }
        if (Block::query()->between($validated['user_id'], $request->user()->id)->exists()) {
            throw new ActivityException('This interaction is unavailable.', 'USER_BLOCKED', 403);
        }
        $eligible = $activity->participants()->where('user_id', $validated['user_id'])
            ->where(fn ($query) => $query->where('role', 'host')->orWhere('status', 'attended'))->exists();
        if (! $eligible) {
            throw new ActivityException('This user is not eligible for a rating.', 'RATING_USER_INELIGIBLE', 422);
        }

        [$rating, $aggregate] = DB::transaction(function () use ($activity, $request, $validated, $reputation): array {
            if (ActivityRating::query()->where([
                'activity_id' => $activity->id, 'rater_id' => $request->user()->id,
                'ratee_id' => $validated['user_id'],
            ])->exists()) {
                throw new ActivityException('You already rated this user for this activity.', 'RATING_ALREADY_SUBMITTED');
            }
            $rating = ActivityRating::create([
                'activity_id' => $activity->id, 'sport_id' => $activity->sport_id,
                'rater_id' => $request->user()->id, 'ratee_id' => $validated['user_id'],
                'sportsmanship' => $validated['sportsmanship'], 'skill' => $validated['skill'],
                'reliability' => $validated['reliability'], 'comment' => $validated['comment'] ?? null,
            ]);

            return [$rating, $reputation->refresh($validated['user_id'], $activity->sport_id)];
        });

        return response()->json(['data' => ['rating' => $rating, 'reputation' => $aggregate]], 201);
    }

    private function ensureRater(Activity $activity, User $user): void
    {
        if ($activity->status !== ActivityStatus::Completed) {
            throw new ActivityException('Ratings open after the activity is completed.', 'RATING_NOT_OPEN', 422);
        }
        $eligible = $activity->participants()->where('user_id', $user->id)
            ->where(fn ($query) => $query->where('role', 'host')->orWhere('status', 'attended'))->exists();
        if (! $eligible) {
            throw new ActivityException('You are not eligible to rate this activity.', 'RATER_INELIGIBLE', 403);
        }
    }
}

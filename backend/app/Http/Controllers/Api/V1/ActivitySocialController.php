<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\ActivityLike;
use App\Models\HiddenActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ActivitySocialController extends Controller
{
    public function like(Activity $activity, Request $request): JsonResponse
    {
        Gate::authorize('view', $activity);
        ActivityLike::firstOrCreate(['activity_id' => $activity->id, 'user_id' => $request->user()->id]);

        return response()->json(['message' => 'Activity liked.']);
    }

    public function unlike(Activity $activity, Request $request): JsonResponse
    {
        ActivityLike::where(['activity_id' => $activity->id, 'user_id' => $request->user()->id])->delete();

        return response()->json(['message' => 'Activity unliked.']);
    }

    public function comments(Activity $activity, Request $request): JsonResponse
    {
        Gate::authorize('view', $activity);
        $items = $activity->comments()->with('user')->latest()->cursorPaginate(30);
        $items->through(fn (ActivityComment $comment) => [
            'id' => $comment->id, 'body' => $comment->body,
            'user' => (new UserResource($comment->user))->resolve($request),
            'can_delete' => $comment->user_id === $request->user()->id || $request->user()->can('manage', $activity),
            'created_at' => $comment->created_at->toISOString(),
        ]);

        return response()->json($items);
    }

    public function comment(Activity $activity, Request $request): JsonResponse
    {
        Gate::authorize('view', $activity);
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $comment = $activity->comments()->create([
            'user_id' => $request->user()->id, 'body' => trim($validated['body']),
        ]);

        return response()->json(['data' => [
            'id' => $comment->id, 'body' => $comment->body,
            'user' => (new UserResource($request->user()))->resolve($request),
            'can_delete' => true, 'created_at' => $comment->created_at->toISOString(),
        ]], 201);
    }

    public function deleteComment(Activity $activity, ActivityComment $comment, Request $request): JsonResponse
    {
        abort_unless($comment->activity_id === $activity->id, 404);
        abort_unless($comment->user_id === $request->user()->id || $request->user()->can('manage', $activity), 403);
        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }

    public function hide(Activity $activity, Request $request): JsonResponse
    {
        abort_if($activity->host_id === $request->user()->id, 422, 'You cannot hide your own activity.');
        HiddenActivity::firstOrCreate(['activity_id' => $activity->id, 'user_id' => $request->user()->id]);

        return response()->json(['message' => 'Activity hidden.']);
    }

    public function unhide(Activity $activity, Request $request): JsonResponse
    {
        HiddenActivity::where(['activity_id' => $activity->id, 'user_id' => $request->user()->id])->delete();

        return response()->json(['message' => 'Activity visible again.']);
    }
}

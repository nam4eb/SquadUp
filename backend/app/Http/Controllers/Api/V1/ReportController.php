<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityRating;
use App\Models\Block;
use App\Models\Clan;
use App\Models\Message;
use App\Models\Report;
use App\Models\ReportReason;
use App\Models\Story;
use App\Models\User;
use App\Support\ConversationAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => ['required', Rule::in(['user', 'activity', 'clan', 'message', 'story', 'rating'])],
            'target_id' => ['required', 'uuid'],
            'reason' => ['required', Rule::in(ReportReason::query()->where('is_active', true)->pluck('code')->all())],
            'details' => ['nullable', 'string', 'max:4000'],
        ]);
        $class = match ($validated['target_type']) {
            'user' => User::class, 'activity' => Activity::class,
            'clan' => Clan::class, 'message' => Message::class,
            'story' => Story::class,
            'rating' => ActivityRating::class,
        };
        $target = $class::findOrFail($validated['target_id']);
        match ($validated['target_type']) {
            'activity', 'clan' => Gate::authorize('view', $target),
            'message' => ConversationAuthorizer::authorize($target->conversation, $request->user()),
            'story' => abort_if(Block::query()->between($target->user_id, $request->user()->id)->exists(), 404),
            'rating' => abort_unless($target->ratee_id === $request->user()->id, 403),
            default => null,
        };
        $report = Report::create([
            'reporter_id' => $request->user()->id, 'reportable_type' => $class,
            'reportable_id' => $target->id, 'reason' => $validated['reason'],
            'details' => $validated['details'] ?? null,
            'status' => 'reported',
        ]);

        return response()->json(['data' => [
            'id' => $report->id, 'status' => $report->status,
            'created_at' => $report->created_at->toISOString(),
        ]], 201);
    }
}

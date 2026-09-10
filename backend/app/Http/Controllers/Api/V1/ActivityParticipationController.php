<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Activities\JoinActivity;
use App\Actions\Activities\LeaveActivity;
use App\Actions\Activities\MarkActivityAttendance;
use App\Actions\Activities\RemoveActivityParticipant;
use App\Actions\Activities\ReviewParticipation;
use App\Enums\ActivityParticipantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityParticipantResource;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ActivityParticipationController extends Controller
{
    public function index(Activity $activity): AnonymousResourceCollection
    {
        Gate::authorize('view', $activity);
        $statuses = Gate::allows('manage', $activity)
            ? ['joined', 'requested', 'waitlisted', 'attended', 'absent']
            : ['joined', 'attended', 'absent'];

        return ActivityParticipantResource::collection(
            $activity->participants()
                ->with('user')
                ->whereIn('status', $statuses)
                ->orderByRaw("case when role = 'host' then 0 else 1 end")
                ->orderBy('joined_at')
                ->paginate(50),
        );
    }

    public function join(Activity $activity, Request $request, JoinActivity $action): ActivityParticipantResource
    {
        Gate::authorize('view', $activity);
        $validated = $request->validate(['password' => ['nullable', 'string', 'max:72']]);

        return new ActivityParticipantResource($action->handle($activity, $request->user(), $validated['password'] ?? null));
    }

    public function leave(Activity $activity, Request $request, LeaveActivity $action): JsonResponse
    {
        Gate::authorize('view', $activity);
        $action->handle($activity, $request->user());

        return response()->json(['message' => 'You left the activity.']);
    }

    public function accept(Activity $activity, ActivityParticipant $participant, Request $request, ReviewParticipation $action): ActivityParticipantResource
    {
        Gate::authorize('manage', $activity);

        return new ActivityParticipantResource($action->accept($activity, $participant, $request->user()));
    }

    public function reject(Activity $activity, ActivityParticipant $participant, Request $request, ReviewParticipation $action): ActivityParticipantResource
    {
        Gate::authorize('manage', $activity);

        return new ActivityParticipantResource($action->reject($activity, $participant, $request->user()));
    }

    public function remove(Activity $activity, ActivityParticipant $participant, Request $request, RemoveActivityParticipant $action): ActivityParticipantResource
    {
        Gate::authorize('manage', $activity);
        $validated = $request->validate(['ban' => ['sometimes', 'boolean']]);

        return new ActivityParticipantResource(
            $action->handle($activity, $participant, $request->user(), (bool) ($validated['ban'] ?? false)),
        );
    }

    public function attendance(
        Activity $activity,
        ActivityParticipant $participant,
        Request $request,
        MarkActivityAttendance $action,
    ): ActivityParticipantResource {
        Gate::authorize('manage', $activity);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['attended', 'absent'])],
        ]);

        return new ActivityParticipantResource(
            $action->handle(
                $activity,
                $participant,
                $request->user(),
                ActivityParticipantStatus::from($validated['status']),
            ),
        );
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Activities\ActivityLifecycle;
use App\Enums\ActivityStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ActivityLifecycleController extends Controller
{
    public function transition(Activity $activity, Request $request, ActivityLifecycle $lifecycle): ActivityResource
    {
        Gate::authorize('manage', $activity);
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ActivityStatus::class)],
        ]);

        return new ActivityResource(
            $lifecycle->transition($activity, $request->user(), ActivityStatus::from($validated['status'])),
        );
    }
}

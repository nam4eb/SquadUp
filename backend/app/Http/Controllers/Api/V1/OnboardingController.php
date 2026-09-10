<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserSportProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    private const STEPS = ['profile', 'sports', 'location', 'notifications', 'completed'];

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('sportProfiles.sport');

        return response()->json(['data' => $this->payload($user)]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'step' => ['required', Rule::in(self::STEPS)],
            'display_name' => ['sometimes', 'string', 'max:120'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'default_city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'default_area_latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:default_area_longitude'],
            'default_area_longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:default_area_latitude'],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
        ]);

        if ($validated['step'] === 'completed' && ! $request->user()->sportProfiles()->exists()) {
            return response()->json([
                'message' => 'Choose at least one sport before completing onboarding.',
                'error' => ['code' => 'SPORT_REQUIRED'],
            ], 422);
        }

        $request->user()->update([
            ...collect($validated)->except('step')->all(),
            'onboarding_step' => $validated['step'],
            'onboarding_completed_at' => $validated['step'] === 'completed' ? now() : null,
        ]);

        return response()->json([
            'message' => 'Onboarding progress updated.',
            'data' => $this->payload($request->user()->fresh()->load('sportProfiles.sport')),
        ]);
    }

    private function payload($user): array
    {
        return [
            'step' => $user->onboarding_step,
            'completed' => $user->onboarding_completed_at !== null,
            'completed_at' => $user->onboarding_completed_at?->toISOString(),
            'default_area' => [
                'city' => $user->default_city,
                'latitude' => $user->default_area_latitude,
                'longitude' => $user->default_area_longitude,
                'timezone' => $user->timezone,
            ],
            'user' => new UserResource($user, includePrivate: true),
            'sport_profiles' => UserSportProfileResource::collection($user->sportProfiles),
        ];
    }
}

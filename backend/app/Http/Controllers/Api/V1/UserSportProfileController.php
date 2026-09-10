<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserSportProfileResource;
use App\Models\Sport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserSportProfileController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return UserSportProfileResource::collection(
            $request->user()->sportProfiles()->with('sport')->get()->sortBy('sport.sort_order')->values()
        );
    }

    public function sync(Request $request): JsonResponse
    {
        $levels = array_keys(config('skills.levels'));
        $validated = $request->validate([
            'sports' => ['required', 'array', 'min:1', 'max:12'],
            'sports.*.sport_id' => ['required', 'uuid', 'distinct', Rule::exists('sports', 'id')->where('is_active', true)],
            'sports.*.level' => ['required', 'string', Rule::in($levels)],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $ids = collect($validated['sports'])->pluck('sport_id');
            $request->user()->sportProfiles()->whereNotIn('sport_id', $ids)->delete();

            foreach ($validated['sports'] as $item) {
                $request->user()->sportProfiles()->updateOrCreate(
                    ['sport_id' => $item['sport_id']],
                    [
                        'self_declared_level' => $item['level'],
                        'skill_rating' => config("skills.levels.{$item['level']}"),
                    ],
                );
            }
        });

        return response()->json([
            'message' => 'Sport profiles updated.',
            'data' => UserSportProfileResource::collection(
                $request->user()->sportProfiles()->with('sport')->get()->sortBy('sport.sort_order')->values()
            ),
        ]);
    }
}

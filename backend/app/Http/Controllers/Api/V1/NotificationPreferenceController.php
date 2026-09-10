<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $stored = $request->user()->notificationPreferences()->get()->keyBy('type');
        $data = collect(NotificationPreferenceService::TYPES)->map(function (string $type) use ($stored): array {
            $preference = $stored->get($type);

            return [
                'type' => $type,
                'in_app_enabled' => $preference?->in_app_enabled ?? true,
                'push_enabled' => $preference?->push_enabled ?? true,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.type' => ['required', 'string', Rule::in(NotificationPreferenceService::TYPES), 'distinct'],
            'preferences.*.in_app_enabled' => ['required', 'boolean'],
            'preferences.*.push_enabled' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            foreach ($validated['preferences'] as $item) {
                NotificationPreference::updateOrCreate(
                    ['user_id' => $request->user()->id, 'type' => $item['type']],
                    ['in_app_enabled' => $item['in_app_enabled'], 'push_enabled' => $item['push_enabled']],
                );
            }
        });

        return $this->index($request);
    }
}

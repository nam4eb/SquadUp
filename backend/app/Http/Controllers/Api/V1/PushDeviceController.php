<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PushDeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', Rule::in(['android', 'ios', 'web'])],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);
        $device = PushDevice::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'],
                'device_name' => $validated['device_name'] ?? null,
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['data' => $device], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(PushDevice $device, Request $request): JsonResponse
    {
        abort_unless($device->user_id === $request->user()->id, 404);
        $device->delete();

        return response()->json(['message' => 'Push device removed.']);
    }
}

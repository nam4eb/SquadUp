<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $validated = collect($request->validated())->except(['avatar', 'cover'])->all();
        foreach (['avatar', 'cover'] as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }
            $column = "{$field}_path";
            if ($user->{$column}) {
                Storage::disk(config('media.disk'))->delete($user->{$column});
            }
            $validated[$column] = $request->file($field)->store(
                "profiles/{$user->id}/{$field}",
                config('media.disk'),
            );
        }
        $user->update($validated);

        return new UserResource($user->refresh(), includePrivate: true);
    }
}

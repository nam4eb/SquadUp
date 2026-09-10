<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function __construct($resource, private readonly bool $includePrivate = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $isOwner = $this->includePrivate || $request->user()?->is($this->resource);

        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $this->display_name,
            'avatar_url' => $this->avatar_path ? Storage::disk(config('media.disk'))->url($this->avatar_path) : null,
            'cover_url' => $this->cover_path ? Storage::disk(config('media.disk'))->url($this->cover_path) : null,
            'bio' => $this->bio,
            'gender' => $isOwner ? $this->gender : null,
            'date_of_birth' => $isOwner ? $this->date_of_birth?->toDateString() : null,
            'location' => $this->location,
            'presence_status' => $this->presence_status,
            'email' => $isOwner ? $this->email : null,
            'email_verified_at' => $isOwner ? $this->email_verified_at?->toISOString() : null,
            'account_status' => $isOwner ? $this->account_status->value : null,
            'onboarding_step' => $isOwner ? $this->onboarding_step : null,
            'onboarding_completed_at' => $isOwner ? $this->onboarding_completed_at?->toISOString() : null,
            'default_area' => $isOwner ? [
                'city' => $this->default_city,
                'latitude' => $this->default_area_latitude,
                'longitude' => $this->default_area_longitude,
                'timezone' => $this->timezone,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

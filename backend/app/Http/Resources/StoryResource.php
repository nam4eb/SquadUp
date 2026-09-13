<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class StoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'activity' => $this->whenLoaded('activity', fn () => $this->activity ? [
                'id' => $this->activity->id,
                'title' => $this->activity->title,
            ] : null),
            'media_url' => Storage::disk($this->media_disk)->url($this->media_path),
            'thumbnail_url' => $this->thumbnail_path
                ? Storage::disk($this->media_disk)->url($this->thumbnail_path)
                : null,
            'media_type' => $this->media_type,
            'duration_ms' => $this->duration_ms,
            'caption' => $this->caption,
            'visibility' => $this->visibility,
            'is_mine' => $request->user()?->id === $this->user_id,
            'viewed_by_me' => (bool) ($this->viewed_by_me
                ?? ($this->relationLoaded('views') && $this->views->isNotEmpty())),
            'views_count' => $request->user()?->id === $this->user_id ? ($this->views_count ?? 0) : null,
            'created_at' => $this->created_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ClanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewerMember = $this->relationLoaded('members')
            ? $this->members->firstWhere('user_id', $request->user()?->id)
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'avatar_url' => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
            'cover_url' => $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'visibility' => $this->visibility,
            'join_policy' => $this->join_policy,
            'status' => $this->status,
            'active_members_count' => (int) ($this->active_members_count ?? 0),
            'viewer_membership' => $this->owner_id === $request->user()?->id
                ? 'active'
                : $viewerMember?->status?->value,
            'is_owner' => $this->owner_id === $request->user()?->id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

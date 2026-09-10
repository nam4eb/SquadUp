<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $members = $this->whenLoaded('members');
        $other = $members instanceof Collection
            ? $members->first(fn ($member) => $member->user_id !== $request->user()?->id)?->user
            : null;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->type === 'direct' ? $other?->display_name
                : ($this->type === 'event' ? $this->activity?->title : $this->name),
            'description' => $this->description,
            'avatar_url' => $this->avatar_path ? Storage::disk(config('media.disk'))->url($this->avatar_path) : null,
            'clan_id' => $this->clan_id,
            'activity_id' => $this->activity_id,
            'current_member_role' => $members instanceof Collection
                ? $members->firstWhere('user_id', $request->user()?->id)?->role
                : null,
            'members' => ConversationMemberResource::collection($this->whenLoaded('members')),
            'latest_message' => new MessageResource($this->whenLoaded('latestMessage')),
            'last_message_at' => $this->last_message_at?->toISOString(),
            'unread_count' => (int) ($this->unread_count ?? 0),
        ];
    }
}

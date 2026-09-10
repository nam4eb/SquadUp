<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewerParticipation = $this->host_id === $request->user()?->id
            ? 'joined'
            : ($this->viewer_participation
                ?? ($this->relationLoaded('participants') ? $this->participants->first()?->status?->value : null));

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'cover_url' => $this->cover_path,
            'host' => new UserResource($this->whenLoaded('host')),
            'category' => new ActivityCategoryResource($this->whenLoaded('category')),
            'topic' => new ActivityTopicResource($this->whenLoaded('topic')),
            'sport' => new SportResource($this->whenLoaded('sport')),
            'venue' => new VenueResource($this->whenLoaded('venue')),
            'clan' => $this->whenLoaded('clanEvent', fn () => $this->clanEvent ? [
                'id' => $this->clanEvent->clan->id,
                'name' => $this->clanEvent->clan->name,
                'slug' => $this->clanEvent->clan->slug,
            ] : null),
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'timezone' => $this->timezone,
            'location' => [
                'name' => $this->location_name,
                'address' => $this->location_address,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],
            'distance_km' => isset($this->distance_km) ? (float) $this->distance_km : null,
            'skill_range' => ['min' => $this->skill_min, 'max' => $this->skill_max],
            'match_format' => $this->match_format,
            'fee' => $this->fee !== null ? (float) $this->fee : null,
            'currency' => $this->currency,
            'min_participants' => $this->min_participants,
            'max_participants' => $this->max_participants,
            'joined_count' => (int) ($this->joined_count ?? 0),
            'likes_count' => (int) ($this->likes_count ?? 0),
            'comments_count' => (int) ($this->comments_count ?? 0),
            'liked_by_me' => (bool) ($this->liked_by_me ?? false),
            'is_host' => $this->host_id === $request->user()?->id,
            'can_manage' => $request->user()?->can('manage', $this->resource) === true,
            'viewer_participation' => $viewerParticipation,
            'status' => $this->status->value,
            'visibility' => $this->visibility->value,
            'allow_waitlist' => $this->allow_waitlist,
            'allow_friend_invitations' => $this->allow_friend_invitations,
            'require_approval' => $this->require_approval,
            'allow_join_by_link' => $this->allow_join_by_link,
            'is_password_protected' => $this->password_hash !== null,
            'minimum_age' => $this->minimum_age,
            'rules' => $this->rules ?? [],
            'equipment_requirements' => $this->equipment_requirements,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'recurrence' => $this->recurrence_rule ? [
                'parent_id' => $this->recurrence_parent_id,
                'index' => $this->recurrence_index,
                'rule' => $this->recurrence_rule,
            ] : null,
            'has_chat' => isset($this->has_chat)
                ? (bool) $this->has_chat
                : ($this->relationLoaded('conversation') ? $this->conversation !== null : $this->conversation()->exists()),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_message_id' => $this->client_message_id,
            'type' => $this->type,
            'body' => $this->deleted_at ? null : $this->body,
            'payload' => $this->deleted_at ? null : $this->payload,
            'sender' => new UserResource($this->whenLoaded('sender')),
            'reply_to_id' => $this->reply_to_id,
            'story' => $this->story_id ? [
                'id' => $this->story_id,
                'available' => $this->relationLoaded('story') && $this->story?->expires_at?->isFuture(),
                'caption' => $this->relationLoaded('story') ? $this->story?->caption : null,
            ] : null,
            'is_mine' => $this->sender_id === $request->user()?->id,
            'edited_at' => $this->edited_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'reactions' => $this->relationLoaded('reactions')
                ? $this->reactions->groupBy('reaction')->map(fn ($items, $reaction) => [
                    'reaction' => $reaction,
                    'count' => $items->count(),
                    'reacted_by_me' => $items->contains('user_id', $request->user()?->id),
                ])->values()
                : [],
            'read_count' => isset($this->reads_count)
                ? (int) $this->reads_count
                : ($this->relationLoaded('reads') ? $this->reads->count() : 0),
            'media' => $this->relationLoaded('media') && ! $this->deleted_at
                ? $this->media->map(fn ($media) => [
                    'id' => $media->id,
                    'url' => $media->url,
                    'original_name' => $media->original_name,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'width' => $media->width,
                    'height' => $media->height,
                    'duration_ms' => $media->duration_ms,
                ])->values()
                : [],
            'mentions' => $this->relationLoaded('mentions')
                ? $this->mentions->map(fn ($mention) => [
                    'user_id' => $mention->user_id,
                    'username' => $mention->user?->username,
                    'display_name' => $mention->user?->display_name,
                ])->values()
                : [],
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClanMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clan_id' => $this->clan_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'role' => $this->whenLoaded('role', fn () => [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
            ]),
            'status' => $this->status->value,
            'joined_at' => $this->joined_at?->toISOString(),
        ];
    }
}

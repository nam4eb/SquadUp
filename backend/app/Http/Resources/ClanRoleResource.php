<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClanRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clan_id' => $this->clan_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'is_system' => $this->is_system,
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->permissions->pluck('permission')->values(),
            ),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'icon' => $this->icon,
            'min_players' => $this->min_players,
            'max_players' => $this->max_players,
            'supports_team' => $this->supports_team,
            'supports_singles' => $this->supports_singles,
            'supports_doubles' => $this->supports_doubles,
            'skill_system' => $this->skill_system,
        ];
    }
}

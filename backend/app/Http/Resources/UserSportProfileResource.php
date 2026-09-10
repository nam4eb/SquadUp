<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSportProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sport' => new SportResource($this->whenLoaded('sport')),
            'self_declared_level' => $this->self_declared_level,
            'verified_level' => $this->verified_level,
            'display_level' => $this->verified_level ?? $this->self_declared_level,
            'matches_played' => $this->matches_played,
            'rating_confidence' => $this->rating_confidence,
        ];
    }
}

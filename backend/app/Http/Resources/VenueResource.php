<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'district' => $this->district,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'is_verified' => $this->is_verified,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'activity_id' => $this->activity_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'role' => $this->role,
            'status' => $this->status->value,
            'joined_at' => $this->joined_at?->toISOString(),
            'attendance_marked_at' => $this->attendance_marked_at?->toISOString(),
            'waitlist_position' => $this->waitlist_position,
        ];
    }
}

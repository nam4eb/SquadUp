<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FriendshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'friend' => new UserResource($this->otherUser($request->user())),
            'accepted_at' => $this->accepted_at->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'image_url' => $this->image_path,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'topics' => ActivityTopicResource::collection($this->whenLoaded('topics')),
        ];
    }
}

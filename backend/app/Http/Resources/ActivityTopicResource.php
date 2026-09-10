<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityTopicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'image_url' => $this->image_path,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'settings' => $this->settings,
            'children' => ActivityTopicResource::collection($this->whenLoaded('children')),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityCategoryResource;
use App\Http\Resources\ActivityTopicResource;
use App\Models\ActivityCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityCategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = ActivityCategory::query()
            ->where('status', 'active')
            ->where('is_visible', true)
            ->with(['topics' => fn ($query) => $query
                ->whereNull('parent_id')
                ->where('status', 'active')
                ->where('is_visible', true)
                ->with(['children' => fn ($children) => $children
                    ->where('status', 'active')
                    ->where('is_visible', true)
                    ->orderBy('sort_order')])
                ->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return ActivityCategoryResource::collection($categories);
    }

    public function topics(ActivityCategory $activityCategory): AnonymousResourceCollection
    {
        abort_unless($activityCategory->status === 'active' && $activityCategory->is_visible, 404);

        return ActivityTopicResource::collection(
            $activityCategory->topics()
                ->where('status', 'active')
                ->where('is_visible', true)
                ->orderBy('sort_order')
                ->get(),
        );
    }
}

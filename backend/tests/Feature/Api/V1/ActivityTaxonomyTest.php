<?php

namespace Tests\Feature\Api\V1;

use App\Models\ActivityCategory;
use App\Models\ActivityTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_taxonomy_requires_authentication(): void
    {
        $this->getJson('/api/v1/activity-categories')->assertUnauthorized();
    }

    public function test_categories_are_dynamic_ordered_and_hide_inactive_records(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $second = ActivityCategory::create(['name' => 'Food', 'slug' => 'food', 'sort_order' => 20]);
        $first = ActivityCategory::create(['name' => 'Sports', 'slug' => 'sports', 'sort_order' => 10]);
        ActivityCategory::create(['name' => 'Hidden', 'slug' => 'hidden', 'status' => 'inactive']);
        ActivityTopic::create(['category_id' => $first->id, 'name' => 'Badminton', 'slug' => 'badminton']);
        ActivityTopic::create(['category_id' => $second->id, 'name' => 'Coffee', 'slug' => 'coffee']);

        $this->getJson('/api/v1/activity-categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'sports')
            ->assertJsonPath('data.0.topics.0.slug', 'badminton');
    }

    public function test_topics_support_subtopics_and_visibility(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = ActivityCategory::create(['name' => 'Sports', 'slug' => 'sports']);
        $topic = ActivityTopic::create(['category_id' => $category->id, 'name' => 'Running', 'slug' => 'running']);
        ActivityTopic::create(['category_id' => $category->id, 'parent_id' => $topic->id, 'name' => 'Trail', 'slug' => 'trail']);
        ActivityTopic::create(['category_id' => $category->id, 'name' => 'Hidden', 'slug' => 'hidden', 'is_visible' => false]);

        $this->getJson('/api/v1/activity-categories')
            ->assertOk()
            ->assertJsonPath('data.0.topics.0.children.0.slug', 'trail');
        $this->getJson("/api/v1/activity-categories/{$category->id}/topics")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityParticipant;
use App\Models\ActivityTopic;
use App\Models\Block;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExploreTest extends TestCase
{
    use RefreshDatabase;

    public function test_explore_filters_by_exact_radius_sport_skill_format_and_open_slots(): void
    {
        $viewer = User::factory()->create();
        $host = User::factory()->create();
        [$category, $topic] = $this->taxonomy();
        $badminton = $this->sport('badminton');
        $football = $this->sport('football');

        $near = $this->activity($host, $category, $topic, $badminton, 'Near badminton', 10.008, 106.0, [
            'skill_min' => 1100, 'skill_max' => 1500, 'match_format' => 'doubles',
        ]);
        $this->activity($host, $category, $topic, $badminton, 'Far badminton', 10.06, 106.0, [
            'skill_min' => 1100, 'skill_max' => 1500, 'match_format' => 'doubles',
        ]);
        $this->activity($host, $category, $topic, $football, 'Near football', 10.008, 106.0, [
            'skill_min' => 1100, 'skill_max' => 1500, 'match_format' => 'team',
        ]);

        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/explore?near_lat=10&near_lng=106&radius_km=2&sport=badminton&skill=1300&match_format=doubles&open_slots=1')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $near->id)
            ->assertJsonPath('data.0.sport.slug', 'badminton')
            ->assertJsonPath('data.0.match_format', 'doubles');

        ActivityParticipant::create([
            'activity_id' => $near->id, 'user_id' => $host->id, 'role' => 'host', 'status' => 'joined', 'joined_at' => now(),
        ]);
        $near->update(['max_participants' => 1]);
        $this->getJson('/api/v1/explore?near_lat=10&near_lng=106&radius_km=2&open_slots=1')
            ->assertOk()->assertJsonMissing(['id' => $near->id]);
    }

    public function test_explore_preserves_private_and_block_privacy_rules(): void
    {
        $viewer = User::factory()->create();
        $host = User::factory()->create();
        [$category, $topic] = $this->taxonomy();
        $sport = $this->sport('pickleball');
        $public = $this->activity($host, $category, $topic, $sport, 'Public', 10, 106);
        $this->activity($host, $category, $topic, $sport, 'Private', 10, 106, ['visibility' => 'private']);
        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/explore')->assertOk()->assertJsonCount(1, 'data');
        Block::create(['blocker_id' => $viewer->id, 'blocked_id' => $host->id]);
        $this->getJson('/api/v1/explore')->assertOk()->assertJsonMissing(['id' => $public->id]);
    }

    public function test_activity_created_with_venue_uses_canonical_venue_coordinates(): void
    {
        $host = User::factory()->create();
        [$category, $topic] = $this->taxonomy();
        $sport = $this->sport('tennis');
        $venue = Venue::create([
            'name' => 'Central Court', 'address' => '12 Nguyen Hue', 'city' => 'Ho Chi Minh City',
            'latitude' => 10.7731, 'longitude' => 106.7030, 'is_verified' => true,
        ]);
        Sanctum::actingAs($host);

        $this->postJson('/api/v1/activities', [
            'category_id' => $category->id, 'topic_id' => $topic->id, 'sport_id' => $sport->id,
            'venue_id' => $venue->id, 'title' => 'Tennis after work',
            'starts_at' => now()->addDay()->toISOString(), 'timezone' => 'Asia/Bangkok',
            'max_participants' => 4, 'visibility' => 'public', 'skill_min' => 1000,
            'skill_max' => 1600, 'match_format' => 'doubles', 'fee' => 100000, 'currency' => 'VND',
        ])->assertCreated()
            ->assertJsonPath('data.venue.id', $venue->id)
            ->assertJsonPath('data.location.name', 'Central Court')
            ->assertJsonPath('data.sport.slug', 'tennis');
    }

    public function test_nearby_results_are_paginated_by_distance_and_respect_privacy(): void
    {
        $viewer = User::factory()->create();
        $host = User::factory()->create();
        [$category, $topic] = $this->taxonomy();
        $sport = $this->sport('tennis');
        $far = $this->activity($host, $category, $topic, $sport, 'Further', 10.02, 106);
        $near = $this->activity($host, $category, $topic, $sport, 'Closer', 10.001, 106);
        $this->activity($host, $category, $topic, $sport, 'Private', 10, 106, ['visibility' => 'private']);
        $this->activity($host, $category, $topic, $sport, 'No coordinates', 10, 106,
            ['latitude' => null, 'longitude' => null]);
        Sanctum::actingAs($viewer);
        $url = '/api/v1/explore?near_lat=10&near_lng=106&radius_km=5&per_page=1';
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.id', $near->id)
            ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
        $this->getJson($url.'&page=2')->assertOk()->assertJsonPath('data.0.id', $far->id);
        Block::create(['blocker_id' => $viewer->id, 'blocked_id' => $host->id]);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_nearby_rejects_invalid_or_incomplete_coordinates(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/explore?near_lat=91&near_lng=106&radius_km=5')
            ->assertUnprocessable()->assertJsonValidationErrors('near_lat');
        $this->getJson('/api/v1/explore?near_lat=10&radius_km=5')
            ->assertUnprocessable()->assertJsonValidationErrors('near_lng');
    }

    private function taxonomy(): array
    {
        $category = ActivityCategory::create(['name' => 'Sports', 'slug' => 'sports']);
        $topic = ActivityTopic::create(['category_id' => $category->id, 'name' => 'Game', 'slug' => 'game']);

        return [$category, $topic];
    }

    private function sport(string $slug): Sport
    {
        return Sport::create(['slug' => $slug, 'name' => ucfirst($slug)]);
    }

    private function activity(User $host, ActivityCategory $category, ActivityTopic $topic, Sport $sport, string $title, float $lat, float $lng, array $extra = []): Activity
    {
        return Activity::create([...[
            'host_id' => $host->id, 'category_id' => $category->id, 'topic_id' => $topic->id,
            'sport_id' => $sport->id, 'title' => $title, 'starts_at' => now()->addDay(),
            'timezone' => 'UTC', 'location_name' => 'Test venue', 'latitude' => $lat,
            'longitude' => $lng, 'max_participants' => 10, 'status' => 'open', 'visibility' => 'public',
        ], ...$extra]);
    }
}

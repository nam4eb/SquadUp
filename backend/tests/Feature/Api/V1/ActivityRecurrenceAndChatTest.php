<?php

namespace Tests\Feature\Api\V1;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityRecurrenceAndChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_create_weekly_series_with_independent_participation(): void
    {
        [$activityId, $host] = $this->activity();
        Sanctum::actingAs($host);
        $this->postJson("/api/v1/activities/{$activityId}/recurrence", [
            'frequency' => 'weekly', 'interval' => 2, 'count' => 4,
        ])->assertOk()->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.recurrence.rule.frequency', 'weekly')
            ->assertJsonPath('data.3.recurrence.index', 3);
        $this->assertDatabaseCount('activities', 4);
        $this->assertDatabaseCount('activity_participants', 4);
        $this->assertDatabaseHas('activities', ['recurrence_parent_id' => $activityId, 'recurrence_index' => 3]);
    }

    public function test_event_chat_is_created_by_host_and_only_joined_participants_can_access(): void
    {
        [$activityId, $host] = $this->activity();
        $participant = User::factory()->create();
        $outsider = User::factory()->create();
        Sanctum::actingAs($host);
        $conversationId = $this->postJson("/api/v1/activities/{$activityId}/chat")
            ->assertCreated()->assertJsonPath('data.type', 'event')->json('data.id');

        Sanctum::actingAs($participant);
        $this->postJson("/api/v1/activities/{$activityId}/join")->assertCreated();
        $this->getJson("/api/v1/activities/{$activityId}/chat")->assertOk();
        $this->postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => 'See you there'])
            ->assertCreated();

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/activities/{$activityId}/chat")->assertNotFound();
        $this->getJson("/api/v1/conversations/{$conversationId}/messages")->assertNotFound();
    }

    public function test_feed_can_filter_and_order_activities_by_exact_distance(): void
    {
        [$nearId, $host] = $this->activity();
        Activity::whereKey($nearId)->update(['latitude' => 13.7563, 'longitude' => 100.5018]);
        $far = Activity::findOrFail($nearId)->replicate();
        $far->id = (string) Str::uuid();
        $far->title = 'Far activity';
        $far->latitude = 18.7883;
        $far->longitude = 98.9853;
        $far->save();
        Sanctum::actingAs($host);

        $this->getJson('/api/v1/activities?near_lat=13.7563&near_lng=100.5018&radius_km=20')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $nearId)
            ->assertJsonPath('data.0.distance_km', 0);
    }

    public function test_feed_can_search_title_and_location(): void
    {
        [$activityId, $host] = $this->activity();
        Activity::whereKey($activityId)->update(['title' => 'Sunset badminton', 'location_name' => 'Lumphini Park']);
        Sanctum::actingAs($host);

        $this->getJson('/api/v1/activities?query=Lumphini')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $activityId);
        $this->getJson('/api/v1/activities?query=not-found')->assertOk()->assertJsonCount(0, 'data');
    }

    private function activity(): array
    {
        $host = User::factory()->create();
        $category = ActivityCategory::create(['name' => 'Sports', 'slug' => fake()->unique()->slug()]);
        $topic = ActivityTopic::create([
            'category_id' => $category->id, 'name' => 'Running', 'slug' => fake()->unique()->slug(),
        ]);
        Sanctum::actingAs($host);
        $id = $this->postJson('/api/v1/activities', [
            'category_id' => $category->id, 'topic_id' => $topic->id,
            'title' => 'Recurring run', 'starts_at' => now()->addDays(2)->toISOString(),
            'ends_at' => now()->addDays(2)->addHour()->toISOString(), 'timezone' => 'UTC',
            'location_name' => 'Park', 'min_participants' => 1, 'max_participants' => 10,
        ])->assertCreated()->json('data.id');

        return [$id, $host];
    }
}

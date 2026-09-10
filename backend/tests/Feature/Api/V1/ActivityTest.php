<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ActivityParticipantStatus;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityTopic;
use App\Models\Block;
use App\Models\Friendship;
use App\Models\User;
use App\Support\UserPair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_create_activity_and_becomes_host_participant(): void
    {
        $host = User::factory()->create();
        [$category, $topic] = $this->taxonomy();
        Sanctum::actingAs($host);

        $response = $this->postJson('/api/v1/activities', $this->payload($category, $topic, [
            'password' => 'secret123',
            'rules' => ['Bring your own racket'],
        ]))->assertCreated()
            ->assertJsonPath('data.host.id', $host->id)
            ->assertJsonPath('data.joined_count', 1)
            ->assertJsonPath('data.is_password_protected', true)
            ->assertJsonMissingPath('data.password_hash');

        $activity = Activity::findOrFail($response->json('data.id'));
        $this->assertTrue(Hash::check('secret123', $activity->password_hash));
        $this->assertDatabaseHas('activity_participants', [
            'activity_id' => $activity->id,
            'user_id' => $host->id,
            'role' => 'host',
            'status' => ActivityParticipantStatus::Joined->value,
        ]);
    }

    public function test_unverified_user_cannot_create_activity(): void
    {
        $user = User::factory()->unverified()->create();
        [$category, $topic] = $this->taxonomy();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/activities', $this->payload($category, $topic))->assertForbidden();
    }

    public function test_host_cannot_create_overlapping_activities_but_can_create_adjacent_one(): void
    {
        $host = User::factory()->create();
        [$category, $topic] = $this->taxonomy();
        Sanctum::actingAs($host);
        $startsAt = now()->addDays(2)->startOfHour();

        $this->postJson('/api/v1/activities', $this->payload($category, $topic, [
            'starts_at' => $startsAt->toISOString(),
            'ends_at' => $startsAt->copy()->addHours(2)->toISOString(),
        ]))->assertCreated();

        $this->postJson('/api/v1/activities', $this->payload($category, $topic, [
            'starts_at' => $startsAt->copy()->addHour()->toISOString(),
            'ends_at' => $startsAt->copy()->addHours(3)->toISOString(),
        ]))->assertUnprocessable()
            ->assertJsonPath('code', 'ACTIVITY_TIME_CONFLICT');

        $this->postJson('/api/v1/activities', $this->payload($category, $topic, [
            'starts_at' => $startsAt->copy()->addHours(2)->toISOString(),
            'ends_at' => $startsAt->copy()->addHours(3)->toISOString(),
        ]))->assertCreated();
    }

    public function test_activity_creation_validates_topic_schedule_and_capacity(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$category, $topic] = $this->taxonomy();
        [, $otherTopic] = $this->taxonomy('Food', 'food', 'Coffee', 'coffee');

        $this->postJson('/api/v1/activities', $this->payload($category, $otherTopic, [
            'starts_at' => now()->subHour()->toISOString(),
            'min_participants' => 10,
            'max_participants' => 5,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['topic_id', 'starts_at', 'max_participants']);
    }

    public function test_visibility_policy_supports_public_friends_private_and_blocks(): void
    {
        $host = User::factory()->create();
        $viewer = User::factory()->create();
        [$category, $topic] = $this->taxonomy();
        $public = $this->activity($host, $category, $topic, 'public');
        $friends = $this->activity($host, $category, $topic, 'friends');
        $private = $this->activity($host, $category, $topic, 'private');
        Sanctum::actingAs($viewer);

        $this->getJson("/api/v1/activities/{$public->id}")->assertOk();
        $this->getJson("/api/v1/activities/{$friends->id}")->assertNotFound();
        $this->getJson("/api/v1/activities/{$private->id}")->assertNotFound();

        [$low, $high] = UserPair::ordered($host->id, $viewer->id);
        Friendship::create(['user_low_id' => $low, 'user_high_id' => $high, 'pair_key' => UserPair::key($low, $high), 'accepted_at' => now()]);
        $this->getJson("/api/v1/activities/{$friends->id}")->assertOk();

        Block::create(['blocker_id' => $host->id, 'blocked_id' => $viewer->id]);
        $this->getJson("/api/v1/activities/{$public->id}")->assertNotFound();
    }

    public function test_activity_feed_only_contains_accessible_records(): void
    {
        $viewer = User::factory()->create();
        $host = User::factory()->create();
        [$category, $topic] = $this->taxonomy();
        $this->activity($host, $category, $topic, 'public', 'Public session');
        $this->activity($host, $category, $topic, 'private', 'Private session');
        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/activities')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Public session');
    }

    private function taxonomy(string $categoryName = 'Sports', string $categorySlug = 'sports', string $topicName = 'Badminton', string $topicSlug = 'badminton'): array
    {
        $category = ActivityCategory::create(['name' => $categoryName, 'slug' => $categorySlug]);
        $topic = ActivityTopic::create(['category_id' => $category->id, 'name' => $topicName, 'slug' => $topicSlug]);

        return [$category, $topic];
    }

    private function payload(ActivityCategory $category, ActivityTopic $topic, array $overrides = []): array
    {
        return [...[
            'category_id' => $category->id,
            'topic_id' => $topic->id,
            'title' => 'Badminton at Cau Giay',
            'description' => 'Need five more players.',
            'starts_at' => now()->addDay()->toISOString(),
            'ends_at' => now()->addDay()->addHours(2)->toISOString(),
            'timezone' => 'Asia/Bangkok',
            'location_name' => 'Cau Giay',
            'min_participants' => 2,
            'max_participants' => 10,
            'visibility' => 'public',
        ], ...$overrides];
    }

    private function activity(User $host, ActivityCategory $category, ActivityTopic $topic, string $visibility, string $title = 'Activity'): Activity
    {
        return Activity::create([
            'host_id' => $host->id, 'category_id' => $category->id, 'topic_id' => $topic->id,
            'title' => $title, 'starts_at' => now()->addDay(), 'timezone' => 'UTC',
            'location_name' => 'Test venue', 'max_participants' => 10,
            'status' => 'open', 'visibility' => $visibility,
        ]);
    }
}

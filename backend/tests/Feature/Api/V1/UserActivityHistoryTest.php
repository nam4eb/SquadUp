<?php

namespace Tests\Feature\Api\V1;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityParticipant;
use App\Models\ActivityTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserActivityHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_scopes_only_return_the_authenticated_users_activities(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        [$category, $topic] = $this->taxonomy();
        $upcoming = $this->activity($user, $category, $topic, 'Upcoming', 'open', now()->addDay());
        $completed = $this->activity($other, $category, $topic, 'Completed', 'completed', now()->subDay());
        ActivityParticipant::create([
            'activity_id' => $completed->id, 'user_id' => $user->id,
            'role' => 'member', 'status' => 'attended', 'joined_at' => now()->subDays(2),
        ]);
        $this->activity($other, $category, $topic, 'Unrelated', 'open', now()->addDay());
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users/me/activity-history?scope=upcoming')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $upcoming->id);
        $this->getJson('/api/v1/users/me/activity-history?scope=completed')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $completed->id);
        $this->getJson('/api/v1/users/me/activity-history?scope=created')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $upcoming->id);
        $this->getJson('/api/v1/users/me/activity-history?scope=joined')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $completed->id);
    }

    private function taxonomy(): array
    {
        $category = ActivityCategory::create(['name' => 'Sports', 'slug' => 'sports']);
        $topic = ActivityTopic::create(['category_id' => $category->id, 'name' => 'Run', 'slug' => 'run']);

        return [$category, $topic];
    }

    private function activity(User $host, ActivityCategory $category, ActivityTopic $topic, string $title, string $status, $startsAt): Activity
    {
        $activity = Activity::create([
            'host_id' => $host->id, 'category_id' => $category->id, 'topic_id' => $topic->id,
            'title' => $title, 'starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addHour(),
            'timezone' => 'UTC', 'location_name' => 'Park', 'max_participants' => 10,
            'status' => $status, 'visibility' => 'public',
        ]);
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'user_id' => $host->id,
            'role' => 'host', 'status' => 'joined', 'joined_at' => now(),
        ]);

        return $activity;
    }
}

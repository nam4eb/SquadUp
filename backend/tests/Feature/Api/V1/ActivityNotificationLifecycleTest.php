<?php

namespace Tests\Feature\Api\V1;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityTopic;
use App\Models\User;
use App\Notifications\ActivityCancelled;
use App\Notifications\ActivityParticipantJoined;
use App\Notifications\ActivityStartingSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityNotificationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_is_notified_when_participant_joins(): void
    {
        Notification::fake();
        [$activity, $host] = $this->activity(now()->addDays(2));
        $participant = User::factory()->create();
        Sanctum::actingAs($participant);

        $this->postJson("/api/v1/activities/{$activity->id}/join")->assertCreated();

        Notification::assertSentTo($host, ActivityParticipantJoined::class);
    }

    public function test_cancellation_notifies_joined_participants(): void
    {
        Notification::fake();
        [$activity, $host] = $this->activity(now()->addDays(2));
        $participant = User::factory()->create();
        Sanctum::actingAs($participant);
        $this->postJson("/api/v1/activities/{$activity->id}/join")->assertCreated();

        Sanctum::actingAs($host);
        $this->patchJson("/api/v1/activities/{$activity->id}/status", ['status' => 'cancelled'])->assertOk();

        Notification::assertSentTo($participant, ActivityCancelled::class);
    }

    public function test_starting_soon_reminders_are_deduplicated(): void
    {
        Notification::fake();
        [$activity, $host] = $this->activity(now()->addMinutes(20));
        $participant = User::factory()->create();
        Sanctum::actingAs($participant);
        $this->postJson("/api/v1/activities/{$activity->id}/join")->assertCreated();
        Notification::fake();

        $this->artisan('activities:send-reminders')->assertSuccessful()->expectsOutput('Sent 2 activity reminder(s).');
        $this->artisan('activities:send-reminders')->assertSuccessful()->expectsOutput('Sent 0 activity reminder(s).');

        Notification::assertSentTo($host, ActivityStartingSoon::class, 1);
        Notification::assertSentTo($participant, ActivityStartingSoon::class, 1);
        $this->assertDatabaseCount('activity_notification_dispatches', 2);
    }

    private function activity($startsAt): array
    {
        $host = User::factory()->create();
        $category = ActivityCategory::create(['name' => 'Sports', 'slug' => fake()->unique()->slug()]);
        $topic = ActivityTopic::create([
            'category_id' => $category->id, 'name' => 'Running', 'slug' => fake()->unique()->slug(),
        ]);
        Sanctum::actingAs($host);
        $id = $this->postJson('/api/v1/activities', [
            'category_id' => $category->id, 'topic_id' => $topic->id,
            'title' => 'Reminder run', 'starts_at' => $startsAt->toISOString(),
            'ends_at' => $startsAt->copy()->addHour()->toISOString(), 'timezone' => 'UTC',
            'location_name' => 'Park', 'max_participants' => 10,
        ])->assertCreated()->json('data.id');

        return [Activity::findOrFail($id), $host];
    }
}

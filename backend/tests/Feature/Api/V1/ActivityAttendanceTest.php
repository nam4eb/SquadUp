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

class ActivityAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_mark_and_correct_attendance_only_after_activity_starts(): void
    {
        [$activity, $host, $participant] = $this->activity();
        Sanctum::actingAs($host);
        $url = "/api/v1/activities/{$activity->id}/participants/{$participant->id}/attendance";

        $this->patchJson($url, ['status' => 'attended'])
            ->assertUnprocessable()->assertJsonPath('code', 'ATTENDANCE_NOT_OPEN');
        $activity->update(['status' => 'ongoing']);
        $this->patchJson($url, ['status' => 'attended'])
            ->assertOk()->assertJsonPath('data.status', 'attended');
        $this->assertDatabaseHas('activity_participants', [
            'id' => $participant->id, 'status' => 'attended', 'attendance_marked_by' => $host->id,
        ]);
        $this->patchJson($url, ['status' => 'absent'])
            ->assertOk()->assertJsonPath('data.status', 'absent');
    }

    public function test_non_manager_and_ineligible_participant_are_rejected(): void
    {
        [$activity, $host, $participant] = $this->activity();
        $activity->update(['status' => 'completed']);
        $url = "/api/v1/activities/{$activity->id}/participants/{$participant->id}/attendance";
        Sanctum::actingAs(User::factory()->create());
        $this->patchJson($url, ['status' => 'attended'])->assertForbidden();

        $participant->update(['status' => 'left']);
        Sanctum::actingAs($host);
        $this->patchJson($url, ['status' => 'attended'])
            ->assertUnprocessable()->assertJsonPath('code', 'PARTICIPANT_NOT_ATTENDANCE_ELIGIBLE');
        $this->patchJson($url, ['status' => 'joined'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    private function activity(): array
    {
        $host = User::factory()->create();
        $user = User::factory()->create();
        $category = ActivityCategory::create(['name' => 'Sports', 'slug' => 'sports']);
        $topic = ActivityTopic::create([
            'category_id' => $category->id, 'name' => 'Running', 'slug' => 'running',
        ]);
        $activity = Activity::create([
            'host_id' => $host->id, 'category_id' => $category->id, 'topic_id' => $topic->id,
            'title' => 'Morning run', 'starts_at' => now()->addHour(), 'timezone' => 'UTC',
            'location_name' => 'Park', 'max_participants' => 10, 'status' => 'open', 'visibility' => 'public',
        ]);
        $activity->participants()->create([
            'user_id' => $host->id, 'role' => 'host', 'status' => 'joined', 'joined_at' => now(),
        ]);
        $participant = ActivityParticipant::create([
            'activity_id' => $activity->id, 'user_id' => $user->id,
            'role' => 'member', 'status' => 'joined', 'joined_at' => now(),
        ]);

        return [$activity, $host, $participant];
    }
}

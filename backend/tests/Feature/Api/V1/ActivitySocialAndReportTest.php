<?php

namespace Tests\Feature\Api\V1;

use App\Models\ActivityCategory;
use App\Models\ActivityTopic;
use App\Models\ReportReason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivitySocialAndReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_like_comment_and_see_social_counts(): void
    {
        [$activityId, $host] = $this->activity();
        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $this->postJson("/api/v1/activities/{$activityId}/like")->assertOk();
        $this->postJson("/api/v1/activities/{$activityId}/like")->assertOk();
        $commentId = $this->postJson("/api/v1/activities/{$activityId}/comments", ['body' => '  Great event!  '])
            ->assertCreated()->assertJsonPath('data.body', 'Great event!')->json('data.id');

        $this->getJson("/api/v1/activities/{$activityId}")
            ->assertOk()->assertJsonPath('data.likes_count', 1)
            ->assertJsonPath('data.comments_count', 1)
            ->assertJsonPath('data.liked_by_me', true);
        $this->getJson("/api/v1/activities/{$activityId}/comments")
            ->assertOk()->assertJsonPath('data.0.can_delete', true);

        Sanctum::actingAs($host);
        $this->deleteJson("/api/v1/activities/{$activityId}/comments/{$commentId}")->assertOk();
        $this->assertSoftDeleted('activity_comments', ['id' => $commentId]);
    }

    public function test_hidden_activity_is_excluded_and_can_be_restored(): void
    {
        [$activityId] = $this->activity();
        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $this->postJson("/api/v1/activities/{$activityId}/hide")->assertOk();
        $this->getJson('/api/v1/activities')->assertOk()->assertJsonCount(0, 'data');
        $this->deleteJson("/api/v1/activities/{$activityId}/hide")->assertOk();
        $this->getJson('/api/v1/activities')->assertOk()->assertJsonPath('data.0.id', $activityId);
    }

    public function test_visible_targets_can_be_reported(): void
    {
        [$activityId] = $this->activity();
        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $reportId = $this->postJson('/api/v1/reports', [
            'target_type' => 'activity', 'target_id' => $activityId,
            'reason' => 'spam', 'details' => 'Repeated promotion',
        ])->assertCreated()->assertJsonPath('data.status', 'reported')->json('data.id');

        $this->assertDatabaseHas('reports', [
            'id' => $reportId, 'reportable_id' => $activityId,
            'reason' => 'spam', 'status' => 'reported',
        ]);
    }

    public function test_inactive_report_reason_is_rejected(): void
    {
        [$activityId] = $this->activity();
        ReportReason::where('code', 'spam')->update(['is_active' => false]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/reports', [
            'target_type' => 'activity', 'target_id' => $activityId, 'reason' => 'spam',
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');
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
            'title' => 'Social run', 'starts_at' => now()->addDays(2)->toISOString(),
            'ends_at' => now()->addDays(2)->addHour()->toISOString(), 'timezone' => 'UTC',
            'location_name' => 'Park', 'max_participants' => 10,
        ])->assertCreated()->json('data.id');

        return [$id, $host];
    }
}

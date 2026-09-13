<?php

namespace Tests\Feature\Api\V1;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityParticipant;
use App\Models\ActivityRating;
use App\Models\ActivityTopic;
use App\Models\Sport;
use App\Models\User;
use App\Services\ReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityRatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendee_can_rate_eligible_participant_once_and_updates_reputation(): void
    {
        [$activity, $host, $attendee] = $this->fixture('completed');
        Sanctum::actingAs($attendee);

        $this->getJson("/api/v1/activities/{$activity->id}/ratings")
            ->assertOk()->assertJsonPath('data.0.id', $host->id)
            ->assertJsonPath('data.0.rated', false);
        $payload = ['user_id' => $host->id, 'sportsmanship' => 5, 'skill' => 4, 'reliability' => 5];
        $this->postJson("/api/v1/activities/{$activity->id}/ratings", $payload)
            ->assertCreated()->assertJsonPath('data.reputation.ratings_count', 1)
            ->assertJsonPath('data.reputation.sportsmanship_average', 5);
        $this->postJson("/api/v1/activities/{$activity->id}/ratings", $payload)
            ->assertConflict()->assertJsonPath('code', 'RATING_ALREADY_SUBMITTED');
    }

    public function test_rating_requires_completed_activity_and_eligible_users(): void
    {
        [$activity, $host, $attendee] = $this->fixture('ongoing');
        Sanctum::actingAs($attendee);
        $this->postJson("/api/v1/activities/{$activity->id}/ratings", [
            'user_id' => $host->id, 'sportsmanship' => 5, 'skill' => 4, 'reliability' => 5,
        ])->assertUnprocessable()->assertJsonPath('code', 'RATING_NOT_OPEN');

        $activity->update(['status' => 'completed']);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/activities/{$activity->id}/ratings")
            ->assertForbidden()->assertJsonPath('code', 'RATER_INELIGIBLE');
    }

    public function test_rating_rejects_self_and_absent_participant(): void
    {
        [$activity, , $attendee] = $this->fixture('completed');
        $absent = User::factory()->create();
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'user_id' => $absent->id,
            'role' => 'member', 'status' => 'absent', 'joined_at' => now(),
        ]);
        Sanctum::actingAs($attendee);
        $scores = ['sportsmanship' => 3, 'skill' => 3, 'reliability' => 3];
        $this->postJson("/api/v1/activities/{$activity->id}/ratings", [
            'user_id' => $attendee->id, ...$scores,
        ])->assertUnprocessable()->assertJsonPath('code', 'CANNOT_RATE_SELF');
        $this->postJson("/api/v1/activities/{$activity->id}/ratings", [
            'user_id' => $absent->id, ...$scores,
        ])->assertUnprocessable()->assertJsonPath('code', 'RATING_USER_INELIGIBLE');
    }

    public function test_ratee_can_report_rating_but_unrelated_user_cannot(): void
    {
        [$activity, $host, $attendee] = $this->fixture('completed');
        Sanctum::actingAs($attendee);
        $ratingId = $this->postJson("/api/v1/activities/{$activity->id}/ratings", [
            'user_id' => $host->id, 'sportsmanship' => 1, 'skill' => 1,
            'reliability' => 1, 'comment' => 'Abusive review',
        ])->assertCreated()->json('data.rating.id');

        Sanctum::actingAs($host);
        $this->postJson('/api/v1/reports', [
            'target_type' => 'rating', 'target_id' => $ratingId,
            'reason' => 'harassment', 'details' => 'Please review this rating.',
        ])->assertCreated()->assertJsonPath('data.status', 'reported');

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/reports', [
            'target_type' => 'rating', 'target_id' => $ratingId,
            'reason' => 'harassment',
        ])->assertForbidden();
    }

    public function test_invalidated_rating_is_excluded_and_restore_is_reversible(): void
    {
        [$activity, $host, $attendee] = $this->fixture('completed');
        Sanctum::actingAs($attendee);
        $ratingId = $this->postJson("/api/v1/activities/{$activity->id}/ratings", [
            'user_id' => $host->id, 'sportsmanship' => 5, 'skill' => 4, 'reliability' => 5,
        ])->assertCreated()->json('data.rating.id');
        $rating = ActivityRating::findOrFail($ratingId);

        $rating->update(['invalidated_at' => now(), 'invalidation_reason' => 'Test moderation']);
        $reputation = app(ReputationService::class)->refresh($host->id, $activity->sport_id);
        $this->assertSame(0, $reputation->ratings_count);
        $this->assertSame(0.0, $reputation->skill_average);

        $rating->update(['invalidated_at' => null, 'invalidation_reason' => null]);
        $reputation = app(ReputationService::class)->refresh($host->id, $activity->sport_id);
        $this->assertSame(1, $reputation->ratings_count);
        $this->assertSame(4.0, $reputation->skill_average);
    }

    private function fixture(string $status): array
    {
        $host = User::factory()->create();
        $attendee = User::factory()->create();
        $category = ActivityCategory::create(['name' => 'Sports', 'slug' => 'sports']);
        $topic = ActivityTopic::create(['category_id' => $category->id, 'name' => 'Badminton', 'slug' => 'badminton']);
        $sport = Sport::create(['name' => 'Badminton', 'slug' => 'badminton']);
        $activity = Activity::create([
            'host_id' => $host->id, 'category_id' => $category->id, 'topic_id' => $topic->id,
            'sport_id' => $sport->id, 'title' => 'Rated game', 'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(), 'timezone' => 'UTC', 'location_name' => 'Court',
            'max_participants' => 4, 'status' => $status, 'visibility' => 'public',
        ]);
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'user_id' => $host->id,
            'role' => 'host', 'status' => 'joined', 'joined_at' => now()->subHours(2),
        ]);
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'user_id' => $attendee->id,
            'role' => 'member', 'status' => 'attended', 'joined_at' => now()->subHours(2),
        ]);
        $host->sportProfiles()->create([
            'sport_id' => $sport->id, 'self_declared_level' => 'intermediate', 'skill_rating' => 1200,
        ]);

        return [$activity, $host, $attendee];
    }
}

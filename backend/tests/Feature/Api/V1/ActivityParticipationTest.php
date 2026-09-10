<?php

namespace Tests\Feature\Api\V1;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityParticipant;
use App\Models\ActivityTopic;
use App\Models\User;
use App\Actions\Activities\JoinActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityParticipationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_join_open_activity_and_duplicate_is_rejected(): void
    {
        [$activity, , $user] = $this->fixture();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/activities/{$activity->id}/join")
            ->assertCreated()
            ->assertJsonPath('data.status', 'joined');
        $this->getJson("/api/v1/activities/{$activity->id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_participation', 'joined')
            ->assertJsonPath('data.is_host', false);
        $this->postJson("/api/v1/activities/{$activity->id}/join")
            ->assertConflict()
            ->assertJsonPath('code', 'ALREADY_PARTICIPATING');
    }

    public function test_full_activity_waitlists_and_leave_promotes_first_user(): void
    {
        [$activity, , $joinedUser] = $this->fixture(['max_participants' => 2]);
        $waitlistedUser = User::factory()->create();
        Sanctum::actingAs($joinedUser);
        $this->postJson("/api/v1/activities/{$activity->id}/join")->assertCreated();
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'status' => 'full']);

        Sanctum::actingAs($waitlistedUser);
        $this->postJson("/api/v1/activities/{$activity->id}/join")
            ->assertCreated()
            ->assertJsonPath('data.status', 'waitlisted')
            ->assertJsonPath('data.waitlist_position', 1);

        Sanctum::actingAs($joinedUser);
        $this->postJson("/api/v1/activities/{$activity->id}/leave")->assertOk();
        $this->assertDatabaseHas('activity_participants', [
            'activity_id' => $activity->id,
            'user_id' => $waitlistedUser->id,
            'status' => 'joined',
            'waitlist_position' => null,
        ]);
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'status' => 'full']);
    }

    public function test_full_activity_without_waitlist_rejects_join(): void
    {
        [$activity, , $user] = $this->fixture(['max_participants' => 1, 'allow_waitlist' => false]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/activities/{$activity->id}/join")
            ->assertConflict()
            ->assertJsonPath('code', 'ACTIVITY_FULL');
    }

    public function test_stale_clients_cannot_overbook_capacity(): void
    {
        [$activity] = $this->fixture(['max_participants' => 2]);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $firstSnapshot = Activity::findOrFail($activity->id);
        $secondSnapshot = Activity::findOrFail($activity->id);
        $join = app(JoinActivity::class);

        $this->assertSame('joined', $join->handle($firstSnapshot, $first)->status->value);
        $this->assertSame('waitlisted', $join->handle($secondSnapshot, $second)->status->value);
        $this->assertSame(2, ActivityParticipant::query()
            ->where('activity_id', $activity->id)->where('status', 'joined')->count());
        $this->assertSame(1, ActivityParticipant::query()
            ->where('activity_id', $activity->id)->where('status', 'waitlisted')->count());
    }

    public function test_approval_request_can_be_accepted_by_host(): void
    {
        [$activity, $host, $user] = $this->fixture(['require_approval' => true]);
        Sanctum::actingAs($user);
        $participantId = $this->postJson("/api/v1/activities/{$activity->id}/join")
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->json('data.id');
        $this->getJson("/api/v1/activities/{$activity->id}/participants")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Sanctum::actingAs($host);
        $this->patchJson("/api/v1/activities/{$activity->id}/participants/{$participantId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'joined');
    }

    public function test_non_host_cannot_review_and_host_can_reject_request(): void
    {
        [$activity, $host, $user] = $this->fixture(['require_approval' => true]);
        Sanctum::actingAs($user);
        $participantId = $this->postJson("/api/v1/activities/{$activity->id}/join")->json('data.id');

        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->patchJson("/api/v1/activities/{$activity->id}/participants/{$participantId}/reject")->assertForbidden();

        Sanctum::actingAs($host);
        $this->patchJson("/api/v1/activities/{$activity->id}/participants/{$participantId}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_password_and_age_restrictions_are_enforced(): void
    {
        [$activity, , $user] = $this->fixture([
            'password_hash' => Hash::make('court123'),
            'minimum_age' => 18,
        ], ['date_of_birth' => now()->subYears(17)->toDateString()]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/activities/{$activity->id}/join", ['password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_ACTIVITY_PASSWORD');
        $this->postJson("/api/v1/activities/{$activity->id}/join", ['password' => 'court123'])
            ->assertForbidden()
            ->assertJsonPath('code', 'AGE_RESTRICTED');
    }

    public function test_host_cannot_leave_and_participants_are_listed(): void
    {
        [$activity, $host] = $this->fixture();
        Sanctum::actingAs($host);

        $this->postJson("/api/v1/activities/{$activity->id}/leave")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'HOST_CANNOT_LEAVE');
        $this->getJson("/api/v1/activities/{$activity->id}/participants")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.role', 'host');
    }

    private function fixture(array $activityOverrides = [], array $userOverrides = []): array
    {
        $host = User::factory()->create();
        $user = User::factory()->create($userOverrides);
        $category = ActivityCategory::create(['name' => 'Sports', 'slug' => fake()->unique()->slug()]);
        $topic = ActivityTopic::create(['category_id' => $category->id, 'name' => 'Badminton', 'slug' => 'badminton']);
        $activity = Activity::create([...[
            'host_id' => $host->id,
            'category_id' => $category->id,
            'topic_id' => $topic->id,
            'title' => 'Badminton night',
            'starts_at' => now()->addDay(),
            'timezone' => 'UTC',
            'location_name' => 'Court 1',
            'max_participants' => 10,
            'status' => 'open',
            'visibility' => 'public',
        ], ...$activityOverrides]);
        ActivityParticipant::create([
            'activity_id' => $activity->id,
            'user_id' => $host->id,
            'role' => 'host',
            'status' => 'joined',
            'joined_at' => now(),
        ]);

        return [$activity, $host, $user];
    }
}

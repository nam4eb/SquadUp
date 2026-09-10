<?php

namespace Tests\Feature\Api\V1;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityParticipant;
use App\Models\ActivityTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_update_lobby_and_capacity_cannot_drop_below_joined_count(): void
    {
        [$activity, $host, $member] = $this->fixture(['max_participants' => 3]);
        $this->join($activity, $member);
        Sanctum::actingAs($host);

        $this->patchJson("/api/v1/activities/{$activity->id}", [
            'title' => 'Updated badminton night',
            'max_participants' => 1,
        ])->assertUnprocessable()->assertJsonPath('code', 'CAPACITY_BELOW_JOINED');

        $this->patchJson("/api/v1/activities/{$activity->id}", [
            'title' => 'Updated badminton night',
            'max_participants' => 4,
            'password' => 'new-secret',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Updated badminton night')
            ->assertJsonPath('data.max_participants', 4)
            ->assertJsonPath('data.is_password_protected', true);
        $this->assertTrue(Hash::check('new-secret', $activity->fresh()->password_hash));
    }

    public function test_expanding_full_activity_reopens_it(): void
    {
        [$activity, $host, $member] = $this->fixture(['max_participants' => 2, 'status' => 'full']);
        $this->join($activity, $member);
        Sanctum::actingAs($host);

        $this->patchJson("/api/v1/activities/{$activity->id}", ['max_participants' => 3])
            ->assertOk()
            ->assertJsonPath('data.status', 'open');
    }

    public function test_host_removal_promotes_waitlist_and_ban_prevents_rejoin(): void
    {
        [$activity, $host, $member] = $this->fixture(['max_participants' => 2, 'status' => 'full']);
        $joined = $this->join($activity, $member);
        $waitlistedUser = User::factory()->create();
        $waitlisted = ActivityParticipant::create([
            'activity_id' => $activity->id, 'user_id' => $waitlistedUser->id,
            'role' => 'member', 'status' => 'waitlisted', 'waitlist_position' => 1,
        ]);
        Sanctum::actingAs($host);

        $this->deleteJson("/api/v1/activities/{$activity->id}/participants/{$joined->id}", ['ban' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'banned');
        $this->assertDatabaseHas('activity_participants', ['id' => $waitlisted->id, 'status' => 'joined']);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/activities/{$activity->id}/join")
            ->assertForbidden()
            ->assertJsonPath('code', 'ACTIVITY_BANNED');
    }

    public function test_ownership_transfer_requires_joined_participant_and_switches_permissions(): void
    {
        [$activity, $host, $member] = $this->fixture();
        $outsider = User::factory()->create();
        Sanctum::actingAs($host);
        $this->postJson("/api/v1/activities/{$activity->id}/transfer-ownership", ['user_id' => $outsider->id])
            ->assertUnprocessable()->assertJsonPath('code', 'NEW_HOST_NOT_JOINED');

        $this->join($activity, $member);
        $this->postJson("/api/v1/activities/{$activity->id}/transfer-ownership", ['user_id' => $member->id])
            ->assertOk()->assertJsonPath('data.host.id', $member->id);
        $this->assertDatabaseHas('activity_participants', [
            'activity_id' => $activity->id, 'user_id' => $host->id, 'role' => 'member',
        ]);
        $this->assertDatabaseHas('activity_participants', [
            'activity_id' => $activity->id, 'user_id' => $member->id, 'role' => 'host',
        ]);

        $this->patchJson("/api/v1/activities/{$activity->id}/status", ['status' => 'locked'])->assertForbidden();
        Sanctum::actingAs($member);
        $this->patchJson("/api/v1/activities/{$activity->id}/status", ['status' => 'locked'])->assertOk();
    }

    public function test_invitation_listing_omits_hash_and_direct_invite_can_be_revoked(): void
    {
        [$activity, $host, $member] = $this->fixture();
        Sanctum::actingAs($host);
        $invitationId = $this->postJson("/api/v1/activities/{$activity->id}/invitations", [
            'user_id' => $member->id,
        ])->json('data.id');
        $this->postJson("/api/v1/activities/{$activity->id}/invitation-secrets", ['type' => 'code'])->assertCreated();

        $this->getJson("/api/v1/activities/{$activity->id}/invitations")
            ->assertOk()
            ->assertJsonCount(1, 'invitations')
            ->assertJsonCount(1, 'secrets')
            ->assertJsonMissingPath('secrets.0.secret_hash');
        $this->deleteJson("/api/v1/activities/{$activity->id}/invitations/{$invitationId}")
            ->assertOk();
        $this->assertDatabaseHas('activity_invitations', ['id' => $invitationId, 'status' => 'revoked']);
    }

    private function fixture(array $overrides = []): array
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $category = ActivityCategory::create(['name' => 'Sports', 'slug' => fake()->unique()->slug()]);
        $topic = ActivityTopic::create(['category_id' => $category->id, 'name' => 'Badminton', 'slug' => 'badminton']);
        $activity = Activity::create([...[
            'host_id' => $host->id, 'category_id' => $category->id, 'topic_id' => $topic->id,
            'title' => 'Badminton night', 'starts_at' => now()->addDay(), 'timezone' => 'UTC',
            'location_name' => 'Court 1', 'max_participants' => 10,
            'status' => 'open', 'visibility' => 'public',
        ], ...$overrides]);
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'user_id' => $host->id,
            'role' => 'host', 'status' => 'joined', 'joined_at' => now(),
        ]);

        return [$activity, $host, $member];
    }

    private function join(Activity $activity, User $user): ActivityParticipant
    {
        return ActivityParticipant::create([
            'activity_id' => $activity->id, 'user_id' => $user->id,
            'role' => 'member', 'status' => 'joined', 'joined_at' => now(),
        ]);
    }
}

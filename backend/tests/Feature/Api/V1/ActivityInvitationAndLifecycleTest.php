<?php

namespace Tests\Feature\Api\V1;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityInvitationToken;
use App\Models\ActivityParticipant;
use App\Models\ActivityTopic;
use App\Models\User;
use App\Notifications\ActivityInvitationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityInvitationAndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_invitation_notifies_invitee_grants_visibility_and_can_be_accepted(): void
    {
        [$activity, $host, $invitee] = $this->fixture();
        Sanctum::actingAs($host);
        $invitationId = $this->postJson("/api/v1/activities/{$activity->id}/invitations", [
            'user_id' => $invitee->id,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $invitee->id,
            'type' => ActivityInvitationReceived::class,
        ]);
        $this->postJson("/api/v1/activities/{$activity->id}/invitations", [
            'user_id' => $invitee->id,
        ])->assertOk()->assertJsonPath('data.id', $invitationId);
        $this->assertDatabaseCount('activity_invitations', 1);
        $this->assertDatabaseCount('notifications', 1);

        Sanctum::actingAs($invitee);
        $this->getJson("/api/v1/activities/{$activity->id}")->assertOk();
        $this->postJson("/api/v1/activity-invitations/{$invitationId}/accept")
            ->assertCreated()
            ->assertJsonPath('data.status', 'joined');
        $this->assertDatabaseHas('activity_invitations', [
            'id' => $invitationId,
            'status' => 'accepted',
        ]);
    }

    public function test_invite_only_activity_is_hidden_and_invitation_is_bound_to_invitee(): void
    {
        [$activity, $host, $invitee] = $this->fixture();
        $other = User::factory()->create();
        Sanctum::actingAs($host);
        $invitationId = $this->postJson("/api/v1/activities/{$activity->id}/invitations", [
            'user_id' => $invitee->id,
        ])->json('data.id');

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/activities/{$activity->id}")->assertNotFound();
        $this->postJson("/api/v1/activity-invitations/{$invitationId}/accept")
            ->assertForbidden()
            ->assertJsonPath('code', 'INVITATION_FORBIDDEN');
    }

    public function test_link_secret_is_only_returned_once_and_usage_limit_is_enforced(): void
    {
        [$activity, $host, $firstUser] = $this->fixture();
        $secondUser = User::factory()->create();
        Sanctum::actingAs($host);
        $secret = $this->postJson("/api/v1/activities/{$activity->id}/invitation-secrets", [
            'type' => 'link',
            'max_uses' => 1,
        ])->assertCreated()->json('secret');
        $this->assertNotEmpty($secret);
        $this->assertDatabaseMissing('activity_invitation_tokens', ['secret_hash' => $secret]);
        $this->assertSame(64, strlen(ActivityInvitationToken::firstOrFail()->secret_hash));

        Sanctum::actingAs($firstUser);
        $this->postJson('/api/v1/activity-invitations/redeem', [
            'type' => 'link', 'secret' => $secret,
        ])->assertCreated()->assertJsonPath('data.status', 'joined');

        Sanctum::actingAs($secondUser);
        $this->postJson('/api/v1/activity-invitations/redeem', [
            'type' => 'link', 'secret' => $secret,
        ])->assertStatus(410)->assertJsonPath('code', 'INVITATION_EXHAUSTED');
    }

    public function test_invitation_code_is_case_insensitive_and_revocable(): void
    {
        [$activity, $host, $invitee] = $this->fixture();
        Sanctum::actingAs($host);
        $response = $this->postJson("/api/v1/activities/{$activity->id}/invitation-secrets", [
            'type' => 'code',
        ])->assertCreated();
        $code = $response->json('secret');
        $tokenId = $response->json('data.id');

        Sanctum::actingAs($invitee);
        $this->postJson('/api/v1/activity-invitations/redeem', [
            'type' => 'code', 'secret' => strtolower($code),
        ])->assertCreated();

        $other = User::factory()->create();
        Sanctum::actingAs($host);
        $this->deleteJson("/api/v1/activities/{$activity->id}/invitation-secrets/{$tokenId}")->assertOk();
        Sanctum::actingAs($other);
        $this->postJson('/api/v1/activity-invitations/redeem', [
            'type' => 'code', 'secret' => $code,
        ])->assertStatus(410)->assertJsonPath('code', 'INVITATION_REVOKED');
    }

    public function test_expired_direct_and_secret_invitations_are_rejected(): void
    {
        [$activity, $host, $invitee] = $this->fixture();
        Sanctum::actingAs($host);
        $invitation = $activity->invitations()->create([
            'inviter_id' => $host->id,
            'invitee_id' => $invitee->id,
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);
        $token = $activity->invitationTokens()->create([
            'created_by' => $host->id,
            'type' => 'link',
            'secret_hash' => hash('sha256', 'expired-secret'),
            'expires_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($invitee);
        $this->postJson("/api/v1/activity-invitations/{$invitation->id}/accept")
            ->assertStatus(410)->assertJsonPath('code', 'INVITATION_EXPIRED');
        $this->postJson('/api/v1/activity-invitations/redeem', [
            'type' => 'link', 'secret' => 'expired-secret',
        ])->assertStatus(410)->assertJsonPath('code', 'INVITATION_EXPIRED');
        $this->assertNotNull($token->fresh()->expires_at);
    }

    public function test_host_can_transition_lifecycle_and_invalid_transition_is_rejected(): void
    {
        [$activity, $host] = $this->fixture(['visibility' => 'public']);
        Sanctum::actingAs($host);

        $this->patchJson("/api/v1/activities/{$activity->id}/status", ['status' => 'locked'])
            ->assertOk()->assertJsonPath('data.status', 'locked');
        $this->patchJson("/api/v1/activities/{$activity->id}/status", ['status' => 'open'])
            ->assertOk()->assertJsonPath('data.status', 'open');
        $this->patchJson("/api/v1/activities/{$activity->id}/status", ['status' => 'ongoing'])
            ->assertOk()->assertJsonPath('data.status', 'ongoing');
        $this->patchJson("/api/v1/activities/{$activity->id}/status", ['status' => 'completed'])
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->patchJson("/api/v1/activities/{$activity->id}/status", ['status' => 'open'])
            ->assertUnprocessable()->assertJsonPath('code', 'INVALID_ACTIVITY_TRANSITION');
    }

    public function test_non_host_cannot_create_secrets_or_transition_activity(): void
    {
        [$activity, , $user] = $this->fixture(['visibility' => 'public']);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/activities/{$activity->id}/invitation-secrets", ['type' => 'link'])
            ->assertForbidden();
        $this->patchJson("/api/v1/activities/{$activity->id}/status", ['status' => 'locked'])
            ->assertForbidden();
    }

    private function fixture(array $overrides = []): array
    {
        $host = User::factory()->create();
        $user = User::factory()->create();
        $category = ActivityCategory::create(['name' => 'Sports', 'slug' => fake()->unique()->slug()]);
        $topic = ActivityTopic::create(['category_id' => $category->id, 'name' => 'Badminton', 'slug' => 'badminton']);
        $activity = Activity::create([...[
            'host_id' => $host->id, 'category_id' => $category->id, 'topic_id' => $topic->id,
            'title' => 'Invite-only badminton', 'starts_at' => now()->addDay(),
            'timezone' => 'UTC', 'location_name' => 'Court 1', 'max_participants' => 10,
            'status' => 'open', 'visibility' => 'invite_only',
        ], ...$overrides]);
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'user_id' => $host->id,
            'role' => 'host', 'status' => 'joined', 'joined_at' => now(),
        ]);

        return [$activity, $host, $user];
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Actions\Clans\CreateClan;
use App\Models\ClanMember;
use App\Models\User;
use App\Notifications\ClanInvitationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClanTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_creates_clan_with_default_rbac_and_owner_membership(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/clans', [
            'name' => 'Hanoi Badminton Squad',
            'slug' => 'hanoi-badminton-squad',
            'description' => 'Weekly badminton activities.',
            'visibility' => 'public',
            'join_policy' => 'approval',
        ])->assertCreated()
            ->assertJsonPath('data.is_owner', true)
            ->assertJsonPath('data.active_members_count', 1);
        $clanId = $response->json('data.id');
        $this->assertDatabaseCount('clan_roles', 5);
        $this->assertDatabaseHas('clan_members', [
            'clan_id' => $clanId, 'user_id' => $owner->id, 'status' => 'active',
        ]);
        $this->assertDatabaseHas('clan_role_permissions', ['permission' => 'manage_roles']);
    }

    public function test_unverified_user_cannot_create_clan(): void
    {
        Sanctum::actingAs(User::factory()->unverified()->create());
        $this->postJson('/api/v1/clans', [
            'name' => 'Test Clan', 'slug' => 'test-clan',
        ])->assertForbidden();
    }

    public function test_open_join_is_active_and_approval_join_can_be_reviewed(): void
    {
        [$openClan, $owner] = $this->clan(['join_policy' => 'open']);
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/clans/{$openClan->id}/join")
            ->assertCreated()->assertJsonPath('data.status', 'active');

        [$approvalClan, $approvalOwner] = $this->clan(['slug' => 'approval-clan', 'join_policy' => 'approval']);
        $requester = User::factory()->create();
        Sanctum::actingAs($requester);
        $memberId = $this->postJson("/api/v1/clans/{$approvalClan->id}/join")
            ->assertCreated()->assertJsonPath('data.status', 'requested')->json('data.id');
        $this->getJson("/api/v1/clans/{$approvalClan->id}/members")
            ->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs($approvalOwner);
        $this->patchJson("/api/v1/clans/{$approvalClan->id}/members/{$memberId}/review", ['accept' => true])
            ->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertNotSame($owner->id, $approvalOwner->id);
    }

    public function test_invite_only_requires_invitation_and_invitation_notifies_user(): void
    {
        [$clan, $owner] = $this->clan(['visibility' => 'public', 'join_policy' => 'invite_only']);
        $invitee = User::factory()->create();
        Sanctum::actingAs($invitee);
        $this->postJson("/api/v1/clans/{$clan->id}/join")
            ->assertForbidden()->assertJsonPath('code', 'CLAN_INVITE_REQUIRED');

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clans/{$clan->id}/invitations", ['user_id' => $invitee->id])
            ->assertCreated()->assertJsonPath('data.status', 'invited');
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $invitee->id, 'type' => ClanInvitationReceived::class,
        ]);

        Sanctum::actingAs($invitee);
        $this->postJson("/api/v1/clans/{$clan->id}/join")
            ->assertOk()->assertJsonPath('data.status', 'active');
    }

    public function test_private_clan_is_hidden_from_non_members_but_invitee_can_view(): void
    {
        [$clan, $owner] = $this->clan(['visibility' => 'private', 'join_policy' => 'invite_only']);
        $invitee = User::factory()->create();
        Sanctum::actingAs($invitee);
        $this->getJson("/api/v1/clans/{$clan->id}")->assertNotFound();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clans/{$clan->id}/invitations", ['user_id' => $invitee->id])->assertCreated();
        Sanctum::actingAs($invitee);
        $this->getJson("/api/v1/clans/{$clan->id}")->assertOk()->assertJsonPath('data.viewer_membership', 'invited');
    }

    public function test_owner_cannot_leave_and_can_remove_or_ban_member(): void
    {
        [$clan, $owner] = $this->clan(['join_policy' => 'open']);
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clans/{$clan->id}/leave")
            ->assertUnprocessable()->assertJsonPath('code', 'CLAN_OWNER_CANNOT_LEAVE');

        $memberUser = User::factory()->create();
        Sanctum::actingAs($memberUser);
        $memberId = $this->postJson("/api/v1/clans/{$clan->id}/join")->json('data.id');
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/clans/{$clan->id}/members/{$memberId}", ['ban' => true])
            ->assertOk()->assertJsonPath('data.status', 'banned');
        Sanctum::actingAs($memberUser);
        $this->postJson("/api/v1/clans/{$clan->id}/join")
            ->assertForbidden()->assertJsonPath('code', 'CLAN_BANNED');
    }

    public function test_role_permission_can_delegate_inviting_but_member_cannot(): void
    {
        [$clan, $owner] = $this->clan();
        $leader = User::factory()->create();
        $member = User::factory()->create();
        $leaderRole = $clan->roles()->where('slug', 'leader')->firstOrFail();
        $memberRole = $clan->roles()->where('slug', 'member')->firstOrFail();
        ClanMember::create(['clan_id' => $clan->id, 'user_id' => $leader->id, 'clan_role_id' => $leaderRole->id, 'status' => 'active', 'joined_at' => now()]);
        ClanMember::create(['clan_id' => $clan->id, 'user_id' => $member->id, 'clan_role_id' => $memberRole->id, 'status' => 'active', 'joined_at' => now()]);
        $target = User::factory()->create();

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clans/{$clan->id}/invitations", ['user_id' => $target->id])
            ->assertForbidden()->assertJsonPath('code', 'CLAN_PERMISSION_DENIED');
        Sanctum::actingAs($leader);
        $this->postJson("/api/v1/clans/{$clan->id}/invitations", ['user_id' => $target->id])
            ->assertCreated();
        $this->assertNotNull($owner);
    }

    private function clan(array $overrides = []): array
    {
        $owner = User::factory()->create();
        $attributes = [...[
            'name' => 'Hanoi Squad', 'slug' => fake()->unique()->slug(),
            'visibility' => 'public', 'join_policy' => 'approval', 'status' => 'active',
        ], ...$overrides];
        $clan = app(CreateClan::class)->handle($owner, $attributes);

        return [$clan, $owner];
    }
}

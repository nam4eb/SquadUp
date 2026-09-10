<?php

namespace Tests\Feature\Api\V1;

use App\Actions\Clans\CreateClan;
use App\Models\ClanMember;
use App\Models\User;
use App\Notifications\ClanRoleChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClanRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_and_update_custom_role_with_whitelisted_permissions(): void
    {
        [$clan, $owner] = $this->clan();
        Sanctum::actingAs($owner);
        $roleId = $this->postJson("/api/v1/clans/{$clan->id}/roles", [
            'name' => 'Event Organizer', 'slug' => 'event-organizer',
            'permissions' => ['create_events', 'manage_events'],
        ])->assertCreated()
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.permissions.0', 'create_events')
            ->json('data.id');

        $this->patchJson("/api/v1/clans/{$clan->id}/roles/{$roleId}", [
            'name' => 'Event Captain',
            'permissions' => ['create_events', 'invite_members'],
        ])->assertOk()->assertJsonPath('data.name', 'Event Captain');
        $this->assertDatabaseHas('clan_role_permissions', [
            'clan_role_id' => $roleId, 'permission' => 'invite_members',
        ]);
        $this->postJson("/api/v1/clans/{$clan->id}/roles", [
            'name' => 'Danger', 'slug' => 'danger', 'permissions' => ['super_admin'],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
    }

    public function test_regular_member_cannot_manage_roles(): void
    {
        [$clan] = $this->clan();
        $member = User::factory()->create();
        $this->activeMember($clan, $member, 'member');
        Sanctum::actingAs($member);

        $this->postJson("/api/v1/clans/{$clan->id}/roles", [
            'name' => 'Captain', 'slug' => 'captain', 'permissions' => [],
        ])->assertForbidden()->assertJsonPath('code', 'CLAN_PERMISSION_DENIED');
    }

    public function test_role_assignment_notifies_member_and_owner_role_is_restricted(): void
    {
        [$clan, $owner] = $this->clan();
        $member = User::factory()->create();
        $membership = $this->activeMember($clan, $member, 'member');
        $moderatorRole = $clan->roles()->where('slug', 'moderator')->firstOrFail();
        $ownerRole = $clan->roles()->where('slug', 'owner')->firstOrFail();
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/clans/{$clan->id}/members/{$membership->id}/role", [
            'role_id' => $moderatorRole->id,
        ])->assertOk()->assertJsonPath('data.role.slug', 'moderator');
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $member->id, 'type' => ClanRoleChanged::class,
        ]);
        $this->patchJson("/api/v1/clans/{$clan->id}/members/{$membership->id}/role", [
            'role_id' => $ownerRole->id,
        ])->assertUnprocessable()->assertJsonPath('code', 'CLAN_OWNER_ROLE_RESTRICTED');
    }

    public function test_system_role_and_role_in_use_cannot_be_deleted(): void
    {
        [$clan, $owner] = $this->clan();
        Sanctum::actingAs($owner);
        $memberRole = $clan->roles()->where('slug', 'member')->firstOrFail();
        $this->deleteJson("/api/v1/clans/{$clan->id}/roles/{$memberRole->id}")
            ->assertUnprocessable()->assertJsonPath('code', 'CLAN_SYSTEM_ROLE_IMMUTABLE');

        $customId = $this->postJson("/api/v1/clans/{$clan->id}/roles", [
            'name' => 'Captain', 'slug' => 'captain', 'permissions' => ['create_events'],
        ])->json('data.id');
        $member = User::factory()->create();
        ClanMember::create([
            'clan_id' => $clan->id, 'user_id' => $member->id,
            'clan_role_id' => $customId, 'status' => 'active', 'joined_at' => now(),
        ]);
        $this->deleteJson("/api/v1/clans/{$clan->id}/roles/{$customId}")
            ->assertUnprocessable()->assertJsonPath('code', 'CLAN_ROLE_IN_USE');
    }

    public function test_owner_transfer_requires_active_member_and_preserves_single_owner_role(): void
    {
        [$clan, $owner] = $this->clan();
        $newOwner = User::factory()->create();
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clans/{$clan->id}/transfer-ownership", ['user_id' => $newOwner->id])
            ->assertUnprocessable()->assertJsonPath('code', 'NEW_CLAN_OWNER_NOT_ACTIVE');

        $this->activeMember($clan, $newOwner, 'member');
        $this->postJson("/api/v1/clans/{$clan->id}/transfer-ownership", ['user_id' => $newOwner->id])
            ->assertOk()->assertJsonPath('data.owner.id', $newOwner->id);
        $ownerRoleId = $clan->roles()->where('slug', 'owner')->value('id');
        $this->assertSame(1, ClanMember::where('clan_id', $clan->id)->where('clan_role_id', $ownerRoleId)->count());
        $this->postJson("/api/v1/clans/{$clan->id}/transfer-ownership", ['user_id' => $owner->id])
            ->assertForbidden()->assertJsonPath('code', 'NOT_CLAN_OWNER');
    }

    private function clan(): array
    {
        $owner = User::factory()->create();
        $clan = app(CreateClan::class)->handle($owner, [
            'name' => 'Role Test Clan', 'slug' => fake()->unique()->slug(),
            'visibility' => 'public', 'join_policy' => 'approval', 'status' => 'active',
        ]);

        return [$clan, $owner];
    }

    private function activeMember($clan, User $user, string $roleSlug): ClanMember
    {
        return ClanMember::create([
            'clan_id' => $clan->id, 'user_id' => $user->id,
            'clan_role_id' => $clan->roles()->where('slug', $roleSlug)->value('id'),
            'status' => 'active', 'joined_at' => now(),
        ]);
    }
}

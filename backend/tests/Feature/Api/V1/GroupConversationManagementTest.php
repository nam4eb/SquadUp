<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupConversationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_profile_add_member_and_avatar(): void
    {
        Storage::fake('public');
        [$id, $owner] = $this->group();
        $newMember = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/conversations/{$id}/members", ['user_id' => $newMember->id])
            ->assertOk()->assertJsonCount(3, 'data.members');
        $this->post("/api/v1/conversations/{$id}/group", [
            '_method' => 'PATCH', 'name' => 'Renamed squad', 'description' => 'Updated',
            'avatar' => UploadedFile::fake()->image('group.png'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.name', 'Renamed squad')
            ->assertJsonPath('data.current_member_role', 'owner')
            ->assertJsonPath('data.description', 'Updated');
        $this->assertDatabaseHas('conversations', ['id' => $id, 'name' => 'Renamed squad']);
    }

    public function test_owner_can_promote_admin_and_transfer_ownership(): void
    {
        [$id, $owner, $member] = $this->group();
        Sanctum::actingAs($owner);
        $members = $this->getJson("/api/v1/conversations/{$id}")->json('data.members');
        $memberId = collect($members)->firstWhere('user.id', $member->id)['id'];
        $this->patchJson("/api/v1/conversations/{$id}/members/{$memberId}/role", ['role' => 'admin'])
            ->assertOk()->assertJsonFragment(['id' => $memberId, 'role' => 'admin']);
        $this->postJson("/api/v1/conversations/{$id}/transfer-ownership/{$memberId}")
            ->assertOk()->assertJsonPath('data.current_member_role', 'admin');
        $this->assertDatabaseHas('conversation_members', [
            'conversation_id' => $id, 'user_id' => $member->id, 'role' => 'owner',
        ]);
    }

    public function test_admin_can_remove_member_but_cannot_remove_owner(): void
    {
        [$id, $owner, $admin] = $this->group();
        $extra = User::factory()->create();
        Sanctum::actingAs($owner);
        $members = $this->getJson("/api/v1/conversations/{$id}")->json('data.members');
        $adminMembership = collect($members)->firstWhere('user.id', $admin->id)['id'];
        $this->patchJson("/api/v1/conversations/{$id}/members/{$adminMembership}/role", ['role' => 'admin'])->assertOk();
        $this->postJson("/api/v1/conversations/{$id}/members", ['user_id' => $extra->id])->assertOk();
        $members = $this->getJson("/api/v1/conversations/{$id}")->json('data.members');
        $ownerMembership = collect($members)->firstWhere('user.id', $owner->id)['id'];
        $extraMembership = collect($members)->firstWhere('user.id', $extra->id)['id'];

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/conversations/{$id}/members/{$extraMembership}")->assertOk();
        $this->deleteJson("/api/v1/conversations/{$id}/members/{$ownerMembership}")
            ->assertUnprocessable()->assertJsonPath('code', 'GROUP_OWNER_PROTECTED');
    }

    public function test_member_can_leave_while_owner_must_transfer_first(): void
    {
        [$id, $owner, $member] = $this->group();
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/conversations/{$id}/leave")
            ->assertUnprocessable()->assertJsonPath('code', 'GROUP_OWNER_PROTECTED');
        Sanctum::actingAs($member);
        $this->postJson("/api/v1/conversations/{$id}/leave")->assertOk();
        $this->getJson("/api/v1/conversations/{$id}")->assertNotFound();
    }

    private function group(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/v1/conversations/group', [
            'name' => 'Managed group', 'member_ids' => [$member->id],
        ])->json('data.id');

        return [$id, $owner, $member];
    }
}

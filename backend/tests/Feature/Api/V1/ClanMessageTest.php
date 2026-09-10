<?php

namespace Tests\Feature\Api\V1;

use App\Actions\Clans\CreateClan;
use App\Models\ClanMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClanMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_members_can_send_and_cursor_list_clan_messages(): void
    {
        [$clan, $owner] = $this->clan();
        $member = User::factory()->create();
        $this->activeMember($clan, $member, 'member');
        Sanctum::actingAs($member);

        $messageId = $this->postJson("/api/v1/clans/{$clan->id}/messages", ['body' => '  Hello clan!  '])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Hello clan!')
            ->assertJsonPath('data.sender.id', $member->id)
            ->assertJsonPath('data.is_mine', true)
            ->json('data.id');
        $this->getJson("/api/v1/clans/{$clan->id}/messages?limit=1")
            ->assertOk()->assertJsonPath('data.0.id', $messageId)
            ->assertJsonStructure(['data', 'meta', 'links']);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/clans/{$clan->id}/messages")
            ->assertOk()->assertJsonPath('data.0.is_mine', false);
    }

    public function test_outsiders_and_pending_members_cannot_use_clan_chat(): void
    {
        [$clan] = $this->clan();
        $outsider = User::factory()->create();
        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/clans/{$clan->id}/messages")->assertForbidden();

        ClanMember::create([
            'clan_id' => $clan->id, 'user_id' => $outsider->id,
            'clan_role_id' => $clan->roles()->where('slug', 'member')->value('id'),
            'status' => 'requested',
        ]);
        $this->postJson("/api/v1/clans/{$clan->id}/messages", ['body' => 'Not yet'])
            ->assertForbidden()->assertJsonPath('code', 'CLAN_MEMBERSHIP_REQUIRED');
    }

    public function test_sender_and_chat_manager_can_delete_but_regular_member_cannot(): void
    {
        [$clan, $owner] = $this->clan();
        $sender = User::factory()->create();
        $regular = User::factory()->create();
        $moderator = User::factory()->create();
        $this->activeMember($clan, $sender, 'member');
        $this->activeMember($clan, $regular, 'member');
        $this->activeMember($clan, $moderator, 'moderator');
        Sanctum::actingAs($sender);
        $messageId = $this->postJson("/api/v1/clans/{$clan->id}/messages", ['body' => 'Moderate me'])->json('data.id');

        Sanctum::actingAs($regular);
        $this->deleteJson("/api/v1/clans/{$clan->id}/messages/{$messageId}")
            ->assertForbidden()->assertJsonPath('code', 'CLAN_PERMISSION_DENIED');
        Sanctum::actingAs($moderator);
        $this->deleteJson("/api/v1/clans/{$clan->id}/messages/{$messageId}")->assertOk();
        $this->assertSoftDeleted('messages', ['id' => $messageId]);
        $this->getJson("/api/v1/clans/{$clan->id}/messages")
            ->assertOk()->assertJsonPath('data.0.body', null);

        Sanctum::actingAs($owner);
        $this->assertDatabaseHas('conversations', [
            'clan_id' => $clan->id, 'type' => 'clan', 'created_by' => $owner->id,
        ]);
    }

    public function test_clan_chat_supports_media_mentions_and_notifications(): void
    {
        Storage::fake('public');
        [$clan] = $this->clan();
        $sender = User::factory()->create();
        $mentioned = User::factory()->create();
        $this->activeMember($clan, $sender, 'member');
        $this->activeMember($clan, $mentioned, 'member');
        Sanctum::actingAs($sender);

        $this->post("/api/v1/clans/{$clan->id}/messages", [
            'body' => "Look @{$mentioned->username}",
            'file' => UploadedFile::fake()->image('clan.png'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.mentions.0.user_id', $mentioned->id)
            ->assertJsonPath('data.media.0.original_name', 'clan.png');
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $mentioned->id]);
    }

    private function clan(): array
    {
        $owner = User::factory()->create();
        $clan = app(CreateClan::class)->handle($owner, [
            'name' => 'Chat Clan', 'slug' => fake()->unique()->slug(),
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

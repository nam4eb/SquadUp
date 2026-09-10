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

class ClanProfileAndAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_profile_and_replace_media(): void
    {
        Storage::fake('public');
        [$clan, $owner] = $this->clan();
        Sanctum::actingAs($owner);

        $first = $this->patch("/api/v1/clans/{$clan->id}", [
            'name' => 'Updated Squad', 'avatar' => UploadedFile::fake()->image('avatar.png'),
            'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 400),
        ])->assertOk()->assertJsonPath('data.name', 'Updated Squad');
        $this->assertStringContainsString('/storage/clans/', $first->json('data.avatar_url'));
        $oldAvatar = $clan->fresh()->avatar_path;

        $this->patch("/api/v1/clans/{$clan->id}", [
            'avatar' => UploadedFile::fake()->image('new.png'),
        ])->assertOk();
        Storage::disk('public')->assertMissing($oldAvatar);
    }

    public function test_announcements_are_member_only_and_content_managers_can_manage_them(): void
    {
        [$clan, $owner] = $this->clan();
        $member = User::factory()->create();
        $memberRole = $clan->roles()->where('slug', 'member')->firstOrFail();
        ClanMember::create([
            'clan_id' => $clan->id, 'user_id' => $member->id,
            'clan_role_id' => $memberRole->id, 'status' => 'active', 'joined_at' => now(),
        ]);
        Sanctum::actingAs($owner);
        $id = $this->postJson("/api/v1/clans/{$clan->id}/announcements", [
            'title' => 'Tournament', 'body' => 'Registration is open.', 'is_pinned' => true,
        ])->assertCreated()->assertJsonPath('data.can_manage', true)->json('data.id');

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clans/{$clan->id}/announcements")
            ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.can_manage', false);
        $this->patchJson("/api/v1/clans/{$clan->id}/announcements/{$id}", ['title' => 'No'])
            ->assertForbidden();
    }

    private function clan(): array
    {
        $owner = User::factory()->create();
        $clan = app(CreateClan::class)->handle($owner, [
            'name' => 'Hanoi Squad', 'slug' => fake()->unique()->slug(),
            'visibility' => 'public', 'join_policy' => 'open', 'status' => 'active',
        ]);

        return [$clan, $owner];
    }
}

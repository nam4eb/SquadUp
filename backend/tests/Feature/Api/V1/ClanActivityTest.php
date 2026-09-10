<?php

namespace Tests\Feature\Api\V1;

use App\Actions\Clans\CreateClan;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityTopic;
use App\Models\ClanMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClanActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_with_permission_can_create_clan_activity_and_members_can_view_it(): void
    {
        [$clan, $owner] = $this->clan();
        $member = User::factory()->create();
        $this->activeMember($clan, $member, 'member');
        [$category, $topic] = $this->taxonomy();
        Sanctum::actingAs($member);

        $activityId = $this->postJson('/api/v1/activities', $this->payload($category, $topic, $clan->id))
            ->assertCreated()
            ->assertJsonPath('data.clan.id', $clan->id)
            ->json('data.id');
        $this->assertDatabaseHas('clan_events', [
            'clan_id' => $clan->id,
            'activity_id' => $activityId,
            'created_by' => $member->id,
        ]);
        $this->getJson("/api/v1/clans/{$clan->id}/activities")
            ->assertOk()->assertJsonPath('data.0.id', $activityId);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/activities/{$activityId}")->assertNotFound();
        $this->getJson("/api/v1/clans/{$clan->id}/activities")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/activities/{$activityId}")->assertOk();
        $this->getJson('/api/v1/activities')
            ->assertOk()->assertJsonPath('data.0.id', $activityId);
        $this->patchJson("/api/v1/activities/{$activityId}", ['title' => 'Managed by clan owner'])
            ->assertOk()->assertJsonPath('data.title', 'Managed by clan owner');
        $this->getJson("/api/v1/clans/{$clan->id}/leaderboard")
            ->assertOk()->assertJsonPath('data.0.score', 1);
        $this->assertDatabaseCount('clan_leaderboard_snapshots', 1);
        $this->patchJson("/api/v1/activities/{$activityId}/status", ['status' => 'ongoing'])
            ->assertOk()->assertJsonPath('data.status', 'ongoing');
        $participantId = Activity::findOrFail($activityId)->participants()
            ->where('user_id', $member->id)->value('id');
        $this->patchJson("/api/v1/activities/{$activityId}/participants/{$participantId}/attendance", [
            'status' => 'attended',
        ])->assertOk()
            ->assertJsonPath('data.status', 'attended')
            ->assertJsonPath('data.user.id', $member->id);
        $this->assertDatabaseCount('clan_leaderboard_snapshots', 0);
        $this->getJson("/api/v1/clans/{$clan->id}/leaderboard?metric=events_attended")
            ->assertOk()->assertJsonPath('data.0.user_id', $member->id);
    }

    public function test_member_without_create_events_permission_is_denied(): void
    {
        [$clan] = $this->clan();
        $moderator = User::factory()->create();
        $this->activeMember($clan, $moderator, 'moderator');
        [$category, $topic] = $this->taxonomy();
        Sanctum::actingAs($moderator);

        $this->postJson('/api/v1/activities', $this->payload($category, $topic, $clan->id))
            ->assertForbidden()->assertJsonPath('code', 'CLAN_PERMISSION_DENIED');
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_statistics_and_dynamic_leaderboard_are_available_and_permission_scoped(): void
    {
        [$clan, $owner] = $this->clan();
        $member = User::factory()->create();
        $this->activeMember($clan, $member, 'member');
        [$category, $topic] = $this->taxonomy();
        Sanctum::actingAs($owner);
        $activityId = $this->postJson('/api/v1/activities', $this->payload($category, $topic, $clan->id))->json('data.id');
        Activity::whereKey($activityId)->update(['status' => 'completed']);
        Activity::findOrFail($activityId)->participants()->create([
            'user_id' => $member->id, 'role' => 'member', 'status' => 'joined', 'joined_at' => now(),
        ]);
        Activity::findOrFail($activityId)->participants()->where('user_id', $owner->id)->update([
            'status' => 'attended', 'attendance_marked_at' => now(), 'attendance_marked_by' => $owner->id,
        ]);
        Activity::findOrFail($activityId)->participants()->where('user_id', $member->id)->update([
            'status' => 'absent', 'attendance_marked_at' => now(), 'attendance_marked_by' => $owner->id,
        ]);

        $this->getJson("/api/v1/clans/{$clan->id}/statistics")
            ->assertOk()
            ->assertJsonPath('data.total_events', 1)
            ->assertJsonPath('data.completed_events', 1)
            ->assertJsonPath('data.active_members', 2)
            ->assertJsonPath('data.attendance.attended', 1)
            ->assertJsonPath('data.attendance.absent', 1)
            ->assertJsonPath('data.attendance.rate', 50)
            ->assertJsonPath('data.by_topic.0.id', $topic->id);
        $this->getJson("/api/v1/clans/{$clan->id}/leaderboard?metric=events_joined&period=monthly&topic_id={$topic->id}")
            ->assertOk()
            ->assertJsonPath('data.0.rank', 1)
            ->assertJsonPath('data.0.score', 1)
            ->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/clans/{$clan->id}/leaderboard?metric=events_attended")
            ->assertOk()->assertJsonPath('data.0.display_name', $owner->display_name)
            ->assertJsonPath('data.0.score', 1);

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clans/{$clan->id}/statistics")
            ->assertForbidden()->assertJsonPath('code', 'CLAN_PERMISSION_DENIED');
        $this->getJson("/api/v1/clans/{$clan->id}/leaderboard")->assertOk();
    }

    private function clan(): array
    {
        $owner = User::factory()->create();
        $clan = app(CreateClan::class)->handle($owner, [
            'name' => 'Activity Clan', 'slug' => fake()->unique()->slug(),
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

    private function taxonomy(): array
    {
        $category = ActivityCategory::create(['name' => 'Sports', 'slug' => 'sports']);
        $topic = ActivityTopic::create(['category_id' => $category->id, 'name' => 'Badminton', 'slug' => 'badminton']);

        return [$category, $topic];
    }

    private function payload(ActivityCategory $category, ActivityTopic $topic, string $clanId): array
    {
        return [
            'category_id' => $category->id, 'topic_id' => $topic->id, 'clan_id' => $clanId,
            'title' => 'Clan Badminton', 'starts_at' => now()->addDay()->toISOString(),
            'timezone' => 'Asia/Bangkok', 'location_name' => 'Court',
            'min_participants' => 1, 'max_participants' => 10, 'visibility' => 'clan',
        ];
    }
}

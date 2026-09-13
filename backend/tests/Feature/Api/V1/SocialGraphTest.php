<?php

namespace Tests\Feature\Api\V1;

use App\Actions\Friends\AcceptFriendRequest;
use App\Actions\Friends\SendFriendRequest;
use App\Models\Block;
use App\Models\Friendship;
use App\Models\User;
use App\Models\Sport;
use App\Support\UserPair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SocialGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestions_rank_mutual_friends_and_exclude_existing_or_blocked_users(): void
    {
        $user = User::factory()->create(['location' => 'Bangkok']);
        $friend = User::factory()->create();
        $candidate = User::factory()->create(['location' => 'Bangkok']);
        $blocked = User::factory()->create();
        foreach ([[$user, $friend], [$friend, $candidate]] as [$first, $second]) {
            [$low, $high] = strcmp($first->id, $second->id) < 0
                ? [$first->id, $second->id] : [$second->id, $first->id];
            Friendship::create([
                'user_low_id' => $low, 'user_high_id' => $high,
                'pair_key' => UserPair::key($first->id, $second->id), 'accepted_at' => now(),
            ]);
        }
        Block::create(['blocker_id' => $user->id, 'blocked_id' => $blocked->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users/suggestions')->assertOk()
            ->assertJsonPath('data.0.id', $candidate->id)
            ->assertJsonPath('data.0.mutual_friends_count', 1)
            ->assertJsonPath('data.0.same_location', true)
            ->assertJsonMissing(['id' => $friend->id])
            ->assertJsonMissing(['id' => $blocked->id]);
    }

    public function test_authenticated_user_can_search_active_users_without_private_fields(): void
    {
        $viewer = User::factory()->create();
        $match = User::factory()->create(['username' => 'badminton_fan', 'display_name' => 'Badminton Fan']);
        User::factory()->create(['username' => 'football_fan']);
        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/users?query=badminton')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('data.0.email', null);
    }

    public function test_people_discovery_filters_by_sport_skill_and_city(): void
    {
        $viewer = User::factory()->create();
        $sport = Sport::create(['name' => 'Pickleball', 'slug' => 'pickleball']);
        $match = User::factory()->create(['default_city' => 'Bangkok']);
        $other = User::factory()->create(['default_city' => 'Bangkok']);
        $match->sportProfiles()->create([
            'sport_id' => $sport->id, 'self_declared_level' => 'intermediate', 'skill_rating' => 1350,
        ]);
        $other->sportProfiles()->create([
            'sport_id' => $sport->id, 'self_declared_level' => 'beginner', 'skill_rating' => 800,
        ]);
        Sanctum::actingAs($viewer);

        $this->getJson("/api/v1/users?sport_id={$sport->id}&skill_min=1200&skill_max=1500&city=bangkok")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('data.0.sports.0.sport.slug', 'pickleball')
            ->assertJsonPath('data.0.sports.0.skill_rating', 1350);
    }

    public function test_user_can_send_friend_request_and_duplicates_are_prevented(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        Sanctum::actingAs($sender);

        $this->postJson('/api/v1/friend-requests', ['receiver_id' => $receiver->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.receiver.id', $receiver->id);

        $this->postJson('/api/v1/friend-requests', ['receiver_id' => $receiver->id])
            ->assertConflict()
            ->assertJsonPath('code', 'FRIEND_REQUEST_PENDING');
        $this->assertDatabaseCount('friend_requests', 1);
    }

    public function test_user_cannot_send_request_to_self(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/friend-requests', ['receiver_id' => $user->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'SELF_FRIEND_REQUEST');
    }

    public function test_recipient_can_accept_request_and_friendship_is_unique(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $friendRequest = app(SendFriendRequest::class)->handle($sender, $receiver);
        Sanctum::actingAs($receiver);

        $this->patchJson("/api/v1/friend-requests/{$friendRequest->id}/accept")
            ->assertCreated()
            ->assertJsonPath('data.friend.id', $sender->id);

        $this->assertDatabaseCount('friendships', 1);
        $this->assertDatabaseHas('friend_requests', ['id' => $friendRequest->id, 'status' => 'accepted']);
    }

    public function test_sender_cannot_accept_own_request(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $friendRequest = app(SendFriendRequest::class)->handle($sender, $receiver);
        Sanctum::actingAs($sender);

        $this->patchJson("/api/v1/friend-requests/{$friendRequest->id}/accept")
            ->assertForbidden()
            ->assertJsonPath('code', 'NOT_REQUEST_RECIPIENT');
    }

    public function test_recipient_can_reject_and_sender_can_cancel(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $action = app(SendFriendRequest::class);

        $rejected = $action->handle($sender, $receiver);
        Sanctum::actingAs($receiver);
        $this->patchJson("/api/v1/friend-requests/{$rejected->id}/reject")
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $cancelled = $action->handle($sender, $receiver);
        Sanctum::actingAs($sender);
        $this->patchJson("/api/v1/friend-requests/{$cancelled->id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_friends_can_be_listed_and_removed(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $request = app(SendFriendRequest::class)->handle($user, $friend);
        app(AcceptFriendRequest::class)->handle($request, $friend);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/friends')->assertOk()->assertJsonPath('data.0.friend.id', $friend->id);
        $this->deleteJson("/api/v1/friends/{$friend->id}")->assertOk();
        $this->assertDatabaseCount('friendships', 0);
    }
}

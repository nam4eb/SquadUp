<?php

namespace Tests\Feature\Api\V1;

use App\Actions\Friends\AcceptFriendRequest;
use App\Actions\Friends\SendFriendRequest;
use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlockAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_friend_request_and_acceptance_create_notifications(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $request = app(SendFriendRequest::class)->handle($sender, $receiver);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $receiver->id,
            'type' => FriendRequestReceived::class,
        ]);

        app(AcceptFriendRequest::class)->handle($request, $receiver);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $sender->id,
            'type' => FriendRequestAccepted::class,
        ]);
    }

    public function test_block_removes_friendship_and_hides_users_from_each_other(): void
    {
        $first = User::factory()->create(['username' => 'first_player']);
        $second = User::factory()->create(['username' => 'second_player']);
        $request = app(SendFriendRequest::class)->handle($first, $second);
        app(AcceptFriendRequest::class)->handle($request, $second);
        Sanctum::actingAs($first);

        $this->postJson("/api/v1/blocks/{$second->id}")
            ->assertCreated()->assertJsonPath('data.user.id', $second->id);

        $this->assertDatabaseCount('friendships', 0);
        $this->getJson('/api/v1/users?query=second_player')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/users/{$second->id}")->assertNotFound();
    }

    public function test_block_transitions_pending_request_and_prevents_new_request(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $request = app(SendFriendRequest::class)->handle($sender, $receiver);
        Sanctum::actingAs($receiver);

        $this->postJson("/api/v1/blocks/{$sender->id}")->assertCreated();
        $this->assertDatabaseHas('friend_requests', ['id' => $request->id, 'status' => 'blocked']);

        Sanctum::actingAs($sender);
        $this->postJson('/api/v1/friend-requests', ['receiver_id' => $receiver->id])
            ->assertForbidden()->assertJsonPath('code', 'USER_BLOCKED');
    }

    public function test_user_can_unblock_and_send_a_new_request(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/blocks/{$target->id}")->assertCreated();
        $this->deleteJson("/api/v1/blocks/{$target->id}")->assertOk();
        $this->postJson('/api/v1/friend-requests', ['receiver_id' => $target->id])->assertCreated();
    }

    public function test_user_can_list_and_mark_own_notification_as_read(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        app(SendFriendRequest::class)->handle($sender, $receiver);
        Sanctum::actingAs($receiver);

        $response = $this->getJson('/api/v1/notifications')->assertOk();
        $notificationId = $response->json('data.0.id');
        $this->patchJson("/api/v1/notifications/{$notificationId}/read")->assertOk();
        $this->assertDatabaseMissing('notifications', ['id' => $notificationId, 'read_at' => null]);
    }

    public function test_user_can_filter_unread_and_mark_all_notifications_as_read(): void
    {
        $firstSender = User::factory()->create();
        $secondSender = User::factory()->create();
        $receiver = User::factory()->create();
        app(SendFriendRequest::class)->handle($firstSender, $receiver);
        app(SendFriendRequest::class)->handle($secondSender, $receiver);
        Sanctum::actingAs($receiver);

        $notificationId = $receiver->notifications()->firstOrFail()->id;
        $this->patchJson("/api/v1/notifications/{$notificationId}/read")->assertOk();
        $this->getJson('/api/v1/notifications?unread=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->getJson('/api/v1/notifications?unread=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}

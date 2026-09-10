<?php

namespace Tests\Feature\Api\V1;

use App\Events\ConversationSignal;
use App\Events\MessageChanged;
use App\Events\PresenceChanged;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealtimeChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_mutations_and_typing_dispatch_realtime_events(): void
    {
        Event::fake([MessageChanged::class, ConversationSignal::class]);
        [$conversation, $sender, $recipient] = $this->direct();
        Sanctum::actingAs($sender);
        $messageId = $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Realtime hello',
        ])->assertCreated()->json('data.id');
        Event::assertDispatched(MessageChanged::class, fn ($event) => $event->action === 'created');

        $this->patchJson("/api/v1/conversations/{$conversation->id}/messages/{$messageId}", [
            'body' => 'Edited live',
        ])->assertOk();
        $this->postJson("/api/v1/conversations/{$conversation->id}/typing", ['typing' => true])->assertOk();
        Event::assertDispatched(MessageChanged::class, fn ($event) => $event->action === 'updated');
        Event::assertDispatched(ConversationSignal::class, fn ($event) => $event->event === 'conversation.typing' && $event->payload['typing'] === true);

        Sanctum::actingAs($recipient);
        $this->postJson("/api/v1/conversations/{$conversation->id}/messages/{$messageId}/read")->assertOk();
        $this->postJson("/api/v1/conversations/{$conversation->id}/messages/{$messageId}/reactions", ['reaction' => '🔥'])->assertOk();
        Event::assertDispatched(ConversationSignal::class, fn ($event) => $event->event === 'message.read');
        Event::assertDispatched(ConversationSignal::class, fn ($event) => $event->event === 'message.reaction');
    }

    public function test_presence_heartbeat_away_and_offline_use_ephemeral_store(): void
    {
        Event::fake([PresenceChanged::class]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/presence/heartbeat', ['status' => 'online'])
            ->assertOk()->assertJsonPath('data.status', 'online');
        $this->getJson("/api/v1/presence/{$user->id}")
            ->assertOk()->assertJsonPath('data.status', 'online');
        $this->postJson('/api/v1/presence/heartbeat', ['status' => 'away'])
            ->assertOk()->assertJsonPath('data.status', 'away');
        $this->deleteJson('/api/v1/presence')
            ->assertOk()->assertJsonPath('data.status', 'offline');
        $this->getJson("/api/v1/presence/{$user->id}")
            ->assertOk()->assertJsonPath('data.status', 'offline');
        Event::assertDispatchedTimes(PresenceChanged::class, 3);
    }

    public function test_private_conversation_channel_authorizes_members_only(): void
    {
        [$conversation, $member] = $this->direct();
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');
        Sanctum::actingAs($member);
        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$conversation->id}",
        ])->assertOk()->assertJsonStructure(['auth']);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$conversation->id}",
        ])->assertForbidden();
    }

    private function direct(): array
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        Sanctum::actingAs($sender);
        $id = $this->postJson('/api/v1/conversations/direct', ['user_id' => $recipient->id])->json('data.id');

        return [Conversation::findOrFail($id), $sender, $recipient];
    }
}

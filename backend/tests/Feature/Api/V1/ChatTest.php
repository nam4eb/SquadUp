<?php

namespace Tests\Feature\Api\V1;

use App\Models\Block;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_conversation_is_idempotent_and_block_aware(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        Sanctum::actingAs($first);
        $id = $this->postJson('/api/v1/conversations/direct', ['user_id' => $second->id])
            ->assertCreated()->assertJsonPath('data.type', 'direct')
            ->assertJsonPath('data.name', $second->display_name)->json('data.id');
        $this->postJson('/api/v1/conversations/direct', ['user_id' => $second->id])
            ->assertOk()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_members', 2);

        $third = User::factory()->create();
        Block::create(['blocker_id' => $third->id, 'blocked_id' => $first->id]);
        $this->postJson('/api/v1/conversations/direct', ['user_id' => $third->id])
            ->assertForbidden()->assertJsonPath('code', 'MESSAGING_BLOCKED');
    }

    public function test_members_can_send_edit_reply_react_and_mark_read(): void
    {
        [$conversationId, $sender, $recipient] = $this->direct();
        Sanctum::actingAs($sender);
        $firstId = $this->postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => 'Hello'])
            ->assertCreated()->assertJsonPath('data.body', 'Hello')->json('data.id');
        $this->patchJson("/api/v1/conversations/{$conversationId}/messages/{$firstId}", ['body' => 'Hello edited'])
            ->assertOk()->assertJsonPath('data.body', 'Hello edited')
            ->assertJsonPath('data.is_mine', true);

        Sanctum::actingAs($recipient);
        $replyId = $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => 'Hi', 'reply_to_id' => $firstId,
        ])->assertCreated()->assertJsonPath('data.reply_to_id', $firstId)->json('data.id');
        $this->postJson("/api/v1/conversations/{$conversationId}/messages/{$firstId}/reactions", ['reaction' => '👍'])
            ->assertOk();
        $this->postJson("/api/v1/conversations/{$conversationId}/messages/{$firstId}/read")->assertOk();
        $this->getJson("/api/v1/conversations/{$conversationId}/messages?limit=10")
            ->assertOk()->assertJsonPath('data.0.id', $replyId)
            ->assertJsonPath('data.1.reactions.0.reaction', '👍')
            ->assertJsonPath('data.1.reactions.0.count', 1)
            ->assertJsonPath('data.1.read_count', 1)
            ->assertJsonStructure(['data', 'meta', 'links']);
        $this->deleteJson("/api/v1/conversations/{$conversationId}/messages/{$firstId}/reactions", ['reaction' => '👍'])
            ->assertOk();
    }

    public function test_group_owner_can_moderate_and_outsider_cannot_access(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        Sanctum::actingAs($owner);
        $conversationId = $this->postJson('/api/v1/conversations/group', [
            'name' => 'Weekend Squad', 'member_ids' => [$member->id],
        ])->assertCreated()->assertJsonCount(2, 'data.members')->json('data.id');
        Sanctum::actingAs($member);
        $messageId = $this->postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => 'Group hello'])
            ->json('data.id');
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/conversations/{$conversationId}/messages/{$messageId}")->assertOk();

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/conversations/{$conversationId}")
            ->assertNotFound()->assertJsonPath('code', 'CONVERSATION_NOT_FOUND');
    }

    public function test_conversation_list_keeps_group_payload_small_but_preserves_direct_peer(): void
    {
        $owner = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/conversations/group', [
            'name' => 'Payload Test', 'member_ids' => [$first->id, $second->id],
        ])->assertCreated();
        $this->postJson('/api/v1/conversations/direct', ['user_id' => $first->id])->assertCreated();

        $items = collect($this->getJson('/api/v1/conversations')->assertOk()->json('data'));
        $group = $items->firstWhere('type', 'group');
        $direct = $items->firstWhere('type', 'direct');

        $this->assertCount(1, $group['members']);
        $this->assertCount(2, $direct['members']);
        $this->assertSame($first->display_name, $direct['name']);
    }

    public function test_cross_conversation_message_ids_are_rejected(): void
    {
        [$firstId, $user, $other] = $this->direct();
        $third = User::factory()->create();
        Sanctum::actingAs($user);
        $secondId = $this->postJson('/api/v1/conversations/direct', ['user_id' => $third->id])->json('data.id');
        $messageId = $this->postJson("/api/v1/conversations/{$firstId}/messages", ['body' => 'First chat'])->json('data.id');

        $this->patchJson("/api/v1/conversations/{$secondId}/messages/{$messageId}", ['body' => 'Wrong chat'])
            ->assertNotFound()->assertJsonPath('code', 'MESSAGE_CONVERSATION_MISMATCH');
        $this->postJson("/api/v1/conversations/{$secondId}/messages", [
            'body' => 'Bad reply', 'reply_to_id' => $messageId,
        ])->assertUnprocessable()->assertJsonPath('code', 'MESSAGE_CONVERSATION_MISMATCH');
    }

    public function test_location_messages_require_and_return_structured_payload(): void
    {
        [$conversationId, $sender] = $this->direct();
        Sanctum::actingAs($sender);

        $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'type' => 'location', 'payload' => ['label' => 'Missing coordinates'],
        ])->assertUnprocessable()->assertJsonValidationErrors('payload');

        $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'type' => 'location',
            'payload' => ['latitude' => 13.7563, 'longitude' => 100.5018, 'label' => 'Meeting point'],
        ])->assertCreated()->assertJsonPath('data.type', 'location')
            ->assertJsonPath('data.payload.label', 'Meeting point')
            ->assertJsonPath('data.payload.latitude', 13.7563);
    }

    public function test_latest_message_handles_timestamp_ties_and_deleted_messages(): void
    {
        [$conversationId, $sender] = $this->direct();
        $first = Message::create([
            'conversation_id' => $conversationId, 'sender_id' => $sender->id,
            'type' => 'text', 'body' => 'First',
        ]);
        $second = Message::create([
            'conversation_id' => $conversationId, 'sender_id' => $sender->id,
            'type' => 'text', 'body' => 'Second',
        ]);
        $first->forceFill(['created_at' => now()->startOfSecond()])->save();
        $second->forceFill(['created_at' => $first->created_at])->save();
        $winner = strcmp($first->id, $second->id) > 0 ? $first : $second;
        $remaining = $winner->is($first) ? $second : $first;
        $loaded = Conversation::with('latestMessage')->findOrFail($conversationId);
        $this->assertSame($winner->id, $loaded->latestMessage->id);
        $winner->delete();
        $this->getJson('/api/v1/conversations')->assertOk()
            ->assertJsonPath('data.0.latest_message.id', $remaining->id);
        $remaining->delete();
        $this->assertNull($loaded->fresh()->latestMessage);
    }

    private function direct(): array
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        Sanctum::actingAs($sender);
        $id = $this->postJson('/api/v1/conversations/direct', ['user_id' => $recipient->id])->json('data.id');

        return [$id, $sender, $recipient];
    }
}

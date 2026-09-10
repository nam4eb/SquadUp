<?php

namespace Tests\Feature\Api\V1;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatMediaMentionPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_upload_valid_media_and_history_exposes_storage_url(): void
    {
        Storage::fake('public');
        [$conversationId, $sender, $recipient] = $this->direct();
        Sanctum::actingAs($sender);

        $response = $this->post("/api/v1/conversations/{$conversationId}/messages", [
            'body' => 'Trip details',
            'file' => UploadedFile::fake()->create('plan.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'file')
            ->assertJsonPath('data.media.0.original_name', 'plan.pdf')
            ->assertJsonPath('data.media.0.mime_type', 'application/pdf');
        $this->assertDatabaseCount('media', 1);
        Storage::disk('public')->assertExists(Media::query()->sole()->path);

        $url = $response->json('data.media.0.url');
        Sanctum::actingAs($recipient);
        $this->get($url)->assertOk()->assertDownload('plan.pdf');

        Sanctum::actingAs(User::factory()->create());
        $this->get($url)->assertForbidden();
        $this->get('/api/v1/media/'.Media::query()->sole()->id)->assertForbidden();
    }

    public function test_invalid_media_is_rejected_without_creating_a_message(): void
    {
        Storage::fake('public');
        [$conversationId, $sender] = $this->direct();
        Sanctum::actingAs($sender);

        $this->post("/api/v1/conversations/{$conversationId}/messages", [
            'file' => UploadedFile::fake()->create('payload.exe', 10, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_mentions_notify_members_and_reject_outsiders(): void
    {
        [$conversationId, $sender, $recipient] = $this->direct();
        $outsider = User::factory()->create();
        Sanctum::actingAs($sender);

        $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => "Hello @{$recipient->username}",
        ])->assertCreated()->assertJsonPath('data.mentions.0.user_id', $recipient->id);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $recipient->id,
        ]);
        $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => 'Invalid mention', 'mention_ids' => [$outsider->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('mention_ids');
    }

    public function test_preferences_default_on_can_be_updated_and_suppress_chat_notification(): void
    {
        [$conversationId, $sender, $recipient] = $this->direct();
        Sanctum::actingAs($recipient);
        $this->getJson('/api/v1/notification-preferences')
            ->assertOk()->assertJsonPath('data.8.type', 'chat_message')
            ->assertJsonPath('data.8.in_app_enabled', true);
        $this->putJson('/api/v1/notification-preferences', ['preferences' => [[
            'type' => 'chat_message', 'in_app_enabled' => false, 'push_enabled' => false,
        ]]])->assertOk()->assertJsonFragment([
            'type' => 'chat_message', 'in_app_enabled' => false, 'push_enabled' => false,
        ]);

        Sanctum::actingAs($sender);
        $this->postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => 'Muted'])
            ->assertCreated();
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $recipient->id]);
    }

    public function test_existing_notification_types_honor_the_same_preferences(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        Sanctum::actingAs($recipient);
        $this->putJson('/api/v1/notification-preferences', ['preferences' => [[
            'type' => 'friend_request', 'in_app_enabled' => false, 'push_enabled' => true,
        ]]])->assertOk();

        Sanctum::actingAs($sender);
        $this->postJson('/api/v1/friend-requests', ['receiver_id' => $recipient->id])->assertCreated();
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $recipient->id]);
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

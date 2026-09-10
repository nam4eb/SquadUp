<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdentityCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_media_upload_replaces_old_files_and_returns_urls(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $first = $this->post('/api/v1/users/me', [
            '_method' => 'PATCH',
            'avatar' => UploadedFile::fake()->image('avatar.png'),
            'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 400),
        ], ['Accept' => 'application/json'])->assertOk();
        $oldAvatar = $user->refresh()->avatar_path;
        Storage::disk('public')->assertExists($oldAvatar);
        $first->assertJsonPath('data.avatar_url', Storage::disk('public')->url($oldAvatar));

        $this->post('/api/v1/users/me', [
            '_method' => 'PATCH', 'avatar' => UploadedFile::fake()->image('new.png'),
        ], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertMissing($oldAvatar);
    }

    public function test_user_can_list_and_revoke_device_sessions(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $first = $user->createToken('phone', ['*'], now()->addDay());
        $second = $user->createToken('tablet', ['*'], now()->addDay());

        $this->withToken($first->plainTextToken)->getJson('/api/v1/auth/sessions')
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonFragment(['device_name' => 'phone', 'is_current' => true]);
        $this->withToken($first->plainTextToken)
            ->deleteJson('/api/v1/auth/sessions/'.$second->accessToken->id)
            ->assertOk()->assertJsonPath('current_session_revoked', false);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_account_deactivation_requires_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $user->createToken('phone')->plainTextToken;
        $this->withToken($token)->deleteJson('/api/v1/users/me', ['password' => 'wrong'])
            ->assertUnprocessable();
        $this->withToken($token)->deleteJson('/api/v1/users/me', ['password' => 'Password123!'])
            ->assertOk();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_idempotency_replays_same_request_and_rejects_key_reuse(): void
    {
        $sender = User::factory()->create();
        $firstRecipient = User::factory()->create();
        $secondRecipient = User::factory()->create();
        Sanctum::actingAs($sender);
        $headers = ['Idempotency-Key' => 'friend-request-1'];

        $first = $this->postJson('/api/v1/friend-requests', ['receiver_id' => $firstRecipient->id], $headers)
            ->assertCreated();
        $this->postJson('/api/v1/friend-requests', ['receiver_id' => $firstRecipient->id], $headers)
            ->assertCreated()->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.id', $first->json('data.id'));
        $this->postJson('/api/v1/friend-requests', ['receiver_id' => $secondRecipient->id], $headers)
            ->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertDatabaseCount('friend_requests', 1);
    }
}

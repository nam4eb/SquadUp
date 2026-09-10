<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receives_a_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'New.Player',
            'display_name' => 'New Player',
            'email' => 'PLAYER@EXAMPLE.COM',
            'password' => 'StrongPass1',
            'password_confirmation' => 'StrongPass1',
            'device_name' => 'test-device',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.username', 'new.player')
            ->assertJsonPath('data.user.email', 'player@example.com')
            ->assertJsonStructure(['data' => ['user' => ['id'], 'token']]);

        $this->assertDatabaseHas('users', ['username' => 'new.player', 'email' => 'player@example.com']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_registration_validates_unique_identity_and_password_strength(): void
    {
        User::factory()->create(['username' => 'taken', 'email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'username' => 'taken',
            'display_name' => 'Taken',
            'email' => 'taken@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
            'device_name' => 'test-device',
        ])->assertUnprocessable()->assertJsonValidationErrors(['username', 'email', 'password']);
    }

    public function test_user_can_login_and_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create(['password' => 'StrongPass1']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPass1',
            'device_name' => 'test-device',
        ])->assertOk()->assertJsonStructure(['data' => ['user', 'token']]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'test-device',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_disabled_account_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => 'StrongPass1',
            'account_status' => AccountStatus::Banned,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPass1',
            'device_name' => 'test-device',
        ])->assertForbidden()->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    public function test_me_requires_authentication_and_returns_private_owner_fields(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_user_can_update_own_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me', [
            'display_name' => 'Updated Name',
            'bio' => 'Ready to play.',
            'location' => 'Bangkok',
        ])->assertOk()->assertJsonPath('data.display_name', 'Updated Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'location' => 'Bangkok']);
    }

    public function test_user_can_change_password_and_other_tokens_are_revoked(): void
    {
        $user = User::factory()->create(['password' => 'OldStrong1']);
        $current = $user->createToken('current');
        $user->createToken('other');

        $this->withToken($current->plainTextToken)->patchJson('/api/v1/users/me/password', [
            'current_password' => 'OldStrong1',
            'password' => 'NewStrong2',
            'password_confirmation' => 'NewStrong2',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewStrong2', $user->refresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('current');

        $this->withToken($token->plainTextToken)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_forgot_password_response_does_not_reveal_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $existing = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);
        $missing = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.com']);

        $existing->assertOk();
        $missing->assertOk();
        $this->assertSame($existing->json('message'), $missing->json('message'));
    }

    public function test_user_can_reset_password_and_existing_tokens_are_revoked(): void
    {
        $user = User::factory()->create(['password' => 'OldStrong1']);
        $user->createToken('old-device');
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewStrong2',
            'password_confirmation' => 'NewStrong2',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewStrong2', $user->refresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_authenticated_user_can_verify_email(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->getJson($url)->assertOk();
        $this->assertNotNull($user->refresh()->email_verified_at);
    }
}

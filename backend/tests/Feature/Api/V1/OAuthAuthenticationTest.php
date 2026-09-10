<?php

namespace Tests\Feature\Api\V1;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\OAuth\OAuthTokenVerifier;
use App\Services\OAuth\ProviderIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OAuthAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_provider_identity_creates_account_and_session(): void
    {
        $this->mockIdentity(new ProviderIdentity(
            'google', 'google-123', 'OAuth Member', 'oauth@example.com', true,
        ));
        $this->postJson('/api/v1/auth/oauth/google', ['token' => 'valid', 'device_name' => 'pixel'])
            ->assertOk()->assertJsonPath('data.user.email', 'oauth@example.com')
            ->assertJsonStructure(['data' => ['token']]);
        $this->assertDatabaseHas('users', ['email' => 'oauth@example.com', 'password_set' => false]);
        $this->assertDatabaseHas('social_accounts', ['provider' => 'google', 'provider_subject' => 'google-123']);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'pixel']);
    }

    public function test_existing_email_requires_explicit_authenticated_link(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);
        $this->mockIdentity(new ProviderIdentity(
            'google', 'google-456', 'Existing', 'existing@example.com', true,
        ));
        $this->postJson('/api/v1/auth/oauth/google', ['token' => 'valid'])
            ->assertConflict()->assertJsonPath('code', 'ACCOUNT_LINK_REQUIRED');
    }

    public function test_user_can_link_and_unlink_provider_without_account_takeover(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->mockIdentity(new ProviderIdentity(
            'facebook', 'fb-123', 'Member', $user->email, true,
        ));
        $this->postJson('/api/v1/auth/oauth/facebook/link', ['token' => 'valid'])->assertOk();
        $this->getJson('/api/v1/auth/oauth/accounts')
            ->assertOk()->assertJsonPath('data.0.provider', 'facebook');
        $this->deleteJson('/api/v1/auth/oauth/facebook')->assertOk();
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_last_oauth_method_cannot_be_unlinked_without_password(): void
    {
        $user = User::factory()->create(['password_set' => false]);
        SocialAccount::create([
            'user_id' => $user->id, 'provider' => 'google',
            'provider_subject' => 'only-provider', 'provider_email' => $user->email,
        ]);
        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/auth/oauth/google')
            ->assertUnprocessable()->assertJsonPath('code', 'LAST_SIGN_IN_METHOD');
    }

    public function test_google_verifier_checks_signature_audience_issuer_and_expiry(): void
    {
        Cache::clear();
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        config(['services.google.client_ids' => 'squadup-client']);
        Http::fake(['www.googleapis.com/oauth2/v1/certs' => Http::response(['kid-1' => $details['key']])]);
        $header = $this->encode(['alg' => 'RS256', 'kid' => 'kid-1']);
        $claims = $this->encode([
            'sub' => 'subject-1', 'aud' => 'squadup-client', 'iss' => 'https://accounts.google.com',
            'exp' => time() + 300, 'email' => 'signed@example.com', 'email_verified' => true,
        ]);
        openssl_sign("{$header}.{$claims}", $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $token = "{$header}.{$claims}.".$this->encodeRaw($signature);

        $identity = app(OAuthTokenVerifier::class)->verify('google', $token);
        $this->assertSame('subject-1', $identity->subject);
        $this->assertSame('signed@example.com', $identity->email);
    }

    private function mockIdentity(ProviderIdentity $identity): void
    {
        $this->mock(OAuthTokenVerifier::class)
            ->shouldReceive('verify')->once()->andReturn($identity);
    }

    private function encode(array $value): string
    {
        return $this->encodeRaw(json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function encodeRaw(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

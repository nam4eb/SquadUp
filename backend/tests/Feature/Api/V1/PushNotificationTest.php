<?php

namespace Tests\Feature\Api\V1;

use App\Models\PushDevice;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_refresh_and_remove_own_push_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/v1/push-devices', [
            'token' => 'device-token', 'platform' => 'android', 'device_name' => 'Pixel',
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/push-devices', [
            'token' => 'device-token', 'platform' => 'android', 'device_name' => 'Pixel 2',
        ])->assertOk()->assertJsonPath('data.id', $id);
        $this->deleteJson("/api/v1/push-devices/{$id}")->assertOk();
        $this->assertDatabaseCount('push_devices', 0);
    }

    public function test_user_cannot_remove_another_users_device(): void
    {
        $owner = User::factory()->create();
        $device = PushDevice::create([
            'user_id' => $owner->id, 'token' => 'private-token',
            'platform' => 'ios', 'last_seen_at' => now(),
        ]);
        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson("/api/v1/push-devices/{$device->id}")->assertNotFound();
    }

    public function test_fcm_service_uses_service_account_oauth_and_http_v1(): void
    {
        Cache::clear();
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $privateKey);
        config([
            'services.fcm.enabled' => true,
            'services.fcm.project_id' => 'squadup-project',
            'services.fcm.client_email' => 'fcm@squadup-project.iam.gserviceaccount.com',
            'services.fcm.private_key' => $privateKey,
        ]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-123']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/squadup/messages/1']),
        ]);
        $device = PushDevice::create([
            'user_id' => User::factory()->create()->id,
            'token' => 'fcm-device-token', 'platform' => 'android', 'last_seen_at' => now(),
        ]);

        app(FcmService::class)->send($device, 'Title', 'Body', ['type' => 'mention']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/messages:send')
            && $request->hasHeader('Authorization', 'Bearer access-123')
            && $request['message']['token'] === 'fcm-device-token');
    }
}

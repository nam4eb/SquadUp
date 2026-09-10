<?php

namespace Tests\Feature\Operations;

use App\Models\IdempotencyKey;
use App\Models\Media;
use App\Models\Message;
use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneOperationalDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_is_dry_run_by_default_and_force_removes_only_eligible_data(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $expired = $this->idempotency($user, 'expired', now()->subMinute());
        $active = $this->idempotency($user, 'active', now()->addHour());
        $stale = PushDevice::create(['user_id' => $user->id, 'token' => 'stale', 'platform' => 'ios', 'last_seen_at' => now()->subDays(181)]);
        $current = PushDevice::create(['user_id' => $user->id, 'token' => 'current', 'platform' => 'android', 'last_seen_at' => now()]);
        Storage::disk('public')->put('chat/orphan.txt', 'orphan');
        $orphan = new Media;
        $orphan->forceFill([
            'mediable_type' => Message::class, 'mediable_id' => fake()->uuid(),
            'uploaded_by' => $user->id, 'disk' => 'public', 'path' => 'chat/orphan.txt',
            'original_name' => 'orphan.txt', 'mime_type' => 'text/plain', 'size' => 6,
        ])->save();
        $orphan = Media::query()->where('path', 'chat/orphan.txt')->sole();
        $orphan->forceFill(['created_at' => now()->subHours(25)])->save();

        $this->artisan('operations:prune')->assertSuccessful();
        $this->assertModelExists($expired);
        $this->assertModelExists($stale);
        $this->assertModelExists($orphan);

        $this->artisan('operations:prune --force')->assertSuccessful();
        $this->assertModelMissing($expired);
        $this->assertModelExists($active);
        $this->assertModelMissing($stale);
        $this->assertModelExists($current);
        $this->assertModelMissing($orphan);
        Storage::disk('public')->assertMissing('chat/orphan.txt');
    }

    private function idempotency(User $user, string $key, mixed $expiresAt): IdempotencyKey
    {
        return IdempotencyKey::create([
            'user_id' => $user->id, 'key' => $key, 'route' => 'test.route',
            'request_hash' => hash('sha256', $key), 'response_status' => 200,
            'response_body' => '{}', 'expires_at' => $expiresAt,
        ]);
    }
}

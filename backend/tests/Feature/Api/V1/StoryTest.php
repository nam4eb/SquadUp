<?php

namespace Tests\Feature\Api\V1;

use App\Models\Friendship;
use App\Models\Story;
use App\Models\User;
use App\Support\UserPair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_story_is_visible_to_friends_tracks_views_and_can_be_deleted(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $friend = User::factory()->create();
        $outsider = User::factory()->create();
        $pair = UserPair::key($owner->id, $friend->id);
        Friendship::create([
            'user_low_id' => min($owner->id, $friend->id),
            'user_high_id' => max($owner->id, $friend->id),
            'pair_key' => $pair,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($owner);
        $response = $this->post('/api/v1/stories', [
            'media' => $this->fakePng(),
            'caption' => 'Morning practice',
        ], ['Accept' => 'application/json'])->assertCreated();
        $storyId = $response->json('data.id');

        Sanctum::actingAs($friend);
        $this->getJson('/api/v1/stories')->assertOk()->assertJsonPath('data.0.id', $storyId);
        $this->postJson("/api/v1/stories/{$storyId}/view")->assertOk();
        $this->assertDatabaseHas('story_views', ['story_id' => $storyId, 'user_id' => $friend->id]);

        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/stories')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/stories/{$storyId}/view")->assertNotFound();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/stories/{$storyId}")->assertOk();
        $this->assertDatabaseMissing('stories', ['id' => $storyId]);
    }

    public function test_expired_stories_are_not_returned(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $storyId = $this->post('/api/v1/stories', [
            'media' => $this->fakePng(),
            'visibility' => 'public',
        ], ['Accept' => 'application/json'])->json('data.id');

        Story::whereKey($storyId)->update(['expires_at' => now()->subSecond()]);

        $this->getJson('/api/v1/stories')->assertOk()->assertJsonCount(0, 'data');
    }

    private function fakePng(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'story.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        );
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Models\Sport;
use App\Models\User;
use Database\Seeders\SportsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingAndSportProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SportsSeeder::class);
    }

    public function test_sports_configuration_is_backend_driven_and_ordered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/sports')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.slug', 'badminton')
            ->assertJsonPath('data.1.slug', 'football')
            ->assertJsonPath('data.2.slug', 'pickleball')
            ->assertJsonPath('data.3.slug', 'tennis');
    }

    public function test_user_can_sync_independent_skill_levels_per_sport(): void
    {
        $user = User::factory()->create();
        $badminton = Sport::query()->where('slug', 'badminton')->firstOrFail();
        $football = Sport::query()->where('slug', 'football')->firstOrFail();

        $this->actingAs($user)->putJson('/api/v1/users/me/sports', ['sports' => [
            ['sport_id' => $badminton->id, 'level' => 'intermediate'],
            ['sport_id' => $football->id, 'level' => 'beginner'],
        ]])->assertOk()->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('user_sport_profiles', [
            'user_id' => $user->id,
            'sport_id' => $badminton->id,
            'self_declared_level' => 'intermediate',
            'skill_rating' => 1200,
        ]);
        $this->assertDatabaseHas('user_sport_profiles', [
            'user_id' => $user->id,
            'sport_id' => $football->id,
            'self_declared_level' => 'beginner',
            'skill_rating' => 800,
        ]);

        $this->actingAs($user)->putJson('/api/v1/users/me/sports', ['sports' => [
            ['sport_id' => $badminton->id, 'level' => 'advanced'],
        ]])->assertOk()->assertJsonCount(1, 'data');

        $this->assertDatabaseMissing('user_sport_profiles', ['user_id' => $user->id, 'sport_id' => $football->id]);
        $this->assertDatabaseHas('user_sport_profiles', ['user_id' => $user->id, 'sport_id' => $badminton->id, 'skill_rating' => 1600]);
    }

    public function test_invalid_or_inactive_sport_profile_is_rejected(): void
    {
        $user = User::factory()->create();
        $sport = Sport::query()->firstOrFail();
        $sport->update(['is_active' => false]);

        $this->actingAs($user)->putJson('/api/v1/users/me/sports', ['sports' => [
            ['sport_id' => $sport->id, 'level' => 'wizard'],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['sports.0.sport_id', 'sports.0.level']);
    }

    public function test_onboarding_is_resumable_and_requires_a_sport_to_complete(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/onboarding', ['step' => 'completed'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'SPORT_REQUIRED');

        $sport = Sport::query()->where('slug', 'badminton')->firstOrFail();
        $this->actingAs($user)->putJson('/api/v1/users/me/sports', ['sports' => [
            ['sport_id' => $sport->id, 'level' => 'intermediate'],
        ]])->assertOk();

        $this->actingAs($user)->putJson('/api/v1/onboarding', [
            'step' => 'location',
            'default_city' => 'Ha Noi',
            'default_area_latitude' => 21.0285,
            'default_area_longitude' => 105.8542,
            'timezone' => 'Asia/Bangkok',
        ])->assertOk()->assertJsonPath('data.step', 'location');

        $this->actingAs($user)->putJson('/api/v1/onboarding', ['step' => 'completed'])
            ->assertOk()->assertJsonPath('data.completed', true);

        $this->actingAs($user)->getJson('/api/v1/onboarding')
            ->assertOk()
            ->assertJsonPath('data.default_area.city', 'Ha Noi')
            ->assertJsonCount(1, 'data.sport_profiles');
    }

    public function test_public_user_resource_does_not_expose_default_coordinates(): void
    {
        $owner = User::factory()->create([
            'default_area_latitude' => 21.0285,
            'default_area_longitude' => 105.8542,
        ]);
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->getJson("/api/v1/users/{$owner->id}")
            ->assertOk()
            ->assertJsonPath('data.default_area', null);
    }
}

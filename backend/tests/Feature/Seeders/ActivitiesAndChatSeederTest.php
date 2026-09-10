<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActivitiesAndChatSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_repeatable_activity_and_chat_scenarios(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('activities', ['title' => 'Badminton After Work', 'status' => 'open']);
        $this->assertDatabaseHas('activities', ['title' => 'Saturday Coffee Tasting', 'status' => 'full']);
        $this->assertDatabaseHas('activities', ['title' => 'Old Quarter Photo Walk', 'status' => 'completed']);
        $this->assertDatabaseHas('activity_participants', ['status' => 'requested']);
        $this->assertDatabaseHas('conversations', ['type' => 'group', 'name' => 'Weekend Planners']);
        $this->assertDatabaseHas('conversations', ['type' => 'event', 'name' => 'Badminton After Work']);
        $this->assertDatabaseHas('messages', ['type' => 'location']);
        $this->assertGreaterThanOrEqual(8, DB::table('messages')->count());

        $counts = [DB::table('activities')->count(), DB::table('conversations')->count(), DB::table('messages')->count()];
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($counts, [DB::table('activities')->count(), DB::table('conversations')->count(), DB::table('messages')->count()]);
    }
}

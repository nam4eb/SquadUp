<?php

namespace Database\Seeders;

use App\Models\Sport;
use Illuminate\Database\Seeder;

class SportsSeeder extends Seeder
{
    public function run(): void
    {
        $sports = [
            ['slug' => 'badminton', 'name' => 'Badminton', 'icon' => 'sports_tennis', 'min_players' => 2, 'max_players' => 4, 'supports_team' => false, 'supports_singles' => true, 'supports_doubles' => true],
            ['slug' => 'football', 'name' => 'Football', 'icon' => 'sports_soccer', 'min_players' => 10, 'max_players' => 22, 'supports_team' => true, 'supports_singles' => false, 'supports_doubles' => false],
            ['slug' => 'pickleball', 'name' => 'Pickleball', 'icon' => 'sports_tennis', 'min_players' => 2, 'max_players' => 4, 'supports_team' => false, 'supports_singles' => true, 'supports_doubles' => true],
            ['slug' => 'tennis', 'name' => 'Tennis', 'icon' => 'sports_tennis', 'min_players' => 2, 'max_players' => 4, 'supports_team' => false, 'supports_singles' => true, 'supports_doubles' => true],
        ];

        foreach ($sports as $index => $sport) {
            Sport::query()->updateOrCreate(['slug' => $sport['slug']], [
                ...$sport,
                'skill_system' => 'squadup_rating',
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }
}

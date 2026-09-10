<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SportsSeeder::class);
        $this->call(VenuesSeeder::class);
        $this->call(ActivityTaxonomySeeder::class);
        $this->call(DemoDataSeeder::class);
        $this->call(ActivitiesAndChatSeeder::class);
    }
}

<?php

namespace Database\Seeders;

use App\Models\ActivityCategory;
use App\Models\ActivityTopic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ActivityTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $taxonomy = [
            ['Sports', 'sports', '#22C55E', ['Badminton', 'Football', 'Basketball']],
            ['Entertainment', 'entertainment', '#A855F7', ['Gaming', 'Movies', 'Karaoke']],
            ['Food & Drink', 'food-drink', '#F97316', ['Coffee', 'Hotpot', 'BBQ']],
            ['Outdoors', 'outdoors', '#16A34A', ['Camping', 'Hiking', 'Cycling']],
            ['Fitness', 'fitness', '#EF4444', ['Running', 'Gym', 'Yoga']],
            ['Learning', 'learning', '#3B82F6', ['Study Group', 'Languages', 'Workshops']],
            ['Arts', 'arts', '#EC4899', ['Photography', 'Drawing', 'Music']],
            ['Travel', 'travel', '#06B6D4', ['Day Trips', 'Road Trips', 'Backpacking']],
            ['Community', 'community', '#EAB308', ['Volunteering', 'Networking', 'Meetups']],
            ['Other', 'other', '#64748B', ['Board Games', 'Pets', 'Custom Activity']],
        ];

        foreach ($taxonomy as $categoryOrder => [$name, $slug, $color, $topics]) {
            $category = ActivityCategory::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'color' => $color,
                    'sort_order' => ($categoryOrder + 1) * 10,
                    'status' => 'active',
                    'is_visible' => true,
                ],
            );

            foreach ($topics as $topicOrder => $topicName) {
                ActivityTopic::updateOrCreate(
                    ['category_id' => $category->id, 'slug' => Str::slug($topicName)],
                    [
                        'name' => $topicName,
                        'sort_order' => ($topicOrder + 1) * 10,
                        'status' => 'active',
                        'is_visible' => true,
                    ],
                );
            }
        }
    }
}

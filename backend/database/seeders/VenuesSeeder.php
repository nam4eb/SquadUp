<?php

namespace Database\Seeders;

use App\Models\Venue;
use Illuminate\Database\Seeder;

class VenuesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Phu Tho Sports Center', 'address' => '1 Lu Gia, District 11', 'city' => 'Ho Chi Minh City', 'district' => 'District 11', 'latitude' => 10.7677, 'longitude' => 106.6578],
            ['name' => 'Cau Giay Sports Hall', 'address' => 'Tran Quy Kien, Cau Giay', 'city' => 'Ha Noi', 'district' => 'Cau Giay', 'latitude' => 21.0365, 'longitude' => 105.7906],
            ['name' => 'My Dinh National Sports Complex', 'address' => 'Le Duc Tho, Nam Tu Liem', 'city' => 'Ha Noi', 'district' => 'Nam Tu Liem', 'latitude' => 21.0206, 'longitude' => 105.7639],
            ['name' => 'Quan Khu 7 Stadium', 'address' => '202 Hoang Van Thu, Tan Binh', 'city' => 'Ho Chi Minh City', 'district' => 'Tan Binh', 'latitude' => 10.8016, 'longitude' => 106.6669],
        ] as $venue) {
            Venue::query()->updateOrCreate(
                ['name' => $venue['name'], 'city' => $venue['city']],
                [...$venue, 'is_verified' => true],
            );
        }
    }
}

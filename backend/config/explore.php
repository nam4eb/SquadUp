<?php

return [
    'default_radius_km' => (float) env('EXPLORE_DEFAULT_RADIUS_KM', 5),
    'max_radius_km' => (float) env('EXPLORE_MAX_RADIUS_KM', 100),
    'weights' => [
        'distance' => 0.45,
        'starts_soon' => 0.30,
        'availability' => 0.15,
        'social' => 0.10,
    ],
];

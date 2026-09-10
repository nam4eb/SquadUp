<?php

return [
    'profile_database' => env('PERFORMANCE_PROFILING_ENABLED', false),
    'api_rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),
];

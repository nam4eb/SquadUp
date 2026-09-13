<?php

return [
    'profile_database' => env('PERFORMANCE_PROFILING_ENABLED', false),
    'api_rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),
    'request_log_sample_rate' => (float) env('REQUEST_LOG_SAMPLE_RATE', 0.05),
    'slow_request_ms' => (float) env('SLOW_REQUEST_MS', 500),
];

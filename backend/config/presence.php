<?php

return [
    'store' => env('PRESENCE_STORE', 'redis'),
    'ttl_seconds' => (int) env('PRESENCE_TTL_SECONDS', 120),
];

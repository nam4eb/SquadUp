<?php

return [
    'disk' => env('MEDIA_DISK', 'public'),
    'max_kilobytes' => (int) env('MEDIA_MAX_KILOBYTES', 20480),
    'url_ttl_minutes' => (int) env('MEDIA_URL_TTL_MINUTES', 5),
    'allowed_mime_types' => [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'video/mp4', 'video/webm', 'audio/mpeg', 'audio/mp4', 'audio/ogg',
        'application/pdf', 'text/plain', 'application/zip',
    ],
];

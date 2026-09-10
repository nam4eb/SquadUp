<?php

return [
    'stale_push_device_days' => (int) env('STALE_PUSH_DEVICE_DAYS', 180),
    'orphan_media_grace_hours' => (int) env('ORPHAN_MEDIA_GRACE_HOURS', 24),
];

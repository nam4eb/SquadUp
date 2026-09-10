<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\FcmChannel;
use App\Services\NotificationPreferenceService;

trait RespectsNotificationPreferences
{
    private function preferredChannels(object $notifiable, string $type): array
    {
        $preferences = app(NotificationPreferenceService::class);
        $channels = [];
        if ($preferences->enabled($notifiable, $type, 'in_app')) {
            $channels[] = 'database';
        }
        if ($preferences->enabled($notifiable, $type, 'push')) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }
}

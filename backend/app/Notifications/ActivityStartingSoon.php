<?php

namespace App\Notifications;

use App\Models\Activity;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ActivityStartingSoon extends Notification
{
    use Queueable, RespectsNotificationPreferences;

    public function __construct(private readonly Activity $activity) {}

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'event_starting_soon');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'activity_starting_soon', 'activity_id' => $this->activity->id,
            'activity_title' => $this->activity->title,
            'starts_at' => $this->activity->starts_at->toISOString(),
        ];
    }
}

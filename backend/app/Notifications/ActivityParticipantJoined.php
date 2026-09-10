<?php

namespace App\Notifications;

use App\Models\Activity;
use App\Models\User;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ActivityParticipantJoined extends Notification
{
    use Queueable, RespectsNotificationPreferences;

    public function __construct(private readonly Activity $activity, private readonly User $participant) {}

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'event_participant_joined');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'activity_participant_joined', 'activity_id' => $this->activity->id,
            'activity_title' => $this->activity->title, 'actor_id' => $this->participant->id,
            'actor_username' => $this->participant->username,
        ];
    }
}

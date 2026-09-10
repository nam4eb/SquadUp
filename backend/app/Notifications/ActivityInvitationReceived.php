<?php

namespace App\Notifications;

use App\Models\ActivityInvitation;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ActivityInvitationReceived extends Notification
{
    use Queueable, RespectsNotificationPreferences;

    public function __construct(private readonly ActivityInvitation $invitation) {}

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'event_invitation');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'activity_invitation',
            'activity_id' => $this->invitation->activity_id,
            'activity_title' => $this->invitation->activity->title,
            'invitation_id' => $this->invitation->id,
            'actor_id' => $this->invitation->inviter_id,
            'actor_username' => $this->invitation->inviter->username,
        ];
    }
}

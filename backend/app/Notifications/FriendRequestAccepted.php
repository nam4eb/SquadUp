<?php

namespace App\Notifications;

use App\Models\FriendRequest;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FriendRequestAccepted extends Notification
{
    use Queueable, RespectsNotificationPreferences;

    public function __construct(private readonly FriendRequest $friendRequest) {}

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'friend_accepted');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'friend_accepted',
            'friend_request_id' => $this->friendRequest->id,
            'actor_id' => $this->friendRequest->receiver_id,
            'actor_username' => $this->friendRequest->receiver->username,
        ];
    }
}

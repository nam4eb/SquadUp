<?php

namespace App\Notifications\Channels;

use App\Services\FcmService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(private readonly FcmService $fcm) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $data = $notification->toArray($notifiable);
        $type = (string) ($data['type'] ?? 'notification');
        $title = match ($type) {
            'mention' => 'You were mentioned',
            'chat_message' => 'New message',
            'friend_request' => 'New friend request',
            'friend_accepted' => 'Friend request accepted',
            'activity_invitation' => 'Activity invitation',
            'activity_participant_joined' => 'New activity participant',
            'activity_starting_soon' => 'Activity starting soon',
            'activity_cancelled' => 'Activity cancelled',
            'clan_invitation' => 'Clan invitation',
            'clan_role_changed' => 'Clan role updated',
            default => 'SquadUP update',
        };
        $body = (string) ($data['preview'] ?? $data['actor_name'] ?? $data['actor_username'] ?? 'Open SquadUP for details.');
        foreach ($notifiable->pushDevices()->get() as $device) {
            $this->fcm->send($device, $title, $body, $data);
        }
    }
}

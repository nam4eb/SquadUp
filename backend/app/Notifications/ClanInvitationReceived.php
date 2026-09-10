<?php

namespace App\Notifications;

use App\Models\ClanMember;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClanInvitationReceived extends Notification
{
    use Queueable, RespectsNotificationPreferences;

    public function __construct(private readonly ClanMember $membership) {}

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'clan_invitation');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'clan_invitation',
            'clan_id' => $this->membership->clan_id,
            'clan_name' => $this->membership->clan->name,
            'actor_id' => $this->membership->invited_by,
            'actor_username' => $this->membership->inviter->username,
        ];
    }
}

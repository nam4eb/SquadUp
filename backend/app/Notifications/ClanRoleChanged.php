<?php

namespace App\Notifications;

use App\Models\ClanMember;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClanRoleChanged extends Notification
{
    use Queueable, RespectsNotificationPreferences;

    public function __construct(private readonly ClanMember $membership) {}

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'clan_role_changed');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'clan_role_changed',
            'clan_id' => $this->membership->clan_id,
            'clan_name' => $this->membership->clan->name,
            'role_id' => $this->membership->clan_role_id,
            'role_name' => $this->membership->role->name,
        ];
    }
}

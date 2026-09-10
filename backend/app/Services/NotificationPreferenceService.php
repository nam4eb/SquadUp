<?php

namespace App\Services;

use App\Models\User;

class NotificationPreferenceService
{
    public const TYPES = [
        'friend_request', 'friend_accepted', 'event_invitation',
        'event_participant_joined', 'event_starting_soon', 'event_cancelled',
        'clan_invitation', 'clan_role_changed', 'chat_message', 'mention',
        'leaderboard_update',
    ];

    public function enabled(User $user, string $type, string $channel = 'in_app'): bool
    {
        $preference = $user->notificationPreferences()->where('type', $type)->first();

        return $preference === null || (bool) $preference->{"{$channel}_enabled"};
    }
}

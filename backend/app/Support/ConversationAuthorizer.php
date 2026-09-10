<?php

namespace App\Support;

use App\Exceptions\ChatException;
use App\Models\Conversation;
use App\Models\User;

final class ConversationAuthorizer
{
    public static function authorize(Conversation $conversation, User $user): void
    {
        if ($conversation->type === 'clan') {
            $clan = $conversation->clan;
            if ($clan && ClanAuthorizer::membership($clan, $user)?->status?->value === 'active') {
                return;
            }
        } elseif ($conversation->type === 'event') {
            if ($conversation->activity
                && ($conversation->activity->host_id === $user->id
                    || $conversation->activity->participants()->where('user_id', $user->id)->where('status', 'joined')->exists())) {
                return;
            }
        } elseif ($conversation->members()->where('user_id', $user->id)->where('status', 'active')->exists()) {
            return;
        }

        throw new ChatException('Conversation was not found.', 'CONVERSATION_NOT_FOUND', 404);
    }

    public static function canManage(Conversation $conversation, User $user): bool
    {
        if ($conversation->type === 'clan') {
            return $conversation->clan && ClanAuthorizer::allows($conversation->clan, $user, 'manage_chat');
        }

        if ($conversation->type === 'event') {
            return $conversation->activity?->host_id === $user->id
                || $user->can('manage', $conversation->activity);
        }

        return $conversation->members()->where('user_id', $user->id)
            ->where('status', 'active')->whereIn('role', ['owner', 'admin'])->exists();
    }
}

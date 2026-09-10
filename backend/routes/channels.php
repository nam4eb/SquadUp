<?php

use App\Models\Conversation;
use App\Models\User;
use App\Support\ConversationAuthorizer;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user->id === $id;
});

Broadcast::channel('users.{id}', fn ($user, string $id): bool => $user->id === $id);

Broadcast::channel('conversation.{conversation}', function (User $user, Conversation $conversation): bool {
    try {
        ConversationAuthorizer::authorize($conversation, $user);

        return true;
    } catch (Throwable) {
        return false;
    }
});

Broadcast::channel('presence.users', fn ($user): array => [
    'id' => $user->id,
    'display_name' => $user->display_name,
]);

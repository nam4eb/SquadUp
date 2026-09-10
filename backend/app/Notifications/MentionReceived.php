<?php

namespace App\Notifications;

use App\Models\Message;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MentionReceived extends Notification implements ShouldQueue
{
    use Queueable, RespectsNotificationPreferences;

    public function __construct(private readonly Message $message) {}

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'mention');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mention',
            'conversation_id' => $this->message->conversation_id,
            'message_id' => $this->message->id,
            'actor_id' => $this->message->sender_id,
            'actor_name' => $this->message->sender->display_name,
        ];
    }
}

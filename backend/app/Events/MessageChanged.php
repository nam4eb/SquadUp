<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message, public string $action) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->message->conversation_id}")];
    }

    public function broadcastAs(): string
    {
        return "message.{$this->action}";
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'client_message_id' => $this->message->client_message_id,
            'type' => $this->message->type,
            'body' => $this->message->deleted_at ? null : $this->message->body,
            'reply_to_id' => $this->message->reply_to_id,
            'story_id' => $this->message->story_id,
            'edited_at' => $this->message->edited_at?->toISOString(),
            'deleted_at' => $this->message->deleted_at?->toISOString(),
            'created_at' => $this->message->created_at?->toISOString(),
        ];
    }
}

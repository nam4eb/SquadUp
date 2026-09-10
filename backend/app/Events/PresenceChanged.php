<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class PresenceChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $userId,
        public string $status,
        public string $lastSeenAt,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('presence.users')];
    }

    public function broadcastAs(): string
    {
        return 'presence.changed';
    }
}

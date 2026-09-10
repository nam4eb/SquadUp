<?php

namespace App\Services;

use App\Events\PresenceChanged;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class PresenceService
{
    public function heartbeat(User $user, string $status): array
    {
        $presence = [
            'user_id' => $user->id,
            'status' => $status,
            'last_seen_at' => now()->toISOString(),
        ];
        $this->store()->put($this->key($user->id), $presence, config('presence.ttl_seconds'));
        broadcast(new PresenceChanged($user->id, $status, $presence['last_seen_at']))->toOthers();

        return $presence;
    }

    public function offline(User $user): array
    {
        $this->store()->forget($this->key($user->id));
        $presence = ['user_id' => $user->id, 'status' => 'offline', 'last_seen_at' => now()->toISOString()];
        broadcast(new PresenceChanged($user->id, 'offline', $presence['last_seen_at']))->toOthers();

        return $presence;
    }

    public function get(User $user): array
    {
        return $this->store()->get($this->key($user->id), [
            'user_id' => $user->id,
            'status' => 'offline',
            'last_seen_at' => null,
        ]);
    }

    private function store(): Repository
    {
        return Cache::store(config('presence.store'));
    }

    private function key(string $userId): string
    {
        return "presence:user:{$userId}";
    }
}

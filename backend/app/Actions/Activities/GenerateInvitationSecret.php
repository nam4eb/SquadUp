<?php

namespace App\Actions\Activities;

use App\Models\Activity;
use App\Models\ActivityInvitationToken;
use App\Models\User;
use Illuminate\Support\Str;

class GenerateInvitationSecret
{
    /** @return array{token: ActivityInvitationToken, secret: string} */
    public function handle(Activity $activity, User $host, string $type, ?string $expiresAt, ?int $maxUses): array
    {
        $secret = $type === 'code'
            ? Str::upper(Str::random(10))
            : Str::random(64);
        $normalized = $type === 'code' ? Str::upper($secret) : $secret;
        $token = $activity->invitationTokens()->create([
            'created_by' => $host->id,
            'type' => $type,
            'secret_hash' => hash('sha256', $normalized),
            'expires_at' => $expiresAt,
            'max_uses' => $maxUses,
        ]);

        return ['token' => $token, 'secret' => $secret];
    }
}

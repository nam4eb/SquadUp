<?php

namespace App\Actions\Activities;

use App\Exceptions\ActivityException;
use App\Models\ActivityInvitationToken;
use App\Models\ActivityParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RedeemInvitationSecret
{
    public function __construct(private readonly JoinActivity $joinActivity) {}

    public function handle(User $user, string $type, string $secret, ?string $password): ActivityParticipant
    {
        return DB::transaction(function () use ($user, $type, $secret, $password): ActivityParticipant {
            $normalized = $type === 'code' ? Str::upper(trim($secret)) : $secret;
            $token = ActivityInvitationToken::query()
                ->where('type', $type)
                ->where('secret_hash', hash('sha256', $normalized))
                ->lockForUpdate()
                ->first();
            if (! $token) {
                throw new ActivityException('Invitation is invalid.', 'INVALID_INVITATION', 404);
            }
            if ($token->revoked_at !== null) {
                throw new ActivityException('Invitation has been revoked.', 'INVITATION_REVOKED', 410);
            }
            if ($token->expires_at?->isPast()) {
                throw new ActivityException('Invitation has expired.', 'INVITATION_EXPIRED', 410);
            }
            if ($token->max_uses !== null && $token->uses_count >= $token->max_uses) {
                throw new ActivityException('Invitation usage limit has been reached.', 'INVITATION_EXHAUSTED', 410);
            }

            $participant = $this->joinActivity->handle($token->activity, $user, $password);
            $token->increment('uses_count');

            return $participant;
        });
    }
}

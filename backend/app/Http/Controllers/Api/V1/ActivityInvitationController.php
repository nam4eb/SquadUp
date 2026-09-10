<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Activities\CreateDirectInvitation;
use App\Actions\Activities\GenerateInvitationSecret;
use App\Actions\Activities\JoinActivity;
use App\Actions\Activities\RedeemInvitationSecret;
use App\Enums\ActivityInvitationStatus;
use App\Exceptions\ActivityException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityInvitationResource;
use App\Http\Resources\ActivityInvitationTokenResource;
use App\Http\Resources\ActivityParticipantResource;
use App\Models\Activity;
use App\Models\ActivityInvitation;
use App\Models\ActivityInvitationToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ActivityInvitationController extends Controller
{
    public function index(Activity $activity): JsonResponse
    {
        Gate::authorize('manage', $activity);

        return response()->json([
            'invitations' => ActivityInvitationResource::collection(
                $activity->invitations()->with(['inviter', 'invitee'])->latest()->get(),
            ),
            'secrets' => ActivityInvitationTokenResource::collection(
                $activity->invitationTokens()->latest()->get(),
            ),
        ]);
    }

    public function invite(Activity $activity, Request $request, CreateDirectInvitation $action): ActivityInvitationResource
    {
        Gate::authorize('manage', $activity);
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        $invitee = User::findOrFail($validated['user_id']);

        return new ActivityInvitationResource(
            $action->handle($activity, $request->user(), $invitee, $validated['expires_at'] ?? null),
        );
    }

    public function createSecret(Activity $activity, Request $request, GenerateInvitationSecret $action): JsonResponse
    {
        Gate::authorize('manage', $activity);
        $validated = $request->validate([
            'type' => ['required', Rule::in(['link', 'code'])],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        $result = $action->handle(
            $activity,
            $request->user(),
            $validated['type'],
            $validated['expires_at'] ?? null,
            $validated['max_uses'] ?? null,
        );

        return response()->json([
            'data' => new ActivityInvitationTokenResource($result['token']),
            'secret' => $result['secret'],
        ], 201);
    }

    public function revoke(Activity $activity, ActivityInvitationToken $token): JsonResponse
    {
        Gate::authorize('manage', $activity);
        abort_unless($token->activity_id === $activity->id, 404);
        $token->update(['revoked_at' => now()]);

        return response()->json(['message' => 'Invitation token revoked.']);
    }

    public function revokeDirect(Activity $activity, ActivityInvitation $invitation): JsonResponse
    {
        Gate::authorize('manage', $activity);
        abort_unless($invitation->activity_id === $activity->id, 404);
        if ($invitation->status === ActivityInvitationStatus::Accepted) {
            throw new ActivityException('An accepted invitation cannot be revoked.', 'INVITATION_ALREADY_ACCEPTED', 422);
        }
        $invitation->update(['status' => ActivityInvitationStatus::Revoked, 'responded_at' => now()]);

        return response()->json(['message' => 'Invitation revoked.']);
    }

    public function accept(ActivityInvitation $invitation, Request $request, JoinActivity $action): ActivityParticipantResource
    {
        $this->ensureInviteeCanRespond($invitation, $request->user());
        if ($invitation->expires_at?->isPast()) {
            $invitation->update(['status' => ActivityInvitationStatus::Expired, 'responded_at' => now()]);
            throw new ActivityException('Invitation has expired.', 'INVITATION_EXPIRED', 410);
        }
        $validated = $request->validate(['password' => ['nullable', 'string', 'max:72']]);

        return new ActivityParticipantResource(
            $action->handle($invitation->activity, $request->user(), $validated['password'] ?? null),
        );
    }

    public function decline(ActivityInvitation $invitation, Request $request): ActivityInvitationResource
    {
        $this->ensureInviteeCanRespond($invitation, $request->user());
        $invitation->update(['status' => ActivityInvitationStatus::Declined, 'responded_at' => now()]);

        return new ActivityInvitationResource($invitation->load(['inviter', 'invitee']));
    }

    public function redeem(Request $request, RedeemInvitationSecret $action): ActivityParticipantResource
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['link', 'code'])],
            'secret' => ['required', 'string', 'max:128'],
            'password' => ['nullable', 'string', 'max:72'],
        ]);

        return new ActivityParticipantResource(
            $action->handle($request->user(), $validated['type'], $validated['secret'], $validated['password'] ?? null),
        );
    }

    private function ensureInviteeCanRespond(ActivityInvitation $invitation, User $user): void
    {
        if ($invitation->invitee_id !== $user->id) {
            throw new ActivityException('This invitation belongs to another user.', 'INVITATION_FORBIDDEN', 403);
        }
        if ($invitation->status !== ActivityInvitationStatus::Pending) {
            throw new ActivityException('This invitation is no longer pending.', 'INVITATION_NOT_PENDING');
        }
    }
}

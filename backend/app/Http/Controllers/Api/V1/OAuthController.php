<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\OAuth\OAuthTokenVerifier;
use App\Services\OAuth\ProviderIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OAuthController extends Controller
{
    public function login(string $provider, Request $request, OAuthTokenVerifier $verifier): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:10000'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);
        $identity = $verifier->verify($provider, $validated['token']);
        $account = SocialAccount::query()->where([
            'provider' => $provider, 'provider_subject' => $identity->subject,
        ])->with('user')->first();
        if ($account) {
            $user = $account->user;
            if (! $user) {
                return response()->json([
                    'success' => false, 'message' => 'This account is not active.', 'code' => 'ACCOUNT_DISABLED',
                ], 403);
            }
        } else {
            if (! $identity->email || ! $identity->emailVerified) {
                throw ValidationException::withMessages([
                    'token' => ['A verified provider email is required to create an account.'],
                ]);
            }
            if (User::withTrashed()->where('email', $identity->email)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sign in with the existing account and link this provider first.',
                    'code' => 'ACCOUNT_LINK_REQUIRED',
                ], 409);
            }
            $user = $this->createUser($identity);
        }
        if ($user->account_status !== AccountStatus::Active || $user->trashed()) {
            return response()->json([
                'success' => false, 'message' => 'This account is not active.', 'code' => 'ACCOUNT_DISABLED',
            ], 403);
        }
        $account ??= $this->persistLink($user, $identity);
        $account->update(['provider_email' => $identity->email, 'metadata' => ['avatar_url' => $identity->avatarUrl]]);
        $plainToken = $user->createToken(
            $validated['device_name'] ?? 'squadup-oauth', ['*'], now()->addDays(30),
        )->plainTextToken;

        return response()->json(['data' => [
            'user' => new UserResource($user, includePrivate: true), 'token' => $plainToken,
        ]]);
    }

    public function link(string $provider, Request $request, OAuthTokenVerifier $verifier): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string', 'max:10000']]);
        $identity = $verifier->verify($provider, $validated['token']);
        $existing = SocialAccount::where([
            'provider' => $provider, 'provider_subject' => $identity->subject,
        ])->first();
        if ($existing && $existing->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false, 'message' => 'This provider account is already linked.',
                'code' => 'SOCIAL_ACCOUNT_ALREADY_LINKED',
            ], 409);
        }
        $current = $request->user()->socialAccounts()->where('provider', $provider)->first();
        if ($current && $current->provider_subject !== $identity->subject) {
            return response()->json([
                'success' => false,
                'message' => 'Unlink the current provider account before linking another one.',
                'code' => 'PROVIDER_ALREADY_LINKED',
            ], 409);
        }
        $this->persistLink($request->user(), $identity);

        return response()->json(['message' => ucfirst($provider).' account linked.']);
    }

    public function unlink(string $provider, Request $request): JsonResponse
    {
        $account = $request->user()->socialAccounts()->where('provider', $provider)->firstOrFail();
        if ($request->user()->password_set === false && $request->user()->socialAccounts()->count() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Set a password or link another provider before unlinking.',
                'code' => 'LAST_SIGN_IN_METHOD',
            ], 422);
        }
        $account->delete();

        return response()->json(['message' => ucfirst($provider).' account unlinked.']);
    }

    public function accounts(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->socialAccounts()
            ->get(['id', 'provider', 'provider_email', 'created_at'])]);
    }

    private function createUser(ProviderIdentity $identity): User
    {
        return DB::transaction(function () use ($identity): User {
            $base = Str::slug($identity->name, '_') ?: 'member';
            $base = mb_substr(preg_replace('/[^a-z0-9_.]/', '', $base), 0, 24);
            do {
                $username = $base.'_'.Str::lower(Str::random(6));
            } while (User::withTrashed()->where('username', $username)->exists());

            $user = User::create([
                'username' => $username,
                'display_name' => mb_substr($identity->name, 0, 80),
                'email' => $identity->email,
                'password' => Str::random(64),
                'password_set' => false,
                'email_verified_at' => now(),
                'account_status' => AccountStatus::Active,
            ]);
            $this->persistLink($user, $identity);

            return $user;
        });
    }

    private function persistLink(User $user, ProviderIdentity $identity): SocialAccount
    {
        return $user->socialAccounts()->updateOrCreate(
            ['provider' => $identity->provider],
            [
                'provider_subject' => $identity->subject,
                'provider_email' => $identity->email,
                'metadata' => ['avatar_url' => $identity->avatarUrl],
            ],
        );
    }
}

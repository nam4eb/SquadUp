<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\RegisterUser;
use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser->handle($request->validated());
        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Account created. Please verify your email address.',
            'data' => [
                'user' => new UserResource($user, includePrivate: true),
                'token' => $this->token($user, $request->string('device_name')->toString()),
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        }

        if ($user->account_status !== AccountStatus::Active) {
            return response()->json([
                'success' => false,
                'message' => 'This account is not active.',
                'code' => 'ACCOUNT_DISABLED',
            ], 403);
        }

        $user->forceFill(['last_active_at' => now()])->save();

        return response()->json(['data' => [
            'user' => new UserResource($user, includePrivate: true),
            'token' => $this->token($user, $request->string('device_name')->toString()),
        ]]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user(), includePrivate: true);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out from this device.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Signed out from all devices.']);
    }

    private function token(User $user, string $deviceName): string
    {
        return $user->createToken($deviceName, ['*'], now()->addDays(30))->plainTextToken;
    }
}

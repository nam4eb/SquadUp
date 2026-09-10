<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($validated['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => ['The password is incorrect.']]);
        }
        DB::transaction(function () use ($request): void {
            $request->user()->tokens()->delete();
            $request->user()->forceFill(['account_status' => AccountStatus::Deleted])->save();
            $request->user()->delete();
        });

        return response()->json(['message' => 'Account deactivated and signed out.']);
    }
}

<?php

namespace App\Actions\Auth;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    public function handle(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            return User::query()->create([
                'username' => $attributes['username'],
                'display_name' => $attributes['display_name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'account_status' => AccountStatus::Active,
                'presence_status' => 'offline',
            ]);
        });
    }
}

<?php

namespace App\Models;

use App\Enums\AccountStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasName, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'display_name',
        'email',
        'password',
        'password_set',
        'avatar_path',
        'cover_path',
        'bio',
        'gender',
        'date_of_birth',
        'location',
        'default_city',
        'default_area_latitude',
        'default_area_longitude',
        'timezone',
        'last_active_at',
        'presence_status',
        'account_status',
        'onboarding_step',
        'onboarding_completed_at',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'last_active_at' => 'datetime',
            'account_status' => AccountStatus::class,
            'onboarding_completed_at' => 'datetime',
            'default_area_latitude' => 'float',
            'default_area_longitude' => 'float',
            'password' => 'hashed',
            'password_set' => 'boolean',
            'is_admin' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin
            && $this->account_status === AccountStatus::Active
            && $this->hasVerifiedEmail();
    }

    public function getFilamentName(): string
    {
        return $this->display_name;
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function pushDevices(): HasMany
    {
        return $this->hasMany(PushDevice::class);
    }

    public function sportProfiles(): HasMany
    {
        return $this->hasMany(UserSportProfile::class);
    }

    public function reputation(): HasOne
    {
        return $this->hasOne(UserReputation::class);
    }
}

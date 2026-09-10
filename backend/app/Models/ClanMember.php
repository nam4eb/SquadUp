<?php

namespace App\Models;

use App\Enums\ClanMemberStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClanMember extends Model
{
    use HasUuids;

    protected $fillable = [
        'clan_id', 'user_id', 'clan_role_id', 'invited_by', 'status',
        'joined_at', 'left_at',
    ];

    protected function casts(): array
    {
        return ['status' => ClanMemberStatus::class, 'joined_at' => 'datetime', 'left_at' => 'datetime'];
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(ClanRole::class, 'clan_role_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function hasPermission(string $permission): bool
    {
        return $this->status === ClanMemberStatus::Active
            && $this->role?->permissions()->where('permission', $permission)->exists();
    }
}

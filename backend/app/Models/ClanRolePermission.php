<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClanRolePermission extends Model
{
    protected $fillable = ['clan_role_id', 'permission'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(ClanRole::class, 'clan_role_id');
    }
}

<?php

namespace App\Actions\Clans;

use App\Exceptions\ClanException;
use App\Models\Clan;
use App\Models\ClanRole;
use App\Models\User;
use App\Support\ClanAuthorizer;
use Illuminate\Support\Facades\DB;

class ManageClanRole
{
    public function create(Clan $clan, User $actor, array $attributes): ClanRole
    {
        $this->authorize($clan, $actor);

        return DB::transaction(function () use ($clan, $attributes): ClanRole {
            $permissions = $attributes['permissions'];
            unset($attributes['permissions']);
            $role = $clan->roles()->create([...$attributes, 'is_system' => false]);
            $role->permissions()->createMany(
                array_map(fn (string $permission): array => ['permission' => $permission], $permissions),
            );

            return $role->load('permissions');
        });
    }

    public function update(Clan $clan, ClanRole $role, User $actor, array $attributes): ClanRole
    {
        $this->authorize($clan, $actor);
        $this->ensureRoleBelongsToClan($clan, $role);
        if ($role->slug === 'owner') {
            throw new ClanException('The Owner role cannot be modified.', 'CLAN_OWNER_ROLE_IMMUTABLE', 422);
        }

        return DB::transaction(function () use ($role, $attributes): ClanRole {
            $permissions = $attributes['permissions'] ?? null;
            unset($attributes['permissions']);
            $role->update($attributes);
            if ($permissions !== null) {
                $role->permissions()->delete();
                $role->permissions()->createMany(
                    array_map(fn (string $permission): array => ['permission' => $permission], $permissions),
                );
            }

            return $role->load('permissions');
        });
    }

    public function delete(Clan $clan, ClanRole $role, User $actor): void
    {
        $this->authorize($clan, $actor);
        $this->ensureRoleBelongsToClan($clan, $role);
        if ($role->is_system) {
            throw new ClanException('System roles cannot be deleted.', 'CLAN_SYSTEM_ROLE_IMMUTABLE', 422);
        }
        if ($clan->members()->where('clan_role_id', $role->id)->exists()) {
            throw new ClanException('Reassign members before deleting this role.', 'CLAN_ROLE_IN_USE', 422);
        }
        $role->delete();
    }

    private function authorize(Clan $clan, User $actor): void
    {
        if (! ClanAuthorizer::allows($clan, $actor, 'manage_roles')) {
            throw new ClanException('You cannot manage clan roles.', 'CLAN_PERMISSION_DENIED', 403);
        }
    }

    private function ensureRoleBelongsToClan(Clan $clan, ClanRole $role): void
    {
        if ($role->clan_id !== $clan->id) {
            throw new ClanException('Role does not belong to this clan.', 'CLAN_ROLE_MISMATCH', 404);
        }
    }
}

<?php

namespace App\Actions\Clans;

use App\Enums\ClanMemberStatus;
use App\Models\Clan;
use App\Models\User;
use App\Support\ClanPermissions;
use Illuminate\Support\Facades\DB;

class CreateClan
{
    public function handle(User $owner, array $attributes): Clan
    {
        return DB::transaction(function () use ($owner, $attributes): Clan {
            $clan = Clan::create(['owner_id' => $owner->id, ...$attributes]);
            $defaults = [
                ['Owner', 'owner', ClanPermissions::ALL],
                ['Leader', 'leader', ClanPermissions::ALL],
                ['Vice Leader', 'vice-leader', ['manage_clan', 'manage_members', 'invite_members', 'remove_members', 'create_events', 'manage_events', 'manage_chat', 'view_statistics']],
                ['Moderator', 'moderator', ['invite_members', 'remove_members', 'manage_chat', 'manage_content']],
                ['Member', 'member', ['create_events']],
            ];
            $ownerRole = null;
            foreach ($defaults as $index => [$name, $slug, $permissions]) {
                $role = $clan->roles()->create([
                    'name' => $name, 'slug' => $slug,
                    'sort_order' => ($index + 1) * 10, 'is_system' => true,
                ]);
                $role->permissions()->createMany(
                    array_map(fn (string $permission): array => ['permission' => $permission], $permissions),
                );
                if ($slug === 'owner') {
                    $ownerRole = $role;
                }
            }
            $clan->members()->create([
                'user_id' => $owner->id,
                'clan_role_id' => $ownerRole->id,
                'status' => ClanMemberStatus::Active,
                'joined_at' => now(),
            ]);
            $clan->conversation()->create([
                'type' => 'clan',
                'name' => $clan->name,
                'created_by' => $owner->id,
            ]);

            return $clan->load('owner')->loadCount(['members as active_members_count' => fn ($query) => $query->where('status', 'active')]);
        });
    }
}

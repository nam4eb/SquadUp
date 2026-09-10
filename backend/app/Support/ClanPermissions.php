<?php

namespace App\Support;

final class ClanPermissions
{
    public const ALL = [
        'manage_clan', 'manage_members', 'invite_members', 'remove_members',
        'ban_members', 'manage_roles', 'create_events', 'manage_events',
        'manage_chat', 'manage_content', 'view_statistics',
    ];
}

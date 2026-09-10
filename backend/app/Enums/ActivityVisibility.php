<?php

namespace App\Enums;

enum ActivityVisibility: string
{
    case Public = 'public';
    case Friends = 'friends';
    case Clan = 'clan';
    case InviteOnly = 'invite_only';
    case Private = 'private';
}

<?php

namespace App\Enums;

enum ClanMemberStatus: string
{
    case Invited = 'invited';
    case Requested = 'requested';
    case Active = 'active';
    case Left = 'left';
    case Removed = 'removed';
    case Banned = 'banned';
}

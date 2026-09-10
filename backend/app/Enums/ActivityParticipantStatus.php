<?php

namespace App\Enums;

enum ActivityParticipantStatus: string
{
    case Joined = 'joined';
    case Requested = 'requested';
    case Waitlisted = 'waitlisted';
    case Left = 'left';
    case Removed = 'removed';
    case Rejected = 'rejected';
    case Banned = 'banned';
    case Attended = 'attended';
    case Absent = 'absent';
}

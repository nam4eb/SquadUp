<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Full = 'full';
    case Locked = 'locked';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}

<?php

namespace App\Enums;

/**
 * An event's publishing state. The column stays a plain string; compare with ->value.
 */
enum EventStatus: string
{
    case Draft = 'draft';
    case Upcoming = 'upcoming';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}

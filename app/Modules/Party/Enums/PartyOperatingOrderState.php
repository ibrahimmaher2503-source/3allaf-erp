<?php

declare(strict_types=1);

namespace App\Modules\Party\Enums;

enum PartyOperatingOrderState: string
{
    case Draft = 'draft';
    case Released = 'released';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}

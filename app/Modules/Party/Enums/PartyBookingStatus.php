<?php

declare(strict_types=1);

namespace App\Modules\Party\Enums;

enum PartyBookingStatus: string
{
    case Draft = 'draft';
    case Tentative = 'tentative';
    case Confirmed = 'confirmed';
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';
    case InOperation = 'in_operation';
    case CompletedPendingSettlement = 'completed_pending_settlement';
    case Closed = 'closed';
}

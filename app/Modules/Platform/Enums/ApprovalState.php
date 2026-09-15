<?php

namespace App\Modules\Platform\Enums;

enum ApprovalState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }

    public function canTransitionTo(self $state): bool
    {
        return $this === self::Pending && $state !== self::Pending;
    }
}

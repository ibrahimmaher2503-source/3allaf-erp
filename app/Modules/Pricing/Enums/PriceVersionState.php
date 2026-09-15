<?php

namespace App\Modules\Pricing\Enums;

enum PriceVersionState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Superseded, self::Cancelled], true);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Retail\Enums;

/**
 * Shift states per `docs/32` §5. `reopened` is intentionally absent: the
 * specification permits it only through an explicit exceptional policy that
 * has not been approved, and inventing it would create a route back into a
 * closed shift.
 */
enum ShiftState: string
{
    case Open = 'open';
    case ClosingSubmitted = 'closing_submitted';
    case VarianceReview = 'variance_review';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /** A shift that still occupies its drawer and cashier. */
    public function isActive(): bool
    {
        return $this !== self::Closed && $this !== self::Cancelled;
    }

    /** A shift that may still accept sales and cash movements. */
    public function acceptsActivity(): bool
    {
        return $this === self::Open;
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed || $this === self::Cancelled;
    }
}

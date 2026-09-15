<?php

namespace App\Modules\Platform\Enums;

enum AttachmentState: string
{
    case Temporary = 'temporary';
    case Active = 'active';
    case Quarantined = 'quarantined';
    case Redacted = 'redacted';
    case Expired = 'expired';
    case Deleted = 'deleted';

    public function isDeliverable(): bool
    {
        return $this === self::Active;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Redacted, self::Expired, self::Deleted], true);
    }
}

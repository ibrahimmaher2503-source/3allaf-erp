<?php

declare(strict_types=1);

namespace App\Modules\Retail\Enums;

enum OfflineTransactionState: string
{
    case Queued = 'queued';
    case Syncing = 'syncing';
    case Accepted = 'accepted';
    case Conflict = 'conflict';
    case Rejected = 'rejected';
}

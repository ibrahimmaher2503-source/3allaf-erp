<?php

declare(strict_types=1);

namespace App\Modules\Party\Enums;

enum PartyInvoiceState: string
{
    case Draft = 'draft';
    case ActiveWorking = 'active_working';
    case FrozenForOperation = 'frozen_for_operation';
    case Finalizing = 'finalizing';
    case Final = 'final';
    case Cancelled = 'cancelled';
    case CorrectedByReference = 'corrected_by_reference';
}

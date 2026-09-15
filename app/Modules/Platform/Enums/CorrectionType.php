<?php

namespace App\Modules\Platform\Enums;

enum CorrectionType: string
{
    case Cancellation = 'cancellation';
    case Reversal = 'reversal';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case Replacement = 'replacement';
}

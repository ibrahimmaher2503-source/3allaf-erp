<?php

declare(strict_types=1);

namespace App\Modules\Retail\Exceptions;

use InvalidArgumentException;

/** A localized POS configuration failure that is safe to show to the cashier. */
final class PosFinancialConfigurationException extends InvalidArgumentException {}

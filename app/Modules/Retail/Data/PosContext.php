<?php

declare(strict_types=1);

namespace App\Modules\Retail\Data;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\PosShift;

final readonly class PosContext
{
    public function __construct(
        public ?Branch $branch,
        public ?Store $store,
        public ?CashDrawer $drawer,
        public ?PosShift $shift,
        public ?string $disabledReason = null,
    ) {}

    public function isReady(): bool
    {
        return $this->branch !== null
            && $this->store !== null
            && $this->drawer !== null
            && $this->shift !== null
            && $this->disabledReason === null;
    }
}

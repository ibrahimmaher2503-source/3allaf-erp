<?php

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;

final class DefaultOperatingContext
{
    public function branch(User $actor): ?Branch
    {
        return $this->only(
            Branch::query()->visibleTo($actor)->where('status', 'active')->orderBy('id')->limit(2)->get(),
        );
    }

    public function warehouse(User $actor): ?Store
    {
        return $this->store($actor, 'warehouse');
    }

    public function sellingOutlet(User $actor): ?Store
    {
        return $this->store($actor, 'selling');
    }

    private function store(User $actor, string $type): ?Store
    {
        return $this->only(
            Store::query()->visibleTo($actor)->where('status', 'active')->where('type', $type)->orderBy('id')->limit(2)->get(),
        );
    }

    private function only(\Illuminate\Support\Collection $records): Branch|Store|null
    {
        return $records->count() === 1 ? $records->first() : null;
    }
}

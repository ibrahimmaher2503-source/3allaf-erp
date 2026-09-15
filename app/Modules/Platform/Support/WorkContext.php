<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class WorkContext
{
    public const SESSION_KEY = 'ui.work_store_id';

    /** @var array<int, Collection<int, Store>> */
    private array $requestStores = [];

    /** @return Collection<int, Store> */
    public function stores(User $actor): Collection
    {
        return $this->requestStores[$actor->id] ??= Store::query()
            ->with('company:id,name_ar,name_en')
            ->visibleTo($actor)
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name_ar', 'name_en']);
    }

    public function selected(User $actor): ?Store
    {
        $storeId = session(self::SESSION_KEY);
        if (! is_numeric($storeId)) {
            session()->forget(self::SESSION_KEY);

            return null;
        }

        $store = $this->stores($actor)->firstWhere('id', (int) $storeId);
        if ($store === null) {
            session()->forget(self::SESSION_KEY);
        }

        return $store;
    }

    public function select(User $actor, ?int $storeId): void
    {
        if ($storeId === null) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        if (! Store::query()->visibleTo($actor)->where('status', 'active')->whereKey($storeId)->exists()) {
            throw ValidationException::withMessages(['store_id' => __('The selected work location is unavailable.')]);
        }

        session([self::SESSION_KEY => $storeId]);
    }

    public function id(User $actor): ?int
    {
        return $this->selected($actor)?->id;
    }
}

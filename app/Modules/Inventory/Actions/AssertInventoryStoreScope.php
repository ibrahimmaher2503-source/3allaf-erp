<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Models\Store;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

final class AssertInventoryStoreScope
{
    public function execute(int $storeId): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new AuthorizationException(__('Authentication is required for this inventory operation.'));
        }
        if ($user->is_super_admin) {
            return;
        }

        if (! Store::query()->visibleTo($user)->whereKey($storeId)->exists()) {
            throw new AuthorizationException(__('You are not authorized for this inventory store.'));
        }
    }

    public function transfer(StockTransfer $transfer, bool $source = true, bool $destination = true): void
    {
        if ($source) {
            $this->execute($transfer->source_store_id);
        }

        if ($destination) {
            $this->execute($transfer->destination_store_id);
        }
    }
}

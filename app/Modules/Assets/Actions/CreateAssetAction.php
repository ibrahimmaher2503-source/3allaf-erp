<?php

declare(strict_types=1);

namespace App\Modules\Assets\Actions;

use App\Models\User;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateAssetAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $user, array $data): RentalAsset
    {
        Gate::forUser($user)->authorize('rental_assets.create');
        $store = Store::query()->findOrFail((int) $data['store_id']);
        abort_unless((int) $store->branch_id === (int) $data['branch_id'], 422);
        abort_unless($user->is_super_admin || $user->canAccessBranch((int) $data['branch_id']) || $user->canAccessStore($store->id), 403);
        if ($store->status !== 'active') throw ValidationException::withMessages(['store_id' => __('The selected store is inactive.')]);
        if (filled($data['cost_value'] ?? null)) Gate::forUser($user)->authorize('rental_assets.cost_edit');

        return DB::transaction(function () use ($user, $data, $store): RentalAsset {
            $asset = RentalAsset::create([
                'public_id' => (string) Str::uuid(), 'code' => strtoupper(trim((string) $data['code'])),
                'name_ar' => trim((string) $data['name_ar']), 'name_en' => trim((string) $data['name_en']),
                'category' => filled($data['category'] ?? null) ? trim((string) $data['category']) : null,
                'branch_id' => $store->branch_id, 'store_id' => $store->id,
                'location' => $data['location'] ?? null, 'condition' => $data['condition'] ?? 'good',
                'status' => 'available', 'cost_value' => $data['cost_value'] ?? null,
                'cost_currency' => $data['cost_currency'] ?? 'EGP', 'created_by' => $user->id,
            ]);
            app(RecordAuditEvent::class)->execute('assets', 'asset_created', $asset, after: $asset->only(['code', 'name_en', 'category', 'branch_id', 'store_id', 'condition', 'status', 'cost_value']), branchId: $asset->branch_id, storeId: $asset->store_id);
            return $asset;
        });
    }
}

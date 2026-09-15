<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SaveBrandAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data, ?int $id = null): Brand
    {
        Gate::authorize($id ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        return DB::transaction(function () use ($data, $id): Brand {
            $userId = Auth::id();
            $brand = $id === null ? null : Brand::query()->lockForUpdate()->findOrFail($id);
            $status = (string) ($data['status'] ?? 'active');

            if ($brand !== null && $brand->status === 'active' && $status === 'inactive' && $brand->products()->where('status', 'active')->exists()) {
                throw new InvalidArgumentException(__('Cannot deactivate a brand with active products.'));
            }

            $attributes = [
                'code' => strtoupper(trim((string) $data['code'])),
                'name_ar' => trim((string) $data['name_ar']),
                'name_en' => trim((string) $data['name_en']),
                'status' => $status,
                'updated_by' => $userId,
            ];

            if ($brand === null) {
                $attributes['created_by'] = $userId;
                $brand = Brand::query()->create($attributes);
                $event = 'create_brand';
                $before = null;
            } else {
                $before = $brand->only(['code', 'name_ar', 'name_en', 'status']);
                $brand->update($attributes);
                $event = 'update_brand';
            }

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: $event,
                source: $brand,
                before: $before,
                after: $brand->fresh()->only(['code', 'name_ar', 'name_en', 'status']),
            );

            return $brand->fresh();
        });
    }
}

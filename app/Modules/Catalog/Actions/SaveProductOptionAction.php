<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ProductOptionGroup;
use App\Modules\Catalog\Models\ProductOptionValue;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SaveProductOptionAction
{
    /** @param array<string, mixed> $data */
    public function saveGroup(array $data, ?int $id = null): ProductOptionGroup
    {
        Gate::authorize($id ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        return DB::transaction(function () use ($data, $id): ProductOptionGroup {
            $group = $id ? ProductOptionGroup::query()->lockForUpdate()->findOrFail($id) : null;
            $code = strtoupper(trim((string) ($data['code'] ?? '')));
            $this->assertIdentity($code, $data);
            if ($group && $group->code !== $code) {
                throw new InvalidArgumentException(__('Option group codes are immutable after creation.'));
            }
            if (ProductOptionGroup::query()->where('code', $code)->when($group, fn ($query) => $query->whereKeyNot($group->id))->exists()) {
                throw new InvalidArgumentException(__('This option group code already exists.'));
            }

            $status = $this->status($data['status'] ?? 'active');
            if ($group && $status === 'inactive' && $group->status === 'active' && DB::table('product_variant_values')
                ->join('products', 'products.id', '=', 'product_variant_values.product_id')
                ->where('product_option_group_id', $group->id)
                ->where('products.status', 'active')
                ->exists()) {
                throw new InvalidArgumentException(__('An option group referenced by an active variation cannot be inactivated.'));
            }

            $attributes = [
                'code' => $code,
                'name_ar' => trim((string) $data['name_ar']),
                'name_en' => trim((string) $data['name_en']),
                'status' => $status,
                'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            ];
            $before = $group?->only(array_keys($attributes));
            $group = $group ? tap($group)->update($attributes) : ProductOptionGroup::query()->create($attributes);
            app(RecordAuditEvent::class)->execute(category: 'master_data', event: $id ? 'update_product_option_group' : 'create_product_option_group', source: $group, before: $before, after: $group->fresh()->only(array_keys($attributes)));

            return $group->fresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function saveValue(int $groupId, array $data, ?int $id = null): ProductOptionValue
    {
        Gate::authorize($id ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        return DB::transaction(function () use ($groupId, $data, $id): ProductOptionValue {
            $group = ProductOptionGroup::query()->lockForUpdate()->findOrFail($groupId);
            $value = $id ? ProductOptionValue::query()->where('product_option_group_id', $group->id)->lockForUpdate()->findOrFail($id) : null;
            $code = strtoupper(trim((string) ($data['code'] ?? '')));
            $this->assertIdentity($code, $data);
            if ($value && $value->code !== $code) {
                throw new InvalidArgumentException(__('Option value codes are immutable after creation.'));
            }
            if (ProductOptionValue::query()->where('product_option_group_id', $group->id)->where('code', $code)->when($value, fn ($query) => $query->whereKeyNot($value->id))->exists()) {
                throw new InvalidArgumentException(__('This option value code already exists in the group.'));
            }
            $swatch = strtoupper(trim((string) ($data['colour_swatch'] ?? '')));
            if ($swatch !== '' && ! preg_match('/^#[0-9A-F]{6}([0-9A-F]{2})?$/', $swatch)) {
                throw new InvalidArgumentException(__('Colour swatches must use a valid six or eight digit hexadecimal value.'));
            }
            $status = $this->status($data['status'] ?? 'active');
            if ($value && $status === 'inactive' && $value->status === 'active' && DB::table('product_variant_values')->join('products', 'products.id', '=', 'product_variant_values.product_id')->where('product_option_value_id', $value->id)->where('products.status', 'active')->exists()) {
                throw new InvalidArgumentException(__('An option value referenced by an active variation cannot be inactivated.'));
            }

            $attributes = [
                'product_option_group_id' => $group->id,
                'code' => $code,
                'name_ar' => trim((string) $data['name_ar']),
                'name_en' => trim((string) $data['name_en']),
                'colour_swatch' => $swatch ?: null,
                'status' => $status,
                'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            ];
            $before = $value?->only(array_keys($attributes));
            $value = $value ? tap($value)->update($attributes) : ProductOptionValue::query()->create($attributes);
            app(RecordAuditEvent::class)->execute(category: 'master_data', event: $id ? 'update_product_option_value' : 'create_product_option_value', source: $value, before: $before, after: $value->fresh()->only(array_keys($attributes)));

            return $value->fresh();
        });
    }

    /** @param array<string, mixed> $data */
    private function assertIdentity(string $code, array $data): void
    {
        if (! preg_match('/^[A-Z0-9][A-Z0-9_-]{0,49}$/', $code)) {
            throw new InvalidArgumentException(__('Option codes may contain only letters, numbers, underscores, and hyphens.'));
        }
        if (trim((string) ($data['name_ar'] ?? '')) === '' || trim((string) ($data['name_en'] ?? '')) === '') {
            throw new InvalidArgumentException(__('Arabic and English option labels are required.'));
        }
    }

    private function status(mixed $status): string
    {
        $status = trim((string) $status);
        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException(__('The selected option status is not supported.'));
        }

        return $status;
    }
}

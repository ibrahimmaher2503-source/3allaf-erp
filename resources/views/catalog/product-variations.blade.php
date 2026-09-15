<?php

use App\Modules\Catalog\Actions\GenerateProductVariationsAction;
use App\Modules\Catalog\Actions\ManageProductMediaAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOptionGroup;
use App\Modules\Catalog\Models\ProductOptionValue;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public int $productId;
    public array $selectedGroups = [];
    public array $selectedValues = [];
    public array $rows = [];
    public ?int $detailVariantId = null;
    public mixed $variantMediaUpload = null;
    public string $variantMediaRole = 'additional';

    public function mount(Product $product): void
    {
        Gate::authorize('products_categories_brands.view');
        abort_if($product->isVariant(), 404);
        $this->productId = $product->id;
        $configured = DB::table('product_family_option_groups')->where('product_id', $product->id)->orderBy('sort_order')->get();
        $this->selectedGroups = $configured->pluck('product_option_group_id')->map(fn ($id): string => (string) $id)->all();
        foreach ($configured as $group) {
            $this->selectedValues[(int) $group->product_option_group_id] = DB::table('product_family_option_values')
                ->join('product_option_values', 'product_option_values.id', '=', 'product_family_option_values.product_option_value_id')
                ->where('product_family_option_values.product_id', $product->id)
                ->where('product_option_values.product_option_group_id', $group->product_option_group_id)
                ->pluck('product_option_values.id')->map(fn ($id): string => (string) $id)->all();
        }
        $this->refreshRows();
    }

    public function updatedSelectedGroups(): void
    {
        $allowed = collect($this->selectedGroups)->map(fn ($id): int => (int) $id)->filter()->take(3)->values()->all();
        $this->selectedGroups = array_map('strval', $allowed);
        foreach (array_keys($this->selectedValues) as $groupId) {
            if (! in_array((int) $groupId, $allowed, true)) unset($this->selectedValues[$groupId]);
        }
        $this->refreshRows();
    }

    public function updatedSelectedValues(): void
    {
        $this->refreshRows();
    }

    public function toggleValue(int $groupId, int $valueId): void
    {
        $groupId = (int) $groupId;
        $valueId = (int) $valueId;
        if (! in_array($groupId, array_map('intval', $this->selectedGroups), true)) return;

        $current = collect($this->selectedValues[$groupId] ?? [])->map(fn ($id): int => (int) $id)->filter()->values();
        $value = ProductOptionValue::query()->whereKey($valueId)->where('product_option_group_id', $groupId)->first();
        if (! $value || ($value->status !== 'active' && ! $current->contains($valueId))) return;

        $this->selectedValues[$groupId] = ($current->contains($valueId)
            ? $current->reject(fn (int $id): bool => $id === $valueId)
            : $current->push($valueId)
        )->values()->map(fn (int $id): string => (string) $id)->all();
        $this->refreshRows();
    }

    public function generate(GenerateProductVariationsAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');
        $selection = [];
        foreach ($this->selectedGroups as $groupId) {
            $selection[(int) $groupId] = $this->selectedValues[(int) $groupId] ?? [];
        }
        $input = [];
        foreach ($this->rows as $row) {
            $input[$row['signature']] = [
                'sku' => $row['sku'] ?? '',
                'barcode' => $row['barcode'] ?? '',
                'status' => $row['status'] ?? 'inactive',
                'reorder_threshold' => $row['reorder_threshold'] ?? null,
            ];
        }
        try {
            $action->execute(Product::query()->findOrFail($this->productId), $selection, $input);
            $this->refreshRows();
            Flux::toast(variant: 'success', text: __('Variation matrix generated. Existing combinations were preserved.'));
        } catch (\Throwable $exception) {
            $this->addError('matrix', \App\Support\UserSafeError::message($exception));
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function inactivate(int $id, GenerateProductVariationsAction $action): void
    {
        $variant = Product::query()->where('parent_product_id', $this->productId)->findOrFail($id);
        $action->inactivate($variant);
        $this->refreshRows();
    }

    public function openDetail(int $id): void
    {
        $this->detailVariantId = Product::query()->where('parent_product_id', $this->productId)->findOrFail($id)->id;
        $this->resetValidation('variantMediaUpload');
    }

    public function uploadVariantImage(ManageProductMediaAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');
        abort_if($this->detailVariantId === null, 422);
        $this->validate(['variantMediaUpload' => ['required', 'file'], 'variantMediaRole' => ['required', 'in:main,additional']]);
        try {
            $action->upload(Product::query()->where('parent_product_id', $this->productId)->findOrFail($this->detailVariantId), $this->variantMediaUpload, $this->variantMediaRole);
            $this->variantMediaUpload = null;
            Flux::toast(variant: 'success', text: __('Variation media saved.'));
        } catch (\Throwable $exception) {
            $this->addError('variantMediaUpload', \App\Support\UserSafeError::message($exception));
        }
    }

    public function removeVariantImage(int $imageId, ManageProductMediaAction $action): void
    {
        abort_if($this->detailVariantId === null, 422);
        $action->revoke(Product::query()->where('parent_product_id', $this->productId)->findOrFail($this->detailVariantId), $imageId);
    }

    /** @return array<int, array{signature:string,value_ids:array<int,int>,label:string}> */
    public function combinations(): array
    {
        if ($this->selectedGroups === []) {
            return [];
        }

        $groups = ProductOptionGroup::query()->with('values')->whereIn('id', array_map('intval', $this->selectedGroups))->get()->keyBy('id');
        $result = [['value_ids' => [], 'labels' => []]];
        foreach ($this->selectedGroups as $groupId) {
            $group = $groups->get((int) $groupId);
            $ids = collect($this->selectedValues[(int) $groupId] ?? [])->map(fn ($id): int => (int) $id)->filter()->values();
            if (! $group || $ids->isEmpty()) return [];
            $values = $group->values->whereIn('id', $ids)->keyBy('id');
            $next = [];
            foreach ($result as $base) foreach ($ids as $valueId) {
                $value = $values->get($valueId);
                if (! $value) continue;
                $next[] = ['value_ids' => [...$base['value_ids'], $valueId], 'labels' => [...$base['labels'], (str_starts_with(app()->getLocale(), 'ar') ? $group->name_ar.': '.$value->name_ar : $group->name_en.': '.$value->name_en)]];
                if (count($next) > 100) return $next;
            }
            $result = $next;
        }

        return array_map(function (array $combo) use ($groups): array {
            $signature = collect($combo['value_ids'])->map(function (int $valueId) use ($groups): string {
                foreach ($groups as $group) if ($group->values->contains('id', $valueId)) return $group->id.':'.$valueId;
                return '0:'.$valueId;
            })->implode('|');
            return ['signature' => $signature, 'value_ids' => $combo['value_ids'], 'label' => implode(' · ', $combo['labels'])];
        }, $result);
    }

    private function refreshRows(): void
    {
        $existing = Product::query()->where('parent_product_id', $this->productId)->with(['variantValues.group', 'variantValues.value', 'barcodes'])->get()->keyBy('variant_signature');
        $previous = collect($this->rows)->keyBy('signature');
        $rows = [];
        foreach ($this->combinations() as $combination) {
            $variant = $existing->get($combination['signature']);
            $old = $previous->get($combination['signature'], []);
            $rows[] = [
                'signature' => $combination['signature'],
                'label' => $combination['label'],
                'variant_id' => $variant?->id,
                'sku' => $variant?->item_code ?? ($old['sku'] ?? ''),
                'barcode' => $variant?->barcodes->firstWhere('status', 'active')?->barcode ?? ($old['barcode'] ?? ''),
                'status' => $variant?->status ?? ($old['status'] ?? 'inactive'),
                'reorder_threshold' => $variant?->reorder_threshold ?? ($old['reorder_threshold'] ?? ''),
            ];
        }
        $this->rows = $rows;
    }

    public function render()
    {
        $family = Product::query()->with(['variants.images.attachment', 'variants.barcodes', 'variants.variantValues.group', 'variants.variantValues.value'])->findOrFail($this->productId);
        $variantIds = $family->variants->pluck('id');
        return view('catalog.product-variations', [
            'family' => $family,
            'optionGroups' => ProductOptionGroup::query()->with('values')->where(fn ($query) => $query->where('status', 'active')->orWhereIn('id', array_map('intval', $this->selectedGroups)))->orderBy('sort_order')->limit(20)->get(),
            'priceReady' => Product::query()->whereIn('id', $variantIds)->where('sale_price', '>', 0)->pluck('id')->flip(),
            'stockReady' => DB::table('stock_balances')->whereIn('product_id', $variantIds)->whereRaw('(on_hand - reserved) > 0')->pluck('product_id')->flip(),
            'detailVariant' => $this->detailVariantId ? $family->variants->firstWhere('id', $this->detailVariantId) : null,
        ]);
    }
}; ?>

<section class="space-y-6 rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 sm:p-6" aria-labelledby="variations-heading">
    <div class="flex flex-col gap-4 border-b border-zinc-200 pb-5 dark:border-zinc-800 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading id="variations-heading" size="lg">{{ __('Variations') }}</flux:heading>
            <flux:text class="mt-1 max-w-3xl text-sm">{{ __('Explicit variation families create independent child SKUs. Ordinary colour and size fields above remain descriptive and never create variants.') }}</flux:text>
        </div>
        <flux:button href="{{ route('catalog.product-options') }}" variant="subtle" icon="swatch">{{ __('Product Filters') }}</flux:button>
    </div>
    @if ($family->product_type !== 'standard')
        <flux:callout variant="warning">{{ __('Only standard products can have variations. Services and composite products remain unavailable here.') }}</flux:callout>
    @else
        @if ($errors->has('matrix'))<flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first('matrix') }}</flux:callout>@endif
        <fieldset @disabled($family->variants->isNotEmpty()) class="space-y-3">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <legend class="text-sm font-bold">{{ __('Select 1 to 3 option groups') }}</legend>
                @if ($family->variants->isNotEmpty())<span class="text-xs text-zinc-500">{{ __('Variation option groups are immutable after the first SKU is generated.') }}</span>@endif
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($optionGroups as $group)
                    <label wire:key="variation-group-{{ $group->id }}" class="flex min-h-16 cursor-pointer items-center justify-between gap-3 rounded-xl border border-zinc-200 p-4 transition has-[:checked]:border-cyan-600 has-[:checked]:bg-cyan-50 dark:border-zinc-700 dark:has-[:checked]:bg-cyan-950/20">
                        <span><strong class="block">{{ str_starts_with(app()->getLocale(), 'ar') ? $group->name_ar : $group->name_en }}</strong><span class="mt-1 block font-mono text-xs text-zinc-500">{{ $group->code }}</span></span>
                        <input type="checkbox" wire:model.live="selectedGroups" value="{{ $group->id }}" class="size-5 rounded border-zinc-300 text-cyan-700">
                    </label>
                @endforeach
            </div>
        </fieldset>

        @if ($selectedGroups === [])
            <flux:callout variant="info" icon="information-circle">{{ __('Select between one and three option groups.') }}</flux:callout>
        @endif

        @foreach($optionGroups->whereIn('id', array_map('intval', $selectedGroups)) as $group)
            <fieldset wire:key="variation-values-{{ $group->id }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <legend class="px-1 text-sm font-bold">{{ str_starts_with(app()->getLocale(), 'ar') ? $group->name_ar : $group->name_en }}</legend>
                <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($group->values as $value)
                        <label wire:key="variation-value-{{ $group->id }}-{{ $value->id }}" class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-zinc-200 px-3 py-2 has-[:checked]:border-cyan-600 has-[:checked]:bg-cyan-50 dark:border-zinc-700 dark:has-[:checked]:bg-cyan-950/20">
                            <input type="checkbox" wire:click="toggleValue({{ $group->id }}, {{ $value->id }})" value="{{ $value->id }}" class="size-5 rounded border-zinc-300 text-cyan-700" @checked(in_array((string) $value->id, $selectedValues[$group->id] ?? [], true)) @disabled($value->status !== 'active' && !in_array((string) $value->id, $selectedValues[$group->id] ?? [], true))>
                            @if($value->colour_swatch)<span class="size-5 shrink-0 rounded-full border" style="background-color:{{ $value->colour_swatch }}"></span>@endif
                            <span>{{ str_starts_with(app()->getLocale(), 'ar') ? $value->name_ar : $value->name_en }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
        @php($combinationCount = count($this->combinations()))
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-950"><div><strong>{{ trans_choice(':count combination|:count combinations', $combinationCount, ['count' => $combinationCount]) }}</strong><p class="text-xs text-zinc-500">{{ __('Maximum 100 child SKUs. Regeneration creates missing combinations only.') }}</p></div><x-status.badge :status="$combinationCount > 100 ? 'blocked' : ($combinationCount > 0 ? 'ready' : 'pending')" /></div>
        @if($combinationCount > 0 && $combinationCount <= 100)
            <div class="space-y-3 md:hidden">
                @foreach($rows as $index => $row)
                    <article wire:key="variant-card-{{ md5($row['signature']) }}" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div><div class="font-semibold">{{ $row['label'] }}</div><div class="mt-1 font-mono text-[10px] text-zinc-400">{{ $row['signature'] }}</div></div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <flux:input wire:model="rows.{{ $index }}.sku" :label="__('Immutable SKU')" :disabled="filled($row['variant_id'])" />
                            <flux:input wire:model="rows.{{ $index }}.barcode" :label="__('Barcode')" :disabled="filled($row['variant_id'])" />
                            <flux:select wire:model="rows.{{ $index }}.status" :label="__('Status')" :disabled="filled($row['variant_id'])"><flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option><flux:select.option value="active">{{ __('Active') }}</flux:select.option></flux:select>
                            <flux:input wire:model="rows.{{ $index }}.reorder_threshold" type="number" min="0" step="0.001" :label="__('Reorder threshold')" :disabled="filled($row['variant_id'])" />
                        </div>
                        <div class="flex flex-wrap gap-2">@if($row['variant_id'] && $priceReady->has($row['variant_id']))<x-status.badge status="ready" />@else<x-status.badge status="unpriced" />@endif @if($row['variant_id'] && $stockReady->has($row['variant_id']))<x-status.badge status="ready" />@else<x-status.badge status="out_of_stock" />@endif</div>
                        @if($row['variant_id'])<x-actions.button semantic="view" :label="__('Details & media')" wire:click="openDetail({{ $row['variant_id'] }})" size="sm" variant="subtle">{{ __('Details & media') }}</x-actions.button>@endif
                    </article>
                @endforeach
            </div>
            <div class="hidden overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800 md:block"><table class="data-table min-w-[980px] w-full text-sm"><thead><tr><th>{{ __('Options') }}</th><th>{{ __('Immutable SKU') }}</th><th>{{ __('Barcode') }}</th><th>{{ __('Status') }}</th><th>{{ __('Reorder threshold') }}</th><th>{{ __('Price') }}</th><th>{{ __('Stock') }}</th><th class="text-end">{{ __('Media') }}</th></tr></thead><tbody>@foreach($rows as $index => $row)<tr wire:key="variant-row-{{ md5($row['signature']) }}"><td><div class="font-semibold">{{ $row['label'] }}</div><div class="mt-1 font-mono text-[10px] text-zinc-400">{{ $row['signature'] }}</div></td><td><flux:input wire:model="rows.{{ $index }}.sku" aria-label="{{ __('Immutable SKU') }}" :disabled="filled($row['variant_id'])" /></td><td><flux:input wire:model="rows.{{ $index }}.barcode" aria-label="{{ __('Barcode') }}" :disabled="filled($row['variant_id'])" /></td><td><flux:select wire:model="rows.{{ $index }}.status" aria-label="{{ __('Status') }}" :disabled="filled($row['variant_id'])"><flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option><flux:select.option value="active">{{ __('Active') }}</flux:select.option></flux:select></td><td><flux:input wire:model="rows.{{ $index }}.reorder_threshold" type="number" min="0" step="0.001" aria-label="{{ __('Reorder threshold') }}" :disabled="filled($row['variant_id'])" /></td><td>@if($row['variant_id'] && $priceReady->has($row['variant_id']))<x-status.badge status="ready" />@else<x-status.badge status="unpriced" />@endif</td><td>@if($row['variant_id'] && $stockReady->has($row['variant_id']))<x-status.badge status="ready" />@else<x-status.badge status="out_of_stock" />@endif</td><td class="text-end">@if($row['variant_id'])<x-actions.button semantic="view" :label="__('Details & media')" wire:click="openDetail({{ $row['variant_id'] }})" size="sm" variant="subtle">{{ __('Details & media') }}</x-actions.button>@else<span class="text-xs text-zinc-400">{{ __('After generation') }}</span>@endif</td></tr>@endforeach</tbody></table></div>
            @can('products_categories_brands.edit')<div class="flex justify-end"><flux:button wire:click="generate" variant="primary" icon="sparkles" wire:loading.attr="disabled" wire:target="generate" :disabled="$combinationCount > 100">{{ __('Generate missing SKUs') }}</flux:button></div>@endcan
        @endif
    @endif

    @if($detailVariant)
        <div class="fixed inset-0 z-50 flex justify-end bg-zinc-950/45" wire:click.self="$set('detailVariantId', null)"><aside class="h-full w-full max-w-xl overflow-y-auto bg-white p-5 shadow-2xl dark:bg-zinc-900" role="dialog" aria-modal="true" aria-labelledby="variant-detail-heading"><div class="flex items-start justify-between"><div><flux:heading id="variant-detail-heading" size="xl">{{ $detailVariant->item_code }}</flux:heading><p class="mt-1 text-sm text-zinc-500">{{ $detailVariant->localizedVariationLabel() }}</p></div><flux:button wire:click="$set('detailVariantId', null)" variant="ghost" icon="x-mark" aria-label="{{ __('Close') }}" /></div><div class="mt-6 grid gap-3 sm:grid-cols-2"><div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-950"><span class="text-xs text-zinc-500">{{ __('Status') }}</span><div class="mt-1"><x-status.badge :status="$detailVariant->status" /></div></div><div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-950"><span class="text-xs text-zinc-500">{{ __('Barcode') }}</span><div class="mt-1 font-mono">{{ $detailVariant->barcodes->firstWhere('status', 'active')?->barcode ?: __('Not assigned') }}</div></div></div><div class="mt-6 space-y-3"><flux:heading size="lg">{{ __('Variation media') }}</flux:heading><flux:callout variant="info">{{ __('When no active child image exists, POS and catalog use the family gallery automatically.') }}</flux:callout><div class="grid gap-3 sm:grid-cols-2"><flux:input wire:model="variantMediaUpload" type="file" accept="image/jpeg,image/png,image/webp" :label="__('Image file')" /><flux:select wire:model="variantMediaRole" :label="__('Image role')"><flux:select.option value="main">{{ __('Main image') }}</flux:select.option><flux:select.option value="additional">{{ __('Additional image') }}</flux:select.option></flux:select></div><flux:button wire:click="uploadVariantImage" variant="primary" wire:loading.attr="disabled" wire:target="uploadVariantImage">{{ __('Upload protected image') }}</flux:button>@error('variantMediaUpload')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror<div class="grid grid-cols-2 gap-3">@foreach($detailVariant->images as $image)<figure class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800"><img class="aspect-square w-full object-cover" loading="lazy" src="{{ route('catalog.products.media', ['product' => $detailVariant, 'attachment' => $image->attachment]) }}" alt=""><figcaption class="flex items-center justify-between p-2 text-xs"><span>{{ __($image->role) }}</span><button wire:click="removeVariantImage({{ $image->id }})" class="text-rose-600">{{ __('Remove') }}</button></figcaption></figure>@endforeach</div></div>@if($detailVariant->status === 'active')<div class="mt-8 border-t border-zinc-200 pt-5 dark:border-zinc-800"><flux:button wire:click="inactivate({{ $detailVariant->id }})" wire:confirm="{{ __('Used variations are preserved and can only be inactivated. Continue?') }}" variant="danger">{{ __('Inactivate variation') }}</flux:button></div>@endif</aside></div>
    @endif
</section>

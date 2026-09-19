<?php

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\SaveOpeningInventoryDraftAction;
use App\Modules\Inventory\Models\OpeningInventoryDocument;
use App\Modules\Inventory\Queries\SearchAssignableProducts;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use App\Modules\Platform\Support\DefaultOperatingContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public ?int $documentId = null;
    public string $companyId = '';
    public string $branchId = '';
    public string $storeId = '';
    public string $documentDate = '';
    public string $search = '';
    public array $quantities = [];
    public array $costs = [];
    public string $pasteRows = '';
    public string $notes = '';

    public function mount(?int $documentId = null): void
    {
        Gate::authorize($documentId === null ? 'inventory_stock_card.create' : 'inventory_stock_card.edit');
        $company = app(AuthorizedCompanyContext::class)->resolve($this->actor());
        $this->companyId = (string) $company->id;
        $this->documentId = $documentId;
        $this->documentDate = now()->toDateString();
        if ($documentId === null) {
            $this->branchId = (string) (app(DefaultOperatingContext::class)->branch($this->actor())?->id ?? '');
            return;
        }

        $document = OpeningInventoryDocument::query()->where('company_id', $company->id)->with('lines')->findOrFail($documentId);
        abort_unless($document->status === 'draft', 403);
        $this->notes = (string) $document->notes;
        $this->documentDate = $document->document_date?->toDateString() ?? $document->created_at->toDateString();
        $first = $document->lines->first();
        if ($first) {
            $store = Store::query()->visibleTo($this->actor())->findOrFail($first->store_id);
            $this->branchId = (string) $store->branch_id;
            $this->storeId = (string) $store->id;
        }
        foreach ($document->lines as $line) {
            $this->quantities[(string) $line->product_id] = (string) $line->quantity;
            $this->costs[(string) $line->product_id] = (string) $line->unit_cost;
        }
    }

    public function updatedCompanyId(): void { $this->branchId = ''; $this->storeId = ''; }
    public function updatedBranchId(): void { $this->storeId = ''; }

    public function addProduct(int $productId): void
    {
        if ($productId < 1) return;
        if (array_key_exists((string) $productId, $this->quantities)) {
            $this->addError('search', __('This product is already in the opening inventory grid.'));
            $this->dispatch('opening-focus-quantity', productId: $productId);
            return;
        }
        $product = app(SearchAssignableProducts::class)->query(['status' => 'active'])->findOrFail($productId);
        $this->quantities[(string) $product->id] = '1';
        $this->costs[(string) $product->id] = bcadd((string) ($product->average_cost ?? 0), '0', 4);
        $this->search = '';
        $this->resetErrorBag('search');
        $this->dispatch('opening-focus-quantity', productId: $product->id);
    }

    public function parsePastedRows(): void
    {
        $lines = preg_split('/\R/u', trim($this->pasteRows)) ?: [];
        if (count($lines) > 500) { $this->addError('pasteRows', __('Paste at most 500 rows.')); return; }
        $wanted = [];
        foreach ($lines as $line) {
            $parts = preg_split('/[\t,;]+/', trim($line)) ?: [];
            if (filled($parts[0] ?? null)) $wanted[trim($parts[0])] = [trim($parts[1] ?? '1'), trim($parts[2] ?? '')];
        }
        $products = Product::query()->sellable()->where('product_type', '!=', 'service')->with(['barcodes', 'productSuppliers'])
            ->where(fn ($query) => $query->whereIn('item_code', array_keys($wanted))->orWhereIn('model_number', array_keys($wanted))
                ->orWhereHas('barcodes', fn ($codes) => $codes->whereIn('barcode', array_keys($wanted))->where('status', 'active'))
                ->orWhereHas('productSuppliers', fn ($links) => $links->whereIn('supplier_item_code', array_keys($wanted))))->get();
        $matched = [];
        foreach ($products as $product) {
            $keys = collect([$product->item_code, $product->model_number, ...$product->barcodes->pluck('barcode'), ...$product->productSuppliers->pluck('supplier_item_code')])->filter();
            $key = $keys->first(fn ($candidate) => array_key_exists((string) $candidate, $wanted));
            if ($key === null || isset($matched[$product->id])) continue;
            $matched[$product->id] = true;
            [$quantity, $cost] = $wanted[$key];
            $this->quantities[(string) $product->id] = $quantity ?: '1';
            $this->costs[(string) $product->id] = $cost !== '' ? $cost : bcadd((string) ($product->average_cost ?? 0), '0', 4);
        }
        if (count($matched) !== count($wanted)) $this->addError('pasteRows', __('Some pasted codes were invalid or duplicated. Valid rows were added once.'));
        else $this->resetErrorBag('pasteRows');
        $this->pasteRows = '';
    }

    public function removeLine(int $productId): void
    {
        unset($this->quantities[(string) $productId], $this->costs[(string) $productId]);
    }

    public function saveDraft(): void
    {
        $this->validate([
            'companyId' => ['required', 'integer'], 'branchId' => ['required', 'integer'], 'storeId' => ['required', 'integer'],
            'documentDate' => ['required', 'date_format:Y-m-d'], 'quantities' => ['required', 'array', 'min:1', 'max:500'],
            'quantities.*' => ['required', 'regex:/^\d+$/', 'gt:0'], 'costs.*' => ['required', 'regex:/^\d+(?:\.\d{1,4})?$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $store = Store::query()->visibleTo($this->actor())->where('company_id', (int) $this->companyId)->where('branch_id', (int) $this->branchId)->whereKey((int) $this->storeId)->firstOrFail();
        $rows = collect($this->quantities)->map(fn ($quantity, $productId) => ['product_id' => (int) $productId, 'store_id' => $store->id, 'quantity' => $quantity, 'unit_cost' => $this->costs[(string) $productId] ?? null])->values()->all();
        $document = app(SaveOpeningInventoryDraftAction::class)->execute($rows, $this->documentId, $this->notes ?: null, (int) $this->companyId, $this->documentDate);
        $this->redirectRoute('inventory.opening.show', ['document' => $document->id], navigate: true);
    }

    public function render()
    {
        $actor = $this->actor();
        $companies = app(AuthorizedCompanyContext::class)->companies($actor);
        $branches = $this->companyId === '' ? collect() : Branch::query()->visibleTo($actor)->where('company_id', (int) $this->companyId)->where('status', 'active')->orderBy('code')->get();
        $stores = $this->branchId === '' ? collect() : Store::query()->visibleTo($actor)->where('company_id', (int) $this->companyId)->where('branch_id', (int) $this->branchId)->where('status', 'active')->whereIn('type', ['warehouse', 'selling'])->orderBy('code')->get();
        $term = Str::limit(trim($this->search), 100, '');
        $searchResults = $term === '' ? collect() : app(SearchAssignableProducts::class)->query(['q' => $term, 'status' => 'active'])->limit(8)->get();
        $lineProducts = $this->quantities === [] ? collect() : Product::query()->with(['barcodes', 'preferredProductSupplier'])->whereIn('id', array_map('intval', array_keys($this->quantities)))->orderBy('item_code')->get();
        return view('inventory.opening-workflow', compact('companies', 'branches', 'stores', 'searchResults', 'lineProducts'));
    }

    private function actor(): User { $actor = Auth::user(); abort_unless($actor instanceof User, 403); return $actor; }
}; ?>

<div class="space-y-4" x-data x-on:opening-focus-quantity.window="requestAnimationFrame(() => document.getElementById('opening-quantity-'+$event.detail.productId)?.focus())">
    <flux:card class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><flux:heading>{{ __('Opening inventory header') }}</flux:heading><p class="text-sm text-text-muted">{{ __('The document number is allocated on first save from the branch opening-inventory sequence.') }}</p></div>@if($documentId)<flux:badge color="amber">{{ __('Draft · Resume') }}</flux:badge>@endif</div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @if($companies->count() > 1)<flux:select wire:model.live="companyId" :label="__('Company')">@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->code }} · {{ str_starts_with(app()->getLocale(),'ar')?$company->name_ar:$company->name_en }}</option>@endforeach</flux:select>@endif
            @if($branches->count() > 1)<flux:select wire:model.live="branchId" :label="__('Branch')" required><option value="">{{ __('Select branch') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->code }} · {{ str_starts_with(app()->getLocale(),'ar')?$branch->name_ar:$branch->name_en }}</option>@endforeach</flux:select>@endif
            <flux:select wire:model="storeId" :label="str_starts_with(app()->getLocale(),'ar') ? 'موقع المخزون' : 'Stock location'" required><option value="">{{ __('Select location') }}</option>@foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->code }} · {{ str_starts_with(app()->getLocale(),'ar')?$store->name_ar:$store->name_en }}</option>@endforeach</flux:select>
            <flux:input wire:model="documentDate" type="date" :label="__('Document date')" required />
            <flux:input value="{{ $documentId ? __('Allocated') : __('Automatic on save') }}" :label="__('Document number')" disabled />
            <flux:input wire:model="notes" :label="__('Notes (optional)')" class="lg:col-span-2" />
        </div>
    </flux:card>

    <flux:card class="space-y-3">
        <div><flux:heading>{{ __('Opening inventory lines') }}</flux:heading><p class="text-sm text-text-muted">{{ __('Scan or search by name, internal code, supplier code, barcode, or model. Use arrows and Enter to select.') }}</p></div>
        <div class="relative" x-data="{active:0}" x-on:keydown.arrow-down.prevent="active=Math.min(active+1,$root.querySelectorAll('[data-opening-result]').length-1)" x-on:keydown.arrow-up.prevent="active=Math.max(active-1,0)" x-on:keydown.escape.prevent="$wire.set('search','')" x-on:keydown.enter.prevent="$root.querySelectorAll('[data-opening-result]')[active]?.click()">
            <flux:input id="opening-product-search" wire:model.live.debounce.250ms="search" autocomplete="off" icon="magnifying-glass" :label="__('Product search / scanner')" :placeholder="__('Name, internal/supplier code, barcode, or model')" />
            @if($searchResults->isNotEmpty())<div class="absolute z-30 mt-1 max-h-72 w-full overflow-auto rounded-xl border border-border bg-surface shadow-xl" role="listbox">@foreach($searchResults as $result)<button type="button" data-opening-result wire:click="addProduct({{ $result->id }})" wire:loading.attr="disabled" wire:target="addProduct" x-bind:class="active==={{ $loop->index }} ? 'bg-primary-soft' : ''" class="flex w-full items-center justify-between gap-3 border-b border-border px-4 py-3 text-start last:border-0"><span><strong>{{ str_starts_with(app()->getLocale(),'ar')?$result->name_ar:($result->name_en?:$result->name_ar) }}</strong><small class="block text-text-muted" dir="ltr">{{ $result->item_code }} @if($result->model_number)· {{ $result->model_number }}@endif</small></span><span class="text-xs font-mono" dir="ltr">{{ $result->barcodes->first()?->barcode ?: $result->productSuppliers->first()?->supplier_item_code }}</span></button>@endforeach</div>@endif
            @error('search')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        @if($lineProducts->isNotEmpty())
            <div class="overflow-x-auto"><table class="app-data-table min-w-[54rem]"><thead><tr><th>{{ __('Product') }}</th><th>{{ __('Internal / supplier code') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Unit cost') }}</th><th>{{ __('Line total') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead><tbody>
                @foreach($lineProducts as $product)@php($qty=(float)($quantities[$product->id]??0))@php($cost=(float)($costs[$product->id]??0))<tr wire:key="opening-line-{{ $product->id }}"><td data-primary>{{ str_starts_with(app()->getLocale(),'ar')?$product->name_ar:($product->name_en?:$product->name_ar) }}<small class="block text-text-muted" dir="ltr">{{ $product->model_number }}</small></td><td dir="ltr"><span class="font-mono">{{ $product->item_code }}</span><small class="block text-text-muted">{{ $product->preferredProductSupplier?->supplier_item_code }}</small></td><td><flux:input id="opening-quantity-{{ $product->id }}" wire:model.live.debounce.250ms="quantities.{{ $product->id }}" type="number" min="1" step="1" x-on:keydown.enter.prevent="$el.closest('tr').querySelector('[data-opening-cost] input')?.focus()" /></td><td data-opening-cost><flux:input wire:model.live.debounce.250ms="costs.{{ $product->id }}" type="number" min="0" step="0.0001" x-on:keydown.enter.prevent="document.getElementById('opening-product-search')?.focus()" /></td><td class="font-mono" dir="ltr">{{ number_format($qty*$cost,4) }}</td><td><flux:button type="button" size="xs" variant="danger" wire:click="removeLine({{ $product->id }})" :aria-label="__('Remove')">{{ __('Remove') }}</flux:button></td></tr>@endforeach
            </tbody></table></div>
            @php($totalQty=collect($quantities)->sum(fn($v)=>(float)$v)) @php($totalValue=$lineProducts->sum(fn($p)=>(float)($quantities[$p->id]??0)*(float)($costs[$p->id]??0)))
            <div class="grid gap-2 sm:grid-cols-3"><div class="rounded-xl bg-surface-muted p-3"><small>{{ __('Product count') }}</small><strong class="block tabular-nums">{{ $lineProducts->count() }}</strong></div><div class="rounded-xl bg-surface-muted p-3"><small>{{ __('Total quantity') }}</small><strong class="block tabular-nums">{{ number_format($totalQty) }}</strong></div><div class="rounded-xl bg-surface-muted p-3"><small>{{ __('Opening inventory value') }}</small><strong class="block tabular-nums" dir="ltr">{{ number_format($totalValue,4) }}</strong></div></div>
        @endif

        <details class="rounded-xl border border-border p-3"><summary class="cursor-pointer font-semibold">{{ __('Paste codes and quantities') }}</summary><div class="mt-3 grid gap-3 sm:grid-cols-[1fr_auto]"><flux:textarea wire:model="pasteRows" rows="3" :label="__('One row: code, quantity, optional unit cost')" placeholder="SKU-1001, 5, 12.50"/><flux:button type="button" class="self-end" wire:click="parsePastedRows">{{ __('Add pasted rows') }}</flux:button></div>@error('pasteRows')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</details>
        <div class="flex flex-wrap items-center justify-between gap-3"><div class="flex items-center gap-2"><p class="text-xs text-text-muted">{{ __('Website products map through the same internal, supplier, model, or barcode identity. Use Product Excel Import first when an external item has no internal product.') }}</p>@can('products_categories_brands.create')<flux:button size="xs" variant="subtle" :href="route('catalog.products.import')" wire:navigate>{{ __('Map / import products') }}</flux:button>@endcan</div><flux:button type="button" variant="primary" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft"><span wire:loading.remove wire:target="saveDraft">{{ __('Save as Draft') }}</span><span wire:loading wire:target="saveDraft">{{ __('Saving…') }}</span></flux:button></div>
    </flux:card>
</div>

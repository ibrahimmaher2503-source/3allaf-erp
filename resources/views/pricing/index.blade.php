<?php

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\ImportPriceProposalsAction;
use App\Modules\Pricing\Actions\RejectPriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Pricing\Queries\PricingDashboard;
use App\Modules\Platform\Support\UiLabel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pricing Workspace')] class extends Component
{
    use WithPagination;

    private const MODES = ['workspace', 'versions', 'unpriced', 'history'];

    public string $mode = 'workspace';

    public string $search = '';

    public string $statusFilter = 'all';

    public string $storeFilter = 'all';

    public string $effectiveFrom = '';

    public string $effectiveTo = '';

    public int $perPage = 12;

    public string $sort = 'id';

    public string $direction = 'desc';

    public bool $showProposalForm = false;

    public bool $showImportForm = false;

    public string $importCsv = "item_code,store_code,amount,effective_from,source_reference\n";

    public ?int $compareVersionId = null;

    public string $rejectionReason = '';

    public array $form = [
        'price_list_code' => 'LOCAL-RETAIL',
        'price_list_name_ar' => 'قائمة أسعار البيع',
        'price_list_name_en' => 'Retail price list',
        'product_id' => '',
        'store_id' => '',
        'amount' => '',
        'reference_amount' => '',
        'open_price_minimum' => '',
        'open_price_maximum' => '',
        'source_type' => 'product_card',
        'source_reference' => '',
        'effective_from' => '',
        'effective_to' => '',
        'reason_text' => '',
        'open_price_allowed' => false,
    ];

    public function mount(?string $mode = null): void
    {
        Gate::authorize('pricing_labels.view');
        $this->mode = $mode ?? 'workspace';
        abort_unless(in_array($this->mode, self::MODES, true), 404);
        $contextStoreId = app(\App\Modules\Platform\Support\WorkContext::class)->id($this->actor());
        $this->storeFilter = $contextStoreId === null ? 'all' : (string) $contextStoreId;
    }

    public function updatingSearch(string $value): void
    {
        $this->search = Str::limit($value, 100, '');
        $this->resetPage();
        $this->resetPage('unpriced_page');
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStoreFilter(): void { $this->resetPage(); $this->resetPage('unpriced_page'); }
    public function updatedEffectiveFrom(): void { $this->resetPage(); }
    public function updatedEffectiveTo(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        abort_unless(in_array($column, ['id', 'effective_from', 'approved_at'], true), 422);
        $this->direction = $this->sort === $column && $this->direction === 'desc' ? 'asc' : 'desc';
        $this->sort = $column;
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        abort_unless(in_array($this->perPage, [12, 24, 48], true), 422);
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->storeFilter = 'all';
        $this->effectiveFrom = '';
        $this->effectiveTo = '';
        $this->resetPage();
        $this->resetPage('unpriced_page');
    }

    public function openProposalForm(): void
    {
        Gate::authorize('pricing_labels.create');
        $this->resetValidation();
        $this->showProposalForm = true;
    }

    public function openImportForm(): void
    {
        Gate::authorize('pricing_labels.create');
        $this->resetValidation();
        $this->showImportForm = true;
    }

    public function importProposals(): void
    {
        Gate::authorize('pricing_labels.create');
        $data = $this->validate([
            'importCsv' => ['required', 'string', 'max:100000'],
        ]);

        app(ImportPriceProposalsAction::class)->execute(
            csv: $data['importCsv'],
            priceListCode: 'LOCAL-RETAIL',
            priceListNameAr: 'قائمة أسعار البيع',
            priceListNameEn: 'Retail price list',
        );

        $this->showImportForm = false;
        $this->resetPage();
        session()->flash('status', __('CSV proposals imported as Draft; approval is still required.'));
    }

    public function openDiff(int $versionId): void
    {
        Gate::authorize('pricing_labels.view');
        $this->compareVersionId = $versionId;
    }

    public function closeDiff(): void
    {
        $this->compareVersionId = null;
    }

    public function saveProposal(): void
    {
        Gate::authorize('pricing_labels.create');
        $data = $this->validate([
            'form.price_list_code' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'form.price_list_name_ar' => ['required', 'string', 'max:255'],
            'form.price_list_name_en' => ['required', 'string', 'max:255'],
            'form.product_id' => ['required', 'integer', 'exists:products,id'],
            'form.store_id' => ['required', 'integer', Rule::exists('stores', 'id')->where(fn ($query) => $query->whereIn('id', Store::query()->visibleTo($this->actor())->select('id')))],
            'form.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'form.reference_amount' => ['nullable', 'numeric', 'gte:0', 'decimal:0,3'],
            'form.open_price_minimum' => ['nullable', 'numeric', 'gte:0', 'decimal:0,4', 'required_if:form.open_price_allowed,true'],
            'form.open_price_maximum' => ['nullable', 'numeric', 'gte:0', 'decimal:0,4', 'required_if:form.open_price_allowed,true', 'gte:form.open_price_minimum'],
            'form.source_type' => ['required', Rule::in(['product_card', 'import', 'purchase_context', 'branch_exception'])],
            'form.source_reference' => ['nullable', 'string', 'max:120'],
            'form.effective_from' => ['nullable', 'date'],
            'form.effective_to' => ['nullable', 'date', 'after:form.effective_from'],
            'form.reason_text' => ['nullable', 'string', 'max:1000'],
            'form.open_price_allowed' => ['boolean'],
        ]);

        app(CreatePriceProposalAction::class)->execute(
            product: Product::query()->sellable()->findOrFail((int) $data['form']['product_id']),
            store: Store::query()->visibleTo($this->actor())->findOrFail((int) $data['form']['store_id']),
            priceListCode: $data['form']['price_list_code'],
            priceListNameAr: $data['form']['price_list_name_ar'],
            priceListNameEn: $data['form']['price_list_name_en'],
            amount: $data['form']['amount'],
            sourceType: $data['form']['source_type'],
            sourceReference: $data['form']['source_reference'] ?: null,
            effectiveFrom: $data['form']['effective_from'] ?: null,
            effectiveTo: $data['form']['effective_to'] ?: null,
            reasonText: $data['form']['reason_text'] ?: null,
            referenceAmount: $data['form']['reference_amount'] ?: null,
            openPriceAllowed: (bool) $data['form']['open_price_allowed'],
            openPriceMinimum: $data['form']['open_price_minimum'] ?: null,
            openPriceMaximum: $data['form']['open_price_maximum'] ?: null,
        );

        $this->showProposalForm = false;
        $this->resetPage();
        session()->flash('status', __('Draft price proposal created.'));
    }

    public function submitProposal(int $versionId): void
    {
        app(SubmitPriceProposalAction::class)->execute($this->scopedVersion($versionId));
        session()->flash('status', __('Price proposal submitted for approval.'));
    }

    public function approveProposal(int $versionId): void
    {
        app(ApprovePriceProposalAction::class)->execute($this->scopedVersion($versionId));
        session()->flash('status', __('Price version approved and effective where its date allows.'));
    }

    public function rejectProposal(int $versionId): void
    {
        Gate::authorize('pricing_labels.approve');
        $this->validate(['rejectionReason' => ['required', 'string', 'max:1000']]);
        app(RejectPriceProposalAction::class)->execute($this->scopedVersion($versionId), $this->rejectionReason);
        $this->rejectionReason = '';
        session()->flash('status', __('Price proposal rejected with an audit reason.'));
    }

    private function actor(): User
    {
        $actor = request()->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function scopedVersion(int $versionId): PriceVersion
    {
        $actor = $this->actor();

        return PriceVersion::query()
            ->whereHas('lines', fn ($lines) => $lines->whereIn('store_id', Store::query()->visibleTo($actor)->select('id')))
            ->findOrFail($versionId);
    }

    public function render(): mixed
    {
        abort_unless(in_array($this->mode, self::MODES, true), 404);
        /** @var User $user */
        $user = $this->actor();
        $selectedStoreId = $this->storeFilter === 'all' ? null : (int) $this->storeFilter;
        abort_if($selectedStoreId !== null && ! Store::query()->visibleTo($user)->whereKey($selectedStoreId)->exists(), 404);

        $query = PriceVersion::query()
            ->whereHas('lines', fn ($lines) => $lines->whereIn('store_id', Store::query()->visibleTo($user)->select('id'))
                ->when($selectedStoreId !== null, fn ($scope) => $scope->where('store_id', $selectedStoreId)))
            ->with(['priceList', 'lines' => fn ($lines) => $lines->whereIn('store_id', Store::query()->visibleTo($user)->select('id'))->with(['product', 'store']), 'approvalRecord.requester', 'approvalRecord.approver']);
        if ($this->statusFilter !== 'all') {
            $query->where('state', $this->statusFilter);
        }
        if ($this->search !== '') {
            $query->where(function ($scope): void {
                $scope->whereHas('priceList', fn ($list) => $list->where('code', 'like', '%'.$this->search.'%'))
                    ->orWhereHas('lines.product', fn ($product) => $product->where('item_code', 'like', '%'.$this->search.'%')->orWhere('name_en', 'like', '%'.$this->search.'%')->orWhere('name_ar', 'like', '%'.$this->search.'%'));
            });
        }

        if ($this->effectiveFrom !== '') {
            $query->where(fn ($dates) => $dates->whereNull('effective_to')->orWhereDate('effective_to', '>=', $this->effectiveFrom));
        }
        if ($this->effectiveTo !== '') {
            $query->where(fn ($dates) => $dates->whereNull('effective_from')->orWhereDate('effective_from', '<=', $this->effectiveTo));
        }
        abort_unless(in_array($this->perPage, [12, 24, 48], true), 422);
        abort_unless(in_array($this->sort, ['id', 'effective_from', 'approved_at'], true) && in_array($this->direction, ['asc', 'desc'], true), 422);
        $versions = in_array($this->mode, ['workspace', 'versions', 'history'], true)
            ? $query->orderBy($this->sort, $this->direction)->orderBy('id', $this->direction)->paginate($this->perPage)
            : new LengthAwarePaginator([], 0, $this->perPage);
        $products = $this->showProposalForm
            ? Product::query()->sellable()->orderBy('item_code')->limit(500)->get(['id', 'item_code', 'name_ar', 'name_en', 'parent_product_id'])
            : collect();
        $stores = Store::query()->visibleTo($user)->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']);
        $unpricedQuery = Product::query()->sellable()->where(fn ($query) => $query->whereNull('sale_price')->orWhere('sale_price', '<=', 0));
        if ($this->search !== '') {
            $unpricedQuery->where(function ($query): void {
                $query->where('item_code', 'like', '%'.$this->search.'%')
                    ->orWhere('name_en', 'like', '%'.$this->search.'%')
                    ->orWhere('name_ar', 'like', '%'.$this->search.'%');
            });
        }
        $unpricedProducts = $unpricedQuery->orderBy('item_code')->paginate(12, ['id', 'item_code', 'name_ar', 'name_en'], 'unpriced_page')->withQueryString();
        $diffVersion = $this->compareVersionId === null ? null : PriceVersion::query()
            ->whereHas('lines', fn ($lines) => $lines->whereIn('store_id', Store::query()->visibleTo($user)->select('id')))
            ->with(['priceList', 'lines' => fn ($lines) => $lines->whereIn('store_id', Store::query()->visibleTo($user)->select('id'))->with(['product', 'store'])])
            ->find($this->compareVersionId);
        $diffPrevious = $diffVersion === null ? null : PriceVersion::query()
            ->whereHas('lines', fn ($lines) => $lines->whereIn('store_id', Store::query()->visibleTo($user)->select('id')))
            ->with(['lines' => fn ($lines) => $lines->whereIn('store_id', Store::query()->visibleTo($user)->select('id'))->with(['product', 'store'])])
            ->where('price_list_id', $diffVersion->price_list_id)->where('version', '<', $diffVersion->version)->latest('version')->first();

        $dashboard = $this->mode === 'workspace'
            ? app(PricingDashboard::class)->for($user, $selectedStoreId, $unpricedProducts->total())
            : [];

        return view('pricing.index', compact('versions', 'products', 'stores', 'unpricedProducts', 'diffVersion', 'diffPrevious', 'dashboard'));
    }
};
?>
<x-app.page :title="str_starts_with(app()->getLocale(), 'ar') ? 'التسعير' : 'Pricing workspace'" :description="str_starts_with(app()->getLocale(), 'ar') ? 'إدارة الأسعار الفعالة والنسخ المجدولة والاعتمادات والاستثناءات ضمن المتاجر المصرح بها.' : 'Manage effective prices, schedules, approvals, and exceptions across authorized stores.'" :breadcrumbs="str_starts_with(app()->getLocale(), 'ar') ? 'التسعير' : 'Pricing'" max-width="7xl" class="pricing-screen" data-pricing-mode="{{ $mode }}">
    @php($modeTitles = ['workspace' => str_starts_with(app()->getLocale(), 'ar') ? 'نظرة عامة' : 'Overview', 'versions' => str_starts_with(app()->getLocale(), 'ar') ? 'قوائم ونسخ الأسعار' : 'Price lists & versions', 'unpriced' => str_starts_with(app()->getLocale(), 'ar') ? 'منتجات بلا سعر' : 'Unpriced products', 'history' => str_starts_with(app()->getLocale(), 'ar') ? 'سجل التغييرات' : 'Change history'])
    <x-slot:actions><x-tables.resource-toolbar filter-target="pricing-filters">@can('pricing_labels.create')<flux:button variant="subtle" wire:click="openImportForm" icon="arrow-up-tray">{{ str_starts_with(app()->getLocale(), 'ar') ? 'استيراد CSV' : 'Import CSV' }}</flux:button><flux:button variant="primary" wire:click="openProposalForm" icon="plus">{{ str_starts_with(app()->getLocale(), 'ar') ? 'مقترح سعر جديد' : 'New price proposal' }}</flux:button>@endcan</x-tables.resource-toolbar></x-slot:actions>
    <div class="space-y-5">
    <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-text-primary sm:text-3xl">{{ $modeTitles[$mode] }}</h1>
            <flux:text class="mt-1 max-w-3xl">{{ __('The Product Card base consumer price is immediately sellable. Optional active price lists override it only when applicable. Cost changes never rewrite sale prices.') }}</flux:text>
        </div>
        <flux:button href="{{ route('pricing.labels') }}" variant="subtle" icon="printer" wire:navigate>{{ str_starts_with(app()->getLocale(), 'ar') ? 'ملصقات الأسعار' : 'Price labels' }}</flux:button>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">{{ session('status') }}</flux:callout>
    @endif

    <nav class="flex flex-wrap gap-2" aria-label="{{ __('Pricing views') }}">
        @foreach($modeTitles as $modeKey => $modeTitle)
            <flux:button size="sm" :variant="$mode === $modeKey ? 'primary' : 'subtle'" href="{{ route('pricing.focus', ['mode' => $modeKey]) }}" wire:navigate>{{ $modeTitle }}</flux:button>
        @endforeach
    </nav>

    <div id="pricing-filters" class="scroll-mt-24 grid gap-3 rounded-2xl border border-border bg-surface p-4 md:grid-cols-2 min-w-0 xl:grid-cols-[minmax(12rem,1.5fr)_repeat(4,minmax(8.5rem,1fr))_auto]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search list, item code, or product') }}" />
        <flux:select wire:model.live="storeFilter" :label="str_starts_with(app()->getLocale(), 'ar') ? 'موقع العمل' : 'Work location'"><option value="all">{{ str_starts_with(app()->getLocale(), 'ar') ? 'كل المتاجر' : 'All stores' }}</option>@foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $store->name_ar : $store->name_en }}</option>@endforeach</flux:select>
        @if($mode !== 'unpriced')<flux:select wire:model.live="statusFilter" :label="__('Status')"><option value="all">{{ __('All states') }}</option>@foreach (PriceVersionState::cases() as $state)<option value="{{ $state->value }}">{{ __(ucfirst($state->value)) }}</option>@endforeach</flux:select>@else<div></div>@endif
        <flux:input wire:model.live="effectiveFrom" type="date" :label="str_starts_with(app()->getLocale(), 'ar') ? 'ساري من' : 'Effective from'" />
        <flux:input wire:model.live="effectiveTo" type="date" :label="str_starts_with(app()->getLocale(), 'ar') ? 'ساري حتى' : 'Effective to'" />
        <flux:select wire:model.live="perPage" :label="str_starts_with(app()->getLocale(), 'ar') ? 'حجم الصفحة' : 'Page size'"><option value="12">12</option><option value="24">24</option><option value="48">48</option></flux:select>
        <flux:button wire:click="resetFilters" variant="ghost" icon="arrow-path">{{ str_starts_with(app()->getLocale(), 'ar') ? 'إعادة ضبط' : 'Reset' }}</flux:button>
    </div>

    @if($mode === 'workspace')<x-pricing.dashboard :dashboard="$dashboard" />@endif

    @if (in_array($mode, ['workspace', 'unpriced'], true))
        <flux:card>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Unpriced products') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('These active products have no positive base consumer selling price. Add a positive base price to make them immediately sellable.') }}</flux:text>
                </div>
                <flux:badge color="amber">{{ __('Pricing pending') }}</flux:badge>
            </div>
            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($unpricedProducts as $unpricedProduct)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm dark:border-amber-900 dark:bg-amber-950/30"><span class="font-semibold">{{ $unpricedProduct->item_code }}</span><span class="text-zinc-600 dark:text-zinc-300"> · {{ str_starts_with(app()->getLocale(), 'ar') ? $unpricedProduct->name_ar : $unpricedProduct->name_en }}</span></div>
                @empty
                    <x-state.empty :title="__('No unpriced products in the visible stores.')" />
                @endforelse
            </div>
            @if($unpricedProducts->hasPages())<div class="mt-4">{{ $unpricedProducts->links() }}</div>@endif
        </flux:card>
    @endif

    @if(in_array($mode, ['workspace', 'versions', 'history'], true))
    <x-tables.data-panel :title="$mode === 'history' ? __('Price change history') : __('Price versions')" :description="__('Review proposed and approved prices by product and store.')">
        <table class="data-table responsive-resource-table min-w-full text-start text-sm">
                <thead><tr><th scope="col"><button type="button" wire:click="sortBy('id')" class="font-semibold">{{ __('Version') }}</button></th><th scope="col">{{ __('Product / location') }}</th><th scope="col">{{ __('Amount') }}</th><th scope="col">{{ __('Source') }}</th><th scope="col"><button type="button" wire:click="sortBy('approved_at')" class="font-semibold">{{ __('State') }}</button></th><th scope="col">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($versions as $version)
                        @php($line = $version->lines->first())
                        <tr wire:key="price-version-{{ $version->id }}" class="align-top">
                            <td data-primary><div class="font-semibold text-text-primary">{{ $version->priceList->code }} · v{{ $version->version }}</div><div class="text-xs text-text-muted">{{ optional($version->effective_from)->format('Y-m-d H:i') ?: __('Immediate') }}</div></td>
                            <td data-label="{{ __('Product / location') }}"><div class="font-medium">{{ $line?->product?->item_code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $line?->product?->name_ar : $line?->product?->name_en }}</div><div class="text-xs text-text-muted">{{ $line?->store?->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $line?->store?->name_ar : $line?->store?->name_en }}</div></td>
                            <td data-label="{{ __('Amount') }}" class="font-semibold"><x-money :amount="$line?->amount" /></td>
                            <td data-label="{{ __('Source') }}"><div>{{ $version->source_type === 'product_card' ? __('Product price') : __(str_replace('_', ' ', ucfirst($version->source_type))) }}</div><div class="text-xs text-text-muted">{{ $version->source_reference ?: '—' }}</div></td>
                            <td data-label="{{ __('State') }}"><x-status.badge :status="$version->state->value" /><div class="mt-1 text-xs text-text-muted">{{ $version->approvalRecord ? __('Approval') . ': ' . UiLabel::status($version->approvalRecord->approval_state->value) : __('No approval yet') }}</div></td>
                            <td data-label="{{ __('Actions') }}" class="space-y-2">
                                @can('pricing_labels.view')<x-actions.button semantic="history" :label="__('Compare history')" wire:click="openDiff({{ $version->id }})">{{ __('Compare history') }}</x-actions.button>@endcan
                                @can('pricing_labels.submit') @if ($version->state === PriceVersionState::Draft)<flux:button size="sm" wire:click="submitProposal({{ $version->id }})">{{ __('Submit') }}</flux:button>@endif @endcan
                                @can('pricing_labels.approve') @if ($version->state === PriceVersionState::Submitted)<x-actions.button semantic="approve" :label="__('Approve')" wire:click="approveProposal({{ $version->id }})">{{ __('Approve') }}</x-actions.button><flux:input size="sm" wire:model="rejectionReason" placeholder="{{ __('Rejection reason if rejecting') }}" /><x-actions.button semantic="reject" :label="__('Reject')" wire:click="rejectProposal({{ $version->id }})">{{ __('Reject') }}</x-actions.button>@endif @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-state.empty :title="__('No price versions yet.')" :description="__('Create a price proposal to begin the approval workflow.')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        <x-slot:footer>{{ $versions->links() }}</x-slot:footer>
    </x-tables.data-panel>
    @endif

    @if ($showImportForm)
        <flux:modal wire:model="showImportForm" name="price-import" class="md:w-[48rem]">
            <div class="space-y-5">
                <flux:heading size="lg">{{ __('Import price proposals') }}</flux:heading>
                <flux:callout variant="info">{{ __('Each imported row starts as a draft and requires approval before it affects selling prices.') }}</flux:callout>
                <flux:textarea wire:model="importCsv" label="{{ __('CSV rows') }}" rows="10" />
                <flux:text class="text-xs">{{ __('Columns: item_code, store_code, amount, effective_from, source_reference. Header optional; maximum 200 rows.') }}</flux:text>
                <div class="flex justify-end gap-2"><flux:button wire:click="$set('showImportForm', false)">{{ __('Cancel') }}</flux:button><flux:button variant="primary" wire:click="importProposals">{{ __('Import as Draft') }}</flux:button></div>
            </div>
        </flux:modal>
    @endif

    @if ($diffVersion)
        <flux:modal wire:model="compareVersionId" name="price-diff" class="md:w-[52rem]">
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-4"><div><flux:heading size="lg">{{ __('Price history comparison') }}</flux:heading><flux:text>{{ $diffVersion->priceList->code }} · v{{ $diffVersion->version }}</flux:text></div><flux:button size="sm" wire:click="closeDiff">{{ __('Close') }}</flux:button></div>
                @php($newLine = $diffVersion->lines->first())
                @php($oldLine = $diffPrevious?->lines->first())
                @if ($diffPrevious)
                    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700"><table class="min-w-full text-sm"><thead class="bg-zinc-50 text-start dark:bg-zinc-800/60"><tr><th class="px-3 py-2">{{ __('Field') }}</th><th class="px-3 py-2">{{ __('Previous v') }}{{ $diffPrevious->version }}</th><th class="px-3 py-2">{{ __('Current v') }}{{ $diffVersion->version }}</th></tr></thead><tbody class="divide-y divide-zinc-200 dark:divide-zinc-700"><tr><td class="px-3 py-2">{{ __('Amount') }}</td><td class="px-3 py-2">{{ $oldLine?->amount ?: '—' }}</td><td class="px-3 py-2 font-semibold">{{ $newLine?->amount ?: '—' }}</td></tr><tr><td class="px-3 py-2">{{ __('State') }}</td><td class="px-3 py-2">{{ __(ucfirst($diffPrevious->state->value)) }}</td><td class="px-3 py-2">{{ __(ucfirst($diffVersion->state->value)) }}</td></tr><tr><td class="px-3 py-2">{{ __('Effective from') }}</td><td class="px-3 py-2">{{ optional($diffPrevious->effective_from)->format('Y-m-d H:i') ?: __('Immediate') }}</td><td class="px-3 py-2">{{ optional($diffVersion->effective_from)->format('Y-m-d H:i') ?: __('Immediate') }}</td></tr><tr><td class="px-3 py-2">{{ __('Source') }}</td><td class="px-3 py-2">{{ $diffPrevious->source_reference ?: '—' }}</td><td class="px-3 py-2">{{ $diffVersion->source_reference ?: '—' }}</td></tr></tbody></table></div>
                @else
                    <flux:callout>{{ __('This is the first version in the price list; no previous version exists.') }}</flux:callout>
                @endif
            </div>
        </flux:modal>
    @endif

    @if ($showProposalForm)
        <flux:modal wire:model="showProposalForm" name="price-proposal" class="md:w-[48rem]">
            <div class="space-y-5">
                <flux:heading size="lg">{{ __('New price proposal') }}</flux:heading>
                <flux:text>{{ __('A proposal is Draft until submitted and approved. It cannot change historical sales or activate by itself.') }}</flux:text>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.price_list_code" label="{{ __('Price list code') }}" />
                    <flux:input wire:model="form.price_list_name_en" label="{{ __('Price list name') }}" />
                    <flux:input wire:model="form.price_list_name_ar" label="{{ __('Price list name (Arabic)') }}" />
                    <flux:select wire:model="form.source_type" label="{{ __('Source') }}"><option value="product_card">{{ __('Product card') }}</option><option value="import">{{ __('Import') }}</option><option value="purchase_context">{{ __('Purchase context') }}</option>@can('pricing_labels.override')<option value="branch_exception">{{ __('Branch exception') }}</option>@endcan</flux:select>
                    <flux:select wire:model="form.product_id" label="{{ __('Product') }}"><option value="">{{ __('Select product') }}</option>@foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->item_code }} · {{ $product->name_en }}</option>@endforeach</flux:select>
                    <flux:select wire:model="form.store_id" label="{{ __('Store') }}"><option value="">{{ __('Select store') }}</option>@foreach ($stores as $store)<option value="{{ $store->id }}">{{ $store->code }} · {{ $store->name_en }}</option>@endforeach</flux:select>
                    <flux:input wire:model="form.amount" label="{{ __('Proposed amount') }}" type="number" step="0.001" />
                    <flux:input wire:model="form.reference_amount" label="{{ __('Reference amount (optional)') }}" type="number" step="0.001" />
                    <flux:input wire:model="form.effective_from" label="{{ __('Effective from (optional)') }}" type="datetime-local" />
                    <flux:input wire:model="form.effective_to" label="{{ __('Effective to (optional)') }}" type="datetime-local" />
                    <flux:input wire:model="form.source_reference" label="{{ __('Source reference') }}" />
                    <flux:checkbox wire:model="form.open_price_allowed" label="{{ __('Allow open-price context (still requires bounds and permission)') }}" />
                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:input wire:model="form.open_price_minimum" label="{{ __('Open-price minimum') }}" type="number" step="0.0001" />
                        <flux:input wire:model="form.open_price_maximum" label="{{ __('Open-price maximum') }}" type="number" step="0.0001" />
                    </div>
                </div>
                <flux:textarea wire:model="form.reason_text" label="{{ __('Proposal reason / audit note') }}" rows="3" />
                <div class="flex justify-end gap-2"><flux:button wire:click="$set('showProposalForm', false)">{{ __('Cancel') }}</flux:button><flux:button variant="primary" wire:click="saveProposal">{{ __('Save draft') }}</flux:button></div>
            </div>
        </flux:modal>
    @endif
    </div>
</x-app.page>

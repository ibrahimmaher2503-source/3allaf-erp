<?php

use App\Modules\Catalog\Actions\SaveProductSupplierAction;
use App\Modules\Catalog\Actions\SaveSupplierCommunicationDestinationAction;
use App\Modules\Catalog\Actions\SaveSupplierContactAction;
use App\Modules\Catalog\Actions\SaveSupplierGroupAction;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Catalog\Actions\ToggleSupplierStatusAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierCommunicationDestination;
use App\Modules\Catalog\Models\SupplierContact;
use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Models\Company;
use App\Modules\Catalog\Support\SupplierSettlementMethod;
use App\Support\Hierarchy\GroupHierarchy;
use App\Support\Bulk\WithBulkSelection;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Supplier Masters')] class extends Component
{
    use WithBulkSelection, WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $supplierGroupFilter = 'all';
    public string $supplierGroupSearch = '';
    public string $supplierGroupStatus = 'all';
    public string $supplierGroupHierarchy = 'all';

    #[Url(as: 'section', except: 'supplier-masters')]
    public string $section = 'supplier-masters';

    public ?int $activeCompanyId = null;

    public bool $showSupplierGroupModal = false;

    public ?int $editingSupplierGroupId = null;

    public array $supplierGroupForm = [
        'name_ar' => '',
        'name_en' => '',
        'code' => '',
        'sort_order' => 0,
        'parent_id' => '',
        'status' => 'active',
    ];

    public bool $showSupplierModal = false;

    public ?int $editingSupplierId = null;

    public array $supplierForm = [
        'code' => '',
        'barcode_prefix' => '',
        'name_ar' => '',
        'name_en' => '',
        'contact_name' => '',
        'email' => '',
        'phone' => '',
        'tax_number' => '',
        'commercial_registration' => '',
        'payment_terms' => '',
        'payment_policy' => '',
        'credit_days' => '',
        'credit_limit' => '',
        'settlement_method' => '',
        'settlement_other_description' => '',
        'address' => '',
        'notes' => '',
        'supplier_group_id' => '',
        'status' => 'active',
        'lock_version' => 0,
    ];

    public bool $showDetailModal = false;

    public ?int $viewingSupplierId = null;

    public string $detailTab = 'profile';

    public bool $showSupplierContactModal = false;

    public ?int $editingSupplierContactId = null;

    public array $supplierContactForm = [
        'role' => 'representative',
        'name' => '',
        'email' => '',
        'phone' => '',
        'mobile' => '',
        'whatsapp' => '',
        'notes' => '',
        'is_primary' => false,
        'status' => 'active',
    ];

    public bool $showSupplierDestinationModal = false;

    public ?int $editingSupplierDestinationId = null;

    public array $supplierDestinationForm = [
        'purpose' => 'purchase_order',
        'channel' => 'email',
        'destination' => '',
        'label' => '',
        'is_primary' => false,
        'status' => 'active',
    ];

    public bool $showLinkProductModal = false;

    public array $productLinkForm = [
        'product_id' => '',
        'supplier_item_code' => '',
        'is_preferred' => false,
        'notes' => '',
    ];

    public function mount(): void
    {
        Gate::authorize('suppliers.view');
        $section = (string) request()->query('section', 'supplier-masters');
        if (in_array($section, ['supplier-masters', 'supplier-groups'], true)) {
            $this->section = $section;
        }
        $this->activeCompanyId = Company::query()->where('status', 'active')->value('id');
    }

    public function rendering(): void
    {
        if (! in_array($this->section, ['supplier-masters', 'supplier-groups'], true)) {
            $this->section = 'supplier-masters';
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSupplierGroupFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateSupplierModal(): void
    {
        Gate::authorize('suppliers.create');
        $this->editingSupplierId = null;
        $this->supplierForm = [
            'code' => '',
            'barcode_prefix' => '',
            'name_ar' => '',
            'name_en' => '',
            'contact_name' => '',
            'email' => '',
            'phone' => '',
            'tax_number' => '',
            'commercial_registration' => '',
            'payment_terms' => '',
            'payment_policy' => '',
            'credit_days' => '',
            'credit_limit' => '',
            'settlement_method' => '',
            'settlement_other_description' => '',
            'address' => '',
            'notes' => '',
            'supplier_group_id' => '',
            'status' => 'active',
            'lock_version' => 0,
        ];
        $this->resetValidation();
        $this->showSupplierModal = true;
    }

    public function openEditSupplierModal(int $id): void
    {
        Gate::authorize('suppliers.edit');
        $supplier = Supplier::query()->findOrFail($id);
        $this->editingSupplierId = $supplier->id;
        $this->supplierForm = [
            'code' => $supplier->code,
            'barcode_prefix' => $supplier->barcode_prefix ?? '',
            'name_ar' => $supplier->name_ar,
            'name_en' => $supplier->name_en,
            'contact_name' => $supplier->contact_name ?? '',
            'email' => $supplier->email ?? '',
            'phone' => $supplier->phone ?? '',
            'tax_number' => $supplier->tax_number ?? '',
            'commercial_registration' => $supplier->commercial_registration ?? '',
            'payment_terms' => $supplier->payment_terms ?? '',
            'payment_policy' => $supplier->payment_policy ?? '',
            'credit_days' => $supplier->credit_days ?? '',
            'credit_limit' => $supplier->credit_limit ?? '',
            'settlement_method' => $supplier->settlement_method ?? '',
            'settlement_other_description' => $supplier->settlement_other_description ?? '',
            'address' => $supplier->address ?? '',
            'notes' => $supplier->notes ?? '',
            'supplier_group_id' => $supplier->supplier_group_id ?? '',
            'status' => $supplier->status,
            'lock_version' => $supplier->lock_version,
        ];
        $this->resetValidation();
        $this->showSupplierModal = true;
    }

    public function generateSupplierCode(): void
    {
        Gate::authorize('suppliers.create');

        if ($this->editingSupplierId !== null) {
            return;
        }

        for ($number = 1; ; $number++) {
            $code = sprintf('SUP-%04d', $number);

            if (! Supplier::query()->where('code', $code)->exists()) {
                $this->supplierForm['code'] = $code;
                break;
            }
        }
    }

    public function saveSupplier(SaveSupplierAction $action): void
    {
        Gate::authorize($this->editingSupplierId ? 'suppliers.edit' : 'suppliers.create');
        $this->supplierForm['barcode_prefix'] = strtoupper(trim((string) ($this->supplierForm['barcode_prefix'] ?? '')));
        $validated = $this->validate([
            'supplierForm.code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/',
                Rule::unique('suppliers', 'code')->ignore($this->editingSupplierId),
            ],
            'supplierForm.name_ar' => ['required', 'string', 'max:255'],
            'supplierForm.name_en' => ['required', 'string', 'max:255'],
            'supplierForm.barcode_prefix' => ['nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('suppliers', 'barcode_prefix')->ignore($this->editingSupplierId)],
            'supplierForm.contact_name' => ['nullable', 'string', 'max:255'],
            'supplierForm.email' => ['nullable', 'email', 'max:255'],
            'supplierForm.phone' => ['nullable', 'string', 'max:50', PhoneNormalizer::validationRule()],
            'supplierForm.tax_number' => ['nullable', 'string', 'max:50'],
            'supplierForm.commercial_registration' => ['nullable', 'string', 'max:100'],
            'supplierForm.payment_terms' => ['nullable', 'string', 'max:1000'],
            'supplierForm.payment_policy' => ['nullable', 'in:cash,credit,both'],
            'supplierForm.credit_days' => ['nullable', 'integer', 'between:0,3650'],
            'supplierForm.credit_limit' => ['nullable', 'decimal:0,4', 'min:0'],
            'supplierForm.settlement_method' => ['required', Rule::in(SupplierSettlementMethod::VALUES)],
            'supplierForm.settlement_other_description' => ['nullable', 'required_if:supplierForm.settlement_method,other', 'string', 'max:255'],
            'supplierForm.address' => ['nullable', 'string', 'max:1000'],
            'supplierForm.notes' => ['nullable', 'string', 'max:4000'],
            'supplierForm.supplier_group_id' => ['nullable', 'integer'],
            'supplierForm.status' => ['required', 'in:active,inactive'],
        ], [], [
            'supplierForm.code' => str_starts_with(app()->getLocale(), 'ar') ? 'كود المورد' : __('Supplier code'),
            'supplierForm.name_ar' => str_starts_with(app()->getLocale(), 'ar') ? 'اسم المورد بالعربية' : __('Arabic name'),
            'supplierForm.name_en' => str_starts_with(app()->getLocale(), 'ar') ? 'اسم المورد بالإنجليزية' : __('English name'),
            'supplierForm.status' => str_starts_with(app()->getLocale(), 'ar') ? 'حالة التشغيل' : __('Operational status'),
        ])['supplierForm'];

        try {
            $action->execute(
                $validated,
                $this->editingSupplierId,
                $this->editingSupplierId ? (int) $this->supplierForm['lock_version'] : null,
                $this->activeCompanyId,
            );
            Flux::toast(
                variant: 'success',
                text: $this->editingSupplierId ? __('Supplier master updated successfully.') : __('Supplier master created successfully.')
            );
            $this->showSupplierModal = false;
        } catch (Throwable $exception) {
            $this->addError('supplierForm', \App\Support\UserSafeError::message($exception));
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function toggleSupplierStatus(int $id, ToggleSupplierStatusAction $action): void
    {
        Gate::authorize('suppliers.edit');

        try {
            $action->execute($id);
            Flux::toast(variant: 'success', text: __('Supplier status updated successfully.'));
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function bulkSetSupplierStatus(string $status, ToggleSupplierStatusAction $action): void
    {
        Gate::authorize('suppliers.edit');

        try {
            $count = $this->forEachBulkSelected(function (int $id) use ($action, $status): void {
                $action->execute($id, $status);
            });
            $this->clearBulkSelection();
            Flux::toast(variant: 'success', text: str_starts_with(app()->getLocale(), 'ar')
                ? "تم تحديث حالة {$count} مورد."
                : __('Supplier status updated for :count records.', ['count' => $count]));
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function openSupplierDetailModal(int $id): void
    {
        Gate::authorize('suppliers.view');
        $this->viewingSupplierId = $id;
        $this->detailTab = 'profile';
        $this->showDetailModal = true;
    }

    public function openLinkProductModal(): void
    {
        Gate::authorize('suppliers.edit');
        $this->productLinkForm = [
            'product_id' => '',
            'supplier_item_code' => '',
            'is_preferred' => false,
            'notes' => '',
        ];
        $this->resetValidation();
        $this->showLinkProductModal = true;
    }

    public function saveProductLink(SaveProductSupplierAction $action): void
    {
        Gate::authorize('suppliers.edit');
        $validated = $this->validate([
            'productLinkForm.product_id' => ['required', 'integer', 'exists:products,id'],
            'productLinkForm.supplier_item_code' => ['nullable', 'string', 'max:100'],
            'productLinkForm.is_preferred' => ['boolean'],
            'productLinkForm.notes' => ['nullable', 'string', 'max:1000'],
        ])['productLinkForm'];

        try {
            $action->execute([
                ...$validated,
                'supplier_id' => $this->viewingSupplierId,
            ]);
            Flux::toast(variant: 'success', text: __('Product linked to supplier successfully.'));
            $this->showLinkProductModal = false;
        } catch (Throwable $exception) {
            $this->addError('productLinkForm', \App\Support\UserSafeError::message($exception));
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function openCreateSupplierGroupModal(): void
    {
        Gate::authorize('suppliers.create');
        $this->editingSupplierGroupId = null;
        $this->supplierGroupForm = ['name_ar' => '', 'name_en' => '', 'code' => '', 'sort_order' => 0, 'parent_id' => '', 'status' => 'active'];
        $this->resetValidation();
        $this->showSupplierGroupModal = true;
    }

    public function openEditSupplierGroupModal(int $id): void
    {
        Gate::authorize('suppliers.edit');
        abort_unless($this->activeCompanyId !== null, 404);
        $group = SupplierGroup::query()->forCompany($this->activeCompanyId)->findOrFail($id);
        $this->editingSupplierGroupId = $group->id;
        $this->supplierGroupForm = [
            'name_ar' => $group->name_ar,
            'name_en' => $group->name_en ?? '',
            'code' => $group->code ?? '',
            'sort_order' => $group->sort_order ?? 0,
            'parent_id' => $group->parent_id ?? '',
            'status' => $group->status,
        ];
        $this->resetValidation();
        $this->showSupplierGroupModal = true;
    }

    public function saveSupplierGroup(SaveSupplierGroupAction $action): void
    {
        Gate::authorize($this->editingSupplierGroupId ? 'suppliers.edit' : 'suppliers.create');
        if ($this->activeCompanyId === null) {
            $this->addError('supplierGroupForm', __('Complete active company setup before creating supplier groups.'));
            return;
        }
        $validated = $this->validate([
            'supplierGroupForm.name_ar' => ['required', 'string', 'max:190'],
            'supplierGroupForm.name_en' => ['nullable', 'string', 'max:190'],
            'supplierGroupForm.code' => ['required', 'alpha_dash:ascii', 'max:50'],
            'supplierGroupForm.sort_order' => ['nullable', 'integer', 'min:0'],
            'supplierGroupForm.parent_id' => ['nullable', 'integer'],
            'supplierGroupForm.status' => ['required', 'in:active,inactive'],
        ])['supplierGroupForm'];
        try {
            $action->execute($validated, $this->activeCompanyId, $this->editingSupplierGroupId);
            $this->showSupplierGroupModal = false;
            Flux::toast(variant: 'success', text: __('Supplier group saved successfully.'));
        } catch (Throwable $exception) {
            $this->addError('supplierGroupForm', \App\Support\UserSafeError::message($exception));
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function openCreateSupplierContactModal(): void
    {
        Gate::authorize('suppliers.edit');
        $this->editingSupplierContactId = null;
        $this->supplierContactForm = ['role' => 'representative', 'name' => '', 'email' => '', 'phone' => '', 'mobile' => '', 'whatsapp' => '', 'notes' => '', 'is_primary' => false, 'status' => 'active'];
        $this->resetValidation();
        $this->showSupplierContactModal = true;
    }

    public function openEditSupplierContactModal(int $id): void
    {
        Gate::authorize('suppliers.edit');
        $contact = SupplierContact::query()->where('supplier_id', $this->viewingSupplierId)->findOrFail($id);
        $this->editingSupplierContactId = $contact->id;
        $this->supplierContactForm = $contact->only(['role', 'name', 'email', 'phone', 'mobile', 'whatsapp', 'notes', 'is_primary', 'status']);
        $this->resetValidation();
        $this->showSupplierContactModal = true;
    }

    public function saveSupplierContact(SaveSupplierContactAction $action): void
    {
        Gate::authorize('suppliers.edit');
        $validated = $this->validate([
            'supplierContactForm.role' => ['required', Rule::in(SupplierContact::ROLES)],
            'supplierContactForm.name' => ['required', 'string', 'max:255'],
            'supplierContactForm.email' => ['nullable', 'email', 'max:255'],
            'supplierContactForm.phone' => ['nullable', 'string', 'max:50', PhoneNormalizer::validationRule()],
            'supplierContactForm.mobile' => ['nullable', 'string', 'max:50', PhoneNormalizer::validationRule()],
            'supplierContactForm.whatsapp' => ['nullable', 'string', 'max:50', PhoneNormalizer::validationRule()],
            'supplierContactForm.notes' => ['nullable', 'string', 'max:1000'],
            'supplierContactForm.is_primary' => ['boolean'],
            'supplierContactForm.status' => ['required', 'in:active,inactive'],
        ])['supplierContactForm'];
        try {
            $action->execute((int) $this->viewingSupplierId, $validated, $this->editingSupplierContactId);
            $this->showSupplierContactModal = false;
            Flux::toast(variant: 'success', text: __('Supplier contact saved successfully.'));
        } catch (Throwable $exception) {
            $this->addError('supplierContactForm', \App\Support\UserSafeError::message($exception));
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function openCreateSupplierDestinationModal(): void
    {
        Gate::authorize('suppliers.edit');
        $this->editingSupplierDestinationId = null;
        $this->supplierDestinationForm = ['purpose' => 'purchase_order', 'channel' => 'email', 'destination' => '', 'label' => '', 'is_primary' => false, 'status' => 'active'];
        $this->resetValidation();
        $this->showSupplierDestinationModal = true;
    }

    public function openEditSupplierDestinationModal(int $id): void
    {
        Gate::authorize('suppliers.edit');
        $destination = SupplierCommunicationDestination::query()->where('supplier_id', $this->viewingSupplierId)->findOrFail($id);
        $this->editingSupplierDestinationId = $destination->id;
        $this->supplierDestinationForm = $destination->only(['purpose', 'channel', 'destination', 'label', 'is_primary', 'status']);
        $this->resetValidation();
        $this->showSupplierDestinationModal = true;
    }

    public function saveSupplierDestination(SaveSupplierCommunicationDestinationAction $action): void
    {
        Gate::authorize('suppliers.edit');
        $validated = $this->validate([
            'supplierDestinationForm.purpose' => ['required', Rule::in(SupplierCommunicationDestination::PURPOSES)],
            'supplierDestinationForm.channel' => ['required', Rule::in(SupplierCommunicationDestination::CHANNELS)],
            'supplierDestinationForm.destination' => ['required', 'string', 'max:255'],
            'supplierDestinationForm.label' => ['nullable', 'string', 'max:190'],
            'supplierDestinationForm.is_primary' => ['boolean'],
            'supplierDestinationForm.status' => ['required', 'in:active,inactive'],
        ])['supplierDestinationForm'];
        try {
            $action->execute((int) $this->viewingSupplierId, $validated, $this->editingSupplierDestinationId);
            $this->showSupplierDestinationModal = false;
            Flux::toast(variant: 'success', text: __('Supplier communication destination saved successfully.'));
        } catch (Throwable $exception) {
            $this->addError('supplierDestinationForm', \App\Support\UserSafeError::message($exception));
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function render()
    {
        $supplierGroupQuery = SupplierGroup::query()->forCompany((int) $this->activeCompanyId)->withCount('suppliers')
            ->when($this->supplierGroupStatus !== 'all', fn ($query) => $query->where('status', $this->supplierGroupStatus))
            ->when($this->supplierGroupHierarchy === 'root', fn ($query) => $query->whereNull('parent_id'))
            ->when($this->supplierGroupHierarchy === 'leaf', fn ($query) => $query->whereDoesntHave('children'));
        $supplierGroups = $this->activeCompanyId === null ? collect() : GroupHierarchy::flatten($supplierGroupQuery->get(), trim($this->supplierGroupSearch));
        $supplierGroupParents = $this->activeCompanyId === null ? collect() : SupplierGroup::query()->forCompany($this->activeCompanyId)->active()->orderBy('parent_id')->orderBy('name_ar')->limit(200)->get(['id', 'parent_id', 'name_ar', 'name_en']);
        $isSupplierMasters = $this->section === 'supplier-masters';
        $suppliers = null;
        $viewingSupplier = null;
        $availableProducts = collect();

        if ($isSupplierMasters) {
            $query = Supplier::query()->with(['supplierGroup', 'preferredPaymentMethod'])->withCount('productSuppliers');
            $term = trim($this->search);

            if ($term !== '') {
                $like = '%'.$term.'%';
                $query->where(fn ($scope) => $scope->where('code', 'like', $like)
                    ->orWhere('name_ar', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('tax_number', 'like', $like)
                );
            }

            if ($this->statusFilter !== 'all') {
                $query->where('status', $this->statusFilter);
            }

            if ($this->supplierGroupFilter !== 'all' && $this->activeCompanyId !== null) {
                $query->whereHas('supplierGroup', fn ($group) => $group->forCompany($this->activeCompanyId)->whereKey((int) $this->supplierGroupFilter));
            }

            $suppliers = $query->orderBy('code')->paginate(15);
            $viewingSupplier = $this->viewingSupplierId
                ? Supplier::query()->with(['productSuppliers.product', 'supplierGroup', 'preferredPaymentMethod', 'contacts'])->find($this->viewingSupplierId)
                : null;
            if ($this->showLinkProductModal) {
                $availableProducts = Product::query()->where('status', 'active')->orderBy('item_code')->limit(200)->get(['id', 'item_code', 'name_ar', 'name_en']);
            }
        }

        return view('catalog.suppliers', [
            'suppliers' => $suppliers,
            'viewingSupplier' => $viewingSupplier,
            'availableProducts' => $availableProducts,
            'settlementMethods' => SupplierSettlementMethod::options(),
            'supplierGroups' => $supplierGroups,
            'supplierGroupParents' => $supplierGroupParents,
            'canCreate' => Gate::allows('suppliers.create'),
            'canEdit' => Gate::allows('suppliers.edit'),
        ]);
    }
}; ?>

<x-app.page
    :title="$section === 'supplier-groups' ? (str_starts_with(app()->getLocale(), 'ar') ? 'تهيئة مجموعات الموردين' : __('Supplier group setup')) : (str_starts_with(app()->getLocale(), 'ar') ? 'الموردون' : __('Supplier Masters & Product-Supplier History'))"
    :description="$section === 'supplier-groups' ? (str_starts_with(app()->getLocale(), 'ar') ? 'رتّب الموردين في مجموعات لتسهيل البحث والمتابعة.' : __('Create and maintain the supplier hierarchy before assigning supplier masters to a group.')) : (str_starts_with(app()->getLocale(), 'ar') ? 'أضف هوية المورد وجهات التواصل المنظمة، ثم اربط منتجاته عند الحاجة.' : __('Maintain supplier identity, structured contacts, preferred product relations, and purchase history.'))"
    max-width="7xl"
    class="catalog-screen"
    data-guide="suppliers-header"
>
    <x-slot:actions>
        <div class="table-resource-toolbar">
            <div class="table-resource-toolbar__controls">
            @if ($canCreate && $section === 'supplier-masters')
                <flux:button
                    icon="plus"
                    variant="primary"
                    wire:click="openCreateSupplierModal"
                    data-guide="suppliers-add-action"
                >
                    {{ str_starts_with(app()->getLocale(), 'ar') ? 'إضافة مورد' : __('Add supplier') }}
                </flux:button>
                <flux:button icon="folder-open" variant="subtle" :href="route('catalog.suppliers', ['section' => 'supplier-groups'])" wire:navigate>
                    {{ str_starts_with(app()->getLocale(), 'ar') ? 'إدارة مجموعات الموردين' : __('Manage supplier groups') }}
                </flux:button>
            @elseif ($canCreate && $section === 'supplier-groups')
                <flux:button icon="folder-plus" variant="primary" wire:click="openCreateSupplierGroupModal" data-guide="supplier-groups-add-action">
                    {{ str_starts_with(app()->getLocale(), 'ar') ? 'إضافة مجموعة موردين' : __('Add supplier group') }}
                </flux:button>
            @endif
            </div>
        </div>
    </x-slot:actions>

    <div class="space-y-5" data-supplier-workspace="{{ $section }}">
        @if ($section === 'supplier-groups')
            <section class="space-y-5" data-guide="supplier-groups-workspace">
                <flux:callout variant="info" icon="information-circle" :title="str_starts_with(app()->getLocale(), 'ar') ? 'تهيئة مجموعات الموردين' : __('Supplier group setup')">
                    {{ str_starts_with(app()->getLocale(), 'ar') ? 'قسّم الموردين إلى مجموعات لتسهيل البحث وتنظيم بيانات الشراء.' : __('Create and maintain the supplier hierarchy here. Supplier master records are assigned to a group from the Supplier masters workspace.') }}
                </flux:callout>

                <flux:card class="space-y-4 p-5 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg">{{ str_starts_with(app()->getLocale(), 'ar') ? 'مجموعات الموردين' : __('Supplier groups') }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? 'أنشئ مجموعة رئيسية، ثم أضف مجموعات فرعية عند الحاجة.' : __('Use parent groups to keep related suppliers organized. Group status does not alter historical supplier records.') }}</flux:text>
                        </div>
                        @if ($canCreate)
                            <flux:button icon="folder-plus" variant="primary" wire:click="openCreateSupplierGroupModal">
                                {{ str_starts_with(app()->getLocale(), 'ar') ? 'إضافة مجموعة موردين' : __('Add supplier group') }}
                            </flux:button>
                        @endif
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <flux:input wire:model.live.debounce.300ms="supplierGroupSearch" icon="magnifying-glass" :label="__('Search')" />
                        <flux:select wire:model.live="supplierGroupStatus" :label="__('Status')"><option value="all">{{ __('All statuses') }}</option><option value="active">{{ __('Active') }}</option><option value="inactive">{{ __('Inactive') }}</option></flux:select>
                        <flux:select wire:model.live="supplierGroupHierarchy" :label="__('Hierarchy')"><option value="all">{{ __('All levels') }}</option><option value="root">{{ __('Root groups') }}</option><option value="leaf">{{ __('Leaf groups') }}</option></flux:select>
                    </div>
                    <div class="app-table-frame"><table class="data-table responsive-resource-table min-w-full">
                        <thead><tr><th>{{ __('Supplier group') }}</th><th>{{ __('Parent / path') }}</th><th>{{ __('Order') }}</th><th>{{ __('Suppliers') }}</th><th>{{ __('Status') }}</th><th class="text-center">{{ __('Actions') }}</th></tr></thead>
                        <tbody>@forelse($supplierGroups as $group)
                            <tr>
                                <td data-primary style="padding-inline-start: {{ 1.25 + ($group->hierarchy_depth * 1.25) }}rem"><div class="font-semibold">{{ $group->hierarchy_depth ? '↳ ' : '' }}{{ str_starts_with(app()->getLocale(), 'ar')?$group->name_ar:$group->name_en }}</div><div class="text-xs text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar')?$group->name_en:$group->name_ar }}</div><span class="catalog-code-chip" dir="ltr">{{ $group->code }}</span></td>
                                <td data-label="{{ __('Parent / path') }}">{{ $group->hierarchy_path }}</td>
                                <td data-label="{{ __('Order') }}" class="text-center tabular-nums">{{ $group->sort_order }}</td>
                                <td data-label="{{ __('Suppliers') }}" class="text-center tabular-nums">{{ $group->suppliers_count }}</td>
                                <td data-label="{{ __('Status') }}"><x-status.badge :status="$group->status" /></td>
                                <td data-label="{{ __('Actions') }}" class="text-center">@if($canEdit)<x-actions.button semantic="edit" :label="__('Edit supplier group')" wire:click="openEditSupplierGroupModal({{ $group->id }})" />@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-state.empty :title="__('No supplier groups yet')" :description="__('Create a root group first, then add optional child groups as the hierarchy grows.')" /></td></tr>
                        @endforelse</tbody>
                    </table></div>
                </flux:card>
            </section>
        @else
            <section class="space-y-5" data-guide="supplier-masters-workspace">
        <flux:card id="suppliers-filters" class="scroll-mt-24 space-y-4 p-5 sm:p-6" data-guide="suppliers-filters">
            <div>
                <flux:heading size="sm">{{ str_starts_with(app()->getLocale(), 'ar') ? 'البحث والتصفية' : 'Search and filters' }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ str_starts_with(app()->getLocale(), 'ar') ? 'ابحث عن المورد أو حدّد الحالة والمجموعة.' : 'Find a supplier or narrow the list by status and group.' }}</flux:text>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    :label="str_starts_with(app()->getLocale(), 'ar') ? 'بحث' : 'Search'"
                    :placeholder="str_starts_with(app()->getLocale(), 'ar') ? 'الكود أو الاسم أو الهاتف أو الرقم الضريبي' : __('Search by code, name, contact, phone, or tax number...')"
                    clearable
                />
                <flux:select wire:model.live="statusFilter" :label="str_starts_with(app()->getLocale(), 'ar') ? 'الحالة' : __('Status filter')">
                    <option value="all">{{ str_starts_with(app()->getLocale(), 'ar') ? 'كل الحالات' : __('All status') }}</option>
                    <option value="active">{{ str_starts_with(app()->getLocale(), 'ar') ? 'نشط' : __('Active only') }}</option>
                    <option value="inactive">{{ str_starts_with(app()->getLocale(), 'ar') ? 'غير نشط' : __('Inactive only') }}</option>
                </flux:select>
                <flux:select wire:model.live="supplierGroupFilter" :label="str_starts_with(app()->getLocale(), 'ar') ? 'مجموعة الموردين' : __('Supplier group')">
                    <option value="all">{{ str_starts_with(app()->getLocale(), 'ar') ? 'كل المجموعات' : __('All supplier groups') }}</option>
                    @foreach ($supplierGroups as $group)
                        <option value="{{ $group->id }}">
                            {{ $group->name_ar }}{{ $group->name_en ? ' — '.$group->name_en : '' }} ({{ $group->suppliers_count }})
                        </option>
                    @endforeach
                </flux:select>
            </div>
        </flux:card>

        <div class="rounded-xl border border-border bg-surface p-4" data-guide="suppliers-bulk-actions">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? 'تحديث الموردين المحددين' : 'Update selected suppliers' }}</div>
                    <div class="text-sm text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? 'حدد موردين ثم فعّلهم أو عطّلهم مرة واحدة.' : 'Select suppliers, then activate or deactivate them together.' }}</div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($canEdit)
                        <flux:button type="button" size="sm" variant="subtle" wire:click="bulkSetSupplierStatus('active')" wire:confirm="{{ str_starts_with(app()->getLocale(), 'ar') ? 'تفعيل الموردين المحددين؟' : 'Activate the selected suppliers?' }}">
                            {{ str_starts_with(app()->getLocale(), 'ar') ? 'تفعيل المحدد' : 'Activate selected' }}
                        </flux:button>
                        <flux:button type="button" size="sm" variant="subtle" wire:click="bulkSetSupplierStatus('inactive')" wire:confirm="{{ str_starts_with(app()->getLocale(), 'ar') ? 'تعطيل الموردين المحددين؟' : 'Deactivate the selected suppliers?' }}">
                            {{ str_starts_with(app()->getLocale(), 'ar') ? 'تعطيل المحدد' : 'Deactivate selected' }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>

        <flux:card class="overflow-hidden p-0" data-guide="suppliers-table">
            <div class="app-table-frame">
                <table class="data-table responsive-resource-table min-w-full">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                        <tr>
                            <th scope="col" class="w-12 px-3 py-3 text-center text-xs font-semibold text-text-muted"><span class="sr-only">{{ str_starts_with(app()->getLocale(), 'ar') ? 'تحديد' : __('Select') }}</span></th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-text-muted uppercase tracking-wider">{{ str_starts_with(app()->getLocale(), 'ar') ? 'كود المورد' : __('Supplier code') }}</th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-text-muted uppercase tracking-wider">{{ str_starts_with(app()->getLocale(), 'ar') ? 'اسم المورد' : __('Bilingual name') }}</th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-text-muted uppercase tracking-wider">{{ str_starts_with(app()->getLocale(), 'ar') ? 'بيانات التواصل' : __('Contact info') }}</th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-text-muted uppercase tracking-wider">{{ str_starts_with(app()->getLocale(), 'ar') ? 'الرقم الضريبي' : __('Tax number') }}</th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-text-muted uppercase tracking-wider">{{ str_starts_with(app()->getLocale(), 'ar') ? 'الحالة' : __('Status') }}</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-text-muted uppercase tracking-wider">{{ str_starts_with(app()->getLocale(), 'ar') ? 'المنتجات' : __('Products') }}</th>
                            <th scope="col" class="px-4 py-3 text-end text-xs font-semibold text-text-muted uppercase tracking-wider">{{ str_starts_with(app()->getLocale(), 'ar') ? 'إجراءات' : __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-white dark:bg-zinc-900">
                        @forelse ($suppliers as $supplier)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-3 py-3 text-center">
                                    <input type="checkbox" value="{{ $supplier->id }}" wire:model.live="selectedIds" aria-label="{{ str_starts_with(app()->getLocale(), 'ar') ? 'تحديد المورد '.$supplier->code : __('Select supplier :code', ['code' => $supplier->code]) }}" class="size-4 rounded border-border text-primary focus:ring-primary" />
                                </td>
                                <td data-primary class="px-4 py-3 text-sm font-medium whitespace-nowrap">
                                    <span class="catalog-code-chip">{{ $supplier->code }}</span>
                                </td>
                                <td data-label="{{ str_starts_with(app()->getLocale(), 'ar') ? 'اسم المورد' : __('Supplier') }}" class="px-4 py-3 text-sm">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ str_starts_with(app()->getLocale(), 'ar') ? $supplier->name_ar : $supplier->name_en }}
                                    </div>
                                    <div class="text-xs text-text-muted">
                                        {{ str_starts_with(app()->getLocale(), 'ar') ? $supplier->name_en : $supplier->name_ar }}
                                    </div>
                                </td>
                                <td data-label="{{ str_starts_with(app()->getLocale(), 'ar') ? 'التواصل' : __('Contact') }}" class="px-4 py-3 text-sm">
                                    @if ($supplier->contact_name)
                                        <div class="font-medium text-xs">{{ $supplier->contact_name }}</div>
                                    @endif
                                    @if ($supplier->phone)
                                        <div class="text-xs text-text-muted" dir="ltr">{{ $supplier->phone }}</div>
                                    @endif
                                    @if ($supplier->email)
                                        <div class="text-xs text-text-muted truncate max-w-48">{{ $supplier->email }}</div>
                                    @endif
                                    @if (! $supplier->contact_name && ! $supplier->phone && ! $supplier->email)
                                        <span class="text-xs text-text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="{{ str_starts_with(app()->getLocale(), 'ar') ? 'الرقم الضريبي' : __('Tax number') }}" class="px-4 py-3 text-sm whitespace-nowrap text-text-muted font-mono text-xs">
                                    {{ $supplier->tax_number ?: '—' }}
                                </td>
                                <td data-label="{{ __('Status') }}" class="px-4 py-3 text-sm whitespace-nowrap">
                                    <flux:badge size="sm" color="{{ $supplier->status === 'active' ? 'emerald' : 'zinc' }}">
                                        {{ __($supplier->status === 'active' ? 'Active' : 'Inactive') }}
                                    </flux:badge>
                                </td>
                                <td data-label="{{ str_starts_with(app()->getLocale(), 'ar') ? 'المنتجات' : __('Products') }}" class="px-4 py-3 text-sm text-center whitespace-nowrap">
                                    <flux:badge size="sm" color="zinc">{{ $supplier->product_suppliers_count }}</flux:badge>
                                </td>
                                <td data-label="{{ __('Actions') }}" class="px-4 py-3 text-sm text-end whitespace-nowrap space-x-1 rtl:space-x-reverse">
                                    <x-actions.button semantic="view" :label="__('View supplier details & history')" size="xs" wire:click="openSupplierDetailModal({{ $supplier->id }})" />
                                    @if ($canEdit)
                                        <x-actions.button semantic="edit" :label="__('Edit supplier master')" size="xs" wire:click="openEditSupplierModal({{ $supplier->id }})" />
                                        <flux:button
                                            size="xs"
                                            variant="subtle"
                                            icon="arrow-path"
                                            wire:click="toggleSupplierStatus({{ $supplier->id }})"
                                            wire:confirm="{{ str_starts_with(app()->getLocale(), 'ar') ? ($supplier->status === 'active' ? 'تعطيل المورد؟ سيظل سجله محفوظاً.' : 'تفعيل المورد؟') : __('Change supplier :name to :status? Its historical records are preserved.', ['name' => $supplier->name_en ?: $supplier->name_ar, 'status' => $supplier->status === 'active' ? __('Inactive') : __('Active')]) }}"
                                            title="{{ str_starts_with(app()->getLocale(), 'ar') ? ($supplier->status === 'active' ? 'تعطيل المورد' : 'تفعيل المورد') : ($supplier->status === 'active' ? __('Deactivate supplier') : __('Activate supplier')) }}"
                                        />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center" data-guide="suppliers-empty">
                                    <x-state.empty
                                        :title="__('No suppliers found')"
                                        :message="__('No supplier master records match the active filters or search criteria.')"
                                        icon="truck"
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-tables.pagination :paginator="$suppliers" />
        </flux:card>
            </section>
        @endif
    </div>

    @if ($section === 'supplier-masters')
    <!-- Create / Edit Supplier Modal -->
    @if ($showSupplierModal)
    <flux:modal wire:key="supplier-modal-content-{{ $editingSupplierId === null ? 'new' : 'edit-'.$editingSupplierId }}" wire:model="showSupplierModal" class="w-full max-w-[calc(100vw-2rem)] md:max-w-xl">
        <div wire:key="supplier-form-{{ $editingSupplierId === null ? 'new' : 'edit-'.$editingSupplierId }}" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ str_starts_with(app()->getLocale(), 'ar') ? ($editingSupplierId ? 'تعديل المورد' : 'إضافة مورد') : ($editingSupplierId ? __('Edit Supplier Master') : __('Create Supplier Master')) }}
                </flux:heading>
                <flux:text class="text-sm text-text-muted">
                    {{ str_starts_with(app()->getLocale(), 'ar') ? 'أدخل هوية المورد وبيانات التواصل الأساسية.' : __('Maintain compact supplier identity and core contact details.') }}
                </flux:text>
            </div>

            <form wire:submit.prevent="saveSupplier" novalidate class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex items-end gap-2"><flux:input class="min-w-0 flex-1" wire:model="supplierForm.code" :label="str_starts_with(app()->getLocale(), 'ar') ? 'كود المورد' : __('Supplier code')" placeholder="SUP-001" required :disabled="$editingSupplierId !== null" />@if ($editingSupplierId === null)<flux:button type="button" variant="subtle" icon="sparkles" wire:click="generateSupplierCode" wire:loading.attr="disabled" wire:target="generateSupplierCode" title="{{ __('Generate code automatically') }}" aria-label="{{ __('Generate code automatically') }}">{{ __('Generate automatically') }}</flux:button>@endif</div>
                    <flux:input wire:model="supplierForm.barcode_prefix" :label="__('Barcode prefix')" maxlength="12" placeholder="AHMED01" />
                    <flux:select wire:model="supplierForm.status" :label="str_starts_with(app()->getLocale(), 'ar') ? 'الحالة' : __('Operational status')">
                        <option value="active">{{ str_starts_with(app()->getLocale(), 'ar') ? 'نشط' : __('Active') }}</option>
                        <option value="inactive">{{ str_starts_with(app()->getLocale(), 'ar') ? 'غير نشط' : __('Inactive') }}</option>
                    </flux:select>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model="supplierForm.name_ar"
                        :label="str_starts_with(app()->getLocale(), 'ar') ? 'اسم المورد بالعربية' : __('Arabic name')"
                        placeholder="مورد جديد"
                        required
                        dir="rtl"
                    />
                    <flux:input
                        wire:model="supplierForm.name_en"
                        :label="str_starts_with(app()->getLocale(), 'ar') ? 'اسم المورد بالإنجليزية' : __('English name')"
                        placeholder="New Supplier Ltd"
                        required
                        dir="ltr"
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input
                        wire:model="supplierForm.contact_name"
                        :label="str_starts_with(app()->getLocale(), 'ar') ? 'اسم جهة الاتصال' : __('Contact name')"
                        placeholder="John Doe"
                    />
                    <flux:input
                        wire:model="supplierForm.phone"
                        :label="str_starts_with(app()->getLocale(), 'ar') ? 'رقم الهاتف' : __('Phone number')"
                        :placeholder="__('e.g. 01012345678 or +20 1012345678')"
                        dir="ltr"
                    />
                    <flux:input
                        wire:model="supplierForm.email"
                        :label="str_starts_with(app()->getLocale(), 'ar') ? 'البريد الإلكتروني' : __('Email address')"
                        type="email"
                        placeholder="supplier@example.com"
                        dir="ltr"
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model="supplierForm.tax_number"
                        :label="str_starts_with(app()->getLocale(), 'ar') ? 'الرقم الضريبي' : __('Tax registration number')"
                        placeholder="123-456-789"
                    />
                    <flux:input wire:model="supplierForm.commercial_registration" :label="__('Commercial registration')" />
                    <flux:select wire:model.live="supplierForm.payment_policy" :label="__('Payment policy')">
                        <option value="">{{ __('Not specified') }}</option>
                        <option value="cash">{{ __('Cash') }}</option>
                        <option value="credit">{{ __('Credit') }}</option>
                        <option value="both">{{ __('Cash and credit') }}</option>
                    </flux:select>
                    @if (in_array(($supplierForm['payment_policy'] ?? ''), ['credit', 'both'], true))
                        <flux:input wire:model="supplierForm.credit_days" type="number" min="0" :label="__('Credit days')" />
                        <flux:input wire:model="supplierForm.credit_limit" type="number" min="0" step="0.0001" :label="__('Credit limit')" />
                    @endif
                    <flux:select wire:model.live="supplierForm.settlement_method" :label="str_starts_with(app()->getLocale(), 'ar') ? 'أسلوب سداد مستحقات المورد' : __('Supplier Settlement Method')" required>
                        <option value="">{{ __('Select settlement method') }}</option>
                        @foreach ($settlementMethods as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </flux:select>
                    @if (($supplierForm['settlement_method'] ?? '') === 'other')
                        <flux:input wire:model="supplierForm.settlement_other_description" :label="__('Other settlement description')" required />
                    @endif
                </div>

                <flux:textarea
                    wire:model="supplierForm.address"
                    :label="str_starts_with(app()->getLocale(), 'ar') ? 'العنوان' : __('Address / Location')"
                    rows="2"
                    placeholder="Supplier physical or mailing address..."
                />
                <flux:textarea wire:model="supplierForm.notes" :label="__('Notes')" rows="2" />

                <flux:select wire:model="supplierForm.supplier_group_id" :label="str_starts_with(app()->getLocale(), 'ar') ? 'مجموعة الموردين (اختياري)' : __('Supplier group')">
                    <option value="">{{ str_starts_with(app()->getLocale(), 'ar') ? 'بدون مجموعة' : __('No supplier group') }}</option>
                    @foreach ($supplierGroups as $group)
                        <option value="{{ $group->id }}">{{ $group->name_ar }}{{ $group->name_en ? ' — '.$group->name_en : '' }}</option>
                    @endforeach
                </flux:select>

                @error('supplierForm')
                    <flux:callout variant="danger" icon="exclamation-triangle" class="text-sm">
                        {{ $message }}
                    </flux:callout>
                @enderror

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-border">
                    <flux:button variant="subtle" wire:click="$set('showSupplierModal', false)">
                        {{ str_starts_with(app()->getLocale(), 'ar') ? 'إلغاء' : __('Cancel') }}
                    </flux:button>
                    <x-actions.loading-button type="submit" action="saveSupplier" :label="__('Save')" />
                </div>
            </form>
        </div>
    </flux:modal>
    @endif

    <!-- Supplier Detail & History Modal -->
    <flux:modal wire:model="showDetailModal" class="md:max-w-3xl">
        @if ($viewingSupplier)
            <div class="space-y-6">
                <div class="flex items-center justify-between border-b border-border pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="catalog-code-chip">{{ $viewingSupplier->code }}</span>
                            <flux:badge size="sm" color="{{ $viewingSupplier->status === 'active' ? 'emerald' : 'zinc' }}">
                                {{ __($viewingSupplier->status === 'active' ? 'Active' : 'Inactive') }}
                            </flux:badge>
                        </div>
                        <flux:heading size="xl" class="mt-1">
                            {{ str_starts_with(app()->getLocale(), 'ar') ? $viewingSupplier->name_ar : $viewingSupplier->name_en }}
                        </flux:heading>
                        <flux:text class="text-sm text-text-muted">
                            {{ str_starts_with(app()->getLocale(), 'ar') ? $viewingSupplier->name_en : $viewingSupplier->name_ar }}
                        </flux:text>
                    </div>

                    <div class="flex gap-2">
                        <flux:button
                            size="sm"
                            variant="{{ $detailTab === 'profile' ? 'primary' : 'subtle' }}"
                            wire:click="$set('detailTab', 'profile')"
                        >
                            {{ __('Profile') }}
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="{{ $detailTab === 'products' ? 'primary' : 'subtle' }}"
                            wire:click="$set('detailTab', 'products')"
                        >
                            {{ __('Linked Products') }} ({{ $viewingSupplier->productSuppliers->count() }})
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="{{ $detailTab === 'contacts' ? 'primary' : 'subtle' }}"
                            wire:click="$set('detailTab', 'contacts')"
                        >
                            {{ __('Contacts') }} ({{ $viewingSupplier->contacts->count() }})
                        </flux:button>
                    </div>
                </div>

                @if ($detailTab === 'profile')
                    {{-- `<dt>`/`<dd>` require a `<dl>` ancestor (WCAG 1.3.1 / axe-core `dlitem`); this
                         was a plain `<div>` with no list semantics at all. --}}
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div class="catalog-detail-field">
                            <dt class="catalog-detail-label">{{ __('Contact person') }}</dt>
                            <dd class="mt-1 text-sm font-medium">{{ $viewingSupplier->contact_name ?: __('Not provided') }}</dd>
                        </div>
                        <div class="catalog-detail-field">
                            <dt class="catalog-detail-label">{{ __('Phone number') }}</dt>
                            <dd class="mt-1 text-sm font-medium" dir="ltr">{{ $viewingSupplier->phone ?: __('Not provided') }}</dd>
                        </div>
                        <div class="catalog-detail-field">
                            <dt class="catalog-detail-label">{{ __('Email address') }}</dt>
                            <dd class="mt-1 text-sm font-medium" dir="ltr">{{ $viewingSupplier->email ?: __('Not provided') }}</dd>
                        </div>
                        <div class="catalog-detail-field">
                            <dt class="catalog-detail-label">{{ __('Tax registration number') }}</dt>
                            <dd class="mt-1 text-sm font-medium font-mono">{{ $viewingSupplier->tax_number ?: __('Not provided') }}</dd>
                        </div>
                        <div class="catalog-detail-field sm:col-span-2">
                            <dt class="catalog-detail-label">{{ __('Supplier group') }}</dt>
                            <dd class="mt-1 text-sm font-medium">{{ $viewingSupplier->supplierGroup?->name_ar ?: __('No supplier group') }}</dd>
                        </div>
                        <div class="catalog-detail-field sm:col-span-2">
                            <dt class="catalog-detail-label">{{ str_starts_with(app()->getLocale(), 'ar') ? 'طريقة الدفع المفضلة' : __('Preferred payment method') }}</dt>
                            <dd class="mt-1 text-sm font-medium">{{ $viewingSupplier->preferredPaymentMethod ? (str_starts_with(app()->getLocale(), 'ar') ? $viewingSupplier->preferredPaymentMethod->name_ar : $viewingSupplier->preferredPaymentMethod->name_en) : __('Not configured') }}</dd>
                        </div>
                        <div class="catalog-detail-field sm:col-span-2">
                            <dt class="catalog-detail-label">{{ __('Address') }}</dt>
                            <dd class="mt-1 text-sm font-medium whitespace-pre-line">{{ $viewingSupplier->address ?: __('Not provided') }}</dd>
                        </div>
                    </dl>
                @elseif ($detailTab === 'contacts')
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <flux:heading size="sm">{{ __('Structured supplier contacts') }}</flux:heading>
                            @if ($canEdit)
                                <flux:button size="xs" variant="primary" icon="plus" wire:click="openCreateSupplierContactModal">{{ __('Add contact') }}</flux:button>
                            @endif
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @forelse ($viewingSupplier->contacts as $contact)
                                <div class="rounded-lg border border-border p-3 space-y-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-medium">{{ $contact->name }}</span>
                                        <flux:badge size="sm" color="zinc">{{ __(ucwords(str_replace('_', ' ', $contact->role))) }}</flux:badge>
                                    </div>
                                    <div class="text-xs text-text-muted">{{ $contact->email ?: __('No email') }} · {{ $contact->phone ?: __('No phone') }} @if($contact->mobile)· {{ $contact->mobile }}@endif</div>
                                    @if($contact->notes)<p class="text-xs text-text-muted">{{ $contact->notes }}</p>@endif
                                    @if ($contact->is_primary)<flux:badge size="sm" color="emerald">{{ __('Primary') }}</flux:badge>@endif
                                    @if ($canEdit)<flux:button size="xs" variant="subtle" icon="pencil" wire:click="openEditSupplierContactModal({{ $contact->id }})">{{ __('Edit') }}</flux:button>@endif
                                </div>
                            @empty
                                <div class="sm:col-span-2"><x-state.empty :title="__('No structured contacts yet')" :message="__('Add owner, representative, order, or accounting contacts without changing the legacy supplier fields.')" icon="users" /></div>
                            @endforelse
                        </div>
                    </div>
                @elseif ($detailTab === 'products')
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <flux:heading size="sm">{{ __('Products supplied by this supplier') }}</flux:heading>
                            @if ($canEdit)
                                <flux:button size="xs" variant="primary" icon="plus" wire:click="openLinkProductModal">
                                    {{ __('Link product') }}
                                </flux:button>
                            @endif
                        </div>

                        <div class="overflow-x-auto rounded-lg border border-border">
                            <table class="min-w-full divide-y divide-border">
                                <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                                    <tr>
                                        <th class="px-3 py-2 text-start text-xs font-semibold text-text-muted">{{ __('Item code') }}</th>
                                        <th class="px-3 py-2 text-start text-xs font-semibold text-text-muted">{{ __('Product name') }}</th>
                                        <th class="px-3 py-2 text-start text-xs font-semibold text-text-muted">{{ __('Supplier item code') }}</th>
                                        <th class="px-3 py-2 text-center text-xs font-semibold text-text-muted">{{ __('Preferred') }}</th>
                                        <th class="px-3 py-2 text-end text-xs font-semibold text-text-muted">{{ __('Last price') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @forelse ($viewingSupplier->productSuppliers as $ps)
                                        <tr>
                                            <td class="px-3 py-2 text-xs font-mono"><span class="catalog-code-chip">{{ $ps->product?->item_code }}</span></td>
                                            <td class="px-3 py-2 text-xs font-medium">{{ str_starts_with(app()->getLocale(), 'ar') ? $ps->product?->name_ar : $ps->product?->name_en }}</td>
                                            <td class="px-3 py-2 text-xs text-text-muted font-mono">{{ $ps->supplier_item_code ?: '—' }}</td>
                                            <td class="px-3 py-2 text-xs text-center">
                                                @if ($ps->is_preferred)
                                                    <flux:badge size="sm" color="amber">{{ __('Preferred') }}</flux:badge>
                                                @else
                                                    <span class="text-text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-xs text-end text-text-muted">
                                                {{ $ps->last_purchase_price ? number_format($ps->last_purchase_price, 2) : '—' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-3 py-6 text-center text-xs text-text-muted">
                                                {{ __('No products linked to this supplier yet.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="flex justify-end border-t border-border pt-4">
                    <flux:button variant="subtle" wire:click="$set('showDetailModal', false)">
                        {{ __('Close') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Link Product Modal -->
    <flux:modal wire:model="showLinkProductModal" class="md:max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Link Product to Supplier') }}</flux:heading>
                <flux:text class="text-sm text-text-muted">{{ __('Associate a catalog product with this supplier master record.') }}</flux:text>
            </div>

            <form wire:submit.prevent="saveProductLink" class="space-y-4">
                <flux:select wire:model="productLinkForm.product_id" :label="__('Select product')" required>
                    <option value="">{{ __('Select a product...') }}</option>
                    @foreach ($availableProducts as $prod)
                        <option value="{{ $prod->id }}">{{ $prod->item_code }} — {{ str_starts_with(app()->getLocale(), 'ar') ? $prod->name_ar : $prod->name_en }}</option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="productLinkForm.supplier_item_code"
                    :label="__('Supplier item code / reference')"
                    placeholder="SKU-SUP-123"
                />

                <div class="flex items-center gap-2 pt-2">
                    <flux:checkbox wire:model="productLinkForm.is_preferred" :label="__('Set as preferred supplier for this product')" />
                </div>

                <flux:textarea
                    wire:model="productLinkForm.notes"
                    :label="__('Notes')"
                    rows="2"
                    placeholder="Optional supply notes or minimum order quantities..."
                />

                @error('productLinkForm')
                    <flux:callout variant="danger" icon="exclamation-triangle" class="text-sm">
                        {{ $message }}
                    </flux:callout>
                @enderror

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-border">
                    <flux:button variant="subtle" wire:click="$set('showLinkProductModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveProductLink"><span wire:loading.remove wire:target="saveProductLink">{{ __('Save Product Link') }}</span><span wire:loading wire:target="saveProductLink">{{ __('Saving...') }}</span></flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    @endif

    @if ($section === 'supplier-groups')
    <flux:modal wire:model="showSupplierGroupModal" class="md:max-w-lg">
        <div class="space-y-5">
            <flux:heading size="lg">{{ str_starts_with(app()->getLocale(), 'ar') ? ($editingSupplierGroupId ? 'تعديل مجموعة موردين' : 'إضافة مجموعة موردين') : ($editingSupplierGroupId ? __('Edit supplier group') : __('Create supplier group')) }}</flux:heading>
            <form wire:submit.prevent="saveSupplierGroup" class="space-y-4">
                <flux:input wire:model="supplierGroupForm.name_ar" :label="str_starts_with(app()->getLocale(), 'ar') ? 'اسم المجموعة بالعربية' : __('Arabic group name')" required dir="rtl" />
                <flux:input wire:model="supplierGroupForm.name_en" :label="str_starts_with(app()->getLocale(), 'ar') ? 'اسم المجموعة بالإنجليزية (اختياري)' : __('English group name (optional)')" dir="ltr" />
                <flux:input wire:model="supplierGroupForm.code" :label="__('Stable code')" required dir="ltr" />
                <flux:input wire:model="supplierGroupForm.sort_order" :label="__('Order')" type="number" min="0" />
                <flux:select wire:model="supplierGroupForm.parent_id" :label="str_starts_with(app()->getLocale(), 'ar') ? 'المجموعة الرئيسية (اختياري)' : __('Parent group (optional)')" :description="str_starts_with(app()->getLocale(), 'ar') ? 'اتركه فارغاً لإنشاء مجموعة رئيسية.' : __('Leave empty for a root group; child groups stay beneath their selected parent.')">
                    <option value="">{{ str_starts_with(app()->getLocale(), 'ar') ? 'بدون مجموعة رئيسية' : __('No parent group') }}</option>
                    @foreach ($supplierGroupParents as $parent)
                        @if ($parent->id !== $editingSupplierGroupId)
                            <option value="{{ $parent->id }}">{{ $parent->name_ar }}{{ $parent->name_en ? ' — '.$parent->name_en : '' }}</option>
                        @endif
                    @endforeach
                </flux:select>
                <flux:select wire:model="supplierGroupForm.status" :label="str_starts_with(app()->getLocale(), 'ar') ? 'الحالة' : __('Status')"><option value="active">{{ str_starts_with(app()->getLocale(), 'ar') ? 'نشطة' : __('Active') }}</option><option value="inactive">{{ str_starts_with(app()->getLocale(), 'ar') ? 'غير نشطة' : __('Inactive') }}</option></flux:select>
                @error('supplierGroupForm')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
                <div class="flex justify-end gap-3 border-t border-border pt-3"><flux:button variant="subtle" wire:click="$set('showSupplierGroupModal', false)">{{ str_starts_with(app()->getLocale(), 'ar') ? 'إلغاء' : __('Cancel') }}</flux:button><flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveSupplierGroup"><span wire:loading.remove wire:target="saveSupplierGroup">{{ __('Save') }}</span><span wire:loading wire:target="saveSupplierGroup">{{ str_starts_with(app()->getLocale(), 'ar') ? 'جارٍ الحفظ...' : __('Saving...') }}</span></flux:button></div>
            </form>
        </div>
    </flux:modal>
    @endif

    @if ($section === 'supplier-masters')
    <flux:modal wire:model="showSupplierContactModal" class="md:max-w-lg">
        <div class="space-y-5">
            <flux:heading size="lg">{{ $editingSupplierContactId ? __('Edit supplier contact') : __('Add supplier contact') }}</flux:heading>
            <form wire:submit.prevent="saveSupplierContact" class="space-y-4">
                <flux:select wire:model="supplierContactForm.role" :label="__('Contact role')"><option value="owner">{{ __('Owner') }}</option><option value="accounting">{{ __('Accountant') }}</option><option value="representative">{{ __('Sales representative') }}</option><option value="order">{{ __('Order contact') }}</option><option value="general">{{ __('General contact') }}</option></flux:select>
                <flux:input wire:model="supplierContactForm.name" :label="__('Contact name')" required />
                <div class="grid gap-4 sm:grid-cols-3"><flux:input wire:model="supplierContactForm.phone" :label="__('Phone')" dir="ltr" /><flux:input wire:model="supplierContactForm.mobile" :label="__('Mobile')" dir="ltr" /><flux:input wire:model="supplierContactForm.email" :label="__('Email')" type="email" dir="ltr" /></div>
                <flux:textarea wire:model="supplierContactForm.notes" :label="__('Notes')" rows="2" />
                <flux:checkbox wire:model="supplierContactForm.is_primary" :label="__('Primary contact for this role')" />
                @error('supplierContactForm')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
                 <div class="flex justify-end gap-3 border-t border-border pt-3"><flux:button variant="subtle" wire:click="$set('showSupplierContactModal', false)">{{ __('Cancel') }}</flux:button><flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveSupplierContact"><span wire:loading.remove wire:target="saveSupplierContact">{{ __('Save contact') }}</span><span wire:loading wire:target="saveSupplierContact">{{ __('Saving...') }}</span></flux:button></div>
            </form>
        </div>
    </flux:modal>

    <flux:modal wire:model="showSupplierDestinationModal" class="md:max-w-lg">
        <div class="space-y-5">
            <flux:heading size="lg">{{ $editingSupplierDestinationId ? __('Edit communication destination') : __('Add communication destination') }}</flux:heading>
            <form wire:submit.prevent="saveSupplierDestination" class="space-y-4">
                <flux:text class="text-sm text-text-muted">{{ __('Mark one active primary destination for each purpose. Purchase Orders use only the designated order recipient; no personal fallback is selected automatically.') }}</flux:text>
                <div class="grid gap-4 sm:grid-cols-2"><flux:select wire:model="supplierDestinationForm.purpose" :label="__('Purpose')"><option value="purchase_order">{{ __('Purchase Orders') }}</option><option value="accounting">{{ __('Accounting correspondence') }}</option><option value="general">{{ __('General communication') }}</option></flux:select><flux:select wire:model="supplierDestinationForm.channel" :label="__('Preferred channel')"><option value="email">{{ __('Email') }}</option><option value="whatsapp">{{ __('WhatsApp') }}</option><option value="phone">{{ __('Phone') }}</option></flux:select></div>
                <flux:input wire:model="supplierDestinationForm.destination" :label="__('Destination')" required dir="ltr" />
                <flux:input wire:model="supplierDestinationForm.label" :label="__('Label (optional)')" />
                <flux:checkbox wire:model="supplierDestinationForm.is_primary" :label="__('Primary destination for this purpose')" />
                @error('supplierDestinationForm')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
                 <div class="flex justify-end gap-3 border-t border-border pt-3"><flux:button variant="subtle" wire:click="$set('showSupplierDestinationModal', false)">{{ __('Cancel') }}</flux:button><flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveSupplierDestination"><span wire:loading.remove wire:target="saveSupplierDestination">{{ __('Save destination') }}</span><span wire:loading wire:target="saveSupplierDestination">{{ __('Saving...') }}</span></flux:button></div>
            </form>
        </div>
    </flux:modal>
    @endif
</x-app.page>

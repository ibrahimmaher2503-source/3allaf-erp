<?php

use App\Modules\Platform\Actions\SaveRoleAction;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Role Permissions')] class extends Component
{
    public Role $role;
    public array $permissionIds = [];
    public string $search = '';
    public string $moduleFilter = 'all';

    public function mount(Role $role): void
    {
        Gate::authorize('users_roles_permissions.view');
        $this->role = $role;
        $this->permissionIds = $role->permissions()->pluck('permissions.id')->map(fn ($id): int => (int) $id)->all();
    }

    public function savePermissions(SaveRoleAction $action): void
    {
        $actor = auth()->user();
        if (! ($actor instanceof User && SaveRoleAction::canManagePermissions($actor, $this->role))) {
            Gate::authorize('users_roles_permissions.edit');
        }
        $validated = $this->validate(['permissionIds' => ['array'], 'permissionIds.*' => ['integer', 'exists:permissions,id']]);

        try {
            $action->syncPermissions($this->role, $validated['permissionIds']);
            $this->role->refresh();
            Flux::toast(variant: 'success', text: __('Role permissions saved successfully.'));
        } catch (\Throwable $exception) {
            $this->addError('permissionIds', \App\Support\UserSafeError::message($exception));
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function render(): mixed
    {
        $permissions = Permission::query()
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', '%'.trim($this->search).'%')->orWhere('module', 'like', '%'.trim($this->search).'%')->orWhere('action', 'like', '%'.trim($this->search).'%')))
            ->when($this->moduleFilter !== 'all', fn ($query) => $query->where('module', $this->moduleFilter))
            ->orderBy('module')->orderBy('action')->get();

        $actor = auth()->user();
        $canManageAll = $actor instanceof User && SaveRoleAction::canManagePermissions($actor, $this->role);

        return view('platform.admin.role-permissions', [
            'permissions' => $permissions,
            'permissionGroups' => $permissions->groupBy('module'),
            'modules' => Permission::query()->distinct()->orderBy('module')->pluck('module'),
            'canonical' => SaveRoleAction::isCanonical($this->role),
            'canEdit' => $canManageAll || (Gate::allows('users_roles_permissions.edit') && ! SaveRoleAction::isCanonical($this->role)),
            'canAssignSensitive' => $canManageAll,
        ]);
    }
}; ?>

@php
    $isArabic = str_starts_with(app()->getLocale(), 'ar');
    $roleName = $isArabic ? $role->name_ar : $role->name_en;
    $locale = $isArabic ? 'ar' : 'en';
    $hiddenModules = ['party_wallet', 'party_bookings_invoices', 'party_operating_orders_consumables', 'rental_assets'];
    $permissions = $permissions->reject(fn ($permission) => in_array($permission->module, $hiddenModules, true));
    $permissionGroups = $permissionGroups->reject(fn ($modulePermissions, $module) => in_array($module, $hiddenModules, true));
    $modules = $modules->reject(fn ($module) => in_array($module, $hiddenModules, true));
    $moduleLabels = [
        'company_settings' => ['ar' => 'إعدادات الشركة', 'en' => 'Company settings'],
        'branches_stores' => ['ar' => 'الفروع والمخازن ومواقع البيع', 'en' => 'Branches, stores, and selling locations'],
        'drawers_payments_tax_numbering_printers' => ['ar' => 'أدراج النقدية والمدفوعات والضرائب والترقيم والطباعة', 'en' => 'Cash drawers, payments, tax, numbering, and printers'],
        'users_roles_permissions' => ['ar' => 'المستخدمون والأدوار والصلاحيات', 'en' => 'Users, roles, and permissions'],
        'products_categories_brands' => ['ar' => 'المنتجات والتصنيفات والعلامات التجارية', 'en' => 'Products, categories, and brands'],
        'suppliers' => ['ar' => 'الموردون', 'en' => 'Suppliers'],
        'purchase_orders' => ['ar' => 'أوامر الشراء', 'en' => 'Purchase orders'],
        'purchase_invoices_supplier_returns' => ['ar' => 'فواتير الشراء ومرتجعات الموردين', 'en' => 'Purchase invoices and supplier returns'],
        'purchase_returns' => ['ar' => 'مرتجعات الشراء', 'en' => 'Purchase returns'],
        'pricing_labels' => ['ar' => 'الأسعار والملصقات', 'en' => 'Pricing and labels'],
        'inventory_stock_card' => ['ar' => 'المخزون وبطاقة الصنف', 'en' => 'Inventory and stock card'],
        'transfers' => ['ar' => 'تحويلات المخزون', 'en' => 'Stock transfers'],
        'stock_counts' => ['ar' => 'الجرد', 'en' => 'Stock counts'],
        'pos_sales' => ['ar' => 'نقطة البيع والمبيعات', 'en' => 'Point of sale and sales'],
        'suspended_sales' => ['ar' => 'المبيعات المعلّقة', 'en' => 'Suspended sales'],
        'shifts_cash_movements' => ['ar' => 'الورديات وحركات النقدية', 'en' => 'Shifts and cash movements'],
        'customers_children' => ['ar' => 'العملاء وبيانات الأطفال', 'en' => 'Customers and child profiles'],
        'loyalty' => ['ar' => 'الولاء', 'en' => 'Loyalty'],
        'product_wallet' => ['ar' => 'محفظة المنتجات', 'en' => 'Product wallet'],
        'returns_exchanges_gift_instruments' => ['ar' => 'المرتجعات والاستبدالات وأدوات الهدايا', 'en' => 'Returns, exchanges, and gift instruments'],
        'quotations' => ['ar' => 'عروض الأسعار', 'en' => 'Quotations'],
        'dashboard_reports' => ['ar' => 'لوحة التحكم والتقارير', 'en' => 'Dashboard and reports'],
        'audit_logs' => ['ar' => 'سجل التدقيق', 'en' => 'Audit logs'],
        'offline_queue_conflicts' => ['ar' => 'العمل دون اتصال والتعارضات', 'en' => 'Offline queue and conflicts'],
        'authorization' => ['ar' => 'خط الأساس للصلاحيات', 'en' => 'Authorization baseline'],
        'platform' => ['ar' => 'المنصة', 'en' => 'Platform'],
        'gift_receipts' => ['ar' => 'إيصالات الهدايا', 'en' => 'Gift receipts'],
        'returns' => ['ar' => 'المرتجعات', 'en' => 'Returns'],
        'gift_cards' => ['ar' => 'بطاقات الهدايا', 'en' => 'Gift cards'],
    ];
    $actionLabels = [
        'view' => ['ar' => 'عرض', 'en' => 'View'], 'create' => ['ar' => 'إنشاء', 'en' => 'Create'], 'edit' => ['ar' => 'تعديل', 'en' => 'Edit'], 'submit' => ['ar' => 'إرسال', 'en' => 'Submit'],
        'approve' => ['ar' => 'اعتماد', 'en' => 'Approve'], 'reject' => ['ar' => 'رفض', 'en' => 'Reject'], 'export' => ['ar' => 'تصدير', 'en' => 'Export'], 'print' => ['ar' => 'طباعة', 'en' => 'Print'],
        'reverse' => ['ar' => 'عكس', 'en' => 'Reverse'], 'cancel' => ['ar' => 'إلغاء', 'en' => 'Cancel'], 'override' => ['ar' => 'تجاوز', 'en' => 'Override'], 'logical_delete' => ['ar' => 'حذف منطقي', 'en' => 'Logical delete'],
        'cost_view' => ['ar' => 'عرض التكلفة', 'en' => 'View cost'], 'payment_view' => ['ar' => 'عرض المدفوعات', 'en' => 'View payments'], 'payment_evidence_view' => ['ar' => 'عرض إثبات الدفع', 'en' => 'View payment evidence'],
        'export_xlsx' => ['ar' => 'تصدير Excel', 'en' => 'Export Excel'], 'export_pdf' => ['ar' => 'تصدير PDF', 'en' => 'Export PDF'], 'reconcile' => ['ar' => 'تسوية', 'en' => 'Reconcile'],
        'dispatch' => ['ar' => 'إرسال', 'en' => 'Dispatch'], 'receive' => ['ar' => 'استلام', 'en' => 'Receive'], 'difference' => ['ar' => 'فرق', 'en' => 'Difference'], 'merge' => ['ar' => 'دمج', 'en' => 'Merge'],
        'settle' => ['ar' => 'تسوية', 'en' => 'Settle'], 'adjust' => ['ar' => 'تعديل رصيد', 'en' => 'Adjust'], 'expire' => ['ar' => 'إنهاء الصلاحية', 'en' => 'Expire'],
        'preferred_change' => ['ar' => 'تغيير المفضل', 'en' => 'Change preferred'], 'manage' => ['ar' => 'إدارة', 'en' => 'Manage'],
        'approve_over_limit' => ['ar' => 'اعتماد فوق الحد', 'en' => 'Approve over limit'], 'apply_tax' => ['ar' => 'تطبيق الضريبة', 'en' => 'Apply tax'],
        'apply_discount' => ['ar' => 'تطبيق خصم', 'en' => 'Apply discount'], 'discount_approve' => ['ar' => 'اعتماد الخصم', 'en' => 'Approve discount'],
        'open_price' => ['ar' => 'سعر مفتوح', 'en' => 'Open price'], 'open_price_approve' => ['ar' => 'اعتماد السعر المفتوح', 'en' => 'Approve open price'],
        'payment_create' => ['ar' => 'إنشاء دفعة', 'en' => 'Create payment'], 'payment_evidence_upload' => ['ar' => 'رفع إثبات الدفع', 'en' => 'Upload payment evidence'],
        'issue' => ['ar' => 'إصدار', 'en' => 'Issue'], 'reprint' => ['ar' => 'إعادة طباعة', 'en' => 'Reprint'], 'validate' => ['ar' => 'تحقق', 'en' => 'Validate'],
        'complete' => ['ar' => 'إكمال', 'en' => 'Complete'], 'redeem' => ['ar' => 'استرداد', 'en' => 'Redeem'], 'void' => ['ar' => 'إبطال', 'en' => 'Void'],
        'reserve' => ['ar' => 'حجز', 'en' => 'Reserve'], 'checkout' => ['ar' => 'تسليم', 'en' => 'Check out'], 'return' => ['ar' => 'إرجاع', 'en' => 'Return'],
        'inspect' => ['ar' => 'فحص', 'en' => 'Inspect'], 'status' => ['ar' => 'الحالة', 'en' => 'Status'], 'cost_edit' => ['ar' => 'تعديل التكلفة', 'en' => 'Edit cost'],
        'share' => ['ar' => 'مشاركة', 'en' => 'Share'], 'customer_view' => ['ar' => 'عرض العميل', 'en' => 'View customer'],
        'customer_create' => ['ar' => 'إنشاء عميل', 'en' => 'Create customer'], 'customer_edit' => ['ar' => 'تعديل العميل', 'en' => 'Edit customer'],
        'customer_sensitive' => ['ar' => 'عرض بيانات العميل الحساسة', 'en' => 'View sensitive customer data'], 'customer_merge' => ['ar' => 'دمج العملاء', 'en' => 'Merge customers'],
        'customer_export' => ['ar' => 'تصدير العملاء', 'en' => 'Export customers'], 'earn' => ['ar' => 'كسب', 'en' => 'Earn'],
        'view_patterns' => ['ar' => 'عرض أنماط الواجهة', 'en' => 'View UI patterns'],
    ];
    $moduleName = fn (string $module): string => $moduleLabels[$module][$locale] ?? ucwords(str_replace('_', ' ', $module));
    $actionName = fn (string $action): string => $actionLabels[$action][$locale] ?? ucwords(str_replace('_', ' ', $action));
@endphp

<x-app.page :title="$isArabic ? 'صلاحيات الدور' : 'Role permissions'" :description="$isArabic ? 'راجع الصلاحيات الممنوحة لدور '.$roleName.'.' : 'Review the permissions assigned to '.$roleName.'.'" :badge="$roleName" badge-color="primary" max-width="7xl" class="space-y-5">
    <x-slot:actions><x-context-help :title="__('Roles and permissions help')" :label="__('Open roles and permissions help')"><ul><li>{{ __('Permissions define actions. Roles collect those permissions for assignment to users.') }}</li><li>{{ __('Branch, warehouse, and point-of-sale scope is assigned on the user access screen and continues to restrict role permissions.') }}</li><li>{{ __('Sensitive and canonical permission safeguards remain enforced by the server.') }}</li></ul></x-context-help><flux:button variant="subtle" icon="arrow-left" :href="route('admin.roles')" wire:navigate>{{ $isArabic ? 'العودة للأدوار' : 'Back to roles' }}</flux:button></x-slot:actions>

    @if ($canonical && ! $canEdit)
        <div class="flex">
            <flux:badge size="sm" color="zinc" icon="lock-closed">{{ app()->isLocale('ar-EG') ? 'دور أساسي، قراءة بس' : ($isArabic ? 'دور أساسي، قراءة فقط' : 'Canonical role, read-only') }}</flux:badge>
        </div>
    @elseif ($canonical)
        <div class="flex">
            <flux:badge size="sm" color="amber" icon="shield-check">{{ $isArabic ? 'دور أساسي، صلاحياته قابلة للإدارة' : 'Canonical role, permissions manageable' }}</flux:badge>
        </div>
    @elseif (! $canEdit)
        <p class="flex items-center gap-2 text-sm text-text-muted"><flux:icon name="eye" class="size-4" />{{ app()->isLocale('ar-EG') ? 'يمكنك مراجعة الصلاحيات، لكن لا تملك صلاحية تعديلها.' : ($isArabic ? 'يمكنك مراجعة الصلاحيات، لكن لا تملك صلاحية تعديلها.' : 'You can review these permissions, but cannot change them.') }}</p>
    @endif

    <form wire:submit="savePermissions" class="space-y-4">
        <x-tables.data-panel :title="$isArabic ? 'صلاحيات الدور' : 'Permissions'" :description="$canAssignSensitive ? (app()->isLocale('ar-EG') ? 'اختار أي صلاحيات نشطة للدور ده.' : ($isArabic ? 'اختر أي صلاحيات نشطة لهذا الدور.' : 'Choose any active permissions for this role.')) : (app()->isLocale('ar-EG') ? 'اختار الصلاحيات النشطة غير الحساسة المسموح بها للدور ده.' : ($isArabic ? 'اختر الصلاحيات النشطة غير الحساسة المسموح بها لهذا الدور.' : 'Choose the active, non-sensitive permissions allowed for this role.'))">
            <x-slot:toolbar>
                <x-tables.filter-bar>
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="app()->isLocale('ar-EG') ? 'دوّر في الصلاحيات' : ($isArabic ? 'بحث في الصلاحيات' : 'Search permissions')" :placeholder="app()->isLocale('ar-EG') ? 'ادوّر بالكود أو الوحدة أو الإجراء' : ($isArabic ? 'ابحث بالكود أو الوحدة أو الإجراء' : 'Code, module, or action')" />
                    <flux:select wire:model.live="moduleFilter" :label="$isArabic ? 'الوحدة' : 'Module'" class="sm:w-56"><option value="all">{{ $isArabic ? 'كل الوحدات' : 'All modules' }}</option>@foreach ($modules as $module)<option value="{{ $module }}">{{ $moduleName($module) }}</option>@endforeach</flux:select>
                </x-tables.filter-bar>
            </x-slot:toolbar>

            @if ($permissions->isEmpty())
                <x-state.empty :title="app()->isLocale('ar-EG') ? 'مفيش صلاحيات' : ($isArabic ? 'لا توجد صلاحيات' : 'No permissions found')" :description="app()->isLocale('ar-EG') ? 'غيّر البحث أو الوحدة لعرض الصلاحيات المتاحة.' : ($isArabic ? 'غيّر البحث أو الوحدة لعرض الصلاحيات المتاحة.' : 'Change the search or module to review the available permissions.')" icon="key" />
            @else
                <div class="space-y-3">
                    @foreach ($permissionGroups as $module => $modulePermissions)
                        <details class="rounded-xl border border-border bg-surface" @if ($loop->first) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4 marker:content-none">
                                <div class="min-w-0">
                                    <h2 class="font-semibold text-text-primary">{{ $moduleName($module) }}</h2>
                                </div>
                                <flux:badge size="sm" color="zinc">{{ $modulePermissions->count() }}</flux:badge>
                            </summary>
                            <div class="grid gap-3 border-t border-border p-3 md:grid-cols-2 lg:grid-cols-3">
                                @foreach ($modulePermissions as $permission)
                                    @php($editable = $canEdit && $permission->status === 'active' && ($canAssignSensitive || $permission->sensitivity !== 'sensitive'))
                                    <article wire:key="role-permission-{{ $role->id }}-{{ $permission->id }}" class="rounded-lg border border-border p-4 transition-colors hover:border-cyan-400/60">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <h3 class="text-base font-medium">{{ $actionName($permission->action) }}</h3>
                                                <p class="mt-1 truncate font-mono text-xs text-text-muted" dir="ltr" title="{{ $permission->code }}">{{ $permission->code }}</p>
                                            </div>
                                            <flux:checkbox wire:key="role-permission-checkbox-{{ $role->id }}-{{ $permission->id }}" wire:model="permissionIds" value="{{ $permission->id }}" :checked="in_array((int) $permission->id, array_map('intval', $permissionIds), true)" :disabled="! $editable" :label="app()->isLocale('ar-EG') ? 'فعّل' : ($isArabic ? 'تفعيل' : 'Granted')" />
                                        </div>
                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            @if ($permission->sensitivity === 'sensitive')
                                                <flux:badge size="sm" color="amber">{{ $isArabic ? 'حساسة' : 'Sensitive' }}</flux:badge>
                                            @else
                                                <flux:badge size="sm" variant="outline">{{ $isArabic ? 'عادية' : 'Standard' }}</flux:badge>
                                            @endif
                                            @if ($permission->status !== 'active')
                                                <flux:badge size="sm" color="zinc">{{ app()->isLocale('ar-EG') ? 'مش نشطة' : ($isArabic ? 'غير نشطة' : 'Inactive') }}</flux:badge>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </x-tables.data-panel>

        @error('permissionIds')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
        @if ($canEdit)<div class="flex justify-end"><flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="savePermissions">{{ app()->isLocale('ar-EG') ? 'احفظ الصلاحيات' : ($isArabic ? 'حفظ الصلاحيات' : 'Save permissions') }}</flux:button></div>@endif
    </form>
</x-app.page>

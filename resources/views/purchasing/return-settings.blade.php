<?php

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Purchasing\Models\FinancialSettingVersion;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Supplier Return Settings')] class extends Component
{
    public string $code = '';

    public string $labelAr = '';

    public string $labelEn = '';

    public bool $isActive = true;

    public string $financialKey = 'purchasing.supplier_return.print_title';

    public string $financialValue = '';

    public string $effectiveFrom = '';

    public string $effectiveTo = '';

    public string $financialNotes = '';

    public function mount(): void
    {
        Gate::authorize('company_settings.view');
    }

    public function saveReason(): void
    {
        Gate::authorize('company_settings.edit');
        $this->validate(['code' => 'required|string|max:50|regex:/^[A-Z0-9_-]+$/', 'labelAr' => 'required|string|max:255', 'labelEn' => 'required|string|max:255']);
        $reason = SupplierReturnReason::query()->updateOrCreate(['code' => strtoupper($this->code)], ['label_ar' => $this->labelAr, 'label_en' => $this->labelEn, 'is_active' => $this->isActive]);
        app(RecordAuditEvent::class)->execute(category: 'settings', event: 'upsert_supplier_return_reason', source: $reason, after: $reason->only(['code', 'label_ar', 'label_en', 'is_active']), reasonCode: $reason->code);
        $this->reset(['code', 'labelAr', 'labelEn']);
        $this->isActive = true;
        $this->dispatch('supplier-return-settings-saved');
    }

    public function saveFinancialSetting(): void
    {
        Gate::authorize('company_settings.edit');

        $allowedKeys = [
            'purchasing.supplier_return.print_title',
            'purchasing.supplier_return.print_footer',
            'purchasing.supplier_return.approval_limit',
        ];

        $rules = [
            'financialKey' => ['required', 'string', 'in:'.implode(',', $allowedKeys)],
            'financialValue' => ['required', 'string', 'max:1000'],
            'effectiveFrom' => ['required', 'date'],
            'effectiveTo' => ['nullable', 'date', 'after:effectiveFrom'],
            'financialNotes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->financialKey === 'purchasing.supplier_return.approval_limit') {
            $rules['financialValue'] = ['required', 'numeric', 'min:0', 'max:999999999999'];
        }

        $this->validate($rules);

        DB::transaction(function () use ($allowedKeys): void {
            $version = (int) FinancialSettingVersion::query()
                ->where('key', $this->financialKey)
                ->lockForUpdate()
                ->max('version') + 1;

            $setting = FinancialSettingVersion::query()->create([
                'key' => $this->financialKey,
                'value' => $this->financialValue,
                'value_type' => $this->financialKey === 'purchasing.supplier_return.approval_limit' ? 'decimal' : 'string',
                'effective_from' => $this->effectiveFrom,
                'effective_to' => $this->effectiveTo !== '' ? $this->effectiveTo : null,
                'created_by' => Auth::id(),
                'version' => $version,
                'notes' => $this->financialNotes !== '' ? $this->financialNotes : null,
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'settings',
                event: 'submit_supplier_return_financial_setting',
                source: $setting,
                after: $setting->only(['key', 'value_type', 'effective_from', 'effective_to', 'version', 'notes']),
                reasonCode: 'OWNER_SETUP_INPUT',
                metadata: ['allowed_keys' => $allowedKeys, 'approval_state' => 'pending'],
            );
        });

        $this->reset(['financialValue', 'effectiveFrom', 'effectiveTo', 'financialNotes']);
        $this->dispatch('supplier-return-settings-saved');
    }

    public function toggle(int $id): void
    {
        Gate::authorize('company_settings.edit');
        $reason = SupplierReturnReason::query()->findOrFail($id);
        $before = $reason->only(['is_active']);
        $reason->update(['is_active' => ! $reason->is_active]);
        app(RecordAuditEvent::class)->execute(category: 'settings', event: 'toggle_supplier_return_reason', source: $reason, before: $before, after: $reason->only(['is_active']), reasonCode: $reason->code);
    }

    public function render()
    {
        $versions = FinancialSettingVersion::query()
            ->with('approvalRecord')
            ->whereIn('key', [
                'purchasing.supplier_return.print_title',
                'purchasing.supplier_return.print_footer',
                'purchasing.supplier_return.approval_limit',
            ])
            ->orderBy('key')
            ->orderByDesc('version')
            ->get()
            ->groupBy('key')
            ->map(static fn ($items) => $items->first());

        return view('purchasing.return-settings', ['reasons' => SupplierReturnReason::query()->orderBy('code')->get(), 'versions' => $versions]);
    }
};
?>

<x-app.page :title="__('Supplier Return Settings')" :description="__('Configure supplier-return reasons, print text, and approval limits. New values remain pending until separately approved.')" max-width="6xl" class="space-y-6">
    <x-slot:actions>
        <flux:button href="{{ route('purchasing.returns') }}" variant="subtle" icon="arrow-left">{{ __('Supplier returns') }}</flux:button>
    </x-slot:actions>

    <flux:callout variant="info" icon="information-circle">
        <flux:heading size="sm">{{ __('What this page controls') }}</flux:heading>
        <flux:text>{{ __('Add the reasons shown when creating a supplier return, then submit print text or an approval limit for review.') }}</flux:text>
    </flux:callout>

    @can('company_settings.edit')
        <section aria-labelledby="supplier-return-reasons" class="space-y-4">
            <div>
                <flux:heading id="supplier-return-reasons" size="lg">{{ __('Supplier return reasons') }}</flux:heading>
                <flux:text class="mt-1">{{ __('These bilingual reasons help the team choose a clear cause when returning goods to a supplier.') }}</flux:text>
            </div>
            <flux:card class="space-y-5">
                <flux:heading size="base">{{ __('Add a return reason') }}</flux:heading>
                <div class="grid gap-4 lg:grid-cols-3">
                    <flux:input wire:model="code" :label="__('Code')" placeholder="DAMAGED" dir="ltr" />
                    <flux:input wire:model="labelAr" :label="__('Arabic label')" dir="rtl" />
                    <flux:input wire:model="labelEn" :label="__('English label')" dir="ltr" />
                </div>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <flux:checkbox wire:model="isActive" :label="__('Active')" />
                    <flux:button wire:click="saveReason" variant="primary">{{ __('Save reason') }}</flux:button>
                </div>
            </flux:card>
        </section>

        <section aria-labelledby="supplier-return-financial-settings" class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <flux:heading id="supplier-return-financial-settings" size="lg">{{ __('Print and approval settings') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Document numbers are managed from the central document-numbering settings. This page only controls supplier-return print text and approval limit.') }}</flux:text>
                </div>
                <flux:button size="sm" variant="subtle" icon="numbered-list" :href="route('admin.settings', ['tab' => 'sequences'])" wire:navigate>{{ __('Open document numbering') }}</flux:button>
            </div>
            <flux:card class="space-y-5">
                <div class="grid gap-4 lg:grid-cols-2">
                    <flux:select wire:model="financialKey" :label="__('Setting type')">
                        <flux:select.option value="purchasing.supplier_return.print_title">{{ __('Print title') }}</flux:select.option>
                        <flux:select.option value="purchasing.supplier_return.print_footer">{{ __('Print footer') }}</flux:select.option>
                        <flux:select.option value="purchasing.supplier_return.approval_limit">{{ __('Approval limit') }}</flux:select.option>
                    </flux:select>
                    <flux:input
                        wire:model="financialValue"
                        :type="$financialKey === 'purchasing.supplier_return.approval_limit' ? 'number' : 'text'"
                        :step="$financialKey === 'purchasing.supplier_return.approval_limit' ? '0.01' : null"
                        :label="match ($financialKey) {
                            'purchasing.supplier_return.print_footer' => __('Print footer'),
                            'purchasing.supplier_return.approval_limit' => __('Approval limit'),
                            default => __('Print title'),
                        }"
                    />
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <flux:input type="date" wire:model="effectiveFrom" :label="__('Effective from')" />
                    <flux:input type="date" wire:model="effectiveTo" :label="__('Effective to')" />
                </div>
                <flux:textarea wire:model="financialNotes" :label="__('Review note (optional)')" />
                <div class="flex justify-end">
                    <flux:button wire:click="saveFinancialSetting" variant="primary">{{ __('Send for review') }}</flux:button>
                </div>
            </flux:card>
        </section>
    @endcan

    <section aria-labelledby="supplier-return-reason-catalog" class="space-y-4">
        <div>
            <flux:heading id="supplier-return-reason-catalog" size="lg">{{ __('Saved return reasons') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Only active reasons can be selected on a new supplier return.') }}</flux:text>
        </div>
        <flux:card class="p-0">
            <div class="overflow-x-auto rounded-xl">
                <flux:table class="min-w-[48rem]">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Code') }}</flux:table.column>
                        <flux:table.column>{{ __('Arabic') }}</flux:table.column>
                        <flux:table.column>{{ __('English') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Action') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($reasons as $reason)
                            <flux:table.row :key="$reason->id">
                                <flux:table.cell class="font-mono" dir="ltr">{{ $reason->code }}</flux:table.cell>
                                <flux:table.cell dir="rtl">{{ $reason->label_ar }}</flux:table.cell>
                                <flux:table.cell dir="ltr">{{ $reason->label_en }}</flux:table.cell>
                                <flux:table.cell>{{ $reason->is_active ? __('Active') : __('Inactive') }}</flux:table.cell>
                                <flux:table.cell>
                                    @can('company_settings.edit')
                                        <x-actions.button :semantic="$reason->is_active ? 'disable' : 'restore'" :label="$reason->is_active ? __('Deactivate') : __('Activate')" wire:click="toggle({{ $reason->id }})">{{ $reason->is_active ? __('Deactivate') : __('Activate') }}</x-actions.button>
                                    @endcan
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5">{{ __('No reasons configured yet.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
    </section>

    <section aria-labelledby="supplier-return-setting-history" class="space-y-4">
        <div>
            <flux:heading id="supplier-return-setting-history" size="lg">{{ __('Setting review history') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Submitted values appear here with their version and approval status.') }}</flux:text>
        </div>
        @php
            $settingLabels = [
                'purchasing.supplier_return.print_title' => __('Print title'),
                'purchasing.supplier_return.print_footer' => __('Print footer'),
                'purchasing.supplier_return.approval_limit' => __('Approval limit'),
            ];
            $valueTypeLabels = ['string' => __('Text'), 'decimal' => __('Number')];
        @endphp
        <flux:card class="p-0">
            <div class="overflow-x-auto rounded-xl">
                <table class="min-w-[44rem] w-full text-sm">
                    <thead class="bg-zinc-50 text-start dark:bg-zinc-800/60">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('Setting') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Value type') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Version') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Effective from') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($versions as $version)
                            @php($approvalState = $version->approvalRecord?->approval_state?->value ?? 'pending')
                            <tr>
                                <td class="px-4 py-3">{{ $settingLabels[$version->key] ?? __('Other setting') }}</td>
                                <td class="px-4 py-3">{{ $valueTypeLabels[$version->value_type] ?? $version->value_type }}</td>
                                <td class="px-4 py-3">{{ $version->version }}</td>
                                <td class="px-4 py-3" dir="ltr">{{ $version->effective_from?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">{{ $approvalState === 'approved' ? __('Approved') : __('Awaiting approval') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-5">{{ __('No approved return-specific financial versions exist.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    </section>
</x-app.page>

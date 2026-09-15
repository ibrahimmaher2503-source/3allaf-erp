<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\PrintTemplate;

use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Enums\TaxTreatment;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Pricing\Actions\EnsureBasePriceList;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveLocalSettingsAction
{
    /**
     * Execute local settings save/update with correlation ID and append-only audit log.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(array $data, ?int $companyId = null): array
    {
        Gate::authorize('company_settings.edit');
        $correlationId = Context::get('request_id') ?? (string) Str::uuid();

        return DB::transaction(function () use ($data, $correlationId, $companyId) {
            // 1. Company identity update or create
            $companyData = [
                'code' => $data['company']['code'] ?? 'TBD',
                'name_ar' => $data['company']['name_ar'] ?? null,
                'name_en' => $data['company']['name_en'] ?? null,
                'legal_name' => $data['company']['legal_name'] ?? null,
                'tax_number' => $data['company']['tax_number'] ?? null,
                'commercial_registration' => $data['company']['commercial_registration'] ?? null,
                'currency_code' => $data['company']['currency_code'] ?? 'TBD',
                'currency_symbol' => $data['company']['currency_symbol'] ?? 'TBD',
                'timezone' => $data['company']['timezone'] ?? 'UTC',
                'locale_default' => $data['company']['locale_default'] ?? 'ar',
                'phone' => filled($data['company']['phone'] ?? null) ? PhoneNormalizer::normalize((string) $data['company']['phone']) : null,
                'email' => $data['company']['email'] ?? null,
                'address' => $data['company']['address'] ?? null,
                'status' => $data['company']['status'] ?? 'active',
                'policy_notes' => isset($data['company']['policy_notes']) && trim((string) $data['company']['policy_notes']) !== '' ? trim((string) $data['company']['policy_notes']) : null,
            ];

            $companies = Company::query()->lockForUpdate()->orderBy('id')->limit(2)->get();
            if ($companies->count() > 1) {
                throw ValidationException::withMessages([
                    'companyForm.code' => __('company.duplicate_save'),
                ]);
            }

            $company = $companies->first();
            if ($companyId !== null && ($company === null || $company->id !== $companyId)) {
                throw ValidationException::withMessages([
                    'companyForm.code' => __('company.stale'),
                ]);
            }
            if ($companyId === null && $company !== null) {
                throw ValidationException::withMessages([
                    'companyForm.code' => __('company.already_exists'),
                ]);
            }

            $before = $company?->getAttributes();
            if ($company !== null) {
                $company->update($companyData);
            } else {
                $company = Company::create($companyData);
            }

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'update_local_settings',
                source: $company,
                before: $before,
                after: $company->getAttributes(),
            );

            app(EnsureBasePriceList::class)->execute($company);

            return [
                'success' => true,
                'correlation_id' => $correlationId,
                'company' => $company,
            ];
        });
    }

    /**
     * Create or update a payment method setting.
     *
     * @param  array<string, mixed>  $data
     */
    public function savePaymentMethod(array $data, ?int $id = null): PaymentMethod
    {
        Gate::authorize('company_settings.edit');
        $correlationId = Context::get('request_id') ?? (string) Str::uuid();

        return DB::transaction(function () use ($data, $id) {
            $paymentType = (string) ($data['type'] ?? 'manual');
            if (! in_array($paymentType, ['cash', 'card', 'transfer', 'manual', 'manual_electronic', 'gift_card', 'cheque'], true)) {
                throw ValidationException::withMessages(['paymentMethodForm.type' => __('The selected payment type is not supported.')]);
            }
            if ((bool) ($data['offline_eligible'] ?? false) && ! in_array($paymentType, ['cash', 'manual_electronic'], true)) {
                throw ValidationException::withMessages(['paymentMethodForm.offline_eligible' => __('Only cash or electronic-wallet methods can be approved for offline POS use.')]);
            }
            $attributes = [
                'code' => strtoupper($data['code']),
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'],
                'type' => $paymentType,
                'requires_evidence' => (bool) ($data['requires_evidence'] ?? false),
                'offline_eligible' => (bool) ($data['offline_eligible'] ?? false),
                'status' => $data['status'] ?? 'active',
                'policy_notes' => $data['policy_notes'] ?? 'TBD',
            ];

            if ($id) {
                $method = PaymentMethod::findOrFail($id);
                $before = $method->getAttributes();
                $method->update($attributes);
                $action = 'update_payment_method';
            } else {
                $method = PaymentMethod::create($attributes);
                $before = null;
                $action = 'create_payment_method';
            }

            app(RecordAuditEvent::class)->execute('master_data', $action, $method, $before, $method->getAttributes());

            return $method;
        });
    }

    /**
     * Create or update a tax setting.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveTaxSetting(array $data, ?int $id = null): TaxSetting
    {
        Gate::authorize('company_settings.edit');

        return $this->persistTaxSetting($data, $id);
    }

    public function applyApprovedTaxSetting(ApprovalRecord $approval): TaxSetting
    {
        return DB::transaction(function () use ($approval): TaxSetting {
            [$data, $id, $metadata] = $this->approvedEffect($approval, 'tax_setting', ['create_tax_setting', 'update_tax_setting']);

            return $this->persistTaxSetting($data, $id, $metadata);
        });
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $metadata */
    private function persistTaxSetting(array $data, ?int $id = null, array $metadata = []): TaxSetting
    {
        return DB::transaction(function () use ($data, $id, $metadata) {
            $treatment = (string) ($data['treatment'] ?? TaxTreatment::Standard->value);
            if (! in_array($treatment, TaxTreatment::values(), true)) {
                throw ValidationException::withMessages(['taxSettingForm.treatment' => __('The selected tax treatment is not supported.')]);
            }
            $rate = $data['rate'] !== null && $data['rate'] !== '' ? (string) $data['rate'] : null;
            if ($treatment !== TaxTreatment::Standard->value && ($rate === null || (float) $rate !== 0.0)) {
                throw ValidationException::withMessages(['taxSettingForm.rate' => __('Zero Rated, Exempt, and Out of Scope treatments must use a zero rate.')]);
            }
            if (($data['status'] ?? 'active') === 'active' && (bool) ($data['is_default'] ?? false) && $rate === null) {
                throw ValidationException::withMessages(['taxSettingForm.rate' => __('The active company default requires an explicit rate.')]);
            }

            $attributes = [
                'code' => strtoupper($data['code']),
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'],
                'rate' => $rate,
                'treatment' => $treatment,
                'is_default' => ($data['status'] ?? 'active') === 'active' && (bool) ($data['is_default'] ?? false),
                'is_tax_inclusive' => (bool) ($data['is_tax_inclusive'] ?? false),
                'tax_number' => $data['tax_number'] ?? null,
                'effective_from' => filled($data['effective_from'] ?? null) ? $data['effective_from'] : null,
                'effective_to' => filled($data['effective_to'] ?? null) ? $data['effective_to'] : null,
                'status' => $data['status'] ?? 'active',
                'policy_notes' => $data['policy_notes'] ?? 'TBD: Production tax policy pending owner approval.',
            ];

            $this->assertTaxPeriodDoesNotOverlap($attributes, $id);
            $currentTax = $id ? TaxSetting::query()->lockForUpdate()->findOrFail($id) : null;
            if ($currentTax?->status === 'active' && $currentTax->is_default && $attributes['status'] === 'inactive') {
                $hasEligibleReplacement = TaxSetting::query()
                    ->lockForUpdate()
                    ->whereKeyNot($currentTax->id)
                    ->where('status', 'active')
                    ->whereNotNull('rate')
                    ->exists();

                throw ValidationException::withMessages([
                    'taxSettingForm.status' => $hasEligibleReplacement
                        ? __('Select another active tax rule as Default before deactivating the current default.')
                        : __('Cannot deactivate the only active default tax rule. Create or activate another tax rule with an explicit rate and set it as Default first.'),
                ]);
            }
            $existingDefault = TaxSetting::query()->lockForUpdate()->where('status', 'active')->where('is_default', true)->first();
            if ($attributes['is_default']) {
                TaxSetting::query()->where('status', 'active')->where('is_default', true)->when($id, fn ($query) => $query->whereKeyNot($id))->update(['is_default' => false]);
            } elseif ($attributes['status'] === 'active' && $existingDefault?->id === $id) {
                // Keep the current default when editing it; choose another default first to replace it safely.
                $attributes['is_default'] = true;
            } elseif ($existingDefault === null && $attributes['status'] === 'active') {
                // The first active rule is the only safe default when the workspace has no tax baseline yet.
                $attributes['is_default'] = true;
            }

            if ($id) {
                $tax = $currentTax ?? TaxSetting::findOrFail($id);
                $before = $tax->getAttributes();
                $tax->update($attributes);
                $action = 'update_tax_setting';
            } else {
                $tax = TaxSetting::create($attributes);
                $before = null;
                $action = 'create_tax_setting';
            }

            app(RecordAuditEvent::class)->execute('master_data', $action, $tax, $before, $tax->getAttributes(), metadata: $metadata);

            return $tax;
        });
    }

    /** @param array<string, mixed> $attributes */
    private function assertTaxPeriodDoesNotOverlap(array $attributes, ?int $id): void
    {
        if (($attributes['status'] ?? 'active') !== 'active') {
            return;
        }

        $from = $attributes['effective_from'] ? now()->parse($attributes['effective_from']) : null;
        $to = $attributes['effective_to'] ? now()->parse($attributes['effective_to']) : null;

        // A missing period is an explicitly unconfigured local/TBD rule and
        // does not claim an effective date range.
        if ($from === null && $to === null) {
            return;
        }

        if ($from && $to && $to->lt($from)) {
            throw ValidationException::withMessages([
                'taxSettingForm.effective_to' => __('The effective end must be on or after the effective start.'),
            ]);
        }

        $overlap = TaxSetting::query()
            ->where('status', 'active')
            ->when($id, fn ($query) => $query->whereKeyNot($id))
            ->get(['effective_from', 'effective_to']);

        foreach ($overlap as $existing) {
            $existingFrom = $existing->effective_from;
            $existingTo = $existing->effective_to;

            if ($existingFrom === null && $existingTo === null) {
                continue;
            }
            $startsBeforeExistingEnds = $existingTo === null || $from === null || $from->lte($existingTo);
            $endsAfterExistingStarts = $to === null || $existingFrom === null || $to->gte($existingFrom);

            if ($startsBeforeExistingEnds && $endsAfterExistingStarts) {
                throw ValidationException::withMessages([
                    'taxSettingForm.effective_from' => __('The effective period overlaps another active tax setting.'),
                ]);
            }
        }
    }

    /**
     * Create or update a document sequence.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveDocumentSequence(array $data, ?int $id = null): DocumentSequence
    {
        Gate::authorize('company_settings.edit');

        return $this->persistDocumentSequence($data, $id);
    }

    public function applyApprovedDocumentSequence(ApprovalRecord $approval): DocumentSequence
    {
        return DB::transaction(function () use ($approval): DocumentSequence {
            [$data, $id, $metadata] = $this->approvedEffect($approval, 'document_sequence', ['create_document_sequence', 'update_document_sequence']);

            return $this->persistDocumentSequence($data, $id, $metadata);
        });
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $metadata */
    private function persistDocumentSequence(array $data, ?int $id = null, array $metadata = []): DocumentSequence
    {
        $user = auth()->user();

        return DB::transaction(function () use ($data, $id, $metadata, $user) {
            $scopeType = strtolower(trim((string) ($data['scope_type'] ?? 'company')));
            $scopeId = $scopeType === 'branch' && filled($data['scope_id'] ?? null) ? (int) $data['scope_id'] : null;
            if (! in_array($scopeType, ['company', 'branch'], true) || ($scopeType === 'branch' && $scopeId === null) || ($scopeType === 'company' && $scopeId !== null)) {
                throw ValidationException::withMessages(['documentSequenceForm.scope_type' => __('Only company-wide and branch document-numbering scopes are supported.')]);
            }
            if ($scopeType === 'branch' && ! Branch::visibleTo($user)->whereKey($scopeId)->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['documentSequenceForm.scope_id' => __('The selected branch is not active or does not exist.')]);
            }
            $scopeKey = DocumentSequence::scopeKeyFor($scopeType, $scopeId);
            if (DocumentSequence::query()
                ->where('document_type', $data['document_type'])
                ->where('scope_key', $scopeKey)
                ->when($id, fn ($query) => $query->whereKeyNot($id))
                ->exists()) {
                throw ValidationException::withMessages(['documentSequenceForm.document_type' => __('A document sequence already exists for this type and scope.')]);
            }
            $attributes = [
                'document_type' => $data['document_type'],
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'prefix' => $data['prefix'] ?? null,
                'suffix' => $data['suffix'] ?? null,
                'padding_length' => (int) ($data['padding_length'] ?? 6),
                'next_value' => (int) ($data['next_value'] ?? 1),
                'reset_rule' => $data['reset_rule'] ?? 'never',
                'last_reset_period' => $data['last_reset_period'] ?? null,
                'status' => $data['status'] ?? 'active',
                'policy_notes' => $data['policy_notes'] ?? 'TBD',
            ];

            if ($id) {
                $seq = DocumentSequence::visibleTo($user)->lockForUpdate()->findOrFail($id);
                $before = $seq->getAttributes();
                unset($attributes['next_value']);
                $seq->update($attributes);
                $action = 'update_document_sequence';
            } else {
                $seq = DocumentSequence::create($attributes);
                $before = null;
                $action = 'create_document_sequence';
            }

            app(RecordAuditEvent::class)->execute('master_data', $action, $seq, $before, $seq->getAttributes(), branchId: $seq->scope_id, metadata: $metadata);

            return $seq;
        });
    }

    /** @param list<string> $effectEvents @return array{0: array<string, mixed>, 1: int|null, 2: array<string, int>} */
    private function approvedEffect(ApprovalRecord $approval, string $resource, array $effectEvents): array
    {
        $approval = ApprovalRecord::query()->lockForUpdate()->findOrFail($approval->id);
        $actor = Auth::user();
        $context = $approval->limit_context ?? [];
        $id = isset($context['id']) && $context['id'] !== null ? (int) $context['id'] : null;
        $proposed = $context['proposed'] ?? null;
        $before = $context['before'] ?? null;
        $expectedHash = hash('sha256', json_encode($context, JSON_THROW_ON_ERROR));
        $expectedSourceId = $resource.':'.($id === null ? 'new:'.substr($expectedHash, 0, 16) : $id);
        $target = $id === null
            ? null
            : ($resource === 'tax_setting'
                ? TaxSetting::query()->lockForUpdate()->findOrFail($id)
                : DocumentSequence::query()->lockForUpdate()->findOrFail($id));
        $canonicalBefore = $target?->getAttributes();
        if ($canonicalBefore !== null) {
            unset($canonicalBefore['created_at'], $canonicalBefore['updated_at']);
        }
        $scopeMatches = $resource !== 'document_sequence' || $target === null || (is_array($proposed) && (
            strtolower(trim((string) ($proposed['scope_type'] ?? ''))) === $target->scope_type
            && (($target->scope_type === 'branch' && filled($proposed['scope_id'] ?? null) ? (int) $proposed['scope_id'] : null) === $target->scope_id)
        ));

        if ($actor === null
            || $approval->approval_state !== ApprovalState::Approved
            || $approval->approver_id !== $actor->id
            || $approval->source_type !== 'platform_settings'
            || $approval->requested_action !== $resource
            || $approval->source_id !== $expectedSourceId
            || $approval->source_version !== ($id === null ? 'new' : (string) $id)
            || $approval->source_hash !== $expectedHash
            || ! is_array($proposed)
            || $before !== $canonicalBefore
            || ! $scopeMatches
            || $approval->request_permission !== 'company_settings.edit'
            || $approval->decision_permission !== 'company_settings.approve'
            || ! Gate::forUser($actor)->allows('company_settings.approve')
            || AuditLog::query()->whereIn('event', $effectEvents)->whereJsonContains('metadata->approval_record_id', $approval->id)->exists()) {
            throw ValidationException::withMessages(['approval' => __('The approved platform setting effect is not available.')]);
        }

        return [$proposed, $id, ['approval_record_id' => $approval->id, 'requester_id' => $approval->requester_id]];
    }

    /**
     * Create or update a printer configuration.
     *
     * @param  array<string, mixed>  $data
     */
    public function savePrinterConfiguration(array $data, ?int $id = null, array $scope = []): PrinterConfiguration
    {
        Gate::authorize('company_settings.edit');
        $correlationId = Context::get('request_id') ?? (string) Str::uuid();
        $user = auth()->user();

        return DB::transaction(function () use ($data, $id, $scope, $user) {
            $existing = $id ? PrinterConfiguration::visibleTo($user)->findOrFail($id) : null;
            if ($existing !== null && $scope === []) {
                $scope = [
                    'scope_type' => $existing->store_id !== null ? 'store' : ($existing->branch_id !== null ? 'branch' : 'global'),
                    'branch_id' => $existing->branch_id,
                    'store_id' => $existing->store_id,
                ];
            }
            $printerType = (string) ($data['printer_type'] ?? 'thermal');
            $paperSize = (string) ($data['paper_size'] ?? '80mm');
            $compatiblePaperSizes = [
                'thermal' => ['80mm', '58mm'],
                'a4' => ['a4', 'a5'],
                'label' => ['label_50x30mm', 'label_40x25mm'],
                'pdf' => ['a4', 'a5'],
            ];
            if (! in_array($paperSize, $compatiblePaperSizes[$printerType] ?? [], true)) {
                throw ValidationException::withMessages([
                    'printerForm.paper_size' => __('The selected paper size is not compatible with this printer type.'),
                ]);
            }

            $isActive = ($data['status'] ?? 'active') === 'active';
            $isDefault = $isActive && (bool) ($data['is_default'] ?? false);
            $scopeType = (string) ($scope['scope_type'] ?? 'global');
            $branchId = filled($scope['branch_id'] ?? null) ? (int) $scope['branch_id'] : null;
            $storeId = filled($scope['store_id'] ?? null) ? (int) $scope['store_id'] : null;
            if (! in_array($scopeType, ['global', 'branch', 'store'], true)) {
                throw ValidationException::withMessages(['printerForm.scope_type' => __('The selected printer scope is not supported.')]);
            }
            if ($scopeType === 'global' && ($branchId !== null || $storeId !== null)) {
                throw ValidationException::withMessages(['printerForm.scope_type' => __('A global printer cannot be linked to a branch or location.')]);
            }
            if ($scopeType === 'branch' && ($branchId === null || $storeId !== null || ! Branch::visibleTo($user)->whereKey($branchId)->where('status', 'active')->exists())) {
                throw ValidationException::withMessages(['printerForm.branch_id' => __('Select an active branch for a branch-scoped printer.')]);
            }
            if ($scopeType === 'store') {
                $store = Store::visibleTo($user)->whereKey($storeId)->where('status', 'active')->first();
                if ($store === null || ($branchId !== null && (int) $store->branch_id !== $branchId)) {
                    throw ValidationException::withMessages(['printerForm.store_id' => __('Select an active location belonging to the selected branch.')]);
                }
                $branchId = (int) $store->branch_id;
            }
            $templateId = filled($data['print_template_id'] ?? null) ? (int) $data['print_template_id'] : null;
            $template = $templateId === null ? null : PrintTemplate::visibleTo($user)->whereKey($templateId)->where('status', 'active')->first();
            if ($template !== null && $template->paper_size !== $paperSize) {
                throw ValidationException::withMessages(['printerForm.print_template_id' => __('The selected template is not compatible with this printer paper size.')]);
            }
            if ($templateId !== null && $template === null) {
                throw ValidationException::withMessages(['printerForm.print_template_id' => __('Select an active compatible print template.')]);
            }
            $attributes = [
                'name' => $data['name'],
                'branch_id' => $branchId,
                'store_id' => $storeId,
                'printer_type' => $printerType,
                'paper_size' => $paperSize,
                'template_name' => $template?->code ?? ($data['template_name'] ?? 'default_thermal'),
                'print_template_id' => $template?->id,
                'connection_type' => $data['connection_type'] ?? 'network',
                'ip_address' => $data['ip_address'] ?? null,
                'port' => ($data['port'] ?? null) !== null && ($data['port'] ?? '') !== '' ? (int) $data['port'] : null,
                'is_default' => $isDefault,
                'status' => $data['status'] ?? 'active',
                'notes' => $data['notes'] ?? 'TBD',
            ];

            if ($id) {
                $printer = PrinterConfiguration::visibleTo($user)->findOrFail($id);
                $before = $printer->getAttributes();
                $printer->update($attributes);
                $action = 'update_printer_configuration';
            } else {
                $printer = PrinterConfiguration::create($attributes);
                $before = null;
                $action = 'create_printer_configuration';
            }

            if ($isDefault) {
                PrinterConfiguration::query()
                    ->whereKeyNot($printer->id)
                    ->where('branch_id', $branchId)
                    ->where('store_id', $storeId)
                    ->update(['is_default' => false]);
            }

            app(RecordAuditEvent::class)->execute('master_data', $action, $printer, $before, $printer->getAttributes(), branchId: $printer->branch_id, storeId: $printer->store_id);

            return $printer;
        });
    }
}

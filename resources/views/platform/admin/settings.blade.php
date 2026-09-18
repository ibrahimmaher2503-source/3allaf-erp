<?php

use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use App\Modules\Platform\Actions\SavePrintTemplateAction;
use App\Modules\Platform\Actions\SaveListDisplayPreference;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\PrintTemplate;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Platform\Support\DocumentTypeCatalog;
use Flux\Flux;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('System Settings')] class extends Component
{
    // Active Tab
    #[Url(as: 'tab', except: 'company')]
    public string $activeTab = 'company';
    #[Url(as: 'setup', except: false)]
    public bool $setupMode = false;

    #[Url(as: 'section', except: 'printer-profiles')]
    public string $printerSection = 'printer-profiles';

    public ?int $companyId = null;

    public bool $companyDirty = false;

    public bool $companyEditingBlocked = false;

    public bool $showSearchFiltersByDefault = false;

    public bool $paymentModalOpen = false;
    public bool $taxModalOpen = false;
    public bool $sequenceModalOpen = false;
    public bool $printerModalOpen = false;
    public bool $templateModalOpen = false;

    // Company Form Data
    public array $companyForm = [
        'code' => '',
        'name_ar' => '',
        'name_en' => '',
        'legal_name' => '',
        'tax_number' => '',
        'commercial_registration' => '',
        'currency_code' => '',
        'currency_symbol' => '',
        'timezone' => 'Africa/Cairo',
        'locale_default' => 'ar',
        'phone' => '',
        'email' => '',
        'address' => '',
        'status' => 'active',
        'policy_notes' => '',
    ];

    // Payment Method Form Data
    public array $paymentMethodForm = [
        'id' => null,
        'code' => '',
        'name_ar' => '',
        'name_en' => '',
        'type' => 'manual',
        'requires_evidence' => false,
        'offline_eligible' => false,
        'status' => 'active',
        'policy_notes' => '',
    ];

    // Tax Setting Form Data
    public array $taxSettingForm = [
        'id' => null,
        'code' => '',
        'name_ar' => '',
        'name_en' => '',
        'rate' => '',
        'treatment' => 'standard',
        'is_default' => false,
        'is_tax_inclusive' => true,
        'tax_number' => '',
        'effective_from' => '',
        'effective_to' => '',
        'status' => 'active',
        'policy_notes' => '',
    ];

    // Document Sequence Form Data
    public array $documentSequenceForm = [
        'id' => null,
        'document_type' => 'retail_sale',
        'scope_type' => 'branch',
        'scope_id' => null,
        'prefix' => '',
        'suffix' => '',
        'padding_length' => 6,
        'next_value' => 1,
        'reset_rule' => 'never',
        'status' => 'active',
        'policy_notes' => '',
    ];

    public array $sequenceOverride = [
        'sequence_id' => null,
        'next_value' => null,
        'expected_lock_version' => null,
        'reason' => '',
    ];

    // Printer Configuration Form Data
    public array $printerForm = [
        'id' => null,
        'name' => '',
        'scope_type' => 'global',
        'branch_id' => null,
        'store_id' => null,
        'printer_type' => 'thermal',
        'paper_size' => '80mm',
        'template_name' => 'default_thermal',
        'print_template_id' => null,
        'connection_type' => 'network',
        'ip_address' => '',
        'port' => 9100,
        'is_default' => false,
        'status' => 'active',
        'notes' => '',
    ];

    public array $templateForm = [
        'id' => null, 'code' => '', 'name_ar' => '', 'name_en' => '',
        'document_type' => 'sales_invoice', 'paper_size' => '80mm', 'language' => 'bilingual',
        'header_text' => '', 'footer_text' => '', 'layout_settings' => ['width_mm'=>50,'height_mm'=>30,'dpi'=>203,'orientation'=>'portrait','margin_mm'=>1,'gap_mm'=>2,'horizontal_gap_mm'=>2,'vertical_gap_mm'=>2,'a4_rows'=>8,'a4_columns'=>3,'symbology'=>'auto','show_name'=>true,'show_price'=>true,'show_item_code'=>true,'show_list_code'=>true,'show_outlet'=>true], 'status' => 'active',
    ];

    /**
     * Mount the component.
     */
    public function mount(Request $request): void
    {
        Gate::authorize('manage-settings');

        $tab = (string) $request->query('tab', 'company');
        if (in_array($tab, ['company', 'payments', 'tax', 'sequences', 'printers', 'audit'], true)) {
            $this->activeTab = $tab;
        }

        $section = (string) $request->query('section', 'printer-profiles');
        if ($this->activeTab === 'printers' && in_array($section, ['printer-profiles', 'print-templates'], true)) {
            $this->printerSection = $section;
        }

        $this->loadSettings();
        $this->showSearchFiltersByDefault = auth()->user()?->uiPreference?->showSearchFiltersByDefault() ?? false;
    }

    public function updatedActiveTab(string $tab): void
    {
        if (! in_array($tab, ['company', 'payments', 'tax', 'sequences', 'printers', 'audit'], true)) {
            $this->activeTab = 'company';

            return;
        }

        if ($tab === 'audit') {
            Gate::authorize('audit_logs.view');
        }
    }

    public function rendering(): void
    {
        // URL hydration can run after mount; normalize it before any panel renders.
        if (! in_array($this->activeTab, ['company', 'payments', 'tax', 'sequences', 'printers', 'audit'], true)) {
            $this->activeTab = 'company';
        }

        if (! in_array($this->printerSection, ['printer-profiles', 'print-templates'], true)) {
            $this->printerSection = 'printer-profiles';
        }

        if ($this->activeTab === 'audit') {
            Gate::authorize('audit_logs.view');
        }
    }

    /**
     * Load the current settings.
     */
    public function loadSettings(): void
    {
        Gate::authorize('manage-settings');

        // Company baseline
        $companies = Company::query()->orderBy('id')->limit(2)->get();
        if ($companies->count() > 1) {
            $this->companyEditingBlocked = true;

            return;
        }

        $company = $companies->first();
        if ($company) {
            $this->companyId = $company->id;
            $this->companyForm = array_merge($this->companyForm, $company->toArray());
            $this->companyForm['timezone'] = $this->companyForm['timezone'] ?: 'Africa/Cairo';
        }

        // No business defaults are seeded here. Payment, tax, numbering, and printer
        // policy must remain owner-provided; an empty state is intentional.
    }

    public function updatedCompanyForm(): void
    {
        $this->companyDirty = true;
    }

    public function saveListDisplayPreference(SaveListDisplayPreference $action): void
    {
        Gate::authorize('manage-settings');
        $user = auth()->user();
        abort_unless($user instanceof \App\Models\User, 403);
        $action->execute($user, $this->showSearchFiltersByDefault);
        $user->unsetRelation('uiPreference');
        Flux::toast(variant: 'success', text: __('List display preference saved.'));
    }

    /**
     * Save the company identity after explicit preview confirmation.
     */
    public function saveCompany(SaveLocalSettingsAction $action): void
    {
        Gate::authorize('manage-settings');

        $validated = $this->validate($this->companyRules());

        $res = $action->execute(['company' => $validated['companyForm']], $this->companyId);

        $this->companyForm = array_merge($this->companyForm, $res['company']->toArray());
        $this->companyId = $res['company']->id;
        $this->companyDirty = false;
        Flux::toast(variant: 'success', text: __('Company settings saved successfully.'));
    }

    /** @return array<string, array<int, string>> */
    private function companyRules(): array
    {
        return [
            'companyForm.code' => ['required', 'string', 'max:20'],
            'companyForm.name_ar' => ['required', 'string', 'max:255'],
            'companyForm.name_en' => ['required', 'string', 'max:255'],
            'companyForm.legal_name' => ['nullable', 'string', 'max:255'],
            'companyForm.tax_number' => ['nullable', 'string', 'max:50'],
            'companyForm.commercial_registration' => ['nullable', 'string', 'max:50'],
            'companyForm.currency_code' => ['required', 'string', 'max:10'],
            'companyForm.currency_symbol' => ['required', 'string', 'max:10'],
            'companyForm.timezone' => ['required', 'timezone'],
            'companyForm.locale_default' => ['required', 'string', 'in:ar,en'],
            'companyForm.phone' => ['nullable', 'string', 'max:50', PhoneNormalizer::validationRule()],
            'companyForm.email' => ['nullable', 'email', 'max:255'],
            'companyForm.address' => ['nullable', 'string', 'max:500'],
            'companyForm.status' => ['required', 'string', 'in:active,inactive'],
            'companyForm.policy_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Save payment method.
     */
    public function savePaymentMethod(SaveLocalSettingsAction $action): void
    {
        Gate::authorize('manage-settings');

        $validated = $this->validate([
            'paymentMethodForm.code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('payment_methods', 'code')->ignore($this->paymentMethodForm['id'] ?? null),
            ],
            'paymentMethodForm.name_ar' => ['required', 'string', 'max:255'],
            'paymentMethodForm.name_en' => ['required', 'string', 'max:255'],
            'paymentMethodForm.type' => ['required', 'string', 'in:cash,card,transfer,manual,manual_electronic,gift_card,cheque'],
            'paymentMethodForm.requires_evidence' => ['boolean'],
            'paymentMethodForm.offline_eligible' => ['boolean'],
            'paymentMethodForm.status' => ['required', 'string', 'in:active,inactive'],
            'paymentMethodForm.policy_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ((bool) $validated['paymentMethodForm']['offline_eligible']
            && ! in_array($validated['paymentMethodForm']['type'], ['cash', 'manual_electronic'], true)) {
            throw ValidationException::withMessages([
                'paymentMethodForm.offline_eligible' => __('Only cash or electronic-wallet methods can be approved for offline POS use.'),
            ]);
        }

        $action->savePaymentMethod($validated['paymentMethodForm'], $this->paymentMethodForm['id'] ?? null);

        $this->resetPaymentMethodForm();

        $this->paymentModalOpen = false;

        Flux::toast(variant: 'success', text: __('Payment method saved successfully.'));
    }

    public function editPaymentMethod(int $id): void
    {
        Gate::authorize('manage-settings');

        $method = PaymentMethod::findOrFail($id);
        $this->paymentMethodForm = $method->toArray();
        $this->resetValidation();
        $this->paymentModalOpen = true;
    }

    public function openPaymentMethodModal(): void
    {
        Gate::authorize('manage-settings');
        $this->resetPaymentMethodForm();
        $this->resetValidation();
        $this->paymentModalOpen = true;
    }

    public function resetPaymentMethodForm(): void
    {
        $this->paymentMethodForm = [
            'id' => null,
            'code' => '',
            'name_ar' => '',
            'name_en' => '',
            'type' => 'manual',
            'requires_evidence' => false,
            'offline_eligible' => false,
            'status' => 'active',
            'policy_notes' => '',
        ];
    }

    /**
     * Save tax setting.
     */
    public function saveTaxSetting(PlatformSettingsApprovalAction $approvalAction): void
    {
        Gate::authorize('manage-settings');

        $this->taxSettingForm['rate'] = $this->taxSettingForm['rate'] === ''
            ? null
            : $this->taxSettingForm['rate'];

        $validated = $this->validate([
            'taxSettingForm.code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('tax_settings', 'code')->ignore($this->taxSettingForm['id'] ?? null),
            ],
            'taxSettingForm.name_ar' => ['required', 'string', 'max:255'],
            'taxSettingForm.name_en' => ['required', 'string', 'max:255'],
            'taxSettingForm.rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'taxSettingForm.treatment' => ['required', 'string', 'in:standard,zero_rated,exempt,out_of_scope'],
            'taxSettingForm.is_default' => ['boolean'],
            'taxSettingForm.is_tax_inclusive' => ['boolean'],
            'taxSettingForm.tax_number' => ['nullable', 'string', 'max:50'],
            'taxSettingForm.effective_from' => ['nullable', 'date'],
            'taxSettingForm.effective_to' => ['nullable', 'date', 'after_or_equal:taxSettingForm.effective_from'],
            'taxSettingForm.status' => ['required', 'string', 'in:active,inactive'],
            'taxSettingForm.policy_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $tax = $this->taxSettingForm['id'] ? TaxSetting::query()->findOrFail((int) $this->taxSettingForm['id']) : null;
        $approvalAction->request(
            resource: 'tax_setting',
            id: $tax?->id,
            proposed: $validated['taxSettingForm'],
            before: $tax?->getAttributes(),
            reason: $validated['taxSettingForm']['policy_notes'] ?? null,
        );

        $this->resetTaxSettingForm();

        $this->taxModalOpen = false;

        Flux::toast(variant: 'success', text: auth()->user()?->canBypassApproval() ? __('Super Admin action completed without separate approval.') : __('Tax setting submitted for independent approval.'));
    }

    public function editTaxSetting(int $id): void
    {
        Gate::authorize('manage-settings');

        $tax = TaxSetting::findOrFail($id);
        $this->taxSettingForm = $tax->toArray();
        $this->resetValidation();
        $this->taxModalOpen = true;
    }

    public function openTaxSettingModal(): void
    {
        Gate::authorize('manage-settings');
        $this->resetTaxSettingForm();
        $this->resetValidation();
        $this->taxModalOpen = true;
    }

    public function resetTaxSettingForm(): void
    {
        $this->taxSettingForm = [
            'id' => null,
            'code' => '',
            'name_ar' => '',
            'name_en' => '',
            'rate' => '',
            'treatment' => 'standard',
            'is_default' => false,
            'is_tax_inclusive' => true,
            'tax_number' => '',
            'effective_from' => '',
            'effective_to' => '',
            'status' => 'active',
            'policy_notes' => '',
        ];
    }

    /**
     * Save document sequence.
     */
    public function saveDocumentSequence(PlatformSettingsApprovalAction $approvalAction): void
    {
        Gate::authorize('manage-settings');

        $isLegacyCompanyRule = filled($this->documentSequenceForm['id'])
            && ($this->documentSequenceForm['scope_type'] ?? null) === 'company';
        if (! $isLegacyCompanyRule) {
            $branch = Branch::visibleTo(auth()->user())
                ->where('status', 'active')
                ->find((int) ($this->documentSequenceForm['scope_id'] ?? 0));
            if ($branch === null) {
                throw ValidationException::withMessages(['documentSequenceForm.scope_id' => __('The selected branch is not active or does not exist.')]);
            }
            $this->documentSequenceForm['scope_type'] = 'branch';
            $this->documentSequenceForm['prefix'] = DocumentTypeCatalog::prefix($branch->code, (string) $this->documentSequenceForm['document_type']);
            $this->documentSequenceForm['suffix'] = '';
            $this->documentSequenceForm['reset_rule'] = 'never';
        }

        $validated = $this->validate([
            'documentSequenceForm.document_type' => [
                'required',
                'string',
                Rule::in(array_keys(DocumentTypeCatalog::NUMBERING_TYPES)),
                Rule::unique('document_sequences', 'document_type')
                    ->where(function ($query): void {
                        $scopeType = (string) ($this->documentSequenceForm['scope_type'] ?? 'company');
                        $scopeId = $this->documentSequenceForm['scope_id'] ?? null;
                        $scopeKey = $scopeType === 'branch' && is_numeric($scopeId)
                            ? 'branch:'.(int) $scopeId
                            : 'company';
                        $query->where('scope_key', $scopeKey);
                    })
                    ->ignore($this->documentSequenceForm['id'] ?? null),
            ],
            'documentSequenceForm.scope_type' => ['required', 'string', Rule::in($isLegacyCompanyRule ? ['company'] : ['branch'])],
            'documentSequenceForm.scope_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn (): bool => ($this->documentSequenceForm['scope_type'] ?? 'company') === 'branch'),
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('status', 'active')),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (($this->documentSequenceForm['scope_type'] ?? 'company') === 'branch'
                        && ! Branch::visibleTo(auth()->user())->whereKey($value)->where('status', 'active')->exists()) {
                        $fail(__('The selected branch is not active or does not exist.'));
                    }
                },
            ],
            'documentSequenceForm.prefix' => ['nullable', 'string', 'max:20'],
            'documentSequenceForm.suffix' => ['nullable', 'string', 'max:20'],
            'documentSequenceForm.padding_length' => ['required', 'integer', 'min:1', 'max:12'],
            'documentSequenceForm.next_value' => [$this->documentSequenceForm['id'] ? 'nullable' : 'required', 'integer', 'min:1'],
            'documentSequenceForm.reset_rule' => ['required', 'string', Rule::in($isLegacyCompanyRule ? ['never', 'daily', 'yearly', 'monthly'] : ['never'])],
            'documentSequenceForm.status' => ['required', 'string', 'in:active,inactive'],
            'documentSequenceForm.policy_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $sequence = $this->documentSequenceForm['id'] ? DocumentSequence::visibleTo(auth()->user())->findOrFail((int) $this->documentSequenceForm['id']) : null;
        $approvalAction->request(
            resource: 'document_sequence',
            id: $sequence?->id,
            proposed: $validated['documentSequenceForm'],
            before: $sequence?->getAttributes(),
            reason: $validated['documentSequenceForm']['policy_notes'] ?? null,
        );

        $this->resetDocumentSequenceForm();

        $this->sequenceModalOpen = false;

        Flux::toast(variant: 'success', text: auth()->user()?->canBypassApproval() ? __('Super Admin action completed without separate approval.') : __('Document sequence submitted for independent approval.'));
    }

    public function editDocumentSequence(int $id): void
    {
        Gate::authorize('manage-settings');

        $seq = DocumentSequence::visibleTo(auth()->user())->findOrFail($id);
        $this->documentSequenceForm = $seq->toArray();
        $this->sequenceOverride = [
            'sequence_id' => $seq->id,
            'next_value' => $seq->next_value,
            'expected_lock_version' => $seq->lock_version,
            'reason' => '',
        ];
        $this->resetValidation();
        $this->sequenceModalOpen = true;
    }

    public function openDocumentSequenceModal(): void
    {
        Gate::authorize('manage-settings');
        $this->resetDocumentSequenceForm();
        $this->resetValidation();
        $this->sequenceModalOpen = true;
    }

    public function overrideSequenceCounter(PlatformSettingsApprovalAction $approvalAction): void
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.override');
        $validated = $this->validate([
            'sequenceOverride.sequence_id' => ['required', 'integer', 'exists:document_sequences,id'],
            'sequenceOverride.next_value' => ['required', 'integer', 'min:1'],
            'sequenceOverride.expected_lock_version' => ['required', 'integer', 'min:1'],
            'sequenceOverride.reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $sequence = DocumentSequence::visibleTo(auth()->user())->findOrFail($validated['sequenceOverride']['sequence_id']);
        $approvalAction->request(
            resource: 'document_sequence_override',
            id: $sequence->id,
            proposed: $validated['sequenceOverride'],
            before: $sequence->only(['document_type', 'next_value', 'lock_version']),
            reason: $validated['sequenceOverride']['reason'],
        );
        Flux::toast(variant: 'success', text: auth()->user()?->canBypassApproval() ? __('Super Admin action completed without separate approval.') : __('Sequence counter override submitted for independent approval.'));
    }

    public function resetDocumentSequenceForm(): void
    {
        $this->documentSequenceForm = [
            'id' => null,
            'document_type' => 'retail_sale',
            'scope_type' => 'branch',
            'scope_id' => null,
            'prefix' => '',
            'suffix' => '',
            'padding_length' => 6,
            'next_value' => 1,
            'reset_rule' => 'never',
            'status' => 'active',
            'policy_notes' => '',
        ];
        $this->sequenceOverride = ['sequence_id' => null, 'next_value' => null, 'expected_lock_version' => null, 'reason' => ''];
    }

    /**
     * Save printer configuration.
     */
    public function savePrinter(SaveLocalSettingsAction $action): void
    {
        Gate::authorize('manage-settings');

        $this->printerForm['port'] = $this->printerForm['port'] === ''
            ? null
            : $this->printerForm['port'];

        $validated = $this->validate([
            'printerForm.name' => ['required', 'string', 'max:255'],
            'printerForm.scope_type' => ['required', 'string', 'in:global,branch,store'],
            'printerForm.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'printerForm.store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'printerForm.printer_type' => ['required', 'string', 'in:thermal,a4,label,pdf'],
            'printerForm.paper_size' => ['required', 'string', 'in:80mm,58mm,a4,a5,label_50x30mm,label_40x25mm'],
            'printerForm.template_name' => ['required', 'string', 'max:100'],
            'printerForm.print_template_id' => ['nullable', 'integer', 'exists:print_templates,id'],
            'printerForm.connection_type' => ['required', 'string', 'in:network,usb,bluetooth,browser'],
            'printerForm.ip_address' => ['nullable', 'string', 'max:50'],
            'printerForm.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'printerForm.is_default' => ['boolean'],
            'printerForm.status' => ['required', 'string', 'in:active,inactive'],
            'printerForm.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $action->savePrinterConfiguration($validated['printerForm'], $this->printerForm['id'] ?? null, [
            'scope_type' => $validated['printerForm']['scope_type'],
            'branch_id' => $validated['printerForm']['branch_id'] ?? null,
            'store_id' => $validated['printerForm']['store_id'] ?? null,
        ]);

        $this->resetPrinterForm();

        $this->printerModalOpen = false;

        Flux::toast(variant: 'success', text: __('Printer configuration saved successfully.'));
    }

    public function editPrinter(int $id): void
    {
        Gate::authorize('manage-settings');

        $this->printerSection = 'printer-profiles';

        $printer = PrinterConfiguration::visibleTo(auth()->user())->findOrFail($id);
        $this->printerForm = $printer->toArray();
        $this->printerForm['scope_type'] = $printer->store_id !== null ? 'store' : ($printer->branch_id !== null ? 'branch' : 'global');
        $this->resetValidation();
        $this->printerModalOpen = true;
    }

    public function openPrinterModal(): void
    {
        Gate::authorize('manage-settings');
        $this->resetPrinterForm();
        $this->resetValidation();
        $this->printerModalOpen = true;
    }

    public function updatedPrinterFormScopeType(string $scope): void
    {
        if ($scope === 'global') {
            $this->printerForm['branch_id'] = null;
            $this->printerForm['store_id'] = null;
        } elseif ($scope === 'branch') {
            $this->printerForm['store_id'] = null;
        }
    }

    public function updatedPrinterFormBranchId(): void
    {
        $this->printerForm['store_id'] = null;
    }

    public function updatedPrinterFormPrinterType(string $type): void
    {
        $this->printerForm['paper_size'] = match ($type) {
            'thermal' => '80mm', 'label' => 'label_50x30mm', default => 'a4',
        };
        $this->printerForm['print_template_id'] = null;
    }

    public function updatedPrinterFormPaperSize(): void
    {
        $this->printerForm['print_template_id'] = null;
    }

    public function savePrintTemplate(SavePrintTemplateAction $action): void
    {
        $validated = $this->validate([
            'templateForm.code' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/'],
            'templateForm.name_ar' => ['required', 'string', 'max:255'],
            'templateForm.name_en' => ['required', 'string', 'max:255'],
            'templateForm.document_type' => ['required', Rule::in(SavePrintTemplateAction::DOCUMENT_TYPES)],
            'templateForm.paper_size' => ['required', Rule::in(SavePrintTemplateAction::PAPER_SIZES)],
            'templateForm.language' => ['required', Rule::in(SavePrintTemplateAction::LANGUAGES)],
            'templateForm.header_text' => ['nullable', 'string', 'max:2000'],
            'templateForm.footer_text' => ['nullable', 'string', 'max:2000'],
            'templateForm.layout_settings.width_mm' => ['required_if:templateForm.document_type,barcode_label','numeric','min:20','max:210'],
            'templateForm.layout_settings.height_mm' => ['required_if:templateForm.document_type,barcode_label','numeric','min:15','max:297'],
            'templateForm.layout_settings.dpi' => ['required_if:templateForm.document_type,barcode_label','integer','in:203,300'],
            'templateForm.layout_settings.orientation' => ['required_if:templateForm.document_type,barcode_label','in:portrait,landscape'],
            'templateForm.layout_settings.margin_mm' => ['nullable','numeric','min:0','max:20'],
            'templateForm.layout_settings.gap_mm' => ['nullable','numeric','min:0','max:20'],
            'templateForm.layout_settings.horizontal_gap_mm' => ['nullable','numeric','min:0','max:20'],
            'templateForm.layout_settings.vertical_gap_mm' => ['nullable','numeric','min:0','max:20'],
            'templateForm.layout_settings.a4_rows' => ['nullable','integer','min:1','max:20'],
            'templateForm.layout_settings.a4_columns' => ['nullable','integer','min:1','max:10'],
            'templateForm.layout_settings.symbology' => ['required_if:templateForm.document_type,barcode_label','in:auto,ean13,code128'],
            'templateForm.layout_settings.show_name' => ['boolean'], 'templateForm.layout_settings.show_price' => ['boolean'], 'templateForm.layout_settings.show_item_code' => ['boolean'], 'templateForm.layout_settings.show_list_code' => ['boolean'], 'templateForm.layout_settings.show_outlet' => ['boolean'],
            'templateForm.status' => ['required', 'in:active,inactive'],
        ]);
        $action->execute($validated['templateForm'], $this->templateForm['id'] ?? null);
        $this->resetPrintTemplateForm();
        $this->templateModalOpen = false;
        Flux::toast(variant: 'success', text: __('Print template saved successfully.'));
    }

    public function editPrintTemplate(int $id): void
    {
        $this->templateForm = PrintTemplate::visibleTo(auth()->user())->findOrFail($id)->toArray();
        $this->templateForm['layout_settings'] = array_replace(['width_mm'=>50,'height_mm'=>30,'dpi'=>203,'orientation'=>'portrait','margin_mm'=>1,'gap_mm'=>2,'horizontal_gap_mm'=>2,'vertical_gap_mm'=>2,'a4_rows'=>8,'a4_columns'=>3,'symbology'=>'auto','show_name'=>true,'show_price'=>true,'show_item_code'=>true,'show_list_code'=>true,'show_outlet'=>true],$this->templateForm['layout_settings']??[]);
        $this->resetValidation();
        $this->templateModalOpen = true;
    }

    public function openPrintTemplateModal(): void
    {
        Gate::authorize('manage-settings');
        $this->resetPrintTemplateForm();
        $this->resetValidation();
        $this->templateModalOpen = true;
    }

    public function resetPrintTemplateForm(): void
    {
        $this->templateForm = ['id' => null, 'code' => '', 'name_ar' => '', 'name_en' => '', 'document_type' => 'sales_invoice', 'paper_size' => '80mm', 'language' => 'bilingual', 'header_text' => '', 'footer_text' => '', 'layout_settings'=>['width_mm'=>50,'height_mm'=>30,'dpi'=>203,'orientation'=>'portrait','margin_mm'=>1,'gap_mm'=>2,'horizontal_gap_mm'=>2,'vertical_gap_mm'=>2,'a4_rows'=>8,'a4_columns'=>3,'symbology'=>'auto','show_name'=>true,'show_price'=>true,'show_item_code'=>true,'show_list_code'=>true,'show_outlet'=>true], 'status' => 'active'];
    }

    public function resetPrinterForm(): void
    {
        $this->printerForm = [
            'id' => null,
            'name' => '',
            'scope_type' => 'global',
            'branch_id' => null,
            'store_id' => null,
            'printer_type' => 'thermal',
            'paper_size' => '80mm',
            'template_name' => 'default_thermal',
            'print_template_id' => null,
            'connection_type' => 'network',
            'ip_address' => '',
            'port' => 9100,
            'is_default' => false,
            'status' => 'active',
            'notes' => '',
        ];
    }
}; ?>

<x-app.page
    :title="__('System Settings')"
    :description="__('Review and configure business settings.')"
    max-width="7xl"
    class="settings-screen space-y-6"
    data-guide="settings-header"
    data-unsaved-company-message="{{ __('company.unsaved_navigation') }}"
    x-data="{ dirty: $wire.entangle('companyDirty'), unsavedNavigationMessage: $el.dataset.unsavedCompanyMessage }"
    x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = '' }"
    x-on:livewire:navigate.document="if (dirty && !window.confirm(unsavedNavigationMessage)) $event.preventDefault()"
>
    <x-slot:actions>
        <x-context-help :title="__('General settings help')" :label="__('Open general settings help')">
            <ul>
                <li>{{ __('Company identity and timezone are shared defaults for the application. Branch screens do not edit timezone.') }}</li>
                <li>{{ __('Payment, tax, numbering, printer, and template settings remain in their dedicated sections.') }}</li>
                <li>{{ __('Search and filters remain available on list screens even when their panel is closed by default.') }}</li>
                <li>{{ __('Validation, approval, and transactional warnings always remain visible.') }}</li>
            </ul>
        </x-context-help>
        <flux:badge size="sm" color="zinc" icon="adjustments-horizontal">{{ __('Configuration') }}</flux:badge>
    </x-slot:actions>

    <div class="settings-screen__content space-y-6" x-data="{ dirty: $wire.entangle('companyDirty') }">

    <?php if ($errors->any()): ?>
        <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Validation Errors') }}">
            <p class="text-sm font-medium">{{ __('Please review and correct the following validation errors:') }}</p>
            <ul class="mt-2 list-disc list-inside space-y-1 text-sm">
                <?php foreach ($errors->all() as $error): ?>
                    <li>{{ $error }}</li>
                <?php endforeach; ?>
            </ul>
        </flux:callout>
    <?php endif; ?>

    <?php
        $activeTab = in_array($activeTab, ['company', 'payments', 'tax', 'sequences', 'printers', 'audit'], true) ? $activeTab : 'company';
        $sectionMeta = [
            'company' => [__('Company Identity'), __('Company identity and contact details.')],
            'payments' => [__('Payment Methods'), __('Payment names, evidence, and offline eligibility.')],
            'tax' => [__('Tax rules'), __('Tax treatment and price display defaults.')],
            'sequences' => [__('Document numbering'), __('Prefixes, counters, and reset timing.')],
            'printers' => [__('Printers & Print Profiles'), __('Printer destinations and assigned layouts.')],
            'audit' => [__('Configuration Change History'), __('Read-only history of setting changes.')],
        ][$activeTab] ?? [__('Company Identity'), __('Company identity and contact details.')];
    ?>

    <flux:card class="settings-screen__summary space-y-1 border-s-4 border-primary bg-surface shadow-card" data-settings-section-summary="{{ $activeTab }}">
        <flux:heading size="lg">{{ $sectionMeta[0] }}</flux:heading>
        <flux:subheading>{{ $sectionMeta[1] }}</flux:subheading>
    </flux:card>

    <!-- TAB 1: Company Identity -->
    <?php if ($activeTab === 'company'): ?>
        <div id="panel-company" role="tabpanel" aria-labelledby="tab-company" class="space-y-6">
            <?php if ($companyEditingBlocked): ?>
                <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Validation Errors') }}">
                    {{ __('company.duplicate_load') }}
                </flux:callout>
            <?php else: ?>
            <form
                wire:submit="saveCompany"
                x-on:input="dirty = true"
                class="space-y-6"
            >
                <flux:card class="space-y-4" data-guide="settings-company-card">
                    <flux:heading size="lg">{{ __('Company Master Information') }}</flux:heading>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:input
                            wire:model="companyForm.code"
                            :label="__('Company Code')"
                            required
                        />

                        <flux:input
                            wire:model="companyForm.legal_name"
                            :label="__('Legal Name')"
                            placeholder="3allaf Commercial Co."
                        />

                        <flux:input
                            wire:model="companyForm.name_ar"
                            :label="__('Name (Arabic)')"
                            placeholder="شركة لعبة وفرحة"
                            required
                        />

                        <flux:input
                            wire:model="companyForm.name_en"
                            :label="__('Name (English)')"
                            placeholder="3allaf Company"
                            required
                        />

                        <flux:input
                            wire:model="companyForm.tax_number"
                            :label="__('Tax Identification Number (TIN)')"
                            placeholder="300000000000003"
                        />

                        <flux:input
                            wire:model="companyForm.commercial_registration"
                            :label="__('Commercial Registration (CR)')"
                            placeholder="1010000000"
                        />
                    </div>
                </flux:card>

                <flux:card class="space-y-4" data-guide="settings-localization-card">
                    <flux:heading size="lg">{{ __('Localization and currency') }}</flux:heading>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <flux:input
                            wire:model="companyForm.currency_code"
                            :label="__('Currency code')"
                            required
                        />

                        <flux:input
                            wire:model="companyForm.currency_symbol"
                            :label="__('Currency symbol')"
                            required
                        />

                        <flux:select wire:model="companyForm.locale_default" :label="__('Default Application Locale')">
                            <option value="ar">العربية (Arabic - RTL)</option>
                            <option value="en">{{ __('English (LTR)') }}</option>
                        </flux:select>

                        <flux:input wire:model="companyForm.timezone" :label="__('Timezone')" placeholder="Africa/Cairo" required dir="ltr" />

                        <flux:input
                            wire:model="companyForm.phone"
                            :label="__('Contact Phone')"
                            :placeholder="__('e.g. 01012345678 or +20 1012345678')"
                            dir="ltr"
                        />

                        <flux:input
                            wire:model="companyForm.email"
                            :label="__('Contact Email')"
                            type="email"
                        />
                    </div>

                    <flux:input
                        wire:model="companyForm.address"
                        :label="__('Address')"
                    />

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <flux:button type="button" :href="route('admin.settings', ['tab' => 'payments'])" variant="subtle" wire:navigate>{{ __('Next') }}</flux:button>
                        <x-actions.loading-button type="submit" action="saveCompany" :label="__('Save')" x-bind:disabled="!dirty" data-guide="settings-save-button" />
                    </div>
                </flux:card>

            </form>

            <flux:card class="space-y-4">
                <div><flux:heading size="lg">{{ __('List display') }}</flux:heading><flux:subheading>{{ __('Choose whether list search and filter panels open automatically.') }}</flux:subheading></div>
                <flux:switch wire:model="showSearchFiltersByDefault" :label="__('Show search and filters by default')" :description="__('When off, use the compact Filter / Search control to open them when needed.')" />
                <div class="flex justify-end"><flux:button type="button" variant="primary" wire:click="saveListDisplayPreference" wire:loading.attr="disabled" wire:target="saveListDisplayPreference">{{ __('Save list display') }}</flux:button></div>
            </flux:card>

            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- TAB 2: Payment Methods -->
    <?php if ($activeTab === 'payments'): ?>
        <div id="panel-payments" role="tabpanel" aria-labelledby="tab-payments" class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><flux:heading size="lg">{{ __('Payment Methods') }}</flux:heading><flux:subheading>{{ __('Manage the payment methods available to authorized staff.') }}</flux:subheading></div>
                <flux:button type="button" variant="primary" icon="plus" wire:click="openPaymentMethodModal">{{ __('Add Payment Method') }}</flux:button>
            </div>
            <flux:modal wire:model="paymentModalOpen" class="max-w-2xl">
            <flux:card class="space-y-4">
                <flux:heading size="lg">
                    {{ $paymentMethodForm['id'] ? __('Edit Payment Method') : __('Add Payment Method') }}
                </flux:heading>
                <flux:subheading>{{ __('Choose the business name customers and staff will recognize. The method type is the accounting classification; it does not rename the method.') }}</flux:subheading>

                <form wire:submit="savePaymentMethod" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <flux:input
                            autofocus
                            wire:model="paymentMethodForm.code"
                            :label="__('Method Code')"
                            placeholder="CASH / CARD / MADA"
                            required
                        />

                        <flux:input
                            wire:model="paymentMethodForm.name_ar"
                            :label="__('Name (Arabic)')"
                            required
                        />

                        <flux:input
                            wire:model="paymentMethodForm.name_en"
                            :label="__('Name (English)')"
                            required
                        />

                        <flux:select wire:model="paymentMethodForm.type" :label="__('Underlying payment type')">
                            <option value="cash">{{ __('Cash') }}</option>
                            <option value="card">{{ __('Card or POS terminal') }}</option>
                            <option value="transfer">{{ __('Bank Transfer') }}</option>
                            <option value="manual">{{ __('Other / manual record') }}</option>
                            <option value="manual_electronic">{{ __('Electronic wallet / manual transfer') }}</option>
                            <option value="cheque">{{ __('Cheque') }}</option>
                            <option value="gift_card">{{ __('Gift card') }}</option>
                        </flux:select>

                        <flux:select wire:model="paymentMethodForm.status" :label="__('Status')">
                            <option value="active">{{ __('Active') }}</option>
                            <option value="inactive">{{ __('Inactive') }}</option>
                        </flux:select>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <flux:switch
                                wire:model="paymentMethodForm.requires_evidence"
                                :label="__('Requires payment evidence')"
                            />
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('When enabled, staff must attach or reference payment evidence before this payment can be approved.') }}</p>
                        </div>

                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <?php if ($paymentMethodForm['id']): ?>
                            <flux:button type="button" wire:click="resetPaymentMethodForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        <?php endif; ?>

                        <flux:button type="button" variant="subtle" wire:click="$set('paymentModalOpen', false)">{{ __('Cancel') }}</flux:button>
                        <x-actions.loading-button type="submit" action="savePaymentMethod" :label="__('Save')" />
                    </div>
                </form>
            </flux:card>
            </flux:modal>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Configured Payment Methods') }}</flux:heading>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Review saved methods below. Add a new method using the form above.') }}</p>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <flux:table class="min-w-[880px]" aria-label="{{ __('Configured Payment Methods') }}">
                        <?php $paymentMethods = PaymentMethod::query()->orderBy('code')->limit(100)->get(); ?>

                        <flux:table.columns>
                            <flux:table.column class="min-w-24 whitespace-nowrap">{{ __('Code') }}</flux:table.column>
                            <flux:table.column class="min-w-56"><span class="block whitespace-normal leading-tight">{{ __('Name (AR/EN)') }}</span></flux:table.column>
                            <flux:table.column class="min-w-36"><span class="block whitespace-normal leading-tight">{{ __('Underlying type') }}</span></flux:table.column>
                            <flux:table.column class="min-w-36"><span class="block whitespace-normal leading-tight">{{ __('Payment evidence') }}</span></flux:table.column>
                            <flux:table.column class="min-w-40"><span class="block whitespace-normal leading-tight">{{ __('Offline POS use') }}</span></flux:table.column>
                            <flux:table.column class="min-w-24 whitespace-nowrap">{{ __('Status') }}</flux:table.column>
                            <flux:table.column class="min-w-24 whitespace-nowrap text-end">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                        <?php if ($paymentMethods->isNotEmpty()): ?>
                            <?php foreach ($paymentMethods as $method): ?>
                            <flux:table.row key="method-{{ $method->id }}">
                                <flux:table.cell class="font-mono font-bold">{{ $method->code }}</flux:table.cell>
                                <flux:table.cell>
                                    <div>{{ $method->name_ar }}</div>
                                    <div class="text-xs text-zinc-500">{{ $method->name_en }}</div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php
                                        $typeLabel = [
                                            'cash' => __('Cash'),
                                            'card' => __('Card or POS terminal'),
                                            'transfer' => __('Bank Transfer'),
                                            'manual' => __('Other / manual record'),
                                            'manual_electronic' => __('Electronic wallet / manual transfer'),
                                            'cheque' => __('Cheque'),
                                            'gift_card' => __('Gift card'),
                                        ][$method->type] ?? __('Other / manual record');
                                    ?>
                                    <flux:badge size="sm" color="zinc">{{ $typeLabel }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($method->requires_evidence): ?>
                                        <flux:badge size="sm" color="amber"><span style="color: light-dark(#78350f, #fde68a)">{{ __('Required') }}</span></flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="zinc">{{ __('Not required') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($method->offline_eligible): ?>
                                        <flux:badge size="sm" color="green">{{ __('Allowed by policy') }}</flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="zinc">{{ __('Not allowed') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($method->status === 'active'): ?>
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="subtle" wire:click="editPaymentMethod({{ $method->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <flux:table.row>
                                <flux:table.cell colspan="7" class="text-center py-4">
                                    <x-state.empty
                                        :title="__('No Payment Methods Configured')"
                                        :description="__('No payment methods are configured yet. Add one using the form above.')"
                                        icon="credit-card"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        <?php endif; ?>
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:card>
        </div>
    <?php endif; ?>

    <!-- TAB 3: Tax Settings -->
    <?php if ($activeTab === 'tax'): ?>
        <div id="panel-tax" role="tabpanel" aria-labelledby="tab-tax" class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2"><div><flux:heading size="lg">{{ __('Taxes') }}</flux:heading><flux:subheading>{{ __('Manage company and product tax choices without changing historical document snapshots.') }}</flux:subheading></div><x-context-help :title="__('Tax configuration help')" :label="__('Open tax configuration help')"><ul class="list-disc space-y-2 ps-5"><li>{{ __('Company default tax applies when a product has no specific tax assignment.') }}</li><li>{{ __('A product-specific tax can be selected on the product card and overrides only that product default assignment.') }}</li><li>{{ __('Tax-inclusive pricing contains tax in the displayed price; tax-exclusive pricing adds tax to the displayed price.') }}</li></ul></x-context-help></div>
                <flux:button type="button" variant="primary" icon="plus" wire:click="openTaxSettingModal">{{ __('Add Tax') }}</flux:button>
            </div>
            <flux:modal wire:model="taxModalOpen" class="max-w-2xl">
            <flux:card class="space-y-4">
                <flux:heading size="lg">
                    {{ $taxSettingForm['id'] ? __('Edit Tax Rule') : __('Add Tax Rule') }}
                </flux:heading>

                <form wire:submit="saveTaxSetting" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <flux:input
                            autofocus
                            wire:model="taxSettingForm.code"
                            :label="__('Tax Rule Code')"
                            placeholder="VAT15 / EXCISE"
                            required
                        />

                        <flux:input
                            wire:model="taxSettingForm.name_ar"
                            :label="__('Name (Arabic)')"
                            required
                        />

                        <flux:input
                            wire:model="taxSettingForm.name_en"
                            :label="__('Name (English)')"
                            required
                        />

                        <flux:select wire:model="taxSettingForm.treatment" :label="__('Tax Treatment')" required>
                            <option value="standard">{{ __('Standard') }}</option>
                            <option value="zero_rated">{{ __('Zero Rated') }}</option>
                            <option value="exempt">{{ __('Exempt') }}</option>
                            <option value="out_of_scope">{{ __('Out of Scope') }}</option>
                        </flux:select>

                        <flux:input
                            wire:model="taxSettingForm.rate"
                            :label="__('Tax Rate (%)')"
                            placeholder="15.00"
                            type="number"
                            step="0.01"
                        />

                        <flux:input
                            wire:model="taxSettingForm.tax_number"
                            :label="__('Specific Tax Reg No.')"
                        />

                        <flux:select wire:model="taxSettingForm.status" :label="__('Status')">
                            <option value="active">{{ __('Active') }}</option>
                            <option value="inactive">{{ __('Inactive') }}</option>
                        </flux:select>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <flux:switch
                            wire:model="taxSettingForm.is_default"
                            align="left"
                            :label="__('Company Default Tax Rule')"
                        />
                        <flux:switch
                            wire:model="taxSettingForm.is_tax_inclusive"
                            align="left"
                            :label="__('Default Prices Are Tax Inclusive')"
                        />
                    </div>
                    <flux:text class="text-sm text-text-muted">{{ __('Tax inclusive means the displayed price already includes the tax amount.') }}</flux:text>

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <?php if ($taxSettingForm['id']): ?>
                            <flux:button type="button" wire:click="resetTaxSettingForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        <?php endif; ?>

                        <flux:button type="button" variant="subtle" wire:click="$set('taxModalOpen', false)">{{ __('Cancel') }}</flux:button>
                        <x-actions.loading-button type="submit" action="saveTaxSetting" :label="__('Save')" />
                    </div>
                </form>
            </flux:card>
            </flux:modal>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Configured Tax Settings') }}</flux:heading>

                <flux:table aria-label="{{ __('Configured Tax Settings') }}">

                    <flux:table.columns>
                        <flux:table.column>{{ __('Code') }}</flux:table.column>
                        <flux:table.column>{{ __('Name (AR/EN)') }}</flux:table.column>
                        <flux:table.column>{{ __('Treatment') }}</flux:table.column>
                        <flux:table.column>{{ __('Rate %') }}</flux:table.column>
                        <flux:table.column>{{ __('Inclusive') }}</flux:table.column>
                        <flux:table.column>{{ __('Default') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        <?php $taxRows = TaxSetting::query()->orderBy('code')->limit(100)->get(); if ($taxRows->isNotEmpty()): foreach ($taxRows as $tax): ?>
                            <flux:table.row key="tax-{{ $tax->id }}">
                                <flux:table.cell class="font-mono font-bold">{{ $tax->code }}</flux:table.cell>
                                <flux:table.cell>
                                    <div>{{ $tax->name_ar }}</div>
                                    <div class="text-xs text-zinc-500">{{ $tax->name_en }}</div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" color="zinc">{{ $tax->treatmentLabel() }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($tax->rate !== null): ?>
                                        <span class="font-mono font-semibold">{{ number_format((float)$tax->rate, 2) }}%</span>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="amber"><span style="color: light-dark(#78350f, #fde68a)">{{ __('Not configured') }}</span></flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($tax->is_tax_inclusive): ?>
                                        <flux:badge size="sm" color="zinc">{{ __('Inclusive') }}</flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="zinc">{{ __('Exclusive') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($tax->is_default): ?>
                                        <flux:badge size="sm" color="green">{{ __('Default') }}</flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="zinc">{{ __('No') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($tax->status === 'active'): ?>
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="subtle" wire:click="editTaxSetting({{ $tax->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        <?php endforeach; else: ?>
                            <flux:table.row>
                                <flux:table.cell colspan="8" class="text-center py-4">
                                    <x-state.empty
                                        :title="__('No Tax Rules Configured')"
                        :description="__('No tax rules are configured yet. Add one using the form above.')"
                                        icon="receipt-percent"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        <?php endif; ?>
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    <?php endif; ?>

    <!-- TAB 4: Document Sequences -->
    <?php if ($activeTab === 'sequences'): ?>
        <div id="panel-sequences" role="tabpanel" aria-labelledby="tab-sequences" class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><flux:heading size="lg">{{ __('Document Numbering') }}</flux:heading><flux:subheading>{{ __('Each branch and document type keeps its own continuous counter.') }}</flux:subheading></div><flux:button type="button" variant="primary" icon="plus" wire:click="openDocumentSequenceModal">{{ __('Add Numbering Rule') }}</flux:button></div>
            <flux:modal wire:model="sequenceModalOpen" class="max-w-3xl">
            <flux:card class="space-y-6">
                <div class="space-y-1">
                    <flux:heading size="lg">
                        {{ $documentSequenceForm['id'] ? __('Edit document-numbering rule') : __('Add document-numbering rule') }}
                    </flux:heading>
                    <flux:text class="text-text-muted">{{ __('Set the document code, where it applies, and how its next number is displayed.') }}</flux:text>
                </div>

                <form wire:submit="saveDocumentSequence" class="space-y-5">
                    <section class="space-y-4 rounded-xl border border-border-subtle bg-surface-muted/40 p-4" aria-labelledby="sequence-basics-heading">
                        <div>
                            <flux:heading id="sequence-basics-heading" size="sm">{{ __('Rule details') }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-text-muted">{{ __('Choose the document and the level that uses this numbering rule.') }}</flux:text>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 {{ ($documentSequenceForm['scope_type'] ?? 'company') === 'branch' ? 'xl:grid-cols-3' : '' }}">
                        <flux:select autofocus wire:model.live="documentSequenceForm.document_type" :label="__('Document type')" required>
                            @foreach (DocumentTypeCatalog::NUMBERING_TYPES as $type => $definition)
                                <option value="{{ $type }}">{{ __($definition['label']) }}</option>
                            @endforeach
                        </flux:select>

                        <?php if (($documentSequenceForm['scope_type'] ?? 'branch') === 'branch'): ?>
                            <?php $activeBranches = Branch::visibleTo(auth()->user())->where('status', 'active')->orderBy('code')->get(); ?>
                            <flux:select wire:model="documentSequenceForm.scope_id" :label="__('Branch')" required>
                                <option value="">{{ __('Select an active branch...') }}</option>
                                <?php foreach ($activeBranches as $branch): ?>
                                    <option value="{{ $branch->id }}">{{ $branch->code }} — {{ str_starts_with(app()->getLocale(), 'ar') ? $branch->name_ar : $branch->name_en }}</option>
                                <?php endforeach; ?>
                            </flux:select>
                        <?php else: ?><flux:input :label="__('Branch')" :value="__('Legacy company-wide rule')" readonly /><?php endif; ?>
                        </div>
                    </section>

                    <section class="space-y-4" aria-labelledby="sequence-format-heading">
                        <div>
                            <flux:heading id="sequence-format-heading" size="sm">{{ __('Number format') }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-text-muted">{{ __('Arrange the text and digits exactly as they should appear on the document.') }}</flux:text>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:input
                            wire:model="documentSequenceForm.padding_length"
                            :label="__('Counter digits (padding)')"
                            :description="__('How many digits the counter uses; 6 turns 42 into 000042.')"
                            type="number"
                            required
                        />
                        <flux:input :label="__('Sequence policy')" :value="__('Continuous — never resets')" readonly />
                        </div>
                    </section>

                    <section class="space-y-4 rounded-xl border border-border-subtle p-4" aria-labelledby="sequence-counter-heading">
                        <div>
                            <flux:heading id="sequence-counter-heading" size="sm">{{ __('Counter settings') }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-text-muted">{{ __('Choose the first number and whether counting restarts on a schedule.') }}</flux:text>
                        </div>
                        <div class="grid grid-cols-1 gap-4">
                        <flux:input
                            wire:model="documentSequenceForm.next_value"
                            :label="$documentSequenceForm['id'] ? __('Current next number (read-only)') : __('First number to use')"
                            type="number"
                            :readonly="(bool) $documentSequenceForm['id']"
                            :description="$documentSequenceForm['id'] ? __('Shown for information while editing. Use the separate authorized correction below to change it.') : __('First number allocated by this sequence.')"
                            :required="! $documentSequenceForm['id']"
                        />
                        </div>
                    </section>

                    <?php
                        $previewValue = (int) ($documentSequenceForm['next_value'] ?? 1);
                        $previewPrefix = ($documentSequenceForm['scope_type'] ?? 'branch') === 'branch'
                            ? (($previewBranch = $activeBranches->firstWhere('id', (int) ($documentSequenceForm['scope_id'] ?? 0)))
                                ? DocumentTypeCatalog::prefix($previewBranch->code, (string) $documentSequenceForm['document_type'])
                                : __('BRANCH-TYPE-'))
                            : (string) ($documentSequenceForm['prefix'] ?? '');
                        $previewNumber = $previewPrefix
                            .str_pad((string) $previewValue, (int) ($documentSequenceForm['padding_length'] ?? 6), '0', STR_PAD_LEFT)
                            .(string) ($documentSequenceForm['suffix'] ?? '');
                    ?>
                    <div class="rounded-xl border border-primary/25 bg-primary/5 px-5 py-4" data-sequence-preview>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <flux:heading size="sm">{{ __('Preview of the next document number') }}</flux:heading>
                                <flux:text class="mt-1 text-sm text-text-muted">{{ __('Changes here do not reserve a number until you save the rule.') }}</flux:text>
                            </div>
                            <output class="rounded-lg bg-surface px-4 py-2 font-mono text-lg font-semibold tracking-wide text-primary shadow-sm" aria-live="polite">{{ $previewNumber }}</output>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <?php if ($documentSequenceForm['id']): ?>
                            <flux:button type="button" wire:click="resetDocumentSequenceForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        <?php endif; ?>

                        <flux:button type="button" variant="subtle" wire:click="$set('sequenceModalOpen', false)">{{ __('Cancel') }}</flux:button>
                        <x-actions.loading-button type="submit" action="saveDocumentSequence" :label="__('Save')" />
                    </div>
                </form>

                <?php if ($documentSequenceForm['id']): ?>
                    <?php if (Gate::allows('drawers_payments_tax_numbering_printers.override')): ?>
                        <form wire:submit="overrideSequenceCounter" class="space-y-4 border-t border-border-subtle pt-5" aria-labelledby="sequence-override-heading">
                            <div>
                                <flux:heading id="sequence-override-heading" size="md">{{ __('Authorized counter correction') }}</flux:heading>
                                <flux:text class="mt-1 text-text-muted">{{ __('Use this separate action to change the current counter. Enter a reason; the change is audited.') }}</flux:text>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <flux:input wire:model="sequenceOverride.next_value" type="number" min="1" :label="__('Replacement next number')" required />
                                <flux:textarea wire:model="sequenceOverride.reason" :label="__('Reason for correction')" required />
                            </div>
                            <div class="flex justify-end"><flux:button type="submit" variant="danger" wire:confirm="{{ __('Change this document counter? The action is permanent and audited.') }}" wire:loading.attr="disabled" wire:target="overrideSequenceCounter"><span wire:loading.remove wire:target="overrideSequenceCounter">{{ __('Submit counter correction') }}</span><span wire:loading wire:target="overrideSequenceCounter">{{ __('Saving...') }}</span></flux:button></div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </flux:card>
            </flux:modal>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Document-numbering rules') }}</flux:heading>

                <flux:table aria-label="{{ __('Document-numbering rules') }}">
                    <?php $documentSequences = DocumentSequence::visibleTo(auth()->user())->with('scopeBranch')->orderBy('document_type')->orderBy('scope_key')->limit(100)->get(); ?>

                    <flux:table.columns>
                        <flux:table.column>{{ __('Document type') }}</flux:table.column>
                        <flux:table.column>{{ __('Numbering scope') }}</flux:table.column>
                        <flux:table.column>{{ __('Example number') }}</flux:table.column>
                        <flux:table.column>{{ __('Current next number') }}</flux:table.column>
                        <flux:table.column>{{ __('Reset cycle') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        <?php if ($documentSequences->isNotEmpty()): ?>
                            <?php foreach ($documentSequences as $seq): ?>
                            <flux:table.row key="seq-{{ $seq->id }}">
                                <flux:table.cell class="font-bold">{{ DocumentTypeCatalog::numberingLabel($seq->document_type) }}</flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($seq->scope_type === 'branch'): ?>
                                        <flux:badge size="sm" color="blue">
                                            {{ __('Branch') }}: {{ str_starts_with(app()->getLocale(), 'ar') ? ($seq->scopeBranch?->name_ar ?? __('Unknown branch')) : ($seq->scopeBranch?->name_en ?? __('Unknown branch')) }}
                                            <span class="text-xs opacity-70">({{ $seq->scopeBranch?->code ?? $seq->scope_id }})</span>
                                        </flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="zinc">{{ __('Company-wide') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell class="font-mono text-xs text-primary">{{ $seq->formatValue((int) $seq->next_value) }}</flux:table.cell>
                                <flux:table.cell class="font-mono font-semibold">{{ $seq->next_value }}</flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" color="zinc">{{ __(Str::headline($seq->reset_rule)) }}</flux:badge></flux:table.cell>
                                <flux:table.cell>
                                    <?php if ($seq->status === 'active'): ?>
                                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                    <?php else: ?>
                                        <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                    <?php endif; ?>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="subtle" wire:click="editDocumentSequence({{ $seq->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <flux:table.row>
                                <flux:table.cell colspan="7" class="text-center py-4">
                                    <x-state.empty
                                        :title="__('No Document Sequences Configured')"
                        :description="__('No document sequences are configured yet. Add one using the form above.')"
                                        icon="numbered-list"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        <?php endif; ?>
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    <?php endif; ?>

    <!-- TAB 5: Printer Configurations -->
    <?php if ($activeTab === 'printers'): ?>
        <div id="panel-printers" role="tabpanel" aria-labelledby="tab-printers" data-settings-active-section="{{ $printerSection }}" class="space-y-6">
            <div class="rounded-xl border border-border-subtle bg-surface-muted/40 p-5">
                <flux:heading size="lg">{{ __('Printers and print settings') }}</flux:heading>
                <flux:text class="mt-1 text-text-muted">{{ __('Add the printers available at your branch, then choose where each document is printed.') }}</flux:text>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3" aria-label="{{ __('Printer workspace') }}">
                <x-context-help :title="__('Printer and template help')" :label="__('Open printer and template help')"><ul class="list-disc space-y-2 ps-5"><li>{{ __('Printer identifies the output device and connection.') }}</li><li>{{ __('Template controls document type, paper size, language, and layout.') }}</li><li>{{ __('Scope limits a printer to the company, a branch, or a point of sale. Relevant printers are active visible printers assigned to an active paper-compatible template.') }}</li></ul></x-context-help>
                <div class="flex flex-wrap gap-2"><flux:button type="button" size="sm" variant="primary" icon="plus" wire:click="openPrinterModal">{{ __('Add printer') }}</flux:button><flux:button type="button" size="sm" variant="primary" icon="plus" wire:click="openPrintTemplateModal">{{ __('Add print template') }}</flux:button></div>
            </div>

            <?php if (true): ?>
            <flux:card id="printer-profiles" class="scroll-mt-24 space-y-6">
                <flux:modal wire:model="printerModalOpen" class="max-w-3xl">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ $printerForm['id'] ? __('Edit printer') : __('Add printer') }}</flux:heading>
                        <flux:subheading>{{ __('Enter the printer details used by the team. Advanced connection settings are optional.') }}</flux:subheading>
                    </div>
        <?php if ($printerForm['id']): ?>
            <flux:button
                href="{{ route('admin.settings.printer-preview', ['printer' => $printerForm['id']]) }}"
                target="_blank"
                variant="subtle"
                icon="printer"
            >
                {{ __('Test print') }}
            </flux:button>
        <?php else: ?>
            <flux:button type="button" variant="subtle" icon="printer" disabled>
                {{ __('Test print') }}
            </flux:button>
        <?php endif; ?>
                </div>

                <form id="printer-profile-form" wire:submit="savePrinter" class="space-y-4">
                    <?php
                        $printerBranches = Branch::visibleTo(auth()->user())->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']);
                        $printerStores = \App\Modules\Platform\Models\Store::visibleTo(auth()->user())->where('status', 'active')->orderBy('code')->get(['id', 'branch_id', 'code', 'name_ar', 'name_en']);
                    ?>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <flux:input autofocus
                            wire:model="printerForm.name"
                            :label="__('Printer Name')"
                            placeholder="Cashier Thermal Printer 1"
                            required
                        />

                        <flux:select wire:model.live="printerForm.scope_type" :label="__('Printer location')">
                            <option value="global">{{ __('Entire company') }}</option>
                            <option value="branch">{{ __('Branch') }}</option>
                            <option value="store">{{ __('Store / point of sale') }}</option>
                        </flux:select>

                        <flux:select wire:model.live="printerForm.branch_id" :label="__('Branch')" :disabled="$printerForm['scope_type'] === 'global'">
                            <option value="">{{ __('Select branch') }}</option>
                            @foreach ($printerBranches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $branch->name_ar : $branch->name_en }}</option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="printerForm.store_id" :label="__('Store / point of sale')" :disabled="$printerForm['scope_type'] !== 'store'">
                            <option value="">{{ __('Select a store or point of sale') }}</option>
                            @foreach ($printerStores->when(filled($printerForm['branch_id']), fn ($stores) => $stores->where('branch_id', (int) $printerForm['branch_id'])) as $store)
                                <option value="{{ $store->id }}">{{ $store->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $store->name_ar : $store->name_en }}</option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model.live="printerForm.printer_type" :label="__('Printer Type')">
                            <option value="thermal">{{ __('Thermal Receipt') }}</option>
                            <option value="a4">{{ __('A4 Document') }}</option>
                            <option value="label">{{ __('Barcode / Label') }}</option>
                            <option value="pdf">{{ __('PDF Virtual') }}</option>
                        </flux:select>

                        <flux:select wire:model.live="printerForm.paper_size" :label="__('Paper Size')">
                            <?php if (($printerForm['printer_type'] ?? 'thermal') === 'thermal'): ?>
                                <option value="80mm">{{ __('80mm Thermal') }}</option>
                                <option value="58mm">{{ __('58mm Thermal') }}</option>
                            <?php elseif (($printerForm['printer_type'] ?? '') === 'label'): ?>
                                <option value="label_50x30mm">{{ __('50 × 30 mm label') }}</option>
                                <option value="label_40x25mm">{{ __('40 × 25 mm label') }}</option>
                            <?php else: ?>
                                <option value="a4">{{ __('A4 Sheet') }}</option>
                                <option value="a5">{{ __('A5 Sheet') }}</option>
                            <?php endif; ?>
                        </flux:select>

                        @php($compatibleTemplates = PrintTemplate::visibleTo(auth()->user())->where('status', 'active')->where('paper_size', $printerForm['paper_size'])->orderBy(str_starts_with(app()->getLocale(), 'ar') ? 'name_ar' : 'name_en')->get())
                        <flux:select wire:model="printerForm.print_template_id" :label="__('Print template')">
                            <option value="">{{ __('No template assigned') }}</option>
                            @foreach ($compatibleTemplates as $template)
                                <option value="{{ $template->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $template->name_ar : $template->name_en }}</option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="printerForm.connection_type" :label="__('Connection')"><option value="network">{{ __('Network') }}</option><option value="usb">USB</option><option value="bluetooth">Bluetooth</option><option value="browser">{{ __('Browser print') }}</option></flux:select>
                        <flux:input wire:model="printerForm.ip_address" :label="__('IP address')" dir="ltr" :disabled="$printerForm['connection_type'] !== 'network'" />
                        <flux:input wire:model="printerForm.port" :label="__('Port')" type="number" min="1" max="65535" :disabled="$printerForm['connection_type'] !== 'network'" />

                    </div>

                    <input type="hidden" wire:model="printerForm.template_name">

                    <flux:switch
                        wire:model="printerForm.is_default"
                        align="start"
                        :label="__('Make this the default printer for this location')"
                    />
                    <p class="-mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Use one default printer per location and printer type.') }}</p>

                    <div class="flex items-center justify-end gap-3">
                        <?php if ($printerForm['id']): ?>
                            <flux:button type="button" wire:click="resetPrinterForm" variant="subtle">
                                {{ __('Cancel Edit') }}
                            </flux:button>
                        <?php endif; ?>

                        <x-actions.loading-button type="submit" action="savePrinter" :label="__('Save')" />
                    </div>
                </form>
                </flux:modal>

                <?php $printers = PrinterConfiguration::visibleTo(auth()->user())->with(['branch', 'store', 'printTemplate'])->orderBy('name')->limit(100)->get(); ?>
                <div class="border-t border-border-subtle pt-5">
                    <div class="mb-4">
                        <flux:heading size="md">{{ __('Configured printers') }}</flux:heading>
                        <flux:text class="mt-1 text-sm text-text-muted">{{ __('Review availability and update the printer that is used at each location.') }}</flux:text>
                    </div>
                    <flux:table aria-label="{{ __('Configured printers') }}">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Printer name') }}</flux:table.column>
                            <flux:table.column>{{ __('Template') }}</flux:table.column>
                            <flux:table.column>{{ __('Scope') }}</flux:table.column>
                            <flux:table.column>{{ __('Type') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            <flux:table.column>{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            <?php foreach ($printers as $printer): ?>
                                <flux:table.row key="physical-printer-{{ $printer->id }}">
                                    <flux:table.cell class="font-medium">{{ $printer->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $printer->printTemplate ? (str_starts_with(app()->getLocale(), 'ar') ? $printer->printTemplate->name_ar : $printer->printTemplate->name_en) : __('No template assigned') }}<div class="text-xs text-text-muted">{{ $printer->paper_size }}</div></flux:table.cell>
                                    <flux:table.cell>{{ $printer->store ? (str_starts_with(app()->getLocale(), 'ar') ? $printer->store->name_ar : $printer->store->name_en) : ($printer->branch ? (str_starts_with(app()->getLocale(), 'ar') ? $printer->branch->name_ar : $printer->branch->name_en) : __('Entire company')) }}</flux:table.cell>
                                    <flux:table.cell>{{ $printer->printTemplate ? DocumentTypeCatalog::printLabel($printer->printTemplate->document_type) : __(match ($printer->printer_type) { 'thermal' => 'Thermal receipt printer', 'label' => 'Barcode / label printer', 'a4' => 'A4 printer', default => 'PDF printer' }) }}</flux:table.cell>
                                    <flux:table.cell><flux:badge size="sm" :color="$printer->status === 'active' ? 'green' : 'zinc'">{{ $printer->status === 'active' ? __('Available') : __('Inactive') }}</flux:badge></flux:table.cell>
                                    <flux:table.cell class="whitespace-nowrap">
                                        <div class="flex flex-wrap gap-2">
                            <flux:button
                                size="sm"
                                variant="subtle"
                                href="{{ route('admin.settings.printer-preview', ['printer' => $printer->id]) }}"
                                target="_blank"
                                icon="printer"
                            >
                                {{ __('Test print') }}
                            </flux:button>
                                            <flux:button size="sm" variant="subtle" type="button" wire:click="editPrinter({{ $printer->id }})">{{ __('Edit') }}</flux:button>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            <?php endforeach; ?>
                            <?php if ($printers->isEmpty()): ?>
                                <flux:table.row>
                                    <flux:table.cell colspan="6" class="py-8 text-center">
                                        <x-state.empty :title="__('No printers added yet')" :description="__('Add your first printer to start setting up invoices, receipts, and reports.')" icon="printer" />
                                    </flux:table.cell>
                                </flux:table.row>
                            <?php endif; ?>
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:card>
            <?php endif; ?>

            <?php if (true): ?>
            <flux:card id="print-templates" class="scroll-mt-24 space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><flux:heading size="lg">{{ __('Printing Template Library') }}</flux:heading><flux:subheading>{{ __('Create reusable document layouts by document type, paper size, and language, then assign compatible templates to printers.') }}</flux:subheading></div>
                </div>
                <flux:modal wire:model="templateModalOpen" class="max-w-3xl"><form wire:submit="savePrintTemplate" class="grid gap-4 rounded-xl border border-border-subtle p-4 md:grid-cols-2 xl:grid-cols-3">
                    <flux:input autofocus wire:model="templateForm.code" :label="__('Code')" required />
                    <flux:input wire:model="templateForm.name_ar" :label="__('Arabic name')" dir="rtl" required />
                    <flux:input wire:model="templateForm.name_en" :label="__('English name')" dir="ltr" required />
                    <flux:select wire:model.live="templateForm.document_type" :label="__('Document type')">
                        @foreach (SavePrintTemplateAction::DOCUMENT_TYPES as $type)<option value="{{ $type }}">{{ DocumentTypeCatalog::printLabel($type) }}</option>@endforeach
                    </flux:select>
                    <flux:select wire:model="templateForm.paper_size" :label="__('Paper size')">
                        @foreach (SavePrintTemplateAction::PAPER_SIZES as $size)<option value="{{ $size }}">{{ __(match($size) {'58mm'=>'58 mm','80mm'=>'80 mm','a4'=>'A4','a5'=>'A5','label_50x30mm'=>'50 × 30 mm','label_40x25mm'=>'40 × 25 mm'}) }}</option>@endforeach
                    </flux:select>
                    <flux:select wire:model="templateForm.language" :label="__('Language')"><option value="ar">{{ __('Arabic') }}</option><option value="en">{{ __('English') }}</option><option value="bilingual">{{ __('Arabic and English') }}</option></flux:select>
                    <flux:textarea wire:model="templateForm.header_text" :label="__('Header text')" />
                    <flux:textarea wire:model="templateForm.footer_text" :label="__('Footer text')" />
                    <flux:select wire:model="templateForm.status" :label="__('Status')"><option value="active">{{ __('Active') }}</option><option value="inactive">{{ __('Inactive') }}</option></flux:select>
                    @if($templateForm['document_type']==='barcode_label')<div class="md:col-span-2 xl:col-span-3 grid gap-3 sm:grid-cols-3"><flux:input wire:model="templateForm.layout_settings.width_mm" type="number" step="0.1" :label="__('Label width (mm)')"/><flux:input wire:model="templateForm.layout_settings.height_mm" type="number" step="0.1" :label="__('Label height (mm)')"/><flux:select wire:model="templateForm.layout_settings.dpi" :label="__('Printer DPI')"><option value="203">203 DPI</option><option value="300">300 DPI</option></flux:select><flux:select wire:model="templateForm.layout_settings.orientation" :label="__('Orientation')"><option value="portrait">{{__('Portrait')}}</option><option value="landscape">{{__('Landscape')}}</option></flux:select><flux:input wire:model="templateForm.layout_settings.margin_mm" type="number" step="0.1" :label="__('Margin (mm)')"/><flux:input wire:model="templateForm.layout_settings.horizontal_gap_mm" type="number" step="0.1" :label="__('Horizontal gap (mm)')"/><flux:input wire:model="templateForm.layout_settings.vertical_gap_mm" type="number" step="0.1" :label="__('Vertical gap (mm)')"/><flux:input wire:model="templateForm.layout_settings.a4_rows" type="number" step="1" :label="__('A4 rows')"/><flux:input wire:model="templateForm.layout_settings.a4_columns" type="number" step="1" :label="__('A4 columns')"/><flux:select wire:model="templateForm.layout_settings.symbology" :label="__('Barcode symbology')"><option value="auto">{{__('Automatic EAN-13 / Code 128')}}</option><option value="ean13">EAN-13</option><option value="code128">Code 128</option></flux:select></div><div class="md:col-span-2 xl:col-span-3 flex flex-wrap gap-4"><flux:checkbox wire:model="templateForm.layout_settings.show_name" :label="__('Product name')"/><flux:checkbox wire:model="templateForm.layout_settings.show_price" :label="__('Price')"/><flux:checkbox wire:model="templateForm.layout_settings.show_item_code" :label="__('Item code')"/><flux:checkbox wire:model="templateForm.layout_settings.show_list_code" :label="__('Price-list code')"/><flux:checkbox wire:model="templateForm.layout_settings.show_outlet" :label="__('Outlet')"/></div>@endif
                    <div class="flex items-end justify-end gap-2 md:col-span-2 xl:col-span-3">@if($templateForm['id'])<flux:button type="button" wire:click="resetPrintTemplateForm" variant="subtle">{{ __('Cancel Edit') }}</flux:button>@endif<x-actions.loading-button type="submit" action="savePrintTemplate" :label="__('Save')" /></div>
                </form></flux:modal>
                @php($templates = PrintTemplate::visibleTo(auth()->user())->withCount(['printers as relevant_printers_count' => fn ($query) => $query->visibleTo(auth()->user())->where('status', 'active')->whereColumn('printer_configurations.paper_size', 'print_templates.paper_size')])->orderBy('document_type')->orderBy('code')->paginate(25, ['*'], 'templates_page'))
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @forelse($templates as $template)
                        <article class="rounded-xl border border-border-subtle p-4"><div class="flex justify-between gap-3"><div><h3 class="font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? $template->name_ar : $template->name_en }}</h3><p class="font-mono text-xs text-text-muted">{{ $template->code }}</p></div><flux:badge size="sm" :color="$template->status==='active'?'green':'zinc'">{{ \App\Modules\Platform\Support\UiLabel::status($template->status) }}</flux:badge></div><dl class="mt-3 grid grid-cols-2 gap-2 text-sm"><dt>{{ __('Document type') }}</dt><dd>{{ DocumentTypeCatalog::printLabel($template->document_type) }}</dd><dt>{{ __('Paper size') }}</dt><dd>{{ __(strtoupper($template->paper_size)) }}</dd><dt>{{ __('Language') }}</dt><dd>{{ __($template->language) }}</dd><dt>{{ __('Relevant printers') }}</dt><dd>{{ $template->status === 'active' ? $template->relevant_printers_count : 0 }}</dd></dl><div class="mt-4 flex flex-wrap gap-2"><flux:button size="sm" type="button" wire:click="editPrintTemplate({{ $template->id }})">{{ __('Edit') }}</flux:button><flux:button size="sm" variant="subtle" href="{{ route('admin.settings.template-preview', $template) }}" target="_blank">{{ __('Preview') }}</flux:button></div></article>
                    @empty<x-state.empty :title="__('No print templates yet')" :description="__('Add the first reusable print template.')" icon="document-text" />@endforelse
                </div>
                {{ $templates->links() }}
                @if(false)
                <?php
                    $printers = PrinterConfiguration::visibleTo(auth()->user())
                        ->with(['branch', 'store'])
                        ->orderBy('name')
                        ->limit(100)
                        ->get();
                    $printMappings = [
                        'default_thermal' => ['document' => 'Sales invoice', 'template' => 'Thermal sales invoice'],
                        'gift_receipt' => ['document' => 'Gift receipt', 'template' => 'Thermal gift receipt'],
                        'return_receipt' => ['document' => 'Sales return', 'template' => 'Thermal return receipt'],
                        'shift_closing' => ['document' => 'Shift closing', 'template' => 'Thermal shift closing receipt'],
                        'barcode_label' => ['document' => 'Barcode labels', 'template' => 'Barcode label'],
                    ];
                ?>

                <div class="rounded-2xl border border-cyan-300/20 bg-cyan-400/5 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg">{{ __('Print assignments') }}</flux:heading>
                            <flux:subheading>{{ __('Choose the document, a compatible template, and the printer that will output it.') }}</flux:subheading>
                        </div>
                        <flux:button size="sm" variant="primary" href="{{ route('admin.settings', ['tab' => 'printers', 'section' => 'printer-profiles']) }}">
                            {{ __('Manage printers') }}
                        </flux:button>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-3" aria-label="{{ __('Print setup path') }}">
                        <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-cyan-400/20 font-semibold text-cyan-200">1</span>
                            <div>
                                <p class="font-medium">{{ __('Document') }}</p>
                                <p class="text-xs text-text-muted">{{ __('What will be printed?') }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-cyan-400/20 font-semibold text-cyan-200">2</span>
                            <div>
                                <p class="font-medium">{{ __('Template') }}</p>
                                <p class="text-xs text-text-muted">{{ __('Layout and paper size') }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-cyan-400/20 font-semibold text-cyan-200">3</span>
                            <div>
                                <p class="font-medium">{{ __('Printer and location') }}</p>
                                <p class="text-xs text-text-muted">{{ __('Where will the document be printed?') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-700">
                    <flux:table class="min-w-[52rem]" aria-label="{{ __('Print assignments') }}">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Document') }}</flux:table.column>
                            <flux:table.column>{{ __('Template and paper size') }}</flux:table.column>
                            <flux:table.column>{{ __('Printer and location') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            <flux:table.column>{{ __('Action') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            <?php if ($printers->isNotEmpty()): ?>
                                <?php foreach ($printers as $printer): ?>
                                    <?php
                                        $mapping = $printMappings[(string) $printer->template_name] ?? ['document' => 'Operational document', 'template' => 'Approved template'];
                                        $paperSize = match (strtolower(trim((string) $printer->paper_size))) {
                                            '58mm', '58 mm' => __('58 mm'),
                                            '80mm', '80 mm' => __('80 mm'),
                                            'a4' => 'A4',
                                            default => (string) $printer->paper_size ?: __('Printer setting'),
                                        };
                                        $printerName = preg_match('/^default[-_]/i', (string) $printer->name)
                                            ? __('Default printer')
                                            : $printer->name;
                                        $location = $printer->store
                                            ? (str_starts_with(app()->getLocale(), 'ar') ? $printer->store->name_ar : $printer->store->name_en)
                                            : ($printer->branch
                                                ? (str_starts_with(app()->getLocale(), 'ar') ? $printer->branch->name_ar : $printer->branch->name_en)
                                                : __('Entire company'));
                                    ?>
                                    <flux:table.row key="printer-{{ $printer->id }}">
                                        <flux:table.cell class="font-medium">{{ __($mapping['document']) }}</flux:table.cell>
                                        <flux:table.cell>
                                            <div class="space-y-1">
                                                <div>{{ __($mapping['template']) }}</div>
                                                <div class="text-xs text-text-muted">{{ $paperSize }}</div>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <div class="space-y-1">
                                                <div class="font-medium">{{ $printerName }}</div>
                                                <div class="text-xs text-text-muted">{{ $location }}</div>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <div class="flex flex-wrap gap-1.5">
                                                <?php if ($printer->status === 'active'): ?>
                                                    <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                                <?php else: ?>
                                                    <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                                                <?php endif; ?>
                                                <?php if ($printer->is_default): ?>
                                                    <flux:badge size="sm" color="zinc">{{ __('Default') }}</flux:badge>
                                                <?php endif; ?>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <flux:button size="sm" variant="subtle" type="button" wire:click="editPrinter({{ $printer->id }})">
                                                {{ __('Edit assignment') }}
                                            </flux:button>
                                        </flux:table.cell>
                                    </flux:table.row>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <flux:table.row>
                                    <flux:table.cell colspan="5" class="py-8 text-center">
                                        <x-state.empty
                                            :title="__('No print assignments yet')"
                                            :description="__('Add a printer, then choose a compatible template for each document.')"
                                            icon="printer"
                                        />
                                    </flux:table.cell>
                                </flux:table.row>
                            <?php endif; ?>
                        </flux:table.rows>
                    </flux:table>
                </div>
                @endif
            </flux:card>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- TAB 6: Settings Audit Trail -->
    <?php if ($activeTab === 'audit'): ?>
        <div id="panel-audit" role="tabpanel" aria-labelledby="tab-audit" class="space-y-6">
            <div class="flex flex-wrap justify-end gap-2"><flux:button type="button" variant="subtle" wire:click="$set('activeTab', 'printers')">{{ str_starts_with(app()->getLocale(), 'ar') ? 'السابق →' : '← Previous' }}</flux:button><flux:button :href="route('initial-setup')" variant="primary" icon="check-circle" wire:navigate>{{ __('Finish setup review') }}</flux:button></div>
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('Configuration Change History') }}</flux:heading>
                        <flux:subheading>{{ __('Read-only history. Edit settings from the tabs above.') }}</flux:subheading>
                    </div>
                    <flux:badge size="sm" color="zinc" icon="shield-check">
                        {{ __('Settings history') }}
                    </flux:badge>
                </div>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:table class="min-w-[120rem]" aria-label="{{ __('Settings Audit Trail') }}">
                    <flux:table.columns>
                        <flux:table.column class="min-w-40 whitespace-nowrap">{{ __('Timestamp') }}</flux:table.column>
                        <flux:table.column class="min-w-56"><span class="block whitespace-normal leading-tight">{{ __('Correlation ID') }}</span></flux:table.column>
                        <flux:table.column class="min-w-36"><span class="block whitespace-normal leading-tight">{{ __('User') }}</span></flux:table.column>
                        <flux:table.column class="min-w-48"><span class="block whitespace-normal leading-tight">{{ __('Configuration area') }}</span></flux:table.column>
                        <flux:table.column class="min-w-48"><span class="block whitespace-normal leading-tight">{{ __('Field/key') }}</span></flux:table.column>
                        <flux:table.column class="min-w-72"><span class="block whitespace-normal leading-tight">{{ __('Previous value') }}</span></flux:table.column>
                        <flux:table.column class="min-w-72"><span class="block whitespace-normal leading-tight">{{ __('New value') }}</span></flux:table.column>
                        <flux:table.column class="min-w-52"><span class="block whitespace-normal leading-tight">{{ __('Reason') }}</span></flux:table.column>
                        <flux:table.column class="min-w-40"><span class="block whitespace-normal leading-tight">{{ __('Branch/company scope') }}</span></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        <?php
                            $settingSourceTypes = [Company::class, PaymentMethod::class, TaxSetting::class, DocumentSequence::class, PrinterConfiguration::class];
                            $settingAuditLogs = AuditLog::visibleTo(auth()->user())
                                ->where('category', 'master_data')
                                ->where(function ($query) use ($settingSourceTypes): void {
                                    $query->whereIn('source_type', $settingSourceTypes)
                                        ->orWhere('source_type', 'like', 'legacy_settings:%');
                                })
                                ->latest('id')
                                ->take(20)
                                ->get();
                        ?>
                        <?php if ($settingAuditLogs->isNotEmpty()): ?>
                            <?php foreach ($settingAuditLogs as $log): ?>
                            <?php
                                $before = is_array($log->before_values) ? $log->before_values : [];
                                $after = is_array($log->after_values) ? $log->after_values : [];
                                $fields = is_array($log->changed_fields) && $log->changed_fields !== []
                                    ? $log->changed_fields
                                    : array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
                                $area = [
                                    Company::class => __('Company identity'),
                                    PaymentMethod::class => __('Payment methods'),
                                    TaxSetting::class => __('Tax rules'),
                                    DocumentSequence::class => __('Document numbering'),
                                    PrinterConfiguration::class => __('Printer profiles'),
                                ][$log->source_type] ?? __('Configuration');
                                $scope = $log->branch_id
                                    ? __('Branch').' #'.$log->branch_id
                                    : ($log->store_id ? __('Store').' #'.$log->store_id : __('Global/company'));
                                $reason = trim((string) ($log->reason_text ?: $log->reason_code));
                                $beforeText = json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                $afterText = json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            ?>
                            <flux:table.row key="log-{{ $log->id }}" class="align-top">
                                <flux:table.cell class="align-top whitespace-nowrap font-mono text-xs" dir="ltr">{{ $log->created_at->format('Y-m-d H:i:s') }}</flux:table.cell>
                                <flux:table.cell class="align-top min-w-56 whitespace-normal break-all font-mono text-xs leading-relaxed" dir="ltr" title="{{ $log->request_id }}">{{ $log->request_id ?: __('Not recorded') }}</flux:table.cell>
                                <flux:table.cell class="align-top min-w-36 whitespace-normal break-words text-xs leading-relaxed">{{ $log->actor_name ?? __('System') }}</flux:table.cell>
                                <flux:table.cell class="align-top min-w-48 whitespace-normal text-xs leading-relaxed"><flux:badge size="sm" class="bg-primary-soft text-primary">{{ $area }}</flux:badge><div class="mt-1 break-words text-xs leading-relaxed text-text-muted">{{ $log->event }}</div></flux:table.cell>
                                <flux:table.cell class="align-top min-w-48 whitespace-normal break-words text-xs leading-relaxed" title="{{ implode(', ', $fields) }}">{{ implode(', ', $fields) ?: __('Not recorded') }}</flux:table.cell>
                                <flux:table.cell class="align-top min-w-72 max-w-80 whitespace-normal break-all font-mono text-xs leading-relaxed" dir="ltr" title="{{ $beforeText }}">{{ $beforeText ?: __('Not recorded') }}</flux:table.cell>
                                <flux:table.cell class="align-top min-w-72 max-w-80 whitespace-normal break-all font-mono text-xs leading-relaxed" dir="ltr" title="{{ $afterText }}">{{ $afterText ?: __('Not recorded') }}</flux:table.cell>
                                <flux:table.cell class="align-top min-w-52 whitespace-normal break-words text-xs leading-relaxed" title="{{ $reason }}">{{ $reason ?: __('No reason recorded') }}</flux:table.cell>
                                <flux:table.cell class="align-top min-w-40 whitespace-normal break-words text-xs leading-relaxed">{{ $scope }}</flux:table.cell>
                            </flux:table.row>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <flux:table.row>
                                <flux:table.cell colspan="9" class="text-center py-4">
                                    <x-state.empty
                                        :title="__('No Audit Logs Recorded')"
                                        :description="__('Setting changes will appear here. This history is read-only.')"
                                        icon="shield-check"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        <?php endif; ?>
                    </flux:table.rows>
                </flux:table>
                </div>
            </flux:card>
        </div>
    <?php endif; ?>
    </div>
</x-app.page>

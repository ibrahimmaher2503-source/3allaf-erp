<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Customer\Actions\ApproveLoyaltyAdjustmentAction;
use App\Modules\Customer\Actions\CreateCustomerAction;
use App\Modules\Customer\Actions\CreateCustomerGroupAction;
use App\Modules\Customer\Actions\ExpireLoyaltyAction;
use App\Modules\Customer\Actions\ImportCustomerGroups;
use App\Modules\Customer\Actions\MergeCustomersAction;
use App\Modules\Customer\Actions\PostPartyWalletEntryAction;
use App\Modules\Customer\Actions\PostProductWalletEntryAction;
use App\Modules\Customer\Actions\RecordCustomerConsentAction;
use App\Modules\Customer\Actions\RedeemLoyaltyAction;
use App\Modules\Customer\Actions\RejectLoyaltyAdjustmentAction;
use App\Modules\Customer\Actions\RequestLoyaltyAdjustmentAction;
use App\Modules\Customer\Actions\RequestPartyWalletAdjustmentAction;
use App\Modules\Customer\Actions\RequestProductWalletAdjustmentAction;
use App\Modules\Customer\Actions\StageCustomerImportAction;
use App\Modules\Customer\Actions\UpdateCustomerAction;
use App\Modules\Customer\Actions\UpdateCustomerGroupAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerConsent;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Customer\Models\CustomerImportBatch;
use App\Modules\Customer\Models\LoyaltyAdjustment;
use App\Modules\Customer\Models\LoyaltyLedger;
use App\Modules\Customer\Models\PartyWalletAdjustment;
use App\Modules\Customer\Models\PartyWalletLedger;
use App\Modules\Customer\Models\ProductWalletAdjustment;
use App\Modules\Customer\Models\ProductWalletLedger;
use App\Modules\Customer\Support\CustomerBalance;
use App\Modules\Customer\Support\CustomerPolicy;
use App\Modules\Customer\Support\PartyWalletBalance;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Customer\Support\ProductWalletBalance;
use App\Modules\Customer\Support\WalletPolicy;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\City;
use App\Modules\Platform\Models\Governorate;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Actions\AssignCustomerPriceListAction;
use App\Modules\Pricing\Actions\SaveCustomerProductPriceAction;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Services\PriceListResolver;
use App\Modules\Retail\Models\Sale;
use App\Modules\Reporting\Queries\SalesReport;
use App\Support\DataExchange\ImportTemplateFactory;
use App\Support\DataExchange\MasterDataDocument;
use App\Support\Hierarchy\GroupHierarchy;
use App\Support\UserSafeError;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

Route::middleware(['auth', 'verified'])->group(function (): void {
    $sellingStore = static function (User $user): Store {
        return Store::query()->visibleTo($user)->where('type', 'selling')->where('status', 'active')->orderBy('id')->firstOrFail();
    };

    Route::get('customers/import', function (Request $request) {
        $user = $request->user();
        $canReview = $user->can('customers.import.approve');
        abort_unless($user->can('customers.create') || $canReview, 403);
        $companyIds = Store::query()->visibleTo($user)->select('company_id');
        $batches = CustomerImportBatch::query()->whereIn('company_id', $companyIds)->where(function ($query) use ($user, $canReview): void {
            $query->where('created_by', $user->id);
            if ($canReview) {
                $query->orWhere('status', 'ready_for_review');
            }
        });
        if ($request->integer('batch') > 0 && ! (clone $batches)->whereKey($request->integer('batch'))->exists()) {
            abort(404);
        }

        return view('pages.customers.import', ['batches' => $batches->latest()->paginate(10)]);
    })->name('customers.import');
    Route::get('customers/import/template', function (Request $request, ImportTemplateFactory $templates) {
        abort_unless($request->user()->can('customers.create'), 403);

        return $templates->customers($request->user(), $request->input('company_id'));
    })->middleware('can:customers.create')->name('customers.import.template');
    Route::post('customers/import', function (Request $request, StageCustomerImportAction $action) use ($sellingStore) {
        $user = $request->user();
        abort_unless($user->can('customers.create'), 403);
        $data = $request->validate(['import_file' => ['required', 'file', 'mimes:xlsx', 'max:10240'], 'mode' => ['required', Rule::in(['create_only', 'update_existing'])]]);
        if ($data['mode'] === 'update_existing') {
            abort_unless($user->can('customers.edit'), 403);
        }
        $store = $sellingStore($user);
        try {
            $rows = StageCustomerImportAction::readSpreadsheet($request->file('import_file')->getRealPath(), (string) $store->company()->value('code'));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['import_file' => UserSafeError::message($exception)]);
        }
        $batch = $action->stage($rows, $request->file('import_file')->getClientOriginalName(), $data['mode'], $user->id, $store);

        return to_route('customers.import')->with('success', __('Customer file staged. Review errors before approval.'))->with('batch_id', $batch->id);
    })->middleware('can:customers.create')->name('customers.import.stage');
    Route::get('customers/import/{batch}/rejections', function (Request $request, CustomerImportBatch $batch, MasterDataDocument $documents) use ($sellingStore) {
        $store = $sellingStore($request->user());
        abort_unless((int) $batch->company_id === (int) $store->company_id && ((int) $batch->created_by === (int) $request->user()->id || $request->user()->can('customers.import.approve')), 404);
        $headers = [__('Original row'), ...StageCustomerImportAction::FIELDS, __('Rejection reason')];
        $rows = $batch->rows()->where('status', 'invalid')->orderBy('row_number')->get()->map(fn ($row) => [$row->row_number, ...array_map(fn ($field) => $row->raw_data[$field] ?? '', StageCustomerImportAction::FIELDS), collect($row->errors)->join(' | ')]);

        return $documents->xlsx('customer-import-rejections-'.$batch->id.'.xlsx', $headers, $rows);
    })->name('customers.import.rejections');
    Route::post('customers/import/{batch}/approve', function (Request $request, CustomerImportBatch $batch, CreateCustomerAction $creator, UpdateCustomerAction $updater) {
        $user = $request->user();
        abort_unless($user->can('customers.import.approve'), 403);
        abort_unless($batch->status === 'ready_for_review' && $batch->valid_rows > 0, 422);
        abort_if((int) $batch->created_by === (int) $user->id, 422, __('The requester cannot approve their own import batch.'));
        $store = Store::query()->visibleTo($user)->where('company_id', $batch->company_id)->where('type', 'selling')->where('status', 'active')->orderBy('id')->firstOrFail();
        DB::transaction(function () use ($batch, $creator, $updater, $user, $store) {
            foreach ($batch->rows()->where('status', 'valid')->lockForUpdate()->get() as $row) {
                $d = $row->mapped_data;
                $data = ['first_name_ar' => $d['first_name_ar'], 'last_name_ar' => $d['last_name_ar'], 'first_name_en' => $d['first_name_en'], 'last_name_en' => $d['last_name_en'], 'phone' => $d['phone'], 'secondary_phone' => $d['secondary_phone'] ?? null, 'email' => $d['email'], 'customer_group_id' => $d['customer_group_id'] ?? null, 'governorate_id' => $d['governorate_id'] ?? null, 'city_id' => $d['city_id'] ?? null, 'address_ar' => $d['address_ar'] ?? null, 'address_en' => $d['address_en'] ?? null];
                if ($batch->mode === 'update_existing') {
                    $customer = $updater->execute($user, Customer::query()->whereKey($d['customer_id'])->where('phone_normalized', PhoneNormalizer::normalize($d['phone']))->firstOrFail(), $store, $data);
                    $status = 'updated';
                } else {
                    $customer = $creator->execute($user, $store, $data + ['consents' => [['purpose' => $d['consent_purpose'], 'status' => $d['consent_status'], 'source' => 'customer_import']], 'idempotency_key' => 'customer-import:'.$batch->id.':'.$row->id]);
                    $status = 'created';
                }$row->update(['status' => $status, 'customer_id' => $customer->id]);
            }$batch->update(['status' => 'completed', 'added_rows' => $batch->valid_rows, 'approved_at' => now()]);
        });

        return to_route('customers.import')->with('success', __('Customer import approved and written.'));
    })->middleware('can:customers.import.approve')->name('customers.import.approve');
    Route::get('customers/groups', function (Request $request) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.view'), 403);
        $store = $sellingStore($user);
        $term = trim((string) $request->string('q'));
        $allGroups = CustomerGroup::query()
            ->forCompany((int) $store->company_id)
            ->with('parent:id,name_ar,name_en')
            ->withCount(['customers as active_customers_count' => fn ($query) => $query->where('status', 'active')])
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->string('status')))
            ->when($request->string('hierarchy')->value() === 'root', fn ($query) => $query->whereNull('parent_id'))
            ->when($request->string('hierarchy')->value() === 'leaf', fn ($query) => $query->whereDoesntHave('children'))
            ->get();
        $groups = GroupHierarchy::flatten($allGroups, $term);
        $parentOptions = GroupHierarchy::flatten(CustomerGroup::query()->forCompany((int) $store->company_id)->active()->get());

        return view('pages.customers.groups', compact('groups', 'parentOptions', 'term', 'store'));
    })->middleware('can:customers.view')->name('customers.groups.index');
    Route::get('customers/groups/export/{format}', function (Request $request, string $format, MasterDataDocument $documents) use ($sellingStore) {
        abort_unless($request->user()->can('customers.export') && in_array($format, ['xlsx', 'pdf'], true), 403);
        $store = $sellingStore($request->user());
        $source = CustomerGroup::query()->forCompany((int) $store->company_id)->withCount('customers')
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->string('status')))
            ->when($request->string('hierarchy')->value() === 'root', fn ($query) => $query->whereNull('parent_id'))
            ->when($request->string('hierarchy')->value() === 'leaf', fn ($query) => $query->whereDoesntHave('children'))
            ->get();
        $groups = GroupHierarchy::flatten($source, (string) $request->string('q'));
        $headers = [__('Code'), __('Arabic name'), __('English name'), __('Path'), __('Order'), __('Status'), __('Customers')];
        $rows = $groups->map(fn ($g) => [$g->code, $g->name_ar, $g->name_en, $g->hierarchy_path, $g->sort_order, __($g->status), $g->customers_count]);

        return $format === 'xlsx' ? $documents->xlsx('customer-groups.xlsx', $headers, $rows) : $documents->pdf('customer-groups.pdf', __('Customer groups'), $headers, $rows);
    })->middleware('can:customers.export')->name('customers.groups.export');
    Route::get('customers/groups/import/template', fn (Request $request, ImportTemplateFactory $templates) => $templates->customerGroups($request->user(), $request->input('company_id')))->middleware('can:customers.edit')->name('customers.groups.import.template');
    Route::post('customers/groups/import', function (Request $request, ImportCustomerGroups $action, MasterDataDocument $documents) use ($sellingStore) {
        $data = $request->validate(['import_file' => 'required|file|mimes:xlsx|max:10240']);
        try {
            $result = $action->execute($request->user(), $sellingStore($request->user()), $request->file('import_file')->getRealPath());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['import_file' => UserSafeError::message($e)]);
        }if ($result['rejected'] !== []) {
            return $documents->xlsx('customer-group-rejections.xlsx', [__('Original row'), ...ImportCustomerGroups::HEADERS, __('Rejection reason')], $result['rejected']);
        }

        return back()->with('success', __('Customer groups imported: :count', ['count' => $result['added']]));
    })->middleware('can:customers.edit')->name('customers.groups.import');

    Route::post('customers/groups', function (Request $request, CreateCustomerGroupAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.edit'), 403);
        $validated = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['required', 'string', 'max:190'],
            'code' => ['required', 'alpha_dash:ascii', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'parent_id' => ['nullable', 'integer'],
        ]);
        try {
            $action->execute($user, $sellingStore($user), $validated);
        } catch (InvalidArgumentException|UniqueConstraintViolationException $exception) {
            return back()->withInput()->withErrors(['group' => UserSafeError::message($exception)]);
        }

        return to_route('customers.groups.index')->with('success', __('Customer group created.'));
    })->middleware('can:customers.edit')->name('customers.groups.store');

    Route::put('customers/groups/{groupId}', function (Request $request, int $groupId, UpdateCustomerGroupAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.edit'), 403);
        $store = $sellingStore($user);
        $group = CustomerGroup::query()->forCompany((int) $store->company_id)->whereKey($groupId)->firstOrFail();
        $validated = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['required', 'string', 'max:190'],
            'code' => ['required', 'alpha_dash:ascii', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'parent_id' => ['nullable', 'integer'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        try {
            $action->execute($user, $group, $store, $validated);
        } catch (InvalidArgumentException|UniqueConstraintViolationException $exception) {
            return back()->withInput()->withErrors(['group' => UserSafeError::message($exception)]);
        }

        return to_route('customers.groups.index')->with('success', __('Customer group updated.'));
    })->middleware('can:customers.edit')->name('customers.groups.update');

    Route::get('customers', function (Request $request) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.view'), 403);
        $mode = (string) $request->string('mode', 'master');
        abort_unless(in_array($mode, ['master', 'history', 'loyalty'], true), 404);
        abort_if($mode === 'loyalty' && ! $user->can('loyalty.view'), 403);

        $store = $sellingStore($user);
        $query = Customer::query()->visibleTo($user)->with(['scopes.store', 'scopes.branch', 'group.parent', 'governorate', 'city'])->withCount('consents');
        $term = trim((string) $request->string('q'));
        if ($term !== '') {
            $digits = preg_replace('/[^0-9]+/', '', $term);
            $customerCodeId = preg_match('/^CUS-0*(\d+)$/i', $term, $match) ? (int) $match[1] : null;
            $query->where(function ($scope) use ($term, $digits, $customerCodeId): void {
                $scope->where('name_ar', 'like', '%'.$term.'%')
                    ->orWhere('name_en', 'like', '%'.$term.'%');
                if ($customerCodeId) {
                    $scope->orWhereKey($customerCodeId);
                }
                if (is_string($digits) && $digits !== '') {
                    $scope->orWhere('phone_normalized', 'like', '%'.$digits.'%');
                }
            });
        }
        $query->when($request->filled('status'), fn ($builder) => $builder->where('status', (string) $request->string('status')));
        $groupId = $request->integer('group_id') ?: null;
        $query->when($groupId !== null, fn ($builder) => $builder->where('customer_group_id', $groupId));
        $query->when($request->integer('governorate_id') > 0, fn ($builder) => $builder->where('governorate_id', $request->integer('governorate_id')));
        $query->when($request->integer('city_id') > 0, fn ($builder) => $builder->where('city_id', $request->integer('city_id')));
        $perPage = in_array($request->integer('per_page'), [20, 50, 100], true) ? $request->integer('per_page') : 20;
        $customers = $query->latest('id')->paginate($perPage)->withQueryString();
        $canViewCustomerAccounts = $user->can('pos_sales.view') && $user->can('pos_sales.payment_view');
        $currencyCode = strtoupper((string) $store->company()->value('currency_code'));
        $customerAccounts = $canViewCustomerAccounts
            ? app(CustomerBalance::class)->summaryForCustomers($customers->pluck('id')->all(), $user, $currencyCode)
            : collect();
        $groupOptions = GroupHierarchy::flatten(CustomerGroup::query()->forCompany((int) $store->company_id)->active()->with('parent')->get());
        $governorates = Governorate::query()->active()->orderBy('sort_order')->get();
        $cities = City::query()->visibleToCompany((int) $store->company_id)->active()->orderBy('sort_order')->get();

        $status = (string) $request->string('status');
        $governorateId = $request->integer('governorate_id') ?: null;
        $cityId = $request->integer('city_id') ?: null;

        return view('pages.customers.index', compact('customers', 'term', 'mode', 'status', 'groupId', 'governorateId', 'cityId', 'groupOptions', 'governorates', 'cities', 'canViewCustomerAccounts', 'currencyCode', 'customerAccounts'));
    })->middleware('can:customers.view')->name('customers.index');

    Route::get('customers/export', function (Request $request, MasterDataDocument $documents) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.export'), 403);
        $store = $sellingStore($user);
        $term = trim((string) $request->string('q'));
        $query = Customer::query()->visibleTo($user)->with(['group.parent', 'governorate', 'city'])->limit(5000);
        if ($term !== '') {
            $digits = preg_replace('/[^0-9]+/', '', $term);
            $query->where(function ($scope) use ($term, $digits): void {
                $scope->where('name_ar', 'like', '%'.$term.'%')->orWhere('name_en', 'like', '%'.$term.'%');
                if (is_string($digits) && $digits !== '') {
                    $scope->orWhere('phone_normalized', 'like', '%'.$digits.'%');
                }
            });
        }
        $query->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->string('status')))->when($request->integer('group_id') > 0, fn ($q) => $q->where('customer_group_id', $request->integer('group_id')))->when($request->integer('governorate_id') > 0, fn ($q) => $q->where('governorate_id', $request->integer('governorate_id')))->when($request->integer('city_id') > 0, fn ($q) => $q->where('city_id', $request->integer('city_id')));
        $customers = $query->orderBy('id')->get();
        app(RecordAuditEvent::class)->execute(category: 'reporting', event: 'customer_exported', branchId: (int) $store->branch_id, storeId: (int) $store->id, metadata: ['row_count' => $customers->count(), 'filter' => $term, 'permission' => 'customers.export', 'company_scoped' => true]);

        $headers = [__('Reference'), __('Phone'), __('Arabic name'), __('English name'), __('Email'), __('Group path'), __('Governorate'), __('City / locality'), __('Status')];
        $rows = $customers->map(fn ($c) => [$c->public_id, $c->phone_display, $c->name_ar, $c->name_en, $c->email, collect([$c->group?->parent?->name_ar, $c->group?->name_ar])->filter()->join(' / '), $c->governorate?->name_ar, $c->city?->name_ar, __($c->status)]);

        return $request->string('format')->value() === 'pdf' ? $documents->pdf('customers.pdf', __('Customers'), $headers, $rows) : $documents->xlsx('customers.xlsx', $headers, $rows);
    })->middleware('can:customers.export')->name('customers.export');

    Route::get('customers/create', function (Request $request) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.create'), 403);
        $store = $sellingStore($user);
        $consentPurposes = [];
        $consentPolicyError = null;
        try {
            $consentPurposes = CustomerPolicy::allowedPurposes('customer.consent.purpose')['value'];
        } catch (InvalidArgumentException $exception) {
            $consentPolicyError = UserSafeError::message($exception);
        }
        $allGroups = CustomerGroup::query()->forCompany((int) $store->company_id)->active()->with('parent')->get();
        $groupOptions = GroupHierarchy::flatten($allGroups);
        $governorates = Governorate::query()->active()->orderBy('sort_order')->get();
        $cities = City::query()->visibleToCompany((int) $store->company_id)->active()->orderBy('sort_order')->get();

        return view('pages.customers.create', compact('store', 'consentPurposes', 'consentPolicyError', 'groupOptions', 'governorates', 'cities'));
    })->middleware('can:customers.create')->name('customers.create');

    Route::post('customers', function (Request $request, CreateCustomerAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.create'), 403);
        $validated = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'phone' => ['nullable', 'string', 'max:64', PhoneNormalizer::validationRule()],
            'first_name_ar' => ['required_without_all:name_ar,name_en', 'nullable', 'string', 'max:190'],
            'last_name_ar' => ['required_without_all:name_ar,name_en', 'nullable', 'string', 'max:190'],
            'first_name_en' => ['nullable', 'string', 'max:190'],
            'last_name_en' => ['nullable', 'string', 'max:190'],
            'name_ar' => ['nullable', 'string', 'max:190'],
            'name_en' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'secondary_phone' => ['nullable', 'string', 'max:64', PhoneNormalizer::validationRule()],
            'address_ar' => ['nullable', 'string', 'max:4000'],
            'address_en' => ['nullable', 'string', 'max:4000'],
            'customer_group_id' => ['nullable', 'integer'],
            'customer_type' => ['nullable', 'in:cash,credit,both'],
            'credit_limit' => ['nullable', 'decimal:0,4', 'min:0'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'governorate_id' => ['required', 'integer'],
            'city_id' => ['required', 'integer'],
        ]);

        $store = $sellingStore($user);
        try {
            $customer = $action->execute($user, $store, $validated + [
                'consents' => [[
                    'purpose' => 'service_delivery',
                    'status' => 'granted',
                    'source' => 'profile_create',
                ]],
            ]);
        } catch (InvalidArgumentException|UniqueConstraintViolationException $exception) {
            $normalizedPhone = PhoneNormalizer::normalize((string) ($validated['phone'] ?? ''));
            $candidate = Customer::query()
                ->visibleFrom($user, (int) $store->branch_id, (int) $store->id)
                ->where('status', 'active')
                ->where(function ($query) use ($normalizedPhone, $validated): void {
                    $query->where('phone_normalized', $normalizedPhone);
                    if (filled($validated['email'] ?? null)) {
                        $query->orWhereRaw('LOWER(email) = ?', [strtolower(trim((string) $validated['email']))]);
                    }
                })
                ->first(['id', 'name_ar', 'name_en', 'phone_display']);

            return back()->withInput()
                ->withErrors(['customer' => UserSafeError::message($exception)])
                ->with('duplicate_candidate', $candidate?->toArray());
        }

        return to_route('customers.show', $customer)->with('success', __('Customer profile created.'));
    })->middleware('can:customers.create')->name('customers.store');

    Route::get('customers/{customerId}', function (Request $request, int $customerId) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.view'), 403);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $store = Store::query()->visibleTo($user)->with('company')->where('status', 'active')->find($customer->created_store_id)
            ?? $sellingStore($user)->load('company');
        $customer->load(['scopes.store', 'scopes.branch', 'group.parent', 'governorate', 'city', 'priceList']);
        $historyIds = Customer::query()->where(fn ($query) => $query->whereKey($customer->id)->orWhere('merged_into_id', $customer->id))->pluck('id');
        $consents = collect();
        if ($user->can('customers.sensitive')) {
            $consents = CustomerConsent::query()->visibleTo($user)->with('capturer')->whereIn('customer_id', $historyIds)->latest('id')->get();
        }
        $canViewSalesAnalysis = $user->can('dashboard_reports.view') && $user->can('pos_sales.view') && $user->can('pos_sales.payment_view');
        $salesAnalysis = $canViewSalesAnalysis ? app(SalesReport::class)->customer($user, collect($historyIds)->all(), $request->only(['date_from', 'date_to', 'store_id', 'product_id', 'category_id', 'payment_method_id', 'sales_page'])) : null;
        $salesStores = $canViewSalesAnalysis ? Store::query()->visibleTo($user)->where('status', 'active')->orderBy('name_en')->get(['id', 'name_ar', 'name_en']) : collect();
        $salesProducts = $canViewSalesAnalysis ? Product::query()->where('status', 'active')->orderBy('item_code')->limit(200)->get(['id', 'item_code', 'name_ar', 'name_en']) : collect();
        $salesCategories = $canViewSalesAnalysis ? Category::query()->where('status', 'active')->orderBy('name_en')->limit(200)->get(['id', 'name_ar', 'name_en']) : collect();
        $salesPaymentMethods = $canViewSalesAnalysis && $user->can('pos_sales.payment_view') ? PaymentMethod::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']) : collect();
        $partyBookings = $user->can('party_bookings_invoices.view')
            ? PartyBooking::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->with(['store', 'invoice'])->latest('party_date')->paginate(8, ['*'], 'party_page')->withQueryString()
            : null;
        $balance = (int) LoyaltyLedger::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->sum('points');
        $dueExpiry = (int) LoyaltyLedger::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->where('points', '>', 0)->whereNotNull('expires_at')->where('expires_at', '<=', now())->sum('points');
        $adjustments = $user->can('loyalty.view') ? LoyaltyAdjustment::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->latest('id')->limit(10)->get() : collect();
        $productWalletBalance = $user->can('product_wallet.view') ? app(ProductWalletBalance::class)->forCustomer($customer, $user) : null;
        $partyWalletBalance = $user->can('party_wallet.view') ? app(PartyWalletBalance::class)->forCustomer($customer, $user) : null;
        $allGroups = CustomerGroup::query()->forCompany((int) $store->company_id)->active()->with('parent')->get();
        $groupOptions = GroupHierarchy::flatten($allGroups);
        if ($customer->group && ! $groupOptions->contains('id', $customer->group->id)) {
            $groupOptions->prepend($customer->group);
        }
        $governorates = Governorate::query()->active()->orderBy('sort_order')->get();
        $cities = City::query()->visibleToCompany((int) $store->company_id)->active()->orderBy('sort_order')->get();
        $currencyCode = strtoupper((string) $store->company?->currency_code);
        $customerBalance = app(CustomerBalance::class);
        $arInvoices = $customerBalance->outstandingInvoices($customer, $user, $store->company, $currencyCode);
        $arOutstanding = $arInvoices->reduce(fn (string $total, Sale $sale): string => bcadd($total, (string) $sale->current_outstanding, 4), '0.0000');
        $unappliedCredit = $customerBalance->unappliedCreditFor($customer, $store->company, $currencyCode);
        $currentBalance = $customerBalance->for($customer, $currencyCode);
        $availableCredit = $customer->credit_limit === null
            ? null
            : bcsub((string) $customer->credit_limit, bccomp($currentBalance, '0', 4) > 0 ? $currentBalance : '0.0000', 4);
        if ($availableCredit !== null && bccomp($availableCredit, '0', 4) < 0) {
            $availableCredit = '0.0000';
        }
        $priceLists = PriceList::query()->where('company_id', $store->company_id)->where('status', 'active')->orderBy('list_number')->get();
        $specialPrices = $customer->specialPrices()->with(['product', 'productUnit.unit'])->latest('id')->limit(50)->get();
        try {
            $normalList = $customer->priceList ?: app(PriceListResolver::class)->listForOutlet($store);
        } catch (Throwable) {
            $normalList = null;
        }
        $specialPrices->each(function ($special) use ($normalList): void {
            try {
                $special->setAttribute('normal_customer_price', $normalList ? app(PriceListResolver::class)->resolveForUnit($special->product, $special->productUnit, $normalList)->finalPrice : null);
            } catch (Throwable) {
                $special->setAttribute('normal_customer_price', null);
            }
        });
        $sellingUnits = ProductUnit::query()->with(['product', 'unit'])->where('is_sale_unit', true)->whereHas('product', fn ($query) => $query->sellable())->orderBy('product_id')->limit(100)->get();

        return view('pages.customers.show', compact('customer', 'store', 'salesAnalysis', 'salesStores', 'salesProducts', 'salesCategories', 'salesPaymentMethods', 'partyBookings', 'balance', 'dueExpiry', 'adjustments', 'consents', 'productWalletBalance', 'partyWalletBalance', 'groupOptions', 'governorates', 'cities', 'currencyCode', 'arInvoices', 'arOutstanding', 'unappliedCredit', 'currentBalance', 'availableCredit', 'priceLists', 'specialPrices', 'sellingUnits'));
    })->middleware('can:customers.view')->name('customers.show');

    Route::put('customers/{customerId}', function (Request $request, int $customerId, UpdateCustomerAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.edit'), 403);
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:64', PhoneNormalizer::validationRule()],
            'first_name_ar' => ['required_without_all:name_ar,name_en', 'nullable', 'string', 'max:190'],
            'last_name_ar' => ['required_without_all:name_ar,name_en', 'nullable', 'string', 'max:190'],
            'first_name_en' => ['nullable', 'string', 'max:190'],
            'last_name_en' => ['nullable', 'string', 'max:190'],
            'name_ar' => ['nullable', 'string', 'max:190'],
            'name_en' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'secondary_phone' => ['nullable', 'string', 'max:64', PhoneNormalizer::validationRule()],
            'address_ar' => ['nullable', 'string', 'max:4000'],
            'address_en' => ['nullable', 'string', 'max:4000'],
            'customer_group_id' => ['nullable', 'integer'],
            'customer_type' => ['nullable', 'in:cash,credit,both'],
            'credit_limit' => ['nullable', 'decimal:0,4', 'min:0'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'governorate_id' => ['nullable', 'required_with:city_id', 'integer'],
            'city_id' => ['nullable', 'required_with:governorate_id', 'integer'],
        ]);
        $store = $sellingStore($user);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        try {
            $saved = $action->execute($user, $customer, $store, $validated);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['customer' => UserSafeError::message($exception)]);
        }

        return to_route('customers.show', $saved)->with('success', __('Customer profile updated.'));
    })->middleware('can:customers.edit')->name('customers.update');

    Route::post('customers/{customerId}/pricing/list', function (Request $request, int $customerId, AssignCustomerPriceListAction $action) {
        $data = $request->validate(['price_list_id' => ['nullable', 'integer']]);
        $customer = Customer::query()->visibleTo($request->user())->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $list = filled($data['price_list_id'] ?? null) ? PriceList::query()->findOrFail((int) $data['price_list_id']) : null;
        $action->execute($request->user(), $customer, $list);

        return back()->with('success', __('تم تحديث نوع سعر العميل.'));
    })->middleware('can:pricing_lists.assign')->name('customers.pricing.list');

    Route::post('customers/{customerId}/pricing/special', function (Request $request, int $customerId, SaveCustomerProductPriceAction $action) {
        $data = $request->validate(['product_unit_id' => ['required', 'integer'], 'price' => ['required', 'decimal:0,4', 'gt:0'], 'reason' => ['required', 'string', 'max:2000'], 'effective_from' => ['nullable', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from']]);
        $customer = Customer::query()->visibleTo($request->user())->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $unit = ProductUnit::query()->with('product')->findOrFail((int) $data['product_unit_id']);
        $action->execute($request->user(), $customer, $unit->product, $unit, (string) $data['price'], (string) $data['reason'], $data['effective_from'] ?? null, $data['effective_to'] ?? null);

        return back()->with('success', __('تم حفظ السعر الخاص للعميل.'));
    })->middleware('can:pricing_lists.overrides')->name('customers.pricing.special');

    Route::delete('customers/{customerId}/pricing/special/{specialId}', function (Request $request, int $customerId, int $specialId, SaveCustomerProductPriceAction $action) {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $customer = Customer::query()->visibleTo($request->user())->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $special = $customer->specialPrices()->whereKey($specialId)->where('status', 'active')->with(['product', 'productUnit'])->firstOrFail();
        $action->execute($request->user(), $customer, $special->product, $special->productUnit, null, (string) $data['reason']);

        return back()->with('success', __('تم إنهاء السعر الخاص مع الاحتفاظ بالتاريخ.'));
    })->middleware('can:pricing_lists.overrides')->name('customers.pricing.special.expire');

    Route::post('customers/{customerId}/consents', function (Request $request, int $customerId, RecordCustomerConsentAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.sensitive'), 403);
        $validated = $request->validate(['purpose' => ['required', 'string', 'max:80'], 'status' => ['required', Rule::in(['granted', 'withdrawn', 'denied'])], 'idempotency_key' => ['required', 'uuid']]);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        try {
            $action->execute($user, $customer, $sellingStore($user), $validated['purpose'], $validated['status'], 'profile', 'CONSENT:'.$validated['idempotency_key']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['consent' => UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Consent history recorded.'));
    })->middleware('can:customers.sensitive')->name('customers.consents.store');

    Route::post('customers/{customerId}/merge', function (Request $request, int $customerId, MergeCustomersAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('customers.merge'), 403);
        $validated = $request->validate(['survivor_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $store = $sellingStore($user);
        $duplicate = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $survivor = Customer::query()->visibleTo($user)->whereKey($validated['survivor_id'])->where('status', 'active')->firstOrFail();
        try {
            $merged = $action->execute($user, $duplicate, $survivor, $store, $validated['reason'], 'MERGE:'.$validated['idempotency_key']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['merge' => UserSafeError::message($exception)]);
        }

        return to_route('customers.show', $merged)->with('success', __('Customer profiles merged with history preserved.'));
    })->middleware('can:customers.merge')->name('customers.merge');

    Route::get('customers/{customerId}/loyalty', function (Request $request, int $customerId) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('loyalty.view'), 403);
        $store = $sellingStore($user);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $historyIds = Customer::query()->where(fn ($query) => $query->whereKey($customer->id)->orWhere('merged_into_id', $customer->id))->pluck('id');
        $entries = LoyaltyLedger::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->latestFirst()->paginate(20)->withQueryString();
        $balance = (int) LoyaltyLedger::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->sum('points');
        $dueExpiry = (int) LoyaltyLedger::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->where('points', '>', 0)->whereNotNull('expires_at')->where('expires_at', '<=', now())->sum('points');
        $adjustments = LoyaltyAdjustment::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->with('approvalRecord')->latest('id')->limit(20)->get();
        $approvedSales = Sale::query()->visibleTo($user)->whereIn('customer_id', $historyIds)->approved()->latest('approved_at')->limit(20)->get(['id', 'document_number', 'approved_at', 'total']);
        $pendingApprovals = $user->can('loyalty.approve')
            ? ApprovalRecord::query()->visibleTo($user)->where('source_type', 'loyalty_adjustments')->whereIn('source_id', $adjustments->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all())->where('approval_state', 'pending')->where('decision_permission', 'loyalty.approve')->latest('id')->limit(20)->get()
            : collect();

        return view('pages.customers.loyalty', compact('customer', 'store', 'entries', 'balance', 'dueExpiry', 'adjustments', 'approvedSales', 'pendingApprovals'));
    })->middleware('can:loyalty.view')->name('customers.loyalty');

    Route::get('customers/{customerId}/loyalty/export', function (Request $request, int $customerId) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('loyalty.export'), 403);
        $store = $sellingStore($user);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $historyIds = Customer::query()->where(fn ($query) => $query->whereKey($customer->id)->orWhere('merged_into_id', $customer->id))->pluck('id');
        $rows = LoyaltyLedger::query()
            ->visibleTo($user)
            ->whereIn('customer_id', $historyIds)
            ->latestFirst()
            ->limit(500)
            ->get(['customer_id', 'activity', 'event_type', 'points', 'balance_before', 'balance_after', 'effective_at', 'expires_at', 'source_type', 'source_id', 'source_reference', 'rule_key', 'rule_version', 'reason', 'branch_id', 'store_id']);

        app(RecordAuditEvent::class)->execute(
            category: 'reporting',
            event: 'loyalty_exported',
            branchId: (int) $store->branch_id,
            storeId: (int) $store->id,
            metadata: ['customer_id' => $customer->id, 'row_count' => $rows->count(), 'permission' => 'loyalty.export', 'scope_limited' => true],
        );

        return response()->streamDownload(static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['customer_id', 'activity', 'event', 'points', 'balance_before', 'balance_after', 'effective_at', 'expires_at', 'source_type', 'source_id', 'source_reference', 'rule_key', 'rule_version', 'reason', 'branch_id', 'store_id']);
            foreach ($rows as $entry) {
                fputcsv($handle, [$entry->customer_id, $entry->activity, $entry->event_type, $entry->points, $entry->balance_before, $entry->balance_after, $entry->effective_at?->format('c'), $entry->expires_at?->format('c'), $entry->source_type, $entry->source_id, $entry->source_reference, $entry->rule_key, $entry->rule_version, $entry->reason, $entry->branch_id, $entry->store_id]);
            }
            fclose($handle);
        }, 'customer-loyalty.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('can:loyalty.export')->name('customers.loyalty.export');

    Route::post('customers/{customerId}/loyalty/redeem', function (Request $request, int $customerId, RedeemLoyaltyAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('loyalty.redeem'), 403);
        $validated = $request->validate(['source_sale_id' => ['required', 'integer'], 'points' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid']]);
        $store = $sellingStore($user);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $sale = Sale::query()->visibleTo($user)->approved()->where('customer_id', $customer->id)->whereKey($validated['source_sale_id'])->firstOrFail();
        try {
            $action->execute($user, $customer, $store, $sale, (int) $validated['points'], 'REDEEM:'.$validated['idempotency_key']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['redeem' => UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Loyalty redemption recorded against the approved sale.'));
    })->middleware('can:loyalty.redeem')->name('customers.loyalty.redeem');

    Route::post('customers/{customerId}/loyalty/adjustments', function (Request $request, int $customerId, RequestLoyaltyAdjustmentAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('loyalty.adjust'), 403);
        $validated = $request->validate(['points' => ['required', 'integer', 'not_in:0'], 'reason' => ['required', 'string', 'max:1000'], 'source_reference' => ['nullable', 'string', 'max:190'], 'idempotency_key' => ['required', 'uuid']]);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        try {
            $action->execute($user, $customer, $sellingStore($user), (int) $validated['points'], $validated['reason'], 'ADJUST:'.$validated['idempotency_key'], $validated['source_reference'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['adjustment' => UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Loyalty adjustment submitted for approval.'));
    })->middleware('can:loyalty.adjust')->name('customers.loyalty.adjustments.store');

    Route::post('customers/{customerId}/loyalty/expire', function (Request $request, int $customerId, ExpireLoyaltyAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('loyalty.expire'), 403);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $count = $action->execute($user, $customer, $sellingStore($user));

        return back()->with('success', __(':count loyalty expiry entries posted.', ['count' => $count]));
    })->middleware('can:loyalty.expire')->name('customers.loyalty.expire');

    Route::post('loyalty/adjustments/{approvalId}/approve', function (Request $request, int $approvalId, ApproveLoyaltyAdjustmentAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('loyalty.approve'), 403);
        $approval = ApprovalRecord::query()->visibleTo($user)->whereKey($approvalId)->where('source_type', 'loyalty_adjustments')->firstOrFail();
        $store = Store::query()->visibleTo($user)->whereKey($approval->store_id)->where('status', 'active')->firstOrFail();
        try {
            $action->execute($user, $approval, $store);
        } catch (InvalidArgumentException|ValidationException $exception) {
            return back()->withErrors(['approval' => UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Loyalty adjustment approved and posted.'));
    })->middleware('can:loyalty.approve')->name('loyalty.adjustments.approve');

    Route::post('loyalty/adjustments/{approvalId}/reject', function (Request $request, int $approvalId, RejectLoyaltyAdjustmentAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('loyalty.approve'), 403);
        $validated = $request->validate(['decision_note' => ['required', 'string', 'min:3', 'max:1000']]);
        $approval = ApprovalRecord::query()->visibleTo($user)->whereKey($approvalId)->where('source_type', 'loyalty_adjustments')->firstOrFail();
        try {
            $action->execute($user, $approval, $validated['decision_note']);
        } catch (ValidationException|InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Loyalty adjustment rejected and audited.'));
    })->middleware('can:loyalty.approve')->name('loyalty.adjustments.reject');

    Route::get('customers/{customerId}/product-wallet', function (Request $request, int $customerId) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('product_wallet.view'), 403);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $entries = ProductWalletLedger::query()->visibleTo($user)->where('customer_id', $customer->id)->with(['customer', 'store'])->latestFirst()->paginate(20)->withQueryString();
        $store = Store::query()->visibleTo($user)->where('status', 'active')->with('company')->orderBy('id')->firstOrFail();
        $policyError = null;
        try {
            WalletPolicy::for('product');
        } catch (InvalidArgumentException $exception) {
            $policyError = UserSafeError::message($exception);
        }
        $adjustmentIds = ProductWalletAdjustment::query()->visibleTo($user)->where('customer_id', $customer->id)->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();
        $pendingAdjustments = $user->can('product_wallet.approve') && $adjustmentIds !== []
            ? ApprovalRecord::query()->visibleTo($user)->where('source_type', 'product_wallet_adjustments')->whereIn('source_id', $adjustmentIds)->where('approval_state', 'pending')->where('decision_permission', 'product_wallet.approve')->latest('id')->limit(20)->get()
            : collect();

        return view('pages.wallets.ledger', [
            'title' => __('Product Wallet'), 'description' => __('Retail-only customer balance derived from a separate immutable, source-linked ledger.'),
            'wallet' => 'product', 'customer' => $customer, 'ledgerTable' => 'product_wallet_ledger', 'entries' => $entries,
            'balance' => app(ProductWalletBalance::class)->forCustomer($customer, $user), 'currencyCode' => strtoupper((string) $store->company?->currency_code), 'policyError' => $policyError,
            'pendingAdjustments' => $pendingAdjustments, 'canSettle' => $user->can('product_wallet.settle'), 'canAdjust' => $user->can('product_wallet.adjust'), 'canApprove' => $user->can('product_wallet.approve'),
            'otherRoute' => 'wallets.party', 'otherCustomerRoute' => 'customers.party-wallet', 'otherPermission' => 'party_wallet.view', 'otherLabel' => __('Open Party Wallet'),
            'exportRoute' => $user->can('product_wallet.export') ? route('customers.product-wallet.export', $customer) : null,
            'settlementRoute' => route('customers.product-wallet.settle', $customer), 'adjustmentRoute' => route('customers.product-wallet.adjustments.store', $customer),
            'approveRoute' => static fn (int $approvalId): string => route('wallets.product.adjustments.approve', $approvalId), 'rejectRoute' => static fn (int $approvalId): string => route('wallets.product.adjustments.reject', $approvalId),
            'guidePrefix' => 'product-wallet',
        ]);
    })->middleware('can:product_wallet.view')->name('customers.product-wallet');

    Route::get('customers/{customerId}/party-wallet', function (Request $request, int $customerId) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_wallet.view'), 403);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $entries = PartyWalletLedger::query()->visibleTo($user)->where('customer_id', $customer->id)->with(['customer', 'store'])->latestFirst()->paginate(20)->withQueryString();
        $store = Store::query()->visibleTo($user)->where('status', 'active')->with('company')->orderBy('id')->firstOrFail();
        $policyError = null;
        try {
            WalletPolicy::for('party');
        } catch (InvalidArgumentException $exception) {
            $policyError = UserSafeError::message($exception);
        }
        $adjustmentIds = PartyWalletAdjustment::query()->visibleTo($user)->where('customer_id', $customer->id)->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();
        $pendingAdjustments = $user->can('party_wallet.approve') && $adjustmentIds !== []
            ? ApprovalRecord::query()->visibleTo($user)->where('source_type', 'party_wallet_adjustments')->whereIn('source_id', $adjustmentIds)->where('approval_state', 'pending')->where('decision_permission', 'party_wallet.approve')->latest('id')->limit(20)->get()
            : collect();

        return view('pages.wallets.ledger', [
            'title' => __('Party Wallet'), 'description' => __('Party-only customer balance derived from a separate immutable, source-linked ledger.'),
            'wallet' => 'party', 'customer' => $customer, 'ledgerTable' => 'party_wallet_ledger', 'entries' => $entries,
            'balance' => app(PartyWalletBalance::class)->forCustomer($customer, $user), 'currencyCode' => strtoupper((string) $store->company?->currency_code), 'policyError' => $policyError,
            'pendingAdjustments' => $pendingAdjustments, 'canSettle' => $user->can('party_wallet.settle'), 'canAdjust' => $user->can('party_wallet.adjust'), 'canApprove' => $user->can('party_wallet.approve'),
            'otherRoute' => 'wallets.product', 'otherCustomerRoute' => 'customers.product-wallet', 'otherPermission' => 'product_wallet.view', 'otherLabel' => __('Open Product Wallet'),
            'exportRoute' => $user->can('party_wallet.export') ? route('customers.party-wallet.export', $customer) : null,
            'settlementRoute' => route('customers.party-wallet.settle', $customer), 'adjustmentRoute' => route('customers.party-wallet.adjustments.store', $customer),
            'approveRoute' => static fn (int $approvalId): string => route('wallets.party.adjustments.approve', $approvalId), 'rejectRoute' => static fn (int $approvalId): string => route('wallets.party.adjustments.reject', $approvalId),
            'guidePrefix' => 'party-wallet',
        ]);
    })->middleware('can:party_wallet.view')->name('customers.party-wallet');

    Route::get('customers/{customerId}/product-wallet/export', function (Request $request, int $customerId) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('product_wallet.export'), 403);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $rows = ProductWalletLedger::query()->visibleTo($user)->where('customer_id', $customer->id)->latestFirst()->limit(500)->get();
        $store = $sellingStore($user);
        app(RecordAuditEvent::class)->execute(category: 'reporting', event: 'product_wallet_customer_exported', branchId: (int) $store->branch_id, storeId: (int) $store->id, metadata: ['wallet' => 'product', 'customer_id' => $customer->id, 'row_count' => $rows->count(), 'scope_limited' => true]);

        return response()->streamDownload(static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['entry_type', 'amount', 'balance_before', 'balance_after', 'currency_code', 'source_type', 'source_id', 'created_at']);
            foreach ($rows as $entry) {
                fputcsv($handle, [$entry->entry_type, $entry->amount, $entry->balance_before, $entry->balance_after, $entry->currency_code, $entry->source_type, $entry->source_id, $entry->created_at?->format('c')]);
            }
            fclose($handle);
        }, 'customer-product-wallet.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('can:product_wallet.export')->name('customers.product-wallet.export');

    Route::get('customers/{customerId}/party-wallet/export', function (Request $request, int $customerId) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_wallet.export'), 403);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $rows = PartyWalletLedger::query()->visibleTo($user)->where('customer_id', $customer->id)->latestFirst()->limit(500)->get();
        $store = Store::query()->visibleTo($user)->where('status', 'active')->firstOrFail();
        app(RecordAuditEvent::class)->execute(category: 'reporting', event: 'party_wallet_customer_exported', branchId: (int) $store->branch_id, storeId: (int) $store->id, metadata: ['wallet' => 'party', 'customer_id' => $customer->id, 'row_count' => $rows->count(), 'scope_limited' => true]);

        return response()->streamDownload(static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['entry_type', 'amount', 'balance_before', 'balance_after', 'currency_code', 'source_type', 'source_id', 'created_at']);
            foreach ($rows as $entry) {
                fputcsv($handle, [$entry->entry_type, $entry->amount, $entry->balance_before, $entry->balance_after, $entry->currency_code, $entry->source_type, $entry->source_id, $entry->created_at?->format('c')]);
            }
            fclose($handle);
        }, 'customer-party-wallet.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('can:party_wallet.export')->name('customers.party-wallet.export');

    Route::post('customers/{customerId}/product-wallet/settle', function (Request $request, int $customerId, PostProductWalletEntryAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('product_wallet.settle'), 403);
        $validated = $request->validate(['direction' => ['required', Rule::in(['credit', 'debit'])], 'amount' => ['required', 'string', 'max:30'], 'source_type' => ['required', 'string', 'max:120'], 'source_id' => ['required', 'string', 'max:120'], 'source_line_id' => ['nullable', 'string', 'max:120'], 'reference' => ['nullable', 'string', 'max:190'], 'reason' => ['nullable', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $store = $sellingStore($user);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        try {
            app($action::class)->settle($user, $customer, $store, $validated['amount'], $validated['direction'], $validated['source_type'], $validated['source_id'], $validated['idempotency_key'], $validated['source_line_id'] ?? null, $validated['reference'] ?? null, $validated['reason'] ?? null);
        } catch (InvalidArgumentException|ValidationException $exception) {
            return back()->withInput()->withErrors(['wallet' => UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Product Wallet settlement posted.'));
    })->middleware('can:product_wallet.settle')->name('customers.product-wallet.settle');

    Route::post('customers/{customerId}/party-wallet/settle', function (Request $request, int $customerId, PostPartyWalletEntryAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_wallet.settle'), 403);
        $validated = $request->validate(['direction' => ['required', Rule::in(['credit', 'debit'])], 'amount' => ['required', 'string', 'max:30'], 'source_type' => ['required', 'string', 'max:120'], 'source_id' => ['required', 'string', 'max:120'], 'source_line_id' => ['nullable', 'string', 'max:120'], 'reference' => ['nullable', 'string', 'max:190'], 'reason' => ['nullable', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $store = Store::query()->visibleTo($user)->where('status', 'active')->orderBy('id')->firstOrFail();
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        try {
            $action->settle($user, $customer, $store, $validated['amount'], $validated['direction'], $validated['source_type'], $validated['source_id'], $validated['idempotency_key'], $validated['source_line_id'] ?? null, $validated['reference'] ?? null, $validated['reason'] ?? null);
        } catch (InvalidArgumentException|ValidationException $exception) {
            return back()->withInput()->withErrors(['wallet' => UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Party Wallet settlement posted.'));
    })->middleware('can:party_wallet.settle')->name('customers.party-wallet.settle');

    Route::post('customers/{customerId}/product-wallet/adjustments', function (Request $request, int $customerId, RequestProductWalletAdjustmentAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('product_wallet.adjust'), 403);
        $validated = $request->validate(['operation' => ['required', Rule::in(['adjustment', 'correction'])], 'amount' => ['required', 'string', 'max:30'], 'target_ledger_id' => ['nullable', 'integer', 'min:1'], 'source_type' => ['required', 'string', 'max:120'], 'source_id' => ['required', 'string', 'max:120'], 'source_line_id' => ['nullable', 'string', 'max:120'], 'source_reference' => ['nullable', 'string', 'max:190'], 'reason' => ['required', 'string', 'min:3', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        try {
            $action->execute($user, $customer, $sellingStore($user), $validated['operation'], $validated['amount'], $validated['source_type'], $validated['source_id'], $validated['reason'], $validated['idempotency_key'], $validated['target_ledger_id'] ?? null, $validated['source_line_id'] ?? null, $validated['source_reference'] ?? null);
        } catch (InvalidArgumentException|ValidationException $exception) {
            return back()->withInput()->withErrors(['wallet' => UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Product Wallet adjustment submitted for approval.'));
    })->middleware('can:product_wallet.adjust')->name('customers.product-wallet.adjustments.store');

    Route::post('customers/{customerId}/party-wallet/adjustments', function (Request $request, int $customerId, RequestPartyWalletAdjustmentAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_wallet.adjust'), 403);
        $validated = $request->validate(['operation' => ['required', Rule::in(['adjustment', 'correction'])], 'amount' => ['required', 'string', 'max:30'], 'target_ledger_id' => ['nullable', 'integer', 'min:1'], 'source_type' => ['required', 'string', 'max:120'], 'source_id' => ['required', 'string', 'max:120'], 'source_line_id' => ['nullable', 'string', 'max:120'], 'source_reference' => ['nullable', 'string', 'max:190'], 'reason' => ['required', 'string', 'min:3', 'max:1000'], 'idempotency_key' => ['required', 'uuid']]);
        $customer = Customer::query()->visibleTo($user)->whereKey($customerId)->where('status', 'active')->firstOrFail();
        $store = Store::query()->visibleTo($user)->where('status', 'active')->orderBy('id')->firstOrFail();
        try {
            $action->execute($user, $customer, $store, $validated['operation'], $validated['amount'], $validated['source_type'], $validated['source_id'], $validated['reason'], $validated['idempotency_key'], $validated['target_ledger_id'] ?? null, $validated['source_line_id'] ?? null, $validated['source_reference'] ?? null);
        } catch (InvalidArgumentException|ValidationException $exception) {
            return back()->withInput()->withErrors(['wallet' => UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Party Wallet adjustment submitted for approval.'));
    })->middleware('can:party_wallet.adjust')->name('customers.party-wallet.adjustments.store');

    Route::post('pos/session-legacy/customer/select', function (Request $request) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create') && $user->can('customers.view'), 403);
        $validated = $request->validate(['customer_id' => ['required', 'integer']]);
        $store = $sellingStore($user);
        $customer = Customer::query()->visibleFrom($user, (int) $store->branch_id, (int) $store->id)->whereKey($validated['customer_id'])->where('status', 'active')->firstOrFail();
        $request->session()->put('pos.customer_id', $customer->id);

        return back()->with('success', __('Customer selected for this sale.'));
    })->middleware(['can:pos_sales.create', 'can:customers.view'])->name('pos.customer.select.legacy');

    Route::post('pos/session-legacy/customer/clear', function (Request $request) {
        abort_unless($request->user()?->can('pos_sales.create'), 403);
        $request->session()->forget('pos.customer_id');

        return back();
    })->middleware('can:pos_sales.create')->name('pos.customer.clear.legacy');

    Route::post('pos/session-legacy/customer/create', function (Request $request, CreateCustomerAction $action) use ($sellingStore) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create') && $user->can('customers.create'), 403);
        $validated = $request->validate([
            'idempotency_key' => ['required', 'uuid'], 'phone' => ['nullable', 'string', 'max:64', PhoneNormalizer::validationRule()],
            'name_ar' => ['required', 'string', 'max:190'], 'name_en' => ['required', 'string', 'max:190'],
            'consent_purpose' => ['required', 'string', 'max:80'],
        ]);
        try {
            $customer = $action->execute($user, $sellingStore($user), $validated + ['consents' => [[
                'purpose' => $validated['consent_purpose'], 'status' => 'granted', 'source' => 'pos',
            ]]]);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['customer' => UserSafeError::message($exception)]);
        }
        $request->session()->put('pos.customer_id', $customer->id);

        return back()->with('success', __('Customer registered and selected for this sale.'));
    })->middleware(['can:pos_sales.create', 'can:customers.create'])->name('pos.customer.create.legacy');
});

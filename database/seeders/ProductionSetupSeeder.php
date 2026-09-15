<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Actions\AddBarcodeAction;
use App\Modules\Catalog\Actions\GenerateProductVariationsAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\SaveProductOptionAction;
use App\Modules\Catalog\Actions\SaveProductSupplierAction;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOptionGroup;
use App\Modules\Catalog\Models\ProductOptionValue;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Customer\Actions\CreateCustomerAction;
use App\Modules\Customer\Actions\SaveCustomerPolicySettingAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Inventory\Actions\ApproveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SaveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SubmitInventoryAdjustmentAction;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Party\Actions\CreatePartyBookingAction;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\PriceLine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;
use LogicException;

/**
 * Imports only an explicitly supplied, owner-approved Production setup file.
 * Transactional prices and stock use their normal maker/checker actions.
 */
final class ProductionSetupSeeder extends Seeder
{
    /** @var array<string, mixed> */
    private array $data = [];

    private User $administrator;

    public function run(): void
    {
        $this->data = $this->loadData();
        $this->administrator = User::query()->where('username', strtolower((string) config('production-seeding.admin.username')))->firstOrFail();

        Auth::login($this->administrator);
        try {
            DB::transaction(function (): void {
                $this->seedCompany();
                $this->seedLocations();
                $this->seedUsersAndScopes();
                $this->seedOperationalSettings();
                $this->seedCatalog();
                $this->seedSuppliers();
                $this->seedApprovedPrices();
                $this->seedOpeningInventory();
                $this->seedCustomersAndPartyBookings();
            }, 5);
        } finally {
            Auth::logout();
        }
    }

    /** @return array<string, mixed> */
    private function loadData(): array
    {
        $path = trim((string) config('production-seeding.setup_data.path'));
        $realPath = $path === '' ? false : realpath($path);
        if ($realPath === false || ! is_file($realPath) || ! is_readable($realPath)) {
            throw new LogicException('PRODUCTION_SETUP_DATA_PATH must reference a readable JSON file.');
        }
        $publicPath = realpath(public_path());
        if ($publicPath !== false && str_starts_with(strtolower($realPath), strtolower($publicPath.DIRECTORY_SEPARATOR))) {
            throw new LogicException('The Production setup data file must be stored outside the public web root.');
        }
        $contents = file_get_contents($realPath);
        if ($contents === false) {
            throw new LogicException('The Production setup data file could not be read.');
        }
        $expectedHash = strtolower(trim((string) config('production-seeding.setup_data.sha256')));
        if ($expectedHash !== '' && ! hash_equals($expectedHash, hash('sha256', $contents))) {
            throw new LogicException('The Production setup data SHA-256 does not match the approved artifact.');
        }
        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new LogicException('The Production setup data file is not valid JSON: '.$exception->getMessage(), previous: $exception);
        }
        if (! is_array($data) || ($data['schema_version'] ?? null) !== 1) {
            throw new LogicException('Production setup data must be an object with schema_version 1.');
        }
        if (str_contains(json_encode($data, JSON_THROW_ON_ERROR), '__REPLACE__')) {
            throw new LogicException('The Production setup data still contains __REPLACE__ template markers.');
        }

        return $data;
    }

    private function seedCompany(): void
    {
        $row = $this->object('company', required: true);
        $this->validate($row, [
            'code' => ['required', 'string', 'max:255'], 'name_ar' => ['required', 'string'], 'name_en' => ['required', 'string'],
            'currency_code' => ['required', 'string', 'size:3'], 'currency_symbol' => ['required', 'string'],
            'timezone' => ['required', 'timezone'], 'locale_default' => ['required', 'in:ar,en'], 'status' => ['required', 'in:active,inactive'],
            'email' => ['nullable', 'email'],
        ], 'company');
        Company::query()->updateOrCreate(['code' => $row['code']], $this->only($row, [
            'name_ar', 'name_en', 'legal_name', 'tax_number', 'commercial_registration', 'currency_code', 'currency_symbol',
            'timezone', 'locale_default', 'phone', 'email', 'address', 'status', 'policy_notes',
        ]));
    }

    private function seedLocations(): void
    {
        $company = Company::query()->where('code', $this->data['company']['code'])->firstOrFail();
        foreach ($this->rows('branches') as $index => $row) {
            $this->requireKeys($row, ['code', 'name_ar', 'name_en'], "branches.$index");
            Branch::query()->updateOrCreate(['code' => $row['code']], [
                ...$this->only($row, ['name_ar', 'name_en', 'phone', 'email', 'address', 'timezone', 'status', 'policy_notes']),
                'company_id' => $company->id, 'timezone' => $row['timezone'] ?? $company->timezone, 'status' => $row['status'] ?? 'active',
            ]);
        }
        foreach ($this->rows('stores') as $index => $row) {
            $this->requireKeys($row, ['code', 'branch_code', 'type', 'name_ar', 'name_en'], "stores.$index");
            $branch = Branch::query()->where('code', $row['branch_code'])->where('company_id', $company->id)->firstOrFail();
            $store = Store::query()->updateOrCreate(['code' => $row['code']], [
                ...$this->only($row, ['type', 'name_ar', 'name_en', 'status', 'allows_negative_stock', 'policy_notes']),
                'company_id' => $company->id, 'branch_id' => $branch->id, 'status' => $row['status'] ?? 'active',
                'allows_negative_stock' => (bool) ($row['allows_negative_stock'] ?? false),
            ]);
            if (($row['selling_store_for_branch'] ?? false) === true) {
                BranchSellingStore::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'store_id' => $store->id],
                    ['status' => 'active', 'effective_from' => $row['effective_from'] ?? now(), 'created_by' => $this->administrator->id],
                );
            }
        }
    }

    private function seedUsersAndScopes(): void
    {
        foreach ($this->rows('users') as $index => $row) {
            $this->requireKeys($row, ['name', 'username', 'email', 'password_key', 'roles'], "users.$index");
            $username = strtolower(trim((string) $row['username']));
            $email = strtolower(trim((string) $row['email']));
            $user = User::query()->where('username', $username)->orWhere('email', $email)->first();
            if ($user !== null && ($user->username !== $username || $user->email !== $email)) {
                throw new LogicException("users.$index conflicts with an existing username or email.");
            }
            if ($user === null) {
                $passwordKey = trim((string) ($row['password_key'] ?? ''));
                $passwords = (array) config('production-seeding.setup_data.user_passwords', []);
                $password = (string) ($passwords[$passwordKey] ?? '');
                if (mb_strlen($password) < 16) {
                    throw new LogicException("users.$index requires a password_key mapped to at least 16 characters in PRODUCTION_SETUP_USER_PASSWORDS.");
                }
                $user = User::query()->create(['name' => $row['name'], 'username' => $username, 'email' => $email, 'password' => Hash::make($password), 'status' => $row['status'] ?? 'active']);
                $user->forceFill(['email_verified_at' => now(), 'is_super_admin' => (bool) ($row['is_super_admin'] ?? false)])->save();
            } else {
                $user->forceFill(['name' => $row['name'], 'status' => $row['status'] ?? 'active'])->save();
            }
            $roleIds = Role::query()->whereIn('code', (array) $row['roles'])->pluck('id');
            if ($roleIds->count() !== count(array_unique((array) $row['roles']))) {
                throw new LogicException("users.$index references an unknown role.");
            }
            $user->roles()->sync($roleIds);
            $branchIds = Branch::query()->whereIn('code', (array) ($row['branch_scopes'] ?? []))->pluck('id');
            $storeIds = Store::query()->whereIn('code', (array) ($row['store_scopes'] ?? []))->pluck('id');
            $user->branchScopes()->whereNotIn('branch_id', $branchIds)->delete();
            foreach ($branchIds as $id) {
                $user->branchScopes()->updateOrCreate(['branch_id' => $id], ['status' => 'active']);
            }
            $user->storeScopes()->whereNotIn('store_id', $storeIds)->delete();
            foreach ($storeIds as $id) {
                $user->storeScopes()->updateOrCreate(['store_id' => $id], ['status' => 'active']);
            }
        }

        $uncoveredRoles = Role::query()
            ->where('status', 'active')
            ->whereDoesntHave('users', fn ($query) => $query->where('users.status', 'active'))
            ->orderBy('code')
            ->pluck('code')
            ->all();

        if ($uncoveredRoles !== []) {
            throw new LogicException('Production setup requires an active credentialed user for every active role. Missing: '.implode(', ', $uncoveredRoles).'.');
        }
    }

    private function seedOperationalSettings(): void
    {
        foreach ($this->rows('payment_methods') as $row) {
            $this->requireKeys($row, ['code', 'name_ar', 'name_en', 'type'], 'payment_methods');
            PaymentMethod::query()->updateOrCreate(['code' => $row['code']], $this->defaults($row, ['requires_evidence' => false, 'offline_eligible' => false, 'status' => 'active']));
        }
        foreach ($this->rows('tax_settings') as $row) {
            $this->requireKeys($row, ['code', 'name_ar', 'name_en', 'rate'], 'tax_settings');
            TaxSetting::query()->updateOrCreate(['code' => $row['code']], $this->defaults($row, ['is_tax_inclusive' => false, 'status' => 'active']));
        }
        foreach ($this->rows('document_sequences') as $row) {
            $this->requireKeys($row, ['document_type'], 'document_sequences');
            $existing = DocumentSequence::query()->where('document_type', $row['document_type'])->first();
            if ($existing === null) {
                DocumentSequence::query()->create($this->defaults($row, ['padding_length' => 6, 'next_value' => 1, 'reset_rule' => 'never', 'status' => 'active', 'lock_version' => 1]));
            } else {
                $existing->update($this->only($row, ['prefix', 'suffix', 'padding_length', 'reset_rule', 'status', 'policy_notes']));
            }
        }
        foreach ($this->rows('printers') as $row) {
            $this->requireKeys($row, ['name', 'printer_type', 'paper_size', 'template_name', 'connection_type'], 'printers');
            PrinterConfiguration::query()->updateOrCreate(['name' => $row['name']], $this->defaults($row, ['is_default' => false, 'status' => 'active']));
        }
        foreach ($this->rows('customer_policy_settings') as $row) {
            $this->requireKeys($row, ['key', 'value'], 'customer_policy_settings');
            $latest = CustomerPolicySettingVersion::query()->where('key', $row['key'])->latest('version')->first();
            if ($latest?->value !== trim((string) $row['value'])) {
                app(SaveCustomerPolicySettingAction::class)->execute((string) $row['key'], (string) $row['value'], $row['notes'] ?? null);
            }
        }
    }

    private function seedCatalog(): void
    {
        foreach ($this->rows('option_groups') as $row) {
            $group = ProductOptionGroup::query()->where('code', strtoupper((string) $row['code']))->first();
            $group = app(SaveProductOptionAction::class)->saveGroup($row, $group?->id);
            foreach ((array) ($row['values'] ?? []) as $value) {
                if (! is_array($value)) {
                    throw new LogicException('Option values must be objects.');
                }
                $existing = ProductOptionValue::query()->where('product_option_group_id', $group->id)->where('code', strtoupper((string) $value['code']))->first();
                app(SaveProductOptionAction::class)->saveValue($group->id, $value, $existing?->id);
            }
        }
        foreach ($this->rows('categories') as $row) {
            $this->requireKeys($row, ['code', 'name_ar', 'name_en'], 'categories');
            $parentId = filled($row['parent_code'] ?? null) ? Category::query()->where('code', $row['parent_code'])->value('id') : null;
            Category::query()->updateOrCreate(['code' => strtoupper((string) $row['code'])], [...$this->only($row, ['name_ar', 'name_en', 'status', 'sort_order']), 'parent_id' => $parentId, 'status' => $row['status'] ?? 'active', 'created_by' => $this->administrator->id, 'updated_by' => $this->administrator->id]);
        }
        foreach ($this->rows('brands') as $row) {
            $this->requireKeys($row, ['code', 'name_ar', 'name_en'], 'brands');
            Brand::query()->updateOrCreate(['code' => strtoupper((string) $row['code'])], [...$this->only($row, ['name_ar', 'name_en', 'status']), 'status' => $row['status'] ?? 'active', 'created_by' => $this->administrator->id, 'updated_by' => $this->administrator->id]);
        }
        foreach ($this->rows('products') as $index => $row) {
            $this->requireKeys($row, ['item_code', 'name_ar', 'name_en', 'category_code'], "products.$index");
            $row['category_id'] = Category::query()->where('code', strtoupper((string) $row['category_code']))->value('id');
            $row['brand_id'] = filled($row['brand_code'] ?? null) ? Brand::query()->where('code', strtoupper((string) $row['brand_code']))->value('id') : null;
            $product = Product::query()->where('item_code', strtoupper((string) $row['item_code']))->first();
            $product = $product ?? app(SaveProductAction::class)->execute($row);
            if (! empty($row['barcode']) && ! Barcode::query()->where('barcode', $row['barcode'])->exists()) {
                app(AddBarcodeAction::class)->addSupplierBarcode($product->id, (string) $row['barcode']);
            }
            if (($row['variation_groups'] ?? []) !== []) {
                $this->seedVariations($product, $row, $index);
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function seedVariations(Product $family, array $row, int $index): void
    {
        $selection = [];
        foreach ((array) $row['variation_groups'] as $groupCode => $valueCodes) {
            $group = ProductOptionGroup::query()->where('code', strtoupper((string) $groupCode))->firstOrFail();
            $values = ProductOptionValue::query()->where('product_option_group_id', $group->id)->whereIn('code', array_map('strtoupper', (array) $valueCodes))->get();
            if ($values->count() !== count((array) $valueCodes)) {
                throw new LogicException("products.$index references an unknown option value.");
            }
            $selection[$group->id] = $values->pluck('id')->all();
        }
        $generator = app(GenerateProductVariationsAction::class);
        $input = [];
        foreach ((array) ($row['variants'] ?? []) as $variant) {
            if (! is_array($variant)) {
                throw new LogicException("products.$index variants must be objects.");
            }
            $ids = [];
            foreach (array_keys($selection) as $groupId) {
                $groupCode = ProductOptionGroup::query()->whereKey($groupId)->value('code');
                $valueCode = $variant['options'][$groupCode] ?? null;
                $ids[] = ProductOptionValue::query()->where('product_option_group_id', $groupId)->where('code', strtoupper((string) $valueCode))->value('id') ?? 0;
            }
            if (in_array(0, $ids, true)) {
                throw new LogicException("products.$index has an incomplete variant option selection.");
            }
            $input[$generator->signature($ids)] = $variant;
        }
        $generator->execute($family, $selection, $input);
    }

    private function seedSuppliers(): void
    {
        foreach ($this->rows('suppliers') as $row) {
            $this->requireKeys($row, ['code', 'name_ar', 'name_en'], 'suppliers');
            $supplier = Supplier::query()->where('code', strtoupper((string) $row['code']))->first();
            app(SaveSupplierAction::class)->execute($row, $supplier?->id, $supplier?->lock_version);
        }
        foreach ($this->rows('supplier_skus') as $row) {
            $product = Product::query()->where('item_code', strtoupper((string) ($row['product_code'] ?? '')))->firstOrFail();
            $supplier = Supplier::query()->where('code', strtoupper((string) ($row['supplier_code'] ?? '')))->firstOrFail();
            app(SaveProductSupplierAction::class)->execute([...$row, 'product_id' => $product->id, 'supplier_id' => $supplier->id]);
        }
    }

    private function seedApprovedPrices(): void
    {
        foreach ($this->rows('approved_prices') as $index => $row) {
            $this->requireKeys($row, ['product_code', 'store_code', 'price_list_code', 'price_list_name_ar', 'price_list_name_en', 'amount', 'maker_username', 'approver_username', 'source_reference'], "approved_prices.$index");
            $product = Product::query()->sellable()->where('item_code', strtoupper((string) $row['product_code']))->firstOrFail();
            $store = Store::query()->where('code', $row['store_code'])->firstOrFail();
            $existing = PriceLine::query()->where('product_id', $product->id)->where('store_id', $store->id)->whereHas('version', fn ($query) => $query->where('source_reference', $row['source_reference'])->where('state', PriceVersionState::Approved))->exists();
            if ($existing) {
                continue;
            }
            Auth::login($this->user((string) $row['maker_username']));
            $version = app(CreatePriceProposalAction::class)->execute($product, $store, (string) $row['price_list_code'], (string) $row['price_list_name_ar'], (string) $row['price_list_name_en'], (string) $row['amount'], (string) ($row['source_type'] ?? 'import'), (string) $row['source_reference'], $row['effective_from'] ?? null, $row['effective_to'] ?? null, $row['reason_text'] ?? null, $row['reference_amount'] ?? null, (bool) ($row['open_price_allowed'] ?? false), $row['open_price_minimum'] ?? null, $row['open_price_maximum'] ?? null);
            $version = app(SubmitPriceProposalAction::class)->execute($version);
            Auth::login($this->distinctApprover($row, $version->requested_by, "approved_prices.$index"));
            app(ApprovePriceProposalAction::class)->execute($version);
        }
    }

    private function seedOpeningInventory(): void
    {
        foreach ($this->rows('opening_inventory') as $index => $row) {
            $this->requireKeys($row, ['idempotency_key', 'store_code', 'maker_username', 'approver_username', 'lines'], "opening_inventory.$index");
            $adjustment = InventoryAdjustment::query()->where('idempotency_key', $row['idempotency_key'])->first();
            if ($adjustment?->status === 'approved') {
                continue;
            }
            $maker = $this->user((string) $row['maker_username']);
            Auth::login($maker);
            if ($adjustment === null) {
                $store = Store::query()->where('code', $row['store_code'])->firstOrFail();
                $lines = collect((array) $row['lines'])->map(function ($line): array {
                    if (! is_array($line)) {
                        throw new LogicException('Opening inventory lines must be objects.');
                    }

                    return [...$line, 'product_id' => Product::query()->sellable()->where('item_code', strtoupper((string) ($line['product_code'] ?? '')))->value('id')];
                })->all();
                $adjustment = app(SaveInventoryAdjustmentAction::class)->execute(['store_id' => $store->id, 'adjustment_type' => 'entry', 'reason_code' => 'opening_inventory', 'reason_notes' => $row['reason_notes'] ?? null, 'notes' => $row['notes'] ?? null, 'idempotency_key' => $row['idempotency_key']], $lines);
            }
            if ($adjustment->status === 'draft') {
                $adjustment = app(SubmitInventoryAdjustmentAction::class)->execute($adjustment->id);
            }
            Auth::login($this->distinctApprover($row, $adjustment->created_by, "opening_inventory.$index"));
            app(ApproveInventoryAdjustmentAction::class)->execute($adjustment->id);
        }
    }

    private function seedCustomersAndPartyBookings(): void
    {
        foreach ($this->rows('customers') as $index => $row) {
            $this->requireKeys($row, ['phone', 'name_ar', 'name_en', 'store_code', 'actor_username', 'idempotency_key', 'consents'], "customers.$index");
            if (Customer::query()->where('idempotency_key', $row['idempotency_key'])->exists()) {
                continue;
            }
            app(CreateCustomerAction::class)->execute($this->user((string) $row['actor_username']), Store::query()->where('code', $row['store_code'])->firstOrFail(), $row);
        }
        foreach ($this->rows('party_bookings') as $index => $row) {
            $this->requireKeys($row, ['idempotency_key', 'customer_phone', 'store_code', 'actor_username', 'lines'], "party_bookings.$index");
            if (PartyBooking::query()->where('idempotency_key', $row['idempotency_key'])->exists()) {
                continue;
            }
            $customer = Customer::query()->where('phone_display', $row['customer_phone'])->orWhere('phone_normalized', $row['customer_phone'])->firstOrFail();
            $lines = collect((array) $row['lines'])->map(function ($line): array {
                if (! is_array($line)) {
                    throw new LogicException('Party booking lines must be objects.');
                }
                if (filled($line['product_code'] ?? null)) {
                    $line['product_id'] = Product::query()->sellable()->where('item_code', strtoupper((string) $line['product_code']))->value('id');
                }

                return $line;
            })->all();
            app(CreatePartyBookingAction::class)->execute($this->user((string) $row['actor_username']), Store::query()->where('code', $row['store_code'])->firstOrFail(), [...$row, 'customer_id' => $customer->id, 'lines' => $lines]);
        }
        Auth::login($this->administrator);
    }

    /** @return array<string, mixed> */
    private function object(string $key, bool $required = false): array
    {
        $value = $this->data[$key] ?? null;
        if ($required && ! is_array($value)) {
            throw new LogicException("Production setup section '$key' is required.");
        }

        return is_array($value) ? $value : [];
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $key): array
    {
        $rows = $this->data[$key] ?? [];
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new LogicException("Production setup section '$key' must be an array.");
        }
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new LogicException("Every '$key' row must be an object.");
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function requireKeys(array $row, array $keys, string $context): void
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === '' || $row[$key] === []) {
                throw new LogicException("$context requires '$key'.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $rules
     */
    private function validate(array $row, array $rules, string $context): void
    {
        try {
            Validator::make($row, $rules)->validate();
        } catch (ValidationException $exception) {
            throw new LogicException("Invalid $context data: ".collect($exception->errors())->flatten()->implode(' '), previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function only(array $row, array $keys): array
    {
        return array_intersect_key($row, array_flip($keys));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function defaults(array $row, array $defaults): array
    {
        return $row + $defaults;
    }

    private function user(string $username): User
    {
        return User::query()->where('username', strtolower(trim($username)))->where('status', 'active')->firstOrFail();
    }

    /** @param array<string, mixed> $row */
    private function distinctApprover(array $row, ?int $makerId, string $context): User
    {
        $approver = $this->user((string) $row['approver_username']);
        if ($approver->id === $makerId) {
            throw new LogicException("$context requires a different maker and approver.");
        }

        return $approver;
    }
}

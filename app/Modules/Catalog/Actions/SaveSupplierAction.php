<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\StaleCatalogRecordException;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Catalog\Support\SupplierSettlementMethod;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SaveSupplierAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data, ?int $id = null, ?int $expectedVersion = null, ?int $companyId = null): Supplier
    {
        Gate::authorize($id ? 'suppliers.edit' : 'suppliers.create');

        return DB::transaction(function () use ($data, $id, $expectedVersion, $companyId): Supplier {
            $userId = Auth::id();
            $supplier = $id === null ? null : Supplier::query()->lockForUpdate()->findOrFail($id);

            if ($supplier !== null && $expectedVersion !== null && $supplier->lock_version !== $expectedVersion) {
                throw new StaleCatalogRecordException(__('This supplier master record changed in another session. Reload it before saving.'));
            }

            $status = (string) ($data['status'] ?? 'active');
            if (! in_array($status, ['active', 'inactive'], true)) {
                throw new InvalidArgumentException(__('The selected supplier status is not supported.'));
            }

            $barcodePrefix = strtoupper(trim((string) ($data['barcode_prefix'] ?? '')));
            if ($barcodePrefix !== '' && ! preg_match('/^[A-Z0-9]{1,12}$/', $barcodePrefix)) {
                throw new InvalidArgumentException(__('The barcode prefix may contain only uppercase English letters and digits, up to 12 characters.'));
            }
            $prefixQuery = Supplier::query()->where('barcode_prefix', $barcodePrefix);
            if ($supplier !== null) {
                $prefixQuery->whereKeyNot($supplier->id);
            }
            if ($barcodePrefix !== '' && $prefixQuery->exists()) {
                throw new InvalidArgumentException(__('The barcode prefix is already assigned to another supplier.'));
            }

            if ($supplier !== null && $supplier->status === 'active' && $status === 'inactive') {
                if ($supplier->productSuppliers()->where('is_preferred', true)->exists()) {
                    throw new InvalidArgumentException(__('Cannot deactivate a supplier that is set as preferred for active products.'));
                }
            }

            $supplierGroupId = filled($data['supplier_group_id'] ?? null) ? (int) $data['supplier_group_id'] : null;
            $supplierGroup = $supplierGroupId === null
                ? null
                : SupplierGroup::query()->when($companyId !== null, fn ($query) => $query->forCompany($companyId))
                    ->active()->lockForUpdate()->find($supplierGroupId);
            if ($supplierGroupId !== null && $supplierGroup === null) {
                throw new InvalidArgumentException(__('The selected supplier group is not available in the active company.'));
            }

            $settlementMethod = trim((string) ($data['settlement_method'] ?? ''));
            if (! SupplierSettlementMethod::isValid($settlementMethod)) {
                throw new InvalidArgumentException(__('Select an active supplier financial-settlement method.'));
            }
            $settlementOther = filled($data['settlement_other_description'] ?? null) ? trim((string) $data['settlement_other_description']) : null;
            if ($settlementMethod === 'other' && $settlementOther === null) {
                throw new InvalidArgumentException(__('Describe the supplier settlement method when Other is selected.'));
            }

            $paymentPolicy = filled($data['payment_policy'] ?? null) ? strtolower(trim((string) $data['payment_policy'])) : null;
            if ($paymentPolicy !== null && ! in_array($paymentPolicy, ['cash', 'credit', 'both'], true)) {
                throw new InvalidArgumentException(__('The selected supplier payment policy is not supported.'));
            }
            $creditDays = filled($data['credit_days'] ?? null) ? filter_var($data['credit_days'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 3650]]) : null;
            if (filled($data['credit_days'] ?? null) && $creditDays === false) {
                throw new InvalidArgumentException(__('Supplier credit days must be a whole number between 0 and 3650.'));
            }
            $creditLimit = filled($data['credit_limit'] ?? null) ? trim((string) $data['credit_limit']) : null;
            if ($creditLimit !== null && (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', $creditLimit) || bccomp($creditLimit, '0', 4) < 0)) {
                throw new InvalidArgumentException(__('Supplier credit limit must be a non-negative decimal.'));
            }

            $attributes = [
                'code' => strtoupper(trim((string) $data['code'])),
                'barcode_prefix' => $barcodePrefix !== '' ? $barcodePrefix : null,
                'name_ar' => trim((string) $data['name_ar']),
                'name_en' => trim((string) ($data['name_en'] ?? '')),
                'contact_name' => ! empty($data['contact_name']) ? trim((string) $data['contact_name']) : null,
                'email' => ! empty($data['email']) ? trim((string) $data['email']) : null,
                'phone' => filled($data['phone'] ?? null) ? PhoneNormalizer::normalize((string) $data['phone']) : null,
                'tax_number' => ! empty($data['tax_number']) ? trim((string) $data['tax_number']) : null,
                'payment_terms' => ! empty($data['payment_terms']) ? trim((string) $data['payment_terms']) : null,
                'payment_policy' => $paymentPolicy,
                'credit_days' => $creditDays === false ? null : $creditDays,
                'credit_limit' => $creditLimit === null ? null : bcadd($creditLimit, '0', 4),
                'commercial_registration' => filled($data['commercial_registration'] ?? null) ? trim((string) $data['commercial_registration']) : null,
                'settlement_method' => $settlementMethod,
                'settlement_other_description' => $settlementMethod === 'other' ? $settlementOther : null,
                'address' => ! empty($data['address']) ? trim((string) $data['address']) : null,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'status' => $status,
                'supplier_group_id' => $supplierGroup?->id,
                'updated_by' => $userId,
            ];

            if ($supplier === null) {
                $attributes['created_by'] = $userId;
                $attributes['lock_version'] = 0;
                $supplier = Supplier::query()->create($attributes);
                $event = 'create_supplier';
                $before = null;
            } else {
                $before = $supplier->only(['code', 'barcode_prefix', 'name_ar', 'name_en', 'contact_name', 'email', 'phone', 'tax_number', 'commercial_registration', 'payment_terms', 'payment_policy', 'credit_days', 'credit_limit', 'settlement_method', 'settlement_other_description', 'address', 'notes', 'status', 'supplier_group_id', 'lock_version']);
                $supplier->update([
                    ...$attributes,
                    'lock_version' => $supplier->lock_version + 1,
                ]);
                $event = 'update_supplier';
            }

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: $event,
                source: $supplier,
                before: $before,
                after: $supplier->fresh()->only(['code', 'barcode_prefix', 'name_ar', 'name_en', 'contact_name', 'email', 'phone', 'tax_number', 'commercial_registration', 'payment_terms', 'payment_policy', 'credit_days', 'credit_limit', 'settlement_method', 'settlement_other_description', 'address', 'notes', 'status', 'supplier_group_id', 'lock_version']),
            );

            return $supplier->fresh();
        });
    }
}

<?php

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Customer\Models\CustomerImportBatch;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Actions\NotifyImportReviewers;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\City;
use App\Modules\Platform\Models\Governorate;
use App\Modules\Platform\Models\Store;
use App\Support\DataExchange\ImportWorkbook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

final class StageCustomerImportAction
{
    public const FIELDS = ['first_name_ar', 'last_name_ar', 'first_name_en', 'last_name_en', 'phone', 'secondary_phone', 'email', 'customer_group_code', 'governorate_code', 'city_code', 'address_ar', 'address_en', 'consent_purpose', 'consent_status'];

    /** @return list<array<string, string>> */
    public static function readSpreadsheet(string $path, string $companyCode): array
    {
        $reader = ReaderFactory::createFromFile($path);
        $reader->open($path);
        $headers = null;
        $rows = [];
        $number = 0;
        try {
            ImportWorkbook::assertMetadata($reader, 'toyjoy.customers.v2', $companyCode);
            $sheet = ImportWorkbook::dataSheet($reader);
            foreach ($sheet->getRowIterator() as $row) {
                    $number++;
                    $cells = $row->getCells();
                    $values = array_map(fn ($cell) => (string) ($cell->getValue() ?? ''), $cells);
                    if ($number === 1) {
                        $headers = array_map(fn ($value) => strtolower(trim($value)), $values);
                        ImportWorkbook::assertHeaders($headers, self::FIELDS, __('The customer template headers must match the configured template exactly.'));

                        continue;
                    }if ($number > 5001) {
                        throw new InvalidArgumentException(__('The import is limited to 5,000 rows.'));
                    }if (! array_filter($values, fn ($value) => trim($value) !== '')) {
                        continue;
                    }foreach ($cells as $index => $cell) {
                        if ($cell instanceof FormulaCell || (($headers[$index] ?? '') !== 'phone' && preg_match('/^[=+\-@]/', ltrim((string) $cell->getValue())))) {
                            throw new InvalidArgumentException(__('Formula-like cell values are not accepted in customer imports.'));
                        }
                    }$rows[] = array_combine($headers, array_slice(array_pad($values, count($headers), ''), 0, count($headers)));
            }

            return $rows;
        } finally {
            $reader->close();
        }
    }

    /** @param list<array<string, string|int>> $rows @return list<array{raw:array<string,string|int>,errors:list<string>,duplicate_existing:bool,duplicate_file:bool}> */
    public static function validateRows(array $rows, string $mode = 'create_only', ?Store $store = null): array
    {
        $seen = [];

        return array_map(function (array $raw) use (&$seen, $mode, $store): array {
            $errors = [];
            $duplicateExisting = false;
            $duplicateFile = false;
            foreach (['first_name_ar', 'last_name_ar', 'phone', 'consent_purpose', 'consent_status'] as $field) {
                if (trim((string) ($raw[$field] ?? '')) === '') {
                    $errors[] = "$field is required";
                }
            }if (($raw['consent_status'] ?? '') !== 'granted') {
                $errors[] = 'Consent must be granted';
            }try {
                $phone = PhoneNormalizer::normalize((string) ($raw['phone'] ?? ''));
            } catch (InvalidArgumentException $exception) {
                $phone = '';
                $errors[] = __('Enter a valid Egyptian phone number, for example 01012345678 or +20 1012345678.');
            }if ($phone !== '' && isset($seen[$phone])) {
                $errors[] = __('Duplicate phone in this import batch');
                $duplicateFile = true;
            }if ($phone !== '') {
                try {
                    $secondary = filled($raw['secondary_phone'] ?? null) ? PhoneNormalizer::normalize((string) $raw['secondary_phone']) : null;
                } catch (InvalidArgumentException) {
                    $secondary = null;
                    $errors[] = __('Enter a valid phone number.');
                }
                if ($secondary === $phone) {
                    $errors[] = __('Primary and secondary phone numbers must be different.');
                }
                if ($secondary !== null && isset($seen[$secondary])) {
                    $errors[] = __('Duplicate phone in this import batch');
                    $duplicateFile = true;
                }
                $seen[$phone] = true;
                if ($secondary !== null) {
                    $seen[$secondary] = true;
                }
                $customer = Customer::query()->where(fn ($query) => $query->whereIn('phone_normalized', array_filter([$phone, $secondary]))->orWhereIn('secondary_phone_normalized', array_filter([$phone, $secondary])))->first();
                if ($mode === 'update_existing') {
                    if ($customer === null) {
                        $errors[] = 'No active customer matches this phone for Update Existing.';
                    } else {
                        $raw['customer_id'] = $customer->id;
                    }
                } elseif ($customer !== null) {
                    $errors[] = __('A normalized phone is already used by an existing customer.');
                    $duplicateExisting = true;
                }
            }$groupCode = strtoupper(trim((string) ($raw['customer_group_code'] ?? '')));
            if ($groupCode !== '') {
                if ($store === null) {
                    $errors[] = 'Customer group imports require a configured stable group code.';
                } else {
                    $groups = CustomerGroup::query()->forCompany((int) $store->company_id)->active()->whereDoesntHave('children')->where('code', $groupCode)->get(['id']);
                    if ($groups->count() === 1) {
                        $raw['customer_group_id'] = $groups->first()->id;
                    } else {
                        $errors[] = __('Customer group code is missing, inactive, or not a selectable leaf group.');
                    }
                }
            }
            if ($store !== null) {
                $governorate = Governorate::query()->active()->where('code', strtoupper(trim((string) ($raw['governorate_code'] ?? ''))))->first();
                $city = $governorate ? City::query()->visibleToCompany((int) $store->company_id)->active()->where('governorate_id', $governorate->id)->where('code', strtoupper(trim((string) ($raw['city_code'] ?? ''))))->first() : null;
                if ($governorate === null || $city === null) {
                    $errors[] = __('Governorate and city/locality codes must resolve to an active residence.');
                } else {
                    $raw['governorate_id'] = $governorate->id;
                    $raw['city_id'] = $city->id;
                }
            }

            return ['raw' => $raw, 'errors' => array_values(array_unique($errors)), 'duplicate_existing' => $duplicateExisting, 'duplicate_file' => $duplicateFile];
        }, $rows);
    }

    public function stage(array $rows, string $filename, string $mode, int $userId, Store $store): CustomerImportBatch
    {
        Gate::forUser(auth()->user())->authorize($mode === 'update_existing' ? 'customers.edit' : 'customers.create');
        if (! in_array($mode, ['create_only', 'update_existing'], true)) {
            throw new InvalidArgumentException('Unsupported import mode.');
        }

        return DB::transaction(function () use ($rows, $filename, $mode, $userId, $store) {
            $b = CustomerImportBatch::create(['company_id' => $store->company_id, 'created_by' => $userId, 'original_filename' => $filename, 'mode' => $mode, 'status' => 'ready_for_review', 'total_rows' => count($rows), 'headers' => self::FIELDS]);
            $valid = 0;
            $invalid = 0;
            $duplicateExisting = 0;
            $duplicateFile = 0;
            foreach (self::validateRows($rows, $mode, $store) as $i => $row) {
                $errors = $row['errors'];
                $status = $errors ? 'invalid' : 'valid';
                $b->rows()->create(['row_number' => $i + 2, 'raw_data' => $row['raw'], 'mapped_data' => $row['raw'], 'errors' => $errors, 'status' => $status]);
                $errors ? $invalid++ : $valid++;
                $duplicateExisting += $row['duplicate_existing'] ? 1 : 0;
                $duplicateFile += $row['duplicate_file'] ? 1 : 0;
            }$b->update(['valid_rows' => $valid, 'invalid_rows' => $invalid, 'duplicate_existing_rows' => $duplicateExisting, 'duplicate_file_rows' => $duplicateFile]);
            app(RecordAuditEvent::class)->execute('customer_import', 'stage_customer_import', $b, after: $b->only(['id', 'mode', 'status', 'total_rows', 'valid_rows', 'invalid_rows']));
            app(NotifyImportReviewers::class)->execute($b->created_by, 'customers.import.approve', 'customer', $b->original_filename, 'customers.import', $b->id);

            return $b;
        });
    }
}

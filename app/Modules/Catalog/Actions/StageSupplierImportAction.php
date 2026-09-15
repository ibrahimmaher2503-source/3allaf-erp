<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Catalog\Models\SupplierImportBatch;
use App\Modules\Catalog\Models\SupplierImportRow;
use App\Modules\Catalog\Support\SupplierSettlementMethod;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Actions\NotifyImportReviewers;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use App\Support\DataExchange\ImportWorkbook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

class StageSupplierImportAction
{
    public const HEADERS = ['code', 'name_ar', 'name_en', 'contact_name', 'email', 'phone', 'tax_number', 'payment_terms', 'settlement_method', 'settlement_other_description', 'address', 'status', 'supplier_group_code'];

    public static function templateHeaders(): array
    {
        return self::HEADERS;
    }

    public function stage(string $file, string $name, string $mode, int $userId): SupplierImportBatch
    {
        if (! in_array($mode, ['create_only', 'update_existing'], true)) {
            throw new InvalidArgumentException(__('The selected import mode is not supported.'));
        } Gate::authorize($mode === 'update_existing' ? 'suppliers.edit' : 'suppliers.create');
        $path = Storage::disk('local')->path($file);
        if (! is_file($path)) {
            throw new InvalidArgumentException(__('The staged import file could not be found.'));
        } $hash = hash_file('sha256', $path);
        if (SupplierImportBatch::where('created_by', $userId)->where('sha256', $hash)->where('status', '!=', 'cancelled')->exists()) {
            throw new InvalidArgumentException(__('This import file was already staged by this user.'));
        }
        $actor = auth()->user(); abort_unless($actor instanceof \App\Models\User && $actor->id === $userId, 403);
        $company = app(AuthorizedCompanyContext::class)->resolve($actor, request()->input('company_id'));
        $reader = ReaderFactory::createFromFile($path);
        $reader->open($path);
        $headers = [];
        $rows = [];
        $n = 0;
        try {
            ImportWorkbook::assertMetadata($reader, 'toyjoy.suppliers.v2', $company->code);
            $sheet = ImportWorkbook::dataSheet($reader);
            foreach ($sheet->getRowIterator() as $row) {
                    $n++;
                    $cells = $row->getCells();
                    $v = array_map(function ($c) {
                        $value = $c->getValue();
                        if ($c instanceof FormulaCell || (is_string($value) && str_starts_with(trim($value), '='))) {
                            throw new InvalidArgumentException(__('Formula cells are not allowed in import files.'));
                        }

                        return $value;
                    }, $cells);
                    if ($n === 1) {
                        $headers = array_map(fn ($x) => strtolower(trim((string) $x)), $v);
                        ImportWorkbook::assertHeaders($headers, self::HEADERS, __('The import headers must exactly match the downloaded template.'));

                        continue;
                    }if ($n > 5001) {
                        throw new InvalidArgumentException(__('The import is limited to 5,000 data rows.'));
                    }if (collect($v)->every(fn ($x) => trim((string) $x) === '')) {
                        continue;
                    }$raw = [];
                    foreach ($headers as $i => $h) {
                        if ($h !== '') {
                            $raw[$h] = $v[$i] ?? null;
                        }
                    }$rows[] = new SupplierImportRow(['row_number' => $n, 'raw_data' => $raw, 'status' => 'staged', 'errors' => []]);
            }
            $companyId = $company->id;
            $batch = SupplierImportBatch::create(['company_id' => $companyId, 'created_by' => $userId, 'original_filename' => $name, 'storage_path' => $file, 'sha256' => $hash, 'mode' => $mode, 'status' => 'mapping_required', 'headers' => $headers, 'total_rows' => count($rows)]);
            $batch->rows()->saveMany($rows);
            app(RecordAuditEvent::class)->execute(category: 'catalog_import', event: 'stage_supplier_import', source: $batch, after: $batch->only(['id', 'mode', 'total_rows', 'status']));

            return $batch->fresh();
        } finally {
            $reader->close();
        }
    }

    public function applyMapping(SupplierImportBatch $batch, array $mapping): SupplierImportBatch
    {
        Gate::authorize($batch->mode === 'update_existing' ? 'suppliers.edit' : 'suppliers.create');
        abort_unless($batch->created_by === auth()->id(), 404);

        return DB::transaction(function () use ($batch, $mapping) {
            $batch->load('rows');
            $seen = [];
            $seenPhones = [];
            $seenEmails = [];
            $valid = 0;
            $invalid = 0;
            foreach ($batch->rows as $row) {
                $d = [];
                $errors = [];
                foreach ($mapping as $source => $target) {
                    $d[$target] = $row->raw_data[$source] ?? null;
                }$code = strtoupper(trim((string) ($d['code'] ?? '')));
                if ($code === '' || trim((string) ($d['name_ar'] ?? '')) === '' || trim((string) ($d['name_en'] ?? '')) === '') {
                    $errors[] = __('Supplier code and bilingual names are required.');
                }if (isset($seen[$code])) {
                    $errors[] = __('The supplier code is duplicated in this batch.');
                }$seen[$code] = true;
                $existing = Supplier::where('code', $code)->first();
                $method = trim((string) ($d['settlement_method'] ?? ''));
                if (! SupplierSettlementMethod::isValid($method)) {
                    $errors[] = __('The supplier settlement method is invalid.');
                }
                if ($method === 'other' && blank($d['settlement_other_description'] ?? null)) {
                    $errors[] = __('Describe the supplier settlement method when Other is selected.');
                }
                if (! in_array($d['status'] ?? '', ['active', 'inactive'], true)) {
                    $errors[] = __('The selected supplier status is not supported.');
                }
                if (filled($d['phone'] ?? null)) {
                    try {
                        $d['phone'] = PhoneNormalizer::normalize((string) $d['phone']);
                        if (isset($seenPhones[$d['phone']])) {
                            $errors[] = __('The supplier phone is duplicated in this batch.');
                        }
                        $seenPhones[$d['phone']] = true;
                        if (Supplier::query()->when($existing, fn ($query) => $query->whereKeyNot($existing->id))->where('phone', $d['phone'])->exists()) {
                            $errors[] = __('The supplier phone is already used by another supplier.');
                        }
                    } catch (InvalidArgumentException) {
                        $errors[] = __('Enter a valid phone number.');
                    }
                }
                if (filled($d['email'] ?? null)) {
                    $d['email'] = mb_strtolower(trim((string) $d['email']));
                    if (! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
                        $errors[] = __('Enter a valid email address.');
                    } elseif (isset($seenEmails[$d['email']])) {
                        $errors[] = __('The supplier email is duplicated in this batch.');
                    } elseif (Supplier::query()->when($existing, fn ($query) => $query->whereKeyNot($existing->id))->whereRaw('LOWER(email) = ?', [$d['email']])->exists()) {
                        $errors[] = __('The supplier email is already used by another supplier.');
                    }
                    $seenEmails[$d['email']] = true;
                }
                if ($batch->mode === 'create_only' && $existing) {
                    $errors[] = __('The supplier code already exists; Create Only does not update existing suppliers.');
                }if ($batch->mode === 'update_existing' && ! $existing) {
                    $errors[] = __('The supplier code does not exist; Update Existing does not create new suppliers.');
                }$groupCode = strtoupper(trim((string) ($d['supplier_group_code'] ?? '')));
                if ($groupCode !== '') {
                    $groups = SupplierGroup::query()->forCompany((int) $batch->company_id)->active()->where('code', $groupCode)->pluck('id');
                    if ($groups->count() !== 1) {
                        $errors[] = __('Supplier group does not resolve to exactly one active group.');
                    } else {
                        $d['supplier_group_id'] = $groups->first();
                    }
                }$row->update(['mapped_data' => ['code' => $code] + $d, 'errors' => $errors, 'status' => $errors ? 'invalid' : 'valid']);
                $errors ? $invalid++ : $valid++;
            } $batch->update(['column_mapping' => $mapping, 'valid_rows' => $valid, 'invalid_rows' => $invalid, 'status' => 'ready_for_review']);
            app(NotifyImportReviewers::class)->execute($batch->created_by, 'suppliers.edit', 'supplier', $batch->original_filename, 'catalog.suppliers.import', $batch->id);

            return $batch->fresh();
        });
    }

    public function approve(SupplierImportBatch $batch): SupplierImportBatch
    {
        Gate::authorize('suppliers.edit');
        if ($batch->created_by === auth()->id() && ! auth()->user()?->canBypassApproval()) {
            throw ValidationException::withMessages(['approval' => __('The requester cannot approve their own import batch.')]);
        }

        return DB::transaction(function () use ($batch) {
            $batch = SupplierImportBatch::lockForUpdate()->findOrFail($batch->id);
            if ($batch->status !== 'ready_for_review' || $batch->valid_rows < 1) {
                throw new InvalidArgumentException(__('The import has no valid rows to approve.'));
            }foreach ($batch->rows()->where('status', 'valid')->lockForUpdate()->get() as $row) {
                $d = $row->mapped_data;
                $existing = Supplier::where('code', $d['code'])->first();
                $saved = app(SaveSupplierAction::class)->execute($d, $existing?->id, $existing?->lock_version, (int) $batch->company_id);
                $row->update(['status' => $existing ? 'updated' : 'created', 'supplier_id' => $saved->id]);
            }$batch->update(['status' => 'completed', 'added_rows' => $batch->valid_rows, 'approved_at' => now()]);
            app(RecordAuditEvent::class)->execute(category: 'catalog_import', event: 'approve_supplier_import', source: $batch, after: $batch->only(['id', 'status', 'total_rows', 'valid_rows']));

            return $batch->fresh();
        });
    }
}

<?php

namespace App\Modules\Catalog\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\AgeLabel;
use App\Modules\Catalog\Models\Character;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Colour;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImportBatch;
use App\Modules\Catalog\Models\ProductImportRow;
use App\Modules\Catalog\Models\Gender;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use App\Support\DataExchange\ImportWorkbook;
use App\Modules\Platform\Actions\LinkAttachmentToSource;
use App\Modules\Platform\Actions\NotifyImportReviewers;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Data\AttachmentSourceReference;
use App\Modules\Platform\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use Throwable;

class StageProductImportAction
{
    private const REQUIRED_FIELDS = ['name_ar', 'name_en', 'category_code'];

    private const ALLOWED_FIELDS = [
        'item_code', 'name_ar', 'name_en', 'description_ar', 'description_en', 'model_number',
        'product_type', 'unit_of_measure', 'category_code', 'brand_code', 'status', 'colour', 'size',
        'character', 'fractional_quantity', 'keywords_ar', 'keywords_en', 'key_points_ar', 'key_points_en',
        'preferred_supplier_code', 'average_cost', 'sale_price', 'dimension_length', 'dimension_width',
        'dimension_height', 'dimension_unit', 'weight', 'battery_required', 'battery_details', 'target_age',
        'age_code', 'age_codes', 'character_code', 'character_codes', 'colour_code', 'colour_codes',
        'gender_code', 'gender_codes',
    ];

    /** @return array<int, string> */
    public static function supportedFields(): array
    {
        return self::ALLOWED_FIELDS;
    }

    public function stage(string|Attachment $sourceFile, string $originalFilename, string $mode, int $userId): ProductImportBatch
    {
        if (! in_array($mode, ['create_only', 'update_existing'], true)) {
            throw new InvalidArgumentException(__('The selected import mode is not supported.'));
        }

        Gate::authorize($mode === 'update_existing' ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        $attachment = $sourceFile instanceof Attachment ? $sourceFile : null;
        $storageDisk = $attachment?->storage_disk ?? 'local';
        $storagePath = $attachment?->storage_path ?? $sourceFile;
        $absolutePath = Storage::disk($storageDisk)->path($storagePath);
        if (! is_file($absolutePath)) {
            throw new InvalidArgumentException(__('The staged import file could not be found.'));
        }

        $hash = hash_file('sha256', $absolutePath);
        if (! is_string($hash)) {
            throw new InvalidArgumentException(__('The import file could not be fingerprinted.'));
        }

        if (ProductImportBatch::query()->where('created_by', $userId)->where('sha256', $hash)->where('status', '!=', 'cancelled')->exists()) {
            throw new InvalidArgumentException(__('This import file was already staged by this user.'));
        }

        $actor = auth()->user(); abort_unless($actor instanceof User && $actor->id === $userId, 403);
        $company = app(AuthorizedCompanyContext::class)->resolve($actor, request()->input('company_id'));
        $reader = ReaderFactory::createFromFile($absolutePath);
        $reader->open($absolutePath);

        try {
            ImportWorkbook::assertMetadata($reader, 'toyjoy.products.staged.v2', $company->code);
            $headers = null;
            $rows = [];
            $rowNumber = 0;
            $sheet = ImportWorkbook::dataSheet($reader);
            foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $values = array_map(static fn ($cell): mixed => $cell->getValue(), $row->getCells());

                    if ($rowNumber === 1) {
                        $headers = array_map(fn (mixed $value): string => $this->normalizeHeader($value), $values);
                        ImportWorkbook::assertHeaders($headers, self::ALLOWED_FIELDS, __('The spreadsheet headers do not match the current Product import template.'));
                        $this->assertUniqueSourceHeaders($headers);

                        continue;
                    }

                    if ($rowNumber > 5001) {
                        throw new InvalidArgumentException(__('The import is limited to 5,000 data rows.'));
                    }

                    if ($this->isBlankRow($values)) {
                        continue;
                    }

                    $raw = [];
                    foreach ($headers as $index => $header) {
                        if ($header !== '') {
                            $raw[$header] = $values[$index] ?? null;
                        }
                    }

                    $errors = $this->formulaErrors($row->getCells());
                    $rows[] = new ProductImportRow([
                        'row_number' => $rowNumber,
                        'raw_data' => $raw,
                        'errors' => $errors,
                        'status' => $errors === [] ? 'staged' : 'invalid',
                    ]);

                    if (count($rows) >= 250) {
                        $this->persistRows($rows, $userId, $originalFilename, $storagePath, $hash, $mode, $headers);
                        $rows = [];
                    }
            }

            $batch = $this->persistRows($rows, $userId, $originalFilename, $storagePath, $hash, $mode, $headers ?? []);
            $batch->loadCount(['rows as total_rows_count']);
            $batch->update([
                'total_rows' => $batch->rows()->count(),
                'valid_rows' => 0,
                'invalid_rows' => $batch->rows()->where('status', 'invalid')->count(),
                'status' => 'mapping_required',
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'catalog_import',
                event: 'stage_product_import',
                source: $batch,
                after: $batch->only(['id', 'original_filename', 'mode', 'headers', 'total_rows', 'valid_rows', 'invalid_rows', 'status']),
            );

            if ($attachment !== null) {
                app(LinkAttachmentToSource::class)->execute(
                    $attachment,
                    new AttachmentSourceReference(ProductImportBatch::class, (string) $batch->id),
                    fn (User $user, Attachment $candidate, AttachmentSourceReference $reference): bool => $user->id === $userId
                        && $candidate->uploaded_by === $userId
                        && $candidate->purpose === 'import_source'
                        && $reference->sourceType === ProductImportBatch::class
                        && $reference->sourceId === (string) $batch->id,
                );
            }

            return $batch->fresh();
        } catch (Throwable $exception) {
            ProductImportBatch::query()
                ->where('created_by', $userId)
                ->where('sha256', $hash)
                ->where('status', 'staging')
                ->get()
                ->each(fn (ProductImportBatch $batch): bool => (bool) $batch->delete());
            if ($attachment === null) {
                Storage::disk($storageDisk)->delete($storagePath);
            }
            throw $exception;
        } finally {
            $reader->close();
        }
    }

    /** @param array<string, mixed> $mapping */
    public function applyMapping(ProductImportBatch $batch, array $mapping): ProductImportBatch
    {
        Gate::authorize($batch->mode === 'update_existing' ? 'products_categories_brands.edit' : 'products_categories_brands.create');
        abort_unless($batch->created_by === auth()->id(), 404);
        $actor = auth()->user(); abort_unless($actor instanceof User, 403);
        $company = app(AuthorizedCompanyContext::class)->resolve($actor, request()->input('company_id'));

        return DB::transaction(function () use ($batch, $mapping, $company): ProductImportBatch {
            $batch = ProductImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            abort_unless($batch->created_by === auth()->id(), 404);

            if ($batch->status !== 'mapping_required') {
                throw new InvalidArgumentException(__('Only an import awaiting column mapping can be mapped.'));
            }

            $mapping = $this->assertMapping($batch->headers ?? [], $mapping);
            $seenCodes = [];
            $validRows = 0;
            $invalidRows = 0;
            $categoryCodes = Category::query()->where('status', 'active')->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all();
            $brandCodes = Brand::query()->where('status', 'active')->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all();
            $supplierCodes = Supplier::query()->where('status', 'active')->where(fn ($query) => $query->whereNull('supplier_group_id')->orWhereHas('supplierGroup', fn ($groups) => $groups->where('company_id', $company->id)))->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all();
            $lookupCodes = [
                'age' => AgeLabel::query()->where('status', 'active')->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all(),
                'character' => Character::query()->where('status', 'active')->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all(),
                'colour' => Colour::query()->where('status', 'active')->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all(),
                'gender' => Gender::query()->where('status', 'active')->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all(),
            ];

            foreach ($batch->rows()->orderBy('row_number')->lockForUpdate()->get() as $row) {
                $raw = [];
                foreach ($mapping as $sourceHeader => $targetField) {
                    $raw[$targetField] = data_get($row->raw_data, $sourceHeader);
                }

                $mapped = $this->mapRow(
                    $raw,
                    $batch->mode,
                    $categoryCodes,
                    $brandCodes, $supplierCodes, $lookupCodes,
                    $seenCodes,
                    $this->formulaErrorsFromRow($row),
                );
                $status = $mapped['errors'] === [] ? 'valid' : 'invalid';
                $row->update([
                    'mapped_data' => $mapped['data'],
                    'errors' => $mapped['errors'],
                    'status' => $status,
                ]);
                $status === 'valid' ? $validRows++ : $invalidRows++;
            }

            $batch->update([
                'column_mapping' => $mapping,
                'valid_rows' => $validRows,
                'invalid_rows' => $invalidRows,
                'status' => 'ready_for_review',
            ]);
            app(RecordAuditEvent::class)->execute(
                category: 'catalog_import',
                event: 'map_product_import_columns',
                source: $batch,
                after: $batch->only(['id', 'mode', 'headers', 'column_mapping', 'total_rows', 'valid_rows', 'invalid_rows', 'status']),
            );
            app(NotifyImportReviewers::class)->execute(
                $batch->created_by,
                'products_categories_brands.approve',
                'product',
                $batch->original_filename,
                'catalog.products.import',
                $batch->id,
            );

            return $batch->fresh();
        });
    }

    public function approve(ProductImportBatch $batch, SaveProductAction $saveProduct): ProductImportBatch
    {
        Gate::authorize('products_categories_brands.approve');

        return DB::transaction(function () use ($batch, $saveProduct): ProductImportBatch {
            $batch = ProductImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($batch->created_by === auth()->id() && ! auth()->user()?->canBypassApproval()) {
                throw ValidationException::withMessages([
                    'approval' => __('The requester cannot approve their own import batch.'),
                ]);
            }
            if ($batch->status !== 'ready_for_review' || $batch->invalid_rows > 0) {
                throw new InvalidArgumentException(__('Only a ready import with no rejected rows can be approved.'));
            }

            foreach ($batch->rows()->where('status', 'valid')->lockForUpdate()->get() as $row) {
                $data = $row->mapped_data ?? [];
                $product = Product::query()->where('item_code', $data['item_code'])->first();
                $saved = $saveProduct->execute($data, $product?->id, $product?->lock_version);
                $row->update(['status' => $product ? 'updated' : 'created', 'product_id' => $saved->id]);
            }

            $batch->update(['status' => 'completed', 'approved_at' => now()]);
            app(RecordAuditEvent::class)->execute(
                category: 'catalog_import',
                event: 'approve_product_import',
                source: $batch,
                after: $batch->only(['id', 'status', 'total_rows', 'valid_rows']),
            );

            return $batch->fresh();
        });
    }

    public function cancel(ProductImportBatch $batch): ProductImportBatch
    {
        Gate::authorize('products_categories_brands.create');
        abort_unless($batch->created_by === auth()->id(), 404);

        if (! in_array($batch->status, ['staging', 'mapping_required', 'ready_for_review'], true)) {
            throw new InvalidArgumentException(__('Only a staged or reviewable import can be cancelled.'));
        }

        $batch->update(['status' => 'cancelled']);
        app(RecordAuditEvent::class)->execute(
            category: 'catalog_import',
            event: 'cancel_product_import',
            source: $batch,
            after: ['status' => 'cancelled'],
        );

        return $batch->fresh();
    }

    /** @param array<int, mixed> $cells */
    private function formulaErrors(array $cells): array
    {
        foreach ($cells as $cell) {
            if ($cell instanceof FormulaCell || (is_string($cell->getValue()) && str_starts_with(trim($cell->getValue()), '='))) {
                return [$this->formulaErrorMessage()];
            }
        }

        return [];
    }

    /** @param array<string, mixed> $raw @param array<string, int> $categoryCodes @param array<string, int> $brandCodes @param array<string, int> $supplierCodes @param array<string, array<string, int>> $lookupCodes @param array<string, bool> $seenCodes @param array<int, string> $initialErrors */
    private function mapRow(array $raw, string $mode, array $categoryCodes, array $brandCodes, array $supplierCodes, array $lookupCodes, array &$seenCodes, array $initialErrors): array
    {
        $errors = $initialErrors;
        $itemCode = strtoupper(trim((string) ($raw['item_code'] ?? '')));
        $categoryCode = strtoupper(trim((string) ($raw['category_code'] ?? '')));
        $brandCode = strtoupper(trim((string) ($raw['brand_code'] ?? '')));
        $type = strtolower(trim((string) ($raw['product_type'] ?? 'standard')));
        $status = strtolower(trim((string) ($raw['status'] ?? 'active')));

        if ($itemCode !== '' && isset($seenCodes[$itemCode])) {
            $errors[] = __('The item code is duplicated in this batch.');
        }
        if ($itemCode !== '') {
            $seenCodes[$itemCode] = true;
        }
        if (trim((string) ($raw['name_ar'] ?? '')) === '') {
            $errors[] = __('Arabic product name is required.');
        }
        if (trim((string) ($raw['name_en'] ?? '')) === '') {
            $errors[] = __('English product name is required.');
        }
        if (! isset($categoryCodes[$categoryCode])) {
            $errors[] = __('The category code is missing or inactive.');
        }
        if ($brandCode !== '' && ! isset($brandCodes[$brandCode])) {
            $errors[] = __('The brand code is missing or inactive.');
        }
        $supplierCode = strtoupper(trim((string) ($raw['preferred_supplier_code'] ?? '')));
        if ($supplierCode !== '' && ! isset($supplierCodes[$supplierCode])) $errors[] = __('The preferred supplier code is missing or inactive.');
        if ($mode === 'create_only' && $itemCode === '' && ! isset($supplierCodes[$supplierCode])) {
            $errors[] = __('An active preferred supplier is required when item_code is omitted.');
        }
        if ($mode === 'update_existing' && $itemCode === '') {
            $errors[] = __('Item code is required when updating existing products.');
        }
        if (! in_array($type, ['standard', 'composite', 'service', 'digital'], true)) {
            $errors[] = __('The product type is not supported.');
        }
        if (! in_array($status, ['active', 'inactive'], true)) {
            $errors[] = __('The product status is not supported.');
        }
        $existingProduct = $itemCode === '' ? null : Product::query()->where('item_code', $itemCode)->first();
        if ($existingProduct?->isFamily() || $existingProduct?->isVariant()) {
            $errors[] = __('Product imports support simple products only. Variation families and child SKUs must be managed in the variation matrix.');
        }
        if ($mode === 'create_only' && $existingProduct !== null) {
            $errors[] = __('The item code already exists; Create Only does not update existing products.');
        }
        if ($mode === 'update_existing' && $itemCode !== '' && $existingProduct === null) {
            $errors[] = __('The item code does not exist; Update Existing does not create new products.');
        }

        $data = [
            'item_code' => $itemCode,
            'name_ar' => trim((string) ($raw['name_ar'] ?? '')),
            'name_en' => trim((string) ($raw['name_en'] ?? '')),
            'description_ar' => $this->nullableString($raw['description_ar'] ?? null),
            'description_en' => $this->nullableString($raw['description_en'] ?? null),
            'model_number' => $this->nullableString($raw['model_number'] ?? null),
            'product_type' => $type,
            'unit_of_measure' => $this->nullableString($raw['unit_of_measure'] ?? null),
            'category_id' => $categoryCodes[$categoryCode] ?? null,
            'brand_id' => $brandCodes[$brandCode] ?? null,
            'preferred_supplier_id' => $supplierCodes[$supplierCode] ?? null,
            'status' => $status,
            'colour' => $this->nullableString($raw['colour'] ?? null),
            'size' => $this->nullableString($raw['size'] ?? null),
            'character' => $this->nullableString($raw['character'] ?? null),
            'fractional_quantity' => $this->booleanValue($raw['fractional_quantity'] ?? false),
            'average_cost' => $this->numericValue($raw['average_cost'] ?? null, $errors, 'average cost'),
            'sale_price' => $this->numericValue($raw['sale_price'] ?? null, $errors, 'sale price'),
            'dimension_length' => $this->numericValue($raw['dimension_length'] ?? null, $errors, 'dimension length'),
            'dimension_width' => $this->numericValue($raw['dimension_width'] ?? null, $errors, 'dimension width'),
            'dimension_height' => $this->numericValue($raw['dimension_height'] ?? null, $errors, 'dimension height'),
            'dimension_unit' => $this->nullableString($raw['dimension_unit'] ?? null),
            'weight' => $this->numericValue($raw['weight'] ?? null, $errors, 'weight'),
            'battery_required' => $this->booleanValue($raw['battery_required'] ?? false),
            'battery_details' => $this->nullableString($raw['battery_details'] ?? null),
            'keywords_ar' => $this->nullableString($raw['keywords_ar'] ?? null),
            'keywords_en' => $this->nullableString($raw['keywords_en'] ?? null),
            'key_points_ar' => $this->nullableString($raw['key_points_ar'] ?? null),
            'key_points_en' => $this->nullableString($raw['key_points_en'] ?? null),
        ];

        foreach (['age' => 'age_label_ids', 'character' => 'character_ids', 'colour' => 'colour_ids', 'gender' => 'gender_ids'] as $kind => $field) {
            $value = $raw[$kind.'_codes'] ?? $raw[$kind.'_code'] ?? null;
            if ($value === null || trim((string) $value) === '') continue;
            $ids = [];
            foreach (preg_split('/[,;|]/', (string) $value) ?: [] as $code) {
                $code = strtoupper(trim($code));
                if ($code !== '' && isset($lookupCodes[$kind][$code])) $ids[] = $lookupCodes[$kind][$code];
                elseif ($code !== '') $errors[] = __('The :kind code is missing or inactive.', ['kind' => $kind]);
            }
            $data[$field] = array_values(array_unique($ids));
            if ($kind === 'age') $data['age_label_id'] = $ids[0] ?? null;
            if ($kind === 'character') $data['character_id'] = $ids[0] ?? null;
            if ($kind === 'colour') $data['colour_id'] = $ids[0] ?? null;
            if ($kind === 'gender') $data['gender_id'] = $ids[0] ?? null;
        }

        return ['data' => $data, 'errors' => array_values(array_unique($errors))];
    }

    private function normalizeHeader(mixed $value): string
    {
        $header = strtolower(trim((string) $value));
        $header = preg_replace('/[^a-z0-9_]+/', '_', $header) ?? '';

        return trim($header, '_');
    }

    /** @param array<int, string> $headers */
    private function assertUniqueSourceHeaders(array $headers): void
    {
        $nonBlankHeaders = array_values(array_filter($headers));
        if (count($nonBlankHeaders) !== count(array_unique($nonBlankHeaders))) {
            throw new InvalidArgumentException(__('Duplicate source column headers cannot be mapped safely.'));
        }
    }

    /** @param array<int, string> $headers @param array<string, mixed> $mapping @return array<string, string> */
    private function assertMapping(array $headers, array $mapping): array
    {
        $headers = array_values(array_filter($headers));
        $mapping = collect($mapping)
            ->filter(fn (mixed $target): bool => is_string($target) && trim($target) !== '')
            ->mapWithKeys(fn (mixed $target, mixed $source): array => [(string) $source => trim((string) $target)])
            ->all();

        if (array_diff(array_keys($mapping), $headers) !== []) {
            throw new InvalidArgumentException(__('The selected source column does not exist in this import file.'));
        }
        if (array_diff($mapping, self::ALLOWED_FIELDS) !== []) {
            throw new InvalidArgumentException(__('The selected product field is not supported by this import.'));
        }
        if (count($mapping) !== count(array_unique($mapping))) {
            throw new InvalidArgumentException(__('Each product field can be mapped from only one source column.'));
        }
        $missing = array_diff(self::REQUIRED_FIELDS, $mapping);
        if ($missing !== []) {
            throw new InvalidArgumentException(__('Required product fields are not mapped: :fields', ['fields' => implode(', ', $missing)]));
        }

        return $mapping;
    }

    private function formulaErrorMessage(): string
    {
        return __('Formula cells are not accepted in product imports.');
    }

    private function formulaErrorsFromRow(ProductImportRow $row): array
    {
        return in_array($this->formulaErrorMessage(), $row->errors ?? [], true) ? [$this->formulaErrorMessage()] : [];
    }

    /** @param array<int, mixed> $values */
    private function isBlankRow(array $values): bool
    {
        return collect($values)->every(fn (mixed $value): bool => trim((string) $value) === '');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function booleanValue(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'نعم'], true);
    }

    private function numericValue(mixed $value, array &$errors, string $label): float|int|null
    {
        if ($value === null || trim((string) $value) === '') return null;
        if (! is_numeric($value) || (float) $value < 0) { $errors[] = __('The :field must be zero or greater.', ['field' => $label]); return null; }
        return (float) $value;
    }

    /** @param array<int, ProductImportRow> $rows */
    private function persistRows(array $rows, int $userId, string $filename, string $path, string $hash, string $mode, array $headers): ProductImportBatch
    {
        $batch = ProductImportBatch::query()->firstOrCreate(
            ['created_by' => $userId, 'sha256' => $hash],
            [
                'original_filename' => $filename,
                'storage_path' => $path,
                'mime_type' => mime_content_type(Storage::disk('local')->path($path)) ?: null,
                'size_bytes' => Storage::disk('local')->size($path),
                'mode' => $mode,
                'status' => 'staging',
                'headers' => $headers,
                'column_mapping' => null,
            ],
        );

        if ($batch->wasRecentlyCreated === false && $batch->status === 'cancelled' && $rows !== []) {
            $batch->rows()->delete();
            $batch->update([
                'original_filename' => $filename,
                'storage_path' => $path,
                'mime_type' => mime_content_type(Storage::disk('local')->path($path)) ?: null,
                'size_bytes' => Storage::disk('local')->size($path),
                'mode' => $mode,
                'status' => 'staging',
                'headers' => $headers,
                'column_mapping' => null,
                'total_rows' => 0,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'approved_at' => null,
            ]);
        }

        foreach ($rows as $row) {
            $row->product_import_batch_id = $batch->id;
            $row->save();
        }

        return $batch;
    }
}

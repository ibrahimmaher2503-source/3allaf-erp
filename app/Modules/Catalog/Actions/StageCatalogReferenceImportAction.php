<?php

namespace App\Modules\Catalog\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\{AgeLabel, Brand, CatalogReferenceImportBatch, CatalogReferenceImportRow, Category, Character, Colour, Gender};
use App\Modules\Platform\Actions\{NotifyImportReviewers, RecordAuditEvent};
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use App\Support\DataExchange\ImportWorkbook;
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

class StageCatalogReferenceImportAction
{
    private const TYPES = ['category', 'brand', 'age', 'character', 'colour', 'gender'];

    public function stage(string $file, string $name, string $type, string $mode, int $userId): CatalogReferenceImportBatch
    {
        if (! in_array($type, self::TYPES, true) || ! in_array($mode, ['create_only', 'update_existing'], true)) throw new InvalidArgumentException(__('The selected import type or mode is not supported.'));
        Gate::authorize($mode === 'update_existing' ? 'products_categories_brands.edit' : 'products_categories_brands.create');
        $path = Storage::disk('local')->path($file);
        if (! is_file($path)) throw new InvalidArgumentException(__('The staged import file could not be found.'));
        $hash = hash_file('sha256', $path);
        if (! is_string($hash)) throw new InvalidArgumentException(__('The import file could not be fingerprinted.'));
        if (CatalogReferenceImportBatch::query()->where(['created_by' => $userId, 'type' => $type, 'mode' => $mode, 'sha256' => $hash])->where('status', '!=', 'cancelled')->exists()) throw new InvalidArgumentException(__('This import file was already staged by this user.'));

        $actor = auth()->user(); abort_unless($actor instanceof User && $actor->id === $userId, 403);
        $company = app(AuthorizedCompanyContext::class)->resolve($actor, request()->input('company_id'));
        $reader = ReaderFactory::createFromFile($path); $reader->open($path); $headers = []; $rows = []; $number = 0;
        try {
            ImportWorkbook::assertMetadata($reader, 'toyjoy.catalog-'.$type.'.v2', $company->code);
            $sheet = ImportWorkbook::dataSheet($reader); foreach ($sheet->getRowIterator() as $row) {
                $number++; $cells = $row->getCells(); $values = array_map(fn ($cell) => $cell->getValue(), $cells);
                if ($number === 1) { $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $values); ImportWorkbook::assertHeaders($headers, self::templateHeaders($type), __('The spreadsheet headers do not match this template.')); continue; }
                if ($number > 5001) throw new InvalidArgumentException(__('The import is limited to 5,000 data rows.'));
                if (collect($values)->every(fn ($value) => trim((string) $value) === '')) continue;
                $raw = array_combine($headers, array_pad($values, count($headers), null));
                $errors = collect($cells)->contains(fn ($cell) => $cell instanceof FormulaCell || (is_string($cell->getValue()) && str_starts_with(trim($cell->getValue()), '='))) ? [__('Spreadsheet formulas are not allowed.')] : [];
                $rows[] = new CatalogReferenceImportRow(['row_number' => $number, 'raw_data' => $raw, 'errors' => $errors, 'status' => $errors ? 'invalid' : 'staged']);
            }
            $batch = CatalogReferenceImportBatch::create(['type' => $type, 'mode' => $mode, 'created_by' => $userId, 'original_filename' => $name, 'storage_path' => $file, 'sha256' => $hash, 'status' => 'staged', 'headers' => $headers, 'total_rows' => count($rows)]);
            $batch->rows()->saveMany($rows); app(RecordAuditEvent::class)->execute(category: 'catalog_import', event: 'stage_catalog_reference_import', source: $batch, after: $batch->only(['id', 'type', 'mode', 'total_rows', 'status'])); return $batch->fresh();
        } finally { $reader->close(); }
    }

    public function validate(CatalogReferenceImportBatch $batch): CatalogReferenceImportBatch
    {
        Gate::authorize($batch->mode === 'update_existing' ? 'products_categories_brands.edit' : 'products_categories_brands.create'); abort_unless($batch->created_by === auth()->id(), 404);
        return DB::transaction(function () use ($batch): CatalogReferenceImportBatch {
            $batch = CatalogReferenceImportBatch::query()->lockForUpdate()->findOrFail($batch->id); $seen = []; $valid = 0; $invalid = 0;
            $sheetCodes = $batch->rows()->pluck('raw_data')->map(fn ($data) => strtoupper(trim((string) ($data['code'] ?? ''))))->filter()->flip();
            foreach ($batch->rows()->lockForUpdate()->get() as $row) {
                $data = $row->raw_data; $code = strtoupper(trim((string) ($data['code'] ?? ''))); $errors = $row->errors ?? [];
                if ($code === '' || trim((string) ($data['name_ar'] ?? '')) === '' || trim((string) ($data['name_en'] ?? '')) === '') $errors[] = __('Code and bilingual names are required.');
                if (! in_array(strtolower((string) ($data['status'] ?? 'active')), ['active', 'inactive'], true)) $errors[] = __('Status must be active or inactive.');
                if (filter_var($data['sort_order'] ?? 0, FILTER_VALIDATE_INT) === false) $errors[] = __('Sort order must be a whole number.');
                if (isset($seen[$code])) $errors[] = __('The code is duplicated in this batch.'); $seen[$code] = true;
                $existing = $this->model($batch->type)::query()->where('code', $code)->exists();
                if ($batch->mode === 'create_only' && $existing) $errors[] = __('The code already exists; Create Only does not update existing master data.');
                if ($batch->mode === 'update_existing' && ! $existing) $errors[] = __('The code does not exist; Update Existing does not create new master data.');
                if ($batch->type === 'category' && filled($data['parent_code'] ?? null)) { $parent = strtoupper(trim((string) $data['parent_code'])); if ($parent === $code || (! Category::query()->where('code', $parent)->exists() && ! isset($sheetCodes[$parent]))) $errors[] = __('Parent category code does not exist in this batch or catalog.'); }
                $errors = array_values(array_unique($errors)); $row->update(['errors' => $errors, 'status' => $errors ? 'invalid' : 'valid']); $errors ? $invalid++ : $valid++;
            }
            $batch->update(['valid_rows' => $valid, 'invalid_rows' => $invalid, 'status' => 'ready_for_review']);
            app(NotifyImportReviewers::class)->execute($batch->created_by, 'products_categories_brands.approve', 'catalog_reference', $batch->original_filename, 'catalog.reference-import', $batch->id);

            return $batch->fresh();
        });
    }

    public function approve(CatalogReferenceImportBatch $batch): CatalogReferenceImportBatch
    {
        Gate::authorize('products_categories_brands.approve'); if ($batch->created_by === auth()->id() && ! auth()->user()?->canBypassApproval()) throw ValidationException::withMessages(['approval' => __('The requester cannot approve their own import batch.')]);
        return DB::transaction(function () use ($batch): CatalogReferenceImportBatch {
            $batch = CatalogReferenceImportBatch::query()->lockForUpdate()->findOrFail($batch->id); if ($batch->status !== 'ready_for_review' || $batch->valid_rows < 1) throw new InvalidArgumentException(__('Only a ready import with no rejected rows can be approved.'));
            $model = $this->model($batch->type); $rows = $batch->rows()->where('status', 'valid')->orderBy('row_number')->lockForUpdate()->get();
            foreach ($rows as $row) { $data = $row->raw_data; $code = strtoupper(trim((string) $data['code'])); $attributes = ['code' => $code, 'name_ar' => trim((string) $data['name_ar']), 'name_en' => trim((string) $data['name_en']), 'status' => strtolower((string) ($data['status'] ?? 'active'))]; if ($batch->type === 'category') $attributes['sort_order'] = (int) ($data['sort_order'] ?? 0); $record = $model::query()->where('code', $code)->lockForUpdate()->first(); if ($batch->mode === 'create_only') { if ($batch->type === 'category' || $batch->type === 'brand') $attributes += ['created_by' => auth()->id(), 'updated_by' => auth()->id()]; $model::query()->create($attributes); } else { if ($batch->type === 'category' || $batch->type === 'brand') $attributes['updated_by'] = auth()->id(); $record->update($attributes); } $row->update(['status' => $batch->mode === 'create_only' ? 'created' : 'updated']); }
            if ($batch->type === 'category') foreach ($rows as $row) { $parent = strtoupper(trim((string) ($row->raw_data['parent_code'] ?? ''))); if ($parent !== '') Category::query()->where('code', strtoupper(trim((string) $row->raw_data['code'])))->update(['parent_id' => Category::query()->where('code', $parent)->value('id'), 'updated_by' => auth()->id()]); }
            $batch->update(['status' => 'completed', 'approved_at' => now(), 'approved_by' => auth()->id()]); app(RecordAuditEvent::class)->execute(category: 'catalog_import', event: 'approve_catalog_reference_import', source: $batch, after: $batch->only(['id', 'type', 'mode', 'status', 'total_rows'])); return $batch->fresh();
        });
    }

    public static function templateHeaders(string $type): array { return $type === 'category' ? ['code', 'name_ar', 'name_en', 'parent_code', 'status', 'sort_order'] : ['code', 'name_ar', 'name_en', 'status', 'sort_order']; }
    private function model(string $type): string { return match ($type) { 'category' => Category::class, 'brand' => Brand::class, 'age' => AgeLabel::class, 'character' => Character::class, 'colour' => Colour::class, 'gender' => Gender::class }; }
}

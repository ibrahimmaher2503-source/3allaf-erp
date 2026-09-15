<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Actions\LinkAttachmentToSource;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Data\AttachmentSourceReference;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoiceImportBatch;
use App\Modules\Purchasing\Models\PurchaseInvoiceImportRow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use Throwable;

final class StagePurchaseInvoiceImportAction
{
    private const REQUIRED_HEADERS = ['supplier_code', 'supplier_invoice_number', 'invoice_date', 'receiving_store_code', 'quantity', 'unit_cost'];

    private const ALLOWED_HEADERS = [
        'supplier_code', 'supplier_invoice_number', 'invoice_date', 'receiving_store_code', 'purchase_order_number',
        'item_code', 'barcode', 'quantity', 'unit_cost', 'line_discount_value', 'line_discount_type',
        'tax_rate', 'tax_code', 'notes',
    ];

    public function stage(string|Attachment $sourceFile, string $originalFilename, string $mimeType, int $sizeBytes, int $userId): PurchaseInvoiceImportBatch
    {
        Gate::authorize('purchase_invoices_supplier_returns.create');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->getAuthIdentifier();
        abort_unless($userId === $actorId, 403);

        $attachment = $sourceFile instanceof Attachment ? $sourceFile : null;
        $storageDisk = $attachment?->storage_disk ?? 'local';
        $storagePath = $attachment?->storage_path ?? $sourceFile;
        $absolutePath = Storage::disk($storageDisk)->path($storagePath);
        if (! is_file($absolutePath)) {
            throw new InvalidArgumentException(__('The staged import file could not be found.'));
        }
        if ($sizeBytes > 10 * 1024 * 1024 || ! in_array(strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION)), ['xlsx', 'csv'], true)) {
            throw new InvalidArgumentException(__('Only .xlsx and .csv files up to 10 MB are accepted.'));
        }

        $hash = hash_file('sha256', $absolutePath);
        if (! is_string($hash)) {
            throw new InvalidArgumentException(__('The import file could not be fingerprinted.'));
        }
        if (PurchaseInvoiceImportBatch::query()->where('created_by', $userId)->where('sha256', $hash)->exists()) {
            throw new InvalidArgumentException(__('This import file was already staged by this user.'));
        }

        $batch = PurchaseInvoiceImportBatch::query()->create([
            'created_by' => $userId,
            'original_filename' => $originalFilename,
            'storage_path' => $storagePath,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'sha256' => $hash,
            'mode' => 'create_only',
            'status' => 'staging',
        ]);

        $reader = ReaderFactory::createFromFile($absolutePath);
        $reader->open($absolutePath);

        try {
            $headers = null;
            $rowNumber = 0;
            $rows = [];
            $seenReferences = [];
            $suppliers = Supplier::query()->where('status', 'active')->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all();
            $stores = Store::query()->visibleTo($actor)->where('status', 'active')->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all();
            $products = Product::query()->sellable()->pluck('id', 'item_code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all();
            $barcodes = Barcode::query()->active()->pluck('product_id', 'barcode')->mapWithKeys(fn ($id, $code) => [(string) $code => $id])->all();

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $cells = $row->getCells();
                    $values = array_map(static fn ($cell): mixed => $cell->getValue(), $cells);
                    if ($rowNumber === 1) {
                        $headers = array_map(fn (mixed $value): string => $this->normalizeHeader($value), $values);
                        $this->assertHeaders($headers);
                        $batch->update(['headers' => $headers]);

                        continue;
                    }
                    if ($rowNumber > 5001) {
                        throw new InvalidArgumentException(__('The import is limited to 5,000 data rows.'));
                    }
                    if ($this->isBlankRow($values)) {
                        continue;
                    }

                    $raw = [];
                    foreach ($headers ?? [] as $index => $header) {
                        if ($header !== '') {
                            $raw[$header] = $values[$index] ?? null;
                        }
                    }
                    $errors = $this->formulaErrors($cells);
                    $mapped = $this->mapRow($raw, $suppliers, $stores, $products, $barcodes, $seenReferences, $errors);
                    $rows[] = new PurchaseInvoiceImportRow([
                        'purchase_invoice_import_batch_id' => $batch->id,
                        'row_number' => $rowNumber,
                        'raw_data' => $raw,
                        'mapped_data' => $mapped,
                        'errors' => array_values(array_unique($errors)),
                        'status' => $errors === [] ? 'valid' : 'invalid',
                    ]);
                    if (count($rows) >= 250) {
                        $batch->rows()->saveMany($rows);
                        $rows = [];
                    }
                }
                break;
            }
            if ($rows !== []) {
                $batch->rows()->saveMany($rows);
            }

            $batch->update([
                'total_rows' => $batch->rows()->count(),
                'valid_rows' => $batch->rows()->where('status', 'valid')->count(),
                'invalid_rows' => $batch->rows()->where('status', 'invalid')->count(),
                'status' => 'ready_for_review',
            ]);
            app(RecordAuditEvent::class)->execute(
                category: 'procurement_import',
                event: 'stage_purchase_invoice_import',
                source: $batch,
                after: $batch->only(['id', 'original_filename', 'total_rows', 'valid_rows', 'invalid_rows']),
            );

            if ($attachment !== null) {
                app(LinkAttachmentToSource::class)->execute(
                    $attachment,
                    new AttachmentSourceReference(PurchaseInvoiceImportBatch::class, (string) $batch->id),
                    fn (User $user, Attachment $candidate, AttachmentSourceReference $reference): bool => $user->id === $userId
                        && $candidate->uploaded_by === $userId
                        && $candidate->purpose === 'import_source'
                        && $reference->sourceType === PurchaseInvoiceImportBatch::class
                        && $reference->sourceId === (string) $batch->id,
                );
            }

            return $batch->fresh();
        } catch (Throwable $exception) {
            $batch->delete();
            if ($attachment === null) {
                Storage::disk($storageDisk)->delete($storagePath);
            }
            throw $exception;
        } finally {
            $reader->close();
        }
    }

    public function createDrafts(PurchaseInvoiceImportBatch $batch, SavePurchaseInvoiceAction $saveAction): PurchaseInvoiceImportBatch
    {
        Gate::authorize('purchase_invoices_supplier_returns.approve');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($actorId, $batch, $saveAction): PurchaseInvoiceImportBatch {
            $batch = PurchaseInvoiceImportBatch::query()
                ->where('created_by', $actorId)
                ->lockForUpdate()
                ->findOrFail($batch->id);
            if ($batch->status !== 'ready_for_review' || $batch->invalid_rows > 0) {
                throw new InvalidArgumentException(__('Only a ready import with no rejected rows can create drafts.'));
            }

            $groups = [];
            foreach ($batch->rows()->where('status', 'valid')->lockForUpdate()->get() as $row) {
                $data = $row->mapped_data ?? [];
                $key = implode('|', [(string) $data['supplier_id'], (string) $data['supplier_reference'], (string) $data['store_id'], (string) $data['invoice_date']]);
                $groups[$key]['data'] = $data;
                $groups[$key]['lines'][] = $data;
                $groups[$key]['rows'][] = $row;
            }

            foreach ($groups as $group) {
                $invoice = $saveAction->execute($group['data'], $group['lines']);
                foreach ($group['rows'] as $row) {
                    $row->update(['status' => 'draft_created', 'purchase_invoice_id' => $invoice->id]);
                }
            }

            $batch->update(['status' => 'completed', 'approved_at' => now()]);
            app(RecordAuditEvent::class)->execute(
                category: 'procurement_import',
                event: 'create_purchase_invoice_drafts_from_import',
                source: $batch,
                after: $batch->only(['id', 'status', 'total_rows', 'valid_rows']),
            );

            return $batch->fresh();
        });
    }

    public function cancel(PurchaseInvoiceImportBatch $batch): PurchaseInvoiceImportBatch
    {
        Gate::authorize('purchase_invoices_supplier_returns.create');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($actorId, $batch): PurchaseInvoiceImportBatch {
            $batch = PurchaseInvoiceImportBatch::query()
                ->where('created_by', $actorId)
                ->lockForUpdate()
                ->findOrFail($batch->id);
            if (! in_array($batch->status, ['staging', 'ready_for_review'], true)) {
                throw new InvalidArgumentException(__('Only a staged or reviewable import can be cancelled.'));
            }
            $batch->update(['status' => 'cancelled']);
            app(RecordAuditEvent::class)->execute(category: 'procurement_import', event: 'cancel_purchase_invoice_import', source: $batch, after: ['status' => 'cancelled']);

            return $batch->fresh();
        });
    }

    /**
     * @param  array<int, mixed>  $cells
     * @return array<int, string>
     */
    private function formulaErrors(array $cells): array
    {
        foreach ($cells as $cell) {
            $value = $cell->getValue();
            if ($cell instanceof FormulaCell || (is_string($value) && preg_match('/^[=+\-@]/', ltrim($value)) === 1)) {
                return [(string) __('Formula-like or executable cell values are not accepted in purchase invoice imports.')];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, int>  $suppliers
     * @param  array<string, int>  $stores
     * @param  array<string, int>  $products
     * @param  array<string, int>  $barcodes
     * @param  array<string, bool>  $seenReferences
     * @param  array<int, string>  $errors
     * @return array<string, int|string|null>
     */
    private function mapRow(array $raw, array $suppliers, array $stores, array $products, array $barcodes, array &$seenReferences, array &$errors): array
    {
        $supplierCode = strtoupper(trim((string) ($raw['supplier_code'] ?? '')));
        $storeCode = strtoupper(trim((string) ($raw['receiving_store_code'] ?? '')));
        $itemCode = strtoupper(trim((string) ($raw['item_code'] ?? '')));
        $barcode = trim((string) ($raw['barcode'] ?? ''));
        $reference = trim((string) ($raw['supplier_invoice_number'] ?? ''));
        $productId = $itemCode !== '' ? ($products[$itemCode] ?? null) : ($barcodes[$barcode] ?? null);
        $supplierId = $suppliers[$supplierCode] ?? null;
        $storeId = $stores[$storeCode] ?? null;

        if ($supplierId === null) {
            $errors[] = (string) __('Supplier code is missing or inactive.');
        }
        if ($reference === '') {
            $errors[] = (string) __('Supplier invoice number is required.');
        }
        if ($reference !== '' && isset($seenReferences[$supplierCode.'|'.$reference])) {
            $errors[] = (string) __('Supplier invoice number is duplicated in this batch.');
        }
        if ($reference !== '') {
            $seenReferences[$supplierCode.'|'.$reference] = true;
        }
        if ($storeId === null) {
            $errors[] = (string) __('Receiving store code is missing or inactive.');
        }
        if ($productId === null) {
            $errors[] = (string) __('Item code or barcode is missing or unknown.');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) ($raw['invoice_date'] ?? '')))) {
            $errors[] = (string) __('Invoice date must use YYYY-MM-DD.');
        }
        if (filter_var($raw['quantity'] ?? null, FILTER_VALIDATE_INT) === false || (int) $raw['quantity'] <= 0) {
            $errors[] = (string) __('Quantity must be greater than zero.');
        }
        if (! is_numeric($raw['unit_cost'] ?? null) || (float) $raw['unit_cost'] < 0) {
            $errors[] = (string) __('Unit cost must be a non-negative number.');
        }

        return [
            'supplier_id' => $supplierId,
            'supplier_reference' => $reference,
            'store_id' => $storeId,
            'invoice_date' => trim((string) ($raw['invoice_date'] ?? '')),
            'purchase_order_id' => null,
            'product_id' => $productId,
            'quantity' => (string) ($raw['quantity'] ?? ''),
            'unit_cost' => (string) ($raw['unit_cost'] ?? ''),
            'discount_type' => trim((string) ($raw['line_discount_type'] ?? '')),
            'discount_value' => (string) ($raw['line_discount_value'] ?? '0'),
            'tax_rate' => (string) ($raw['tax_rate'] ?? '0'),
            'tax_code' => trim((string) ($raw['tax_code'] ?? '')),
            'notes' => trim((string) ($raw['notes'] ?? '')),
        ];
    }

    private function normalizeHeader(mixed $value): string
    {
        $header = strtolower(trim((string) $value));

        return trim(preg_replace('/[^a-z0-9_]+/', '_', $header) ?? '', '_');
    }

    /** @param array<int, string> $headers */
    private function assertHeaders(array $headers): void
    {
        $unknown = array_diff(array_filter($headers), self::ALLOWED_HEADERS);
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);
        if (! in_array('item_code', $headers, true) && ! in_array('barcode', $headers, true)) {
            $missing[] = 'item_code or barcode';
        }
        if ($unknown !== []) {
            throw new InvalidArgumentException(__('Unsupported import columns: :columns', ['columns' => implode(', ', $unknown)]));
        }
        if ($missing !== []) {
            throw new InvalidArgumentException(__('Required import columns are missing: :columns', ['columns' => implode(', ', $missing)]));
        }
    }

    /** @param array<int, mixed> $values */
    private function isBlankRow(array $values): bool
    {
        return array_reduce($values, static fn (bool $blank, mixed $value): bool => $blank && trim((string) $value) === '', true);
    }
}

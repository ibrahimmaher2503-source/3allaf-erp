<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ProductImportBatch;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadProductImportErrorsAction
{
    public function execute(ProductImportBatch $batch): StreamedResponse
    {
        Gate::authorize('products_categories_brands.export');

        abort_unless($batch->created_by === auth()->id(), 404);

        return response()->streamDownload(function () use ($batch): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['row_number', 'item_code', 'name_ar', 'name_en', 'status', 'errors']);

            $batch->rows()
                ->where('status', 'invalid')
                ->orderBy('row_number')
                ->cursor()
                ->each(function ($row) use ($handle): void {
                    $mapped = $row->mapped_data ?? [];
                    $values = [
                        $row->row_number,
                        $this->safeCell($mapped['item_code'] ?? $row->raw_data['item_code'] ?? ''),
                        $this->safeCell($mapped['name_ar'] ?? $row->raw_data['name_ar'] ?? ''),
                        $this->safeCell($mapped['name_en'] ?? $row->raw_data['name_en'] ?? ''),
                        $row->status,
                        $this->safeCell(implode(' | ', $row->errors ?? [])),
                    ];
                    fputcsv($handle, $values);
                });

            fclose($handle);
        }, 'product-import-errors-'.$batch->id.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function safeCell(mixed $value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@]/', ltrim($value)) === 1 ? "'".$value : $value;
    }
}

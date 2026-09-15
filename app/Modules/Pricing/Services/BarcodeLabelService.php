<?php

namespace App\Modules\Pricing\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\PrintTemplate;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Services\PriceListResolver;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;

final class BarcodeLabelService
{
    /** @return array<string,mixed> */
    public function profile(User $actor, ?int $templateId = null, ?int $printerId = null): array
    {
        $printer = $printerId ? PrinterConfiguration::visibleTo($actor)->where('status', 'active')->with('printTemplate')->findOrFail($printerId) : null;
        $template = $templateId ? PrintTemplate::visibleTo($actor)->where('status', 'active')->where('document_type', 'barcode_label')->findOrFail($templateId) : null;

        if (! $template && $printer?->printTemplate?->document_type === 'barcode_label' && $printer->printTemplate->status === 'active') $template = $printer->printTemplate;
        if (! $printer && ! $template) {
            $printer = PrinterConfiguration::visibleTo($actor)->where('status', 'active')->where('is_default', true)->with('printTemplate')->get()->first(fn ($item) => $item->printTemplate?->document_type === 'barcode_label' && $item->printTemplate?->status === 'active');
            $template = $printer?->printTemplate;
        }
        if ($printer && $template && $printer->print_template_id && (int) $printer->print_template_id !== (int) $template->id) {
            throw ValidationException::withMessages(['printer_id' => __('The selected printer is assigned to another template.')]);
        }

        $paper = (string) ($template?->paper_size ?: 'label_50x30mm');
        $settings = array_replace([
            'width_mm' => 50, 'height_mm' => 30, 'dpi' => 203, 'orientation' => 'portrait',
            'margin_mm' => 1, 'gap_mm' => 2, 'horizontal_gap_mm' => 2, 'vertical_gap_mm' => 2,
            'a4_rows' => 8, 'a4_columns' => 3, 'symbology' => 'auto',
            'show_name' => true, 'show_price' => true, 'show_item_code' => true,
            'show_list_code' => false, 'show_outlet' => false,
        ], $template?->layout_settings ?? []);
        if ($paper === 'label_50x30mm') [$settings['width_mm'], $settings['height_mm']] = [50, 30];
        if ($paper === 'label_40x25mm') [$settings['width_mm'], $settings['height_mm']] = [40, 25];
        if ($paper === '58mm') $settings['width_mm'] = 58;
        if ($paper === '80mm') $settings['width_mm'] = 80;

        return ['paper_size' => $paper, 'settings' => $settings, 'template' => $template, 'printer' => $printer, 'built_in' => $template === null];
    }

    /** @param array<int,array<string,mixed>> $rows @return array<string,mixed> */
    public function compose(User $actor, array $rows, array $profile, ?int $storeId = null): array
    {
        $store = $storeId ? Store::visibleTo($actor)->where('status', 'active')->where('type', 'selling')->findOrFail($storeId) : null;
        $list = $store ? app(PriceListResolver::class)->listForOutlet($store) : null;
        $labels = [];

        foreach ($rows as $row) {
            $product = Product::query()->sellable()->with(['barcodes' => fn ($query) => $query->active()->orderByDesc('is_primary')->orderBy('id')])->findOrFail((int) ($row['product_id'] ?? 0));
            $barcode = $product->barcodes->firstWhere('id', (int) ($row['barcode_id'] ?? 0));
            if (! $barcode) throw ValidationException::withMessages(['products' => __('Only a real active product barcode can be printed.')]);
            $copies = filter_var($row['copies'] ?? null, FILTER_VALIDATE_INT);
            if ($copies === false || $copies < 1 || $copies > 500) throw ValidationException::withMessages(['products' => __('Copies must be a whole number from 1 to 500.')]);
            $price = $list ? app(PriceListResolver::class)->resolve($product, $list)->finalPrice : $product->sale_price;
            for ($copy = 1; $copy <= $copies; $copy++) {
                $labels[] = compact('product', 'barcode', 'price', 'list', 'store', 'copy');
            }
        }
        if ($labels === []) throw ValidationException::withMessages(['products' => __('Select at least one product with an active barcode.')]);

        return ['labels' => $labels, ...$profile];
    }

    public function pdfResponse(array $document): Response
    {
        $fontCache = storage_path('framework/cache/dompdf');
        (new Filesystem)->ensureDirectoryExists($fontCache);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->setChroot(base_path());
        $options->setFontDir($fontCache); $options->setFontCache($fontCache); $options->setTempDir($fontCache);
        $options->setDefaultFont(str_starts_with(app()->getLocale(), 'ar') ? 'Cairo PDF Arabic' : 'Cairo PDF Latin');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pricing.label-print', $document + ['toolbar' => false, 'pdf' => true])->render(), 'UTF-8');
        $settings = $document['settings'];
        $paper = $document['paper_size'] === 'a4' ? 'a4' : [0, 0, (float) $settings['width_mm'] * 72 / 25.4, (float) $settings['height_mm'] * 72 / 25.4];
        $dompdf->setPaper($paper, (string) $settings['orientation']);
        $dompdf->render();
        $filename = str_starts_with(app()->getLocale(), 'ar') ? 'ملصقات-الباركود.pdf' : 'barcode-labels.pdf';
        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename*=UTF-8''".rawurlencode($filename)]);
    }
}

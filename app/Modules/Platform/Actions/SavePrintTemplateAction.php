<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\PrintTemplate;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SavePrintTemplateAction
{
    public const DOCUMENT_TYPES = [
        'sales_invoice', 'sales_return', 'gift_receipt', 'shift_closing', 'barcode_label',
        'purchase_order', 'purchase_invoice', 'supplier_return', 'stock_transfer',
        'inventory_adjustment', 'stock_count', 'quotation', 'party_booking',
        'party_invoice', 'party_payment_receipt',
    ];
    public const PAPER_SIZES = ['58mm', '80mm', 'a4', 'a5', 'label_50x30mm', 'label_40x25mm'];
    public const LANGUAGES = ['ar', 'en', 'bilingual'];

    public function execute(array $data, ?int $id = null): PrintTemplate
    {
        Gate::authorize('manage-settings');
        $user = auth()->user();
        $companyId = (int) Store::visibleTo($user)->where('status', 'active')->value('company_id');
        abort_unless($companyId > 0, 403);

        if ($data['document_type'] !== 'barcode_label' && str_starts_with($data['paper_size'], 'label_')) {
            throw ValidationException::withMessages(['templateForm.paper_size' => __('The paper size is not compatible with the document type.')]);
        }
        if ($data['document_type'] === 'barcode_label') {
            $layout = $data['layout_settings'] ?? [];
            [$width, $height] = match ($data['paper_size']) {
                'label_50x30mm' => [50, 30],
                'label_40x25mm' => [40, 25],
                '58mm' => [58, (float) ($layout['height_mm'] ?? 30)],
                '80mm' => [80, (float) ($layout['height_mm'] ?? 30)],
                default => [(float) ($layout['width_mm'] ?? 50), (float) ($layout['height_mm'] ?? 30)],
            };
            $data['layout_settings'] = array_replace($layout, ['width_mm' => $width, 'height_mm' => $height]);
        } else {
            $data['layout_settings'] = null;
        }
        if (PrintTemplate::query()->where('company_id', $companyId)->where('code', $data['code'])->when($id, fn ($query) => $query->whereKeyNot($id))->exists()) {
            throw ValidationException::withMessages(['templateForm.code' => __('The print template code is already in use.')]);
        }

        return DB::transaction(function () use ($data, $id, $user, $companyId): PrintTemplate {
            $template = $id
                ? PrintTemplate::visibleTo($user)->lockForUpdate()->findOrFail($id)
                : new PrintTemplate(['company_id' => $companyId]);
            $before = $template->exists ? $template->getAttributes() : null;
            $template->fill($data)->save();
            app(RecordAuditEvent::class)->execute('master_data', $id ? 'update_print_template' : 'create_print_template', $template, $before, $template->getAttributes());
            return $template;
        });
    }
}

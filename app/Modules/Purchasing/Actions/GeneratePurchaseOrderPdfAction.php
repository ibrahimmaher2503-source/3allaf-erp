<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\StoreAttachment;
use App\Modules\Platform\Data\AttachmentSourceReference;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderDocument;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class GeneratePurchaseOrderPdfAction
{
    public function execute(PurchaseOrder $order, ?string $locale = null): PurchaseOrderDocument
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User && ($actor->can('purchase_orders.print') || $actor->can('purchase_orders.create') || $actor->can('purchase_orders.edit') || $actor->can('purchase_orders.approve')), 403);
        $locale = in_array($locale, config('app.supported_locales', ['ar', 'en', 'ar-EG']), true) ? $locale : app()->getLocale();

        return DB::transaction(function () use ($order, $actor, $locale): PurchaseOrderDocument {
            $locked = PurchaseOrder::query()->with(['branch.company', 'supplier', 'store', 'creator', 'submitter', 'approver', 'lines.product'])->lockForUpdate()->findOrFail($order->id);
            $approved = $locked->isApproved();
            $frozen = PurchaseOrderDocument::query()->where('purchase_order_id', $locked->id)->where('locale', $locale)->whereNotNull('frozen_at')->latest('version')->lockForUpdate()->first();
            if ($approved && $frozen !== null) return $frozen;

            $approvals = ApprovalRecord::query()->where('source_type', 'purchase_orders')->where('source_id', (string) $locked->id)->oldest('id')->get();
            $previousLocale = app()->getLocale();
            app()->setLocale($locale);
            try {
                $options = new Options;
                $options->set('isRemoteEnabled', false);
                $options->set('isJavascriptEnabled', false);
                $options->setChroot(base_path());
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml(view('purchasing.order-pdf', ['order' => $locked, 'approvals' => $approvals])->render(), 'UTF-8');
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $bytes = $dompdf->output();
            } finally {
                app()->setLocale($previousLocale);
            }
            if ($bytes === '') throw new RuntimeException('Purchase-order PDF generation returned no bytes.');

            $temporary = tempnam(sys_get_temp_dir(), 'toyjoy-po-');
            if ($temporary === false) throw new RuntimeException('Could not allocate purchase-order PDF storage.');
            $pdfPath = $temporary.'.pdf';
            rename($temporary, $pdfPath);
            file_put_contents($pdfPath, $bytes, LOCK_EX);
            try {
                $attachment = app(StoreAttachment::class)->execute(
                    new File($pdfPath),
                    'generated_document',
                    new AttachmentSourceReference(PurchaseOrder::class, (string) $locked->id, $locked->branch_id, $locked->store_id, 'private'),
                    fn (User $user): bool => $user->can('purchase_orders.print') || $user->can('purchase_orders.create') || $user->can('purchase_orders.edit') || $user->can('purchase_orders.approve'),
                );
            } finally {
                @unlink($pdfPath);
            }

            $version = (int) PurchaseOrderDocument::query()->where('purchase_order_id', $locked->id)->lockForUpdate()->max('version') + 1;
            $document = PurchaseOrderDocument::query()->create([
                'purchase_order_id' => $locked->id,
                'attachment_id' => $attachment->id,
                'version' => $version,
                'document_status' => $approved ? 'approved' : 'draft',
                'locale' => $locale,
                'content_sha256' => hash('sha256', $bytes),
                'generated_by' => $actor->id,
                'generated_at' => now(),
                'frozen_at' => $approved ? now() : null,
            ]);
            app(RecordAuditEvent::class)->execute('procurement', $approved ? 'freeze_purchase_order_pdf' : 'regenerate_purchase_order_draft_pdf', $locked, metadata: ['document_id' => $document->id, 'version' => $version, 'locale' => $locale, 'sha256' => $document->content_sha256], branchId: $locked->branch_id, storeId: $locked->store_id);

            return $document->load('attachment');
        });
    }
}

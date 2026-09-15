<?php

declare(strict_types=1);

use App\Modules\Platform\Support\DocumentTypeCatalog;
use App\Support\ApplicationVersion;
use Illuminate\Contracts\Console\Kernel;

$root=dirname(__DIR__,3); $assert=static function(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);};
try {
    require $root.'/vendor/autoload.php'; $app=require $root.'/bootstrap/app.php'; $app->make(Kernel::class)->bootstrap();
    $assert(ApplicationVersion::RELEASE==='0.1.22-hotfix40','Version mismatch.');
    $migration=file_get_contents($root.'/database/migrations/2026_09_14_000114_extend_purchase_orders_and_exports_for_hotfix40.php');
    foreach(['receiving_status','purchase_order_documents','attachment_id','request_hash','po_branch_workflow_idx'] as $needle)$assert(str_contains($migration,$needle),"Migration omitted {$needle}.");
    $assert(!str_contains($migration,'dropColumn')&&!str_contains($migration,'dropIfExists'),'Hotfix40 rollback must retain additions.');
    $assert(DocumentTypeCatalog::prefix('BR1','purchase_order')==='BR1-PO-','Purchase-order branch numbering mismatch.');
    $approve=file_get_contents($root.'/app/Modules/Purchasing/Actions/ApprovePurchaseInvoiceAction.php');
    $assert(str_contains($approve,"'receiving_status' =>"),'Receiving state is not independent.');
    $assert(!str_contains($approve,'Partial receipt is not allowed'),'Partial receipt remains blocked.');
    foreach(['lockForUpdate()','idempotency_key','quantity_ordered'] as $needle)$assert(str_contains($approve,$needle),"Approval integrity omitted {$needle}.");
    $save=file_get_contents($root.'/app/Modules/Purchasing/Actions/SavePurchaseInvoiceAction.php');
    $assert(str_contains($save,'remaining purchase-order quantity'),'Draft over-invoicing guard missing.');
    $orders=file_get_contents($root.'/resources/views/purchasing/orders.blade.php');
    foreach(['purchasing.orders.edit','workflowStatusLabel','nextAction','branchFilter','invoices.store','documents.attachment'] as $needle)$assert(str_contains($orders,$needle),"Purchase-order UI omitted {$needle}.");
    $assert(!str_contains(file_get_contents($root.'/resources/views/purchasing/partials/order-form.blade.php'),"orderForm.payment_terms"),'Payment terms remain on full-page editor.');
    $pdf=file_get_contents($root.'/app/Modules/Purchasing/Actions/GeneratePurchaseOrderPdfAction.php');
    foreach(['StoreAttachment','PurchaseOrderDocument','frozen_at','content_sha256','Cairo'] as $needle)$assert(str_contains($pdf.file_get_contents($root.'/resources/views/purchasing/order-pdf.blade.php'),$needle),"Stored PDF omitted {$needle}.");
    $central=file_get_contents($root.'/app/Modules/Reporting/Queries/CentralExportSnapshot.php');
    foreach(['products_barcodes','customers_groups','suppliers_groups','purchase_orders','purchase_invoices_receiving','opening_inventory','inventory_movements','visibleTo'] as $needle)$assert(str_contains($central,$needle),"Central exports omitted {$needle}.");
    $jobs=file_get_contents($root.'/app/Modules/Reporting/Jobs/GenerateReportExportJob.php');
    foreach(['request_hash','fputcsv','safe(','CentralExportSnapshot'] as $needle)$assert(str_contains($jobs.file_get_contents($root.'/app/Modules/Reporting/Actions/CreateExportJobAction.php'),$needle),"Export integrity omitted {$needle}.");
    foreach(['ar','en','ar-EG'] as $locale){$map=json_decode(file_get_contents($root."/lang/{$locale}.json"),true,512,JSON_THROW_ON_ERROR);foreach(['Submitted for Review','Procurement Manager Approved','Ready to Convert to Purchase Invoice','Reports and Export Center'] as $key)$assert(filled($map[$key]??null),"{$locale} missing {$key}.");}
    echo "HOTFIX40_FOCUSED_VERIFICATION=PASS database_mutation=none\n";
} catch(Throwable $e){fwrite(STDERR,'HOTFIX40_FOCUSED_VERIFICATION=FAIL '.$e->getMessage()."\n");exit(1);}

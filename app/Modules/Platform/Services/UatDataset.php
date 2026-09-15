<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\BarcodeSequence;
use App\Modules\Catalog\Actions\AddBarcodeAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\UatBatch;
use App\Modules\Platform\Models\UatRecord;
use App\Modules\Pricing\Actions\EnsureBasePriceList;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\ReversePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseDistributionAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

final class UatDataset
{
    private const TABLES = ['purchase_invoice_distributions','stock_movements','stock_balances','approval_records','purchase_invoice_lines','purchase_invoices','barcodes','product_suppliers','products','suppliers','brands','categories','cash_drawers','branch_selling_stores','user_store_scopes','user_branch_scopes','role_user','users','stores','branches','price_lists','payment_methods','tax_settings','barcode_sequences'];

    /** @return array<string,mixed> */
    public function seed(int $companyId, bool $dryRun = false): array
    {
        $this->guard();
        $company = Company::query()->whereKey($companyId)->where('status', 'active')->firstOrFail();
        $existing = UatBatch::query()->where('company_id', $company->id)->where('status', 'active')->first();
        if ($dryRun) return ['operation'=>'seed','dry_run'=>true,'company_id'=>$company->id,'existing_batch'=>$existing?->batch_key,'would_create'=>$this->coverage()];
        if ($existing) return ['operation'=>'seed','dry_run'=>false,'company_id'=>$company->id,'batch'=>$existing->batch_key,'idempotent'=>true,'created_records'=>$existing->records()->count(),'credentials'=>'unchanged; purge and reseed to issue a new password'];

        return DB::transaction(function () use ($company): array {
            $batch = UatBatch::query()->create(['company_id'=>$company->id,'batch_key'=>(string)Str::uuid(),'status'=>'active','coverage'=>$this->coverage()]);
            $suffix = str_pad((string)$company->id, 4, '0', STR_PAD_LEFT);
            $password = 'Uat!'.Str::password(20, true, true, false, false);
            $user = User::query()->create(['name'=>'TEST-UAT Administrator','username'=>'test-uat-'.$suffix,'email'=>'test-uat-'.$suffix.'@example.test','password'=>Hash::make($password),'status'=>'active']);
            $user->forceFill(['is_super_admin'=>true,'email_verified_at'=>now()])->save(); $this->mark($batch,'users',$user->id,40);
            $previous = Auth::user(); Auth::login($user);
            try {
                $branch = Branch::query()->create(['company_id'=>$company->id,'code'=>'TEST-BR-'.$suffix,'name_ar'=>'اختبار — الفرع الرئيسي','name_en'=>'TEST-Main Branch','timezone'=>$company->timezone ?: 'Africa/Cairo','status'=>'active','policy_notes'=>'UAT '.$batch->batch_key]); $this->mark($batch,'branches',$branch->id,20);
                $receiving = $this->store($batch,$company,$branch,'TEST-REC-'.$suffix,'warehouse','اختبار — مخزن الاستلام','TEST-Receiving Warehouse');
                $warehouse = $this->store($batch,$company,$branch,'TEST-WH-'.$suffix,'warehouse','اختبار — المخزن','TEST-Warehouse');
                $outlet = $this->store($batch,$company,$branch,'TEST-OUT-'.$suffix,'selling','اختبار — منفذ البيع','TEST-Sales Outlet');
                $mappingId=DB::table('branch_selling_stores')->insertGetId(['branch_id'=>$branch->id,'store_id'=>$outlet->id,'status'=>'active','effective_from'=>now(),'created_at'=>now(),'updated_at'=>now()]); $this->mark($batch,'branch_selling_stores',$mappingId,80);
                $drawerId=DB::table('cash_drawers')->insertGetId(['company_id'=>(int)$outlet->company_id,'branch_id'=>(int)$outlet->branch_id,'store_id'=>$outlet->id,'code'=>'TEST-REG-'.$suffix,'name_ar'=>'اختبار — الخزنة','name_en'=>'TEST-Register','status'=>'active','assigned_user_id'=>$user->id,'created_at'=>now(),'updated_at'=>now()]); $this->mark($batch,'cash_drawers',$drawerId,90);
                $paymentId=DB::table('payment_methods')->insertGetId(['code'=>'TEST-CASH-'.$suffix,'name_ar'=>'اختبار — نقدي','name_en'=>'TEST-Cash','type'=>'cash','requires_evidence'=>false,'offline_eligible'=>false,'status'=>'active','policy_notes'=>'UAT '.$batch->batch_key,'created_at'=>now(),'updated_at'=>now()]);$this->mark($batch,'payment_methods',$paymentId,30);
                $taxId=DB::table('tax_settings')->insertGetId(['code'=>'TEST-TAX-'.$suffix,'name_ar'=>'اختبار — بدون ضريبة','name_en'=>'TEST-Zero Tax','rate'=>0,'is_tax_inclusive'=>false,'status'=>'active','policy_notes'=>'UAT '.$batch->batch_key,'created_at'=>now(),'updated_at'=>now()]);$this->mark($batch,'tax_settings',$taxId,30);
                if (! DB::table('document_sequences')->where('document_type', 'purchase_invoice')->where('status', 'active')->exists()) throw new LogicException('An active purchase-invoice document sequence must exist before UAT seeding.');
                foreach([$branch->id] as $id){$scope=DB::table('user_branch_scopes')->insertGetId(['user_id'=>$user->id,'branch_id'=>$id,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);$this->mark($batch,'user_branch_scopes',$scope,100);} foreach([$receiving,$warehouse,$outlet] as $store){$scope=DB::table('user_store_scopes')->insertGetId(['user_id'=>$user->id,'store_id'=>$store->id,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);$this->mark($batch,'user_store_scopes',$scope,100);}
                $category=Category::query()->create(['code'=>'TEST-CAT-'.$suffix,'name_ar'=>'اختبار — ألعاب','name_en'=>'TEST-Toys','status'=>'active','sort_order'=>999,'created_by'=>$user->id,'updated_by'=>$user->id]);$this->mark($batch,'categories',$category->id,30);
                $brand=Brand::query()->create(['code'=>'TEST-BRAND-'.$suffix,'name_ar'=>'اختبار — علامة','name_en'=>'TEST-Brand','status'=>'active','created_by'=>$user->id,'updated_by'=>$user->id]);$this->mark($batch,'brands',$brand->id,35);
                $supplier=Supplier::query()->create(['code'=>'TEST-SUP-1234-'.$suffix,'barcode_prefix'=>'1234','name_ar'=>'اختبار — مورد','name_en'=>'TEST-Supplier','settlement_method'=>'cash','status'=>'active','lock_version'=>0,'created_by'=>$user->id,'updated_by'=>$user->id]);$this->mark($batch,'suppliers',$supplier->id,35);
                if (BarcodeSequence::query()->where('supplier_code','1234')->exists()) throw new LogicException('Supplier barcode prefix 1234 already has a real sequence and cannot be used by UAT.');
                $internationalProduct=Product::query()->create(['item_code'=>'TEST-ITEM-INT-'.$suffix,'name_ar'=>'اختبار — منتج دولي','name_en'=>'TEST-International Product','model_number'=>'TEST-MODEL-INT-'.$suffix,'product_type'=>'standard','category_id'=>$category->id,'brand_id'=>$brand->id,'status'=>'active','barcode_mode'=>'international','barcode_registration_type'=>'international','average_cost'=>10,'sale_price'=>25,'lock_version'=>0]);$this->mark($batch,'products',$internationalProduct->id,50);
                $localProduct=Product::query()->create(['item_code'=>'TEST-ITEM-LOC-'.$suffix,'name_ar'=>'اختبار — منتج محلي','name_en'=>'TEST-Local Product','model_number'=>'TEST-MODEL-LOC-'.$suffix,'product_type'=>'standard','category_id'=>$category->id,'brand_id'=>$brand->id,'status'=>'active','barcode_mode'=>'local','barcode_registration_type'=>'local','average_cost'=>12,'sale_price'=>30,'lock_version'=>0]);$this->mark($batch,'products',$localProduct->id,50);
                $internationalBarcode=app(AddBarcodeAction::class)->addSupplierBarcode($internationalProduct->id,$this->uatGtin((int)$company->id));$this->mark($batch,'barcodes',$internationalBarcode->id,70);
                $localBarcode=app(AddBarcodeAction::class)->allocateLocalBarcode($localProduct->id,$supplier->code,'uat:'.$batch->id.':local-product');$this->mark($batch,'barcodes',$localBarcode->id,70);
                $sequence=BarcodeSequence::query()->where('supplier_code','1234')->firstOrFail();$this->mark($batch,'barcode_sequences',$sequence->id,65);
                foreach([[$internationalProduct,'TEST-SKU-INT-'.$suffix],[$localProduct,'TEST-SKU-LOC-'.$suffix]] as [$product,$supplierSku]){$link=DB::table('product_suppliers')->insertGetId(['product_id'=>$product->id,'supplier_id'=>$supplier->id,'supplier_item_code'=>$supplierSku,'is_preferred'=>true,'last_purchase_price'=>10,'created_by'=>$user->id,'updated_by'=>$user->id,'created_at'=>now(),'updated_at'=>now()]);$this->mark($batch,'product_suppliers',$link,75);}
                $base=app(EnsureBasePriceList::class)->execute($company); $outlet->update(['price_list_id'=>$base->id]);
                $list=PriceList::query()->create(['company_id'=>$company->id,'list_number'=>9000+$company->id,'code'=>'TEST-PL-'.$suffix,'name_ar'=>'اختبار — قائمة 10%','name_en'=>'TEST-10 Percent','percentage_increase'=>10,'status'=>'active','created_by'=>$user->id,'updated_by'=>$user->id]);$this->mark($batch,'price_lists',$list->id,25);
                $states=[]; $states['draft']=$this->invoice($batch,$supplier,$receiving,$outlet,$internationalProduct,'DRAFT',false,false,false); $states['awaiting_distribution']=$this->invoice($batch,$supplier,$receiving,$outlet,$internationalProduct,'AWAIT',true,false,false); $states['approved']=$this->invoice($batch,$supplier,$receiving,$outlet,$internationalProduct,'APPROVED',true,true,false); $states['reversed']=$this->invoice($batch,$supplier,$receiving,$outlet,$internationalProduct,'REVERSED',true,true,true);
                $this->markGenerated($batch,$user,$states);
                return ['operation'=>'seed','dry_run'=>false,'company_id'=>$company->id,'batch'=>$batch->batch_key,'idempotent'=>false,'created_records'=>$batch->records()->count(),'credentials'=>['username'=>$user->username,'email'=>$user->email,'password'=>$password],'invoices'=>$states,'coverage'=>$this->coverage()];
            } finally { $previous ? Auth::login($previous) : Auth::logout(); }
        });
    }

    /** @return array<string,mixed> */
    public function purge(int $companyId, bool $dryRun, bool $confirmed): array
    {
        $this->guard(); $batch=UatBatch::query()->where('company_id',$companyId)->where('status','active')->first();
        if(!$batch)return ['operation'=>'purge','dry_run'=>$dryRun,'company_id'=>$companyId,'records'=>0,'message'=>'No active marked UAT batch.'];
        if(!$dryRun&&!$confirmed)throw new LogicException('Real purge requires --confirm.');
        $counts=$batch->records()->selectRaw('table_name,count(*) total')->groupBy('table_name')->pluck('total','table_name')->all();
        if($dryRun)return ['operation'=>'purge','dry_run'=>true,'company_id'=>$companyId,'batch'=>$batch->batch_key,'records'=>$batch->records()->count(),'tables'=>$counts];
        DB::transaction(function()use($batch):void{foreach($batch->records()->orderByDesc('delete_order')->orderByDesc('id')->get() as $record){if(!in_array($record->table_name,self::TABLES,true))throw new LogicException('Unapproved UAT purge table: '.$record->table_name);if($record->table_name==='role_user'){DB::table('role_user')->where('user_id',$record->record_id)->delete();}else{DB::table($record->table_name)->where('id',$record->record_id)->delete();}}$batch->delete();});
        return ['operation'=>'purge','dry_run'=>false,'company_id'=>$companyId,'records'=>array_sum($counts),'tables'=>$counts];
    }

    private function store(UatBatch $batch,Company $company,Branch $branch,string $code,string $type,string $ar,string $en):Store{$store=Store::query()->create(['company_id'=>$company->id,'branch_id'=>$branch->id,'code'=>$code,'type'=>$type,'name_ar'=>$ar,'name_en'=>$en,'status'=>'active','allows_negative_stock'=>false,'policy_notes'=>'UAT '.$batch->batch_key]);$this->mark($batch,'stores',$store->id,30);return $store;}
    private function invoice(UatBatch $batch,Supplier $supplier,Store $receiving,Store $outlet,Product $product,string $key,bool $submit,bool $approve,bool $reverse):int{$invoice=app(SavePurchaseInvoiceAction::class)->execute(['supplier_id'=>$supplier->id,'store_id'=>$receiving->id,'purchase_order_id'=>null,'supplier_reference'=>'TEST-'.$key.'-'.$batch->id,'invoice_date'=>today()->toDateString(),'currency_code'=>'EGP','notes'=>'UAT '.$batch->batch_key], [['product_id'=>$product->id,'purchase_order_line_id'=>null,'quantity'=>'2','unit_cost'=>'10','base_consumer_price'=>'25','discount_type'=>null,'discount_value'=>'0','tax_rate'=>'0','tax_code'=>null]]);$this->mark($batch,'purchase_invoices',$invoice->id,120);foreach($invoice->lines as $line)$this->mark($batch,'purchase_invoice_lines',$line->id,130);if($submit){$invoice=app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id,$invoice->lock_version);}if($approve){$invoice=app(SavePurchaseDistributionAction::class)->execute($invoice->id,[$invoice->lines->first()->id=>[$outlet->id=>'2']],$invoice->lock_version);$invoice=app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id,$invoice->lock_version);}if($reverse)$invoice=app(ReversePurchaseInvoiceAction::class)->execute($invoice->id,'TEST-UAT reversible journey',$invoice->lock_version);return $invoice->id;}
    private function markGenerated(UatBatch $batch,User $user,array $invoiceIds):void{foreach(DB::table('purchase_invoice_distributions')->whereIn('purchase_invoice_id',$invoiceIds)->pluck('id') as $id)$this->mark($batch,'purchase_invoice_distributions',(int)$id,150);foreach(DB::table('stock_movements')->whereIn('source_id',$invoiceIds)->where('source_type',PurchaseInvoice::class)->pluck('id') as $id)$this->mark($batch,'stock_movements',(int)$id,160);$productIds=DB::table('purchase_invoice_lines')->whereIn('purchase_invoice_id',$invoiceIds)->pluck('product_id');$storeIds=DB::table('purchase_invoices')->whereIn('id',$invoiceIds)->pluck('store_id')->merge(DB::table('purchase_invoice_distributions')->whereIn('purchase_invoice_id',$invoiceIds)->pluck('destination_store_id'));foreach(DB::table('stock_balances')->whereIn('product_id',$productIds)->whereIn('store_id',$storeIds)->pluck('id') as $id)$this->mark($batch,'stock_balances',(int)$id,140);foreach(DB::table('approval_records')->where('source_type','purchase_invoices')->whereIn('source_id',array_map('strval',$invoiceIds))->pluck('id') as $id)$this->mark($batch,'approval_records',(int)$id,145);}
    private function mark(UatBatch $batch,string $table,int $id,int $order):void{UatRecord::query()->firstOrCreate(['uat_batch_id'=>$batch->id,'table_name'=>$table,'record_id'=>$id],['delete_order'=>$order]);}
    private function uatGtin(int $companyId):string{$body='622'.str_pad((string)$companyId,9,'0',STR_PAD_LEFT);$sum=0;$weight=3;for($index=strlen($body)-1;$index>=0;$index--){$sum+=((int)$body[$index])*$weight;$weight=$weight===3?1:3;}return $body.(string)((10-($sum%10))%10);}
    private function guard():void{if(app()->environment('production')&&getenv('RAJEH_UAT_ALLOW_PRODUCTION')!=='CONFIRMED')throw new LogicException('Production UAT commands require RAJEH_UAT_ALLOW_PRODUCTION=CONFIRMED.');}
    /** @return array<int,array<string,string>> */ private function coverage():array{return [['area'=>'Setup 1–21','route'=>'/initial-setup','result'=>'company-scoped masters and readiness fixtures'],['area'=>'Purchase Invoice Stage 1','route'=>'/purchasing/invoices','result'=>'draft and scanner/search product'],['area'=>'Distribution','route'=>'/purchasing/invoices','result'=>'awaiting, approved, reversed; full allocation'],['area'=>'Inventory','route'=>'/inventory','result'=>'receipt/distribution ledger and zero receiving remainder'],['area'=>'Pricing and labels','route'=>'/pricing/lists','result'=>'List 0, percentage list, outlet snapshot and label source'],['area'=>'Other core modules','route'=>'/dashboard','result'=>'existing supported routes remain accessible to UAT administrator; unsupported workflows are not fabricated']];}
}

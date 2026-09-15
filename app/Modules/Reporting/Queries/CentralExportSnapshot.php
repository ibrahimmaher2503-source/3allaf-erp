<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Queries;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Models\OpeningInventoryDocument;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class CentralExportSnapshot
{
    public const DATASETS = ['products_barcodes','customers_groups','suppliers_groups','purchase_orders','purchase_invoices_receiving','opening_inventory','inventory_movements'];
    private const PERMISSIONS = ['products_barcodes'=>'products_categories_brands.view','customers_groups'=>'customers.view','suppliers_groups'=>'suppliers.view','purchase_orders'=>'purchase_orders.view','purchase_invoices_receiving'=>'purchase_invoices_supplier_returns.view','opening_inventory'=>'inventory_stock_card.view','inventory_movements'=>'inventory_stock_card.view'];

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function execute(User $user, string $dataset, array $filters): array
    {
        abort_unless(in_array($dataset, self::DATASETS, true), 422);
        Gate::forUser($user)->authorize(self::PERMISSIONS[$dataset]);
        if (filled($filters['branch_id'] ?? null)) abort_unless($user->is_super_admin || $user->canAccessBranch((int)$filters['branch_id']), 403);
        if (filled($filters['store_id'] ?? null)) abort_unless($user->is_super_admin || $user->canAccessStore((int)$filters['store_id']), 403);
        $storeIds = Store::query()->visibleTo($user)
            ->when(filled($filters['branch_id'] ?? null), fn (Builder $q) => $q->where('branch_id', (int)$filters['branch_id']))
            ->when(filled($filters['store_id'] ?? null), fn (Builder $q) => $q->whereKey((int)$filters['store_id']))->pluck('id');
        $companyIds = Store::query()->whereIn('id',$storeIds)->whereNotNull('company_id')->pluck('company_id')->merge(\App\Modules\Platform\Models\Branch::query()->whereIn('id',Store::query()->whereIn('id',$storeIds)->select('branch_id'))->pluck('company_id'))->unique();
        $from = filled($filters['date_from'] ?? null) ? (string)$filters['date_from'] : now()->subDays(30)->toDateString();
        $to = filled($filters['date_to'] ?? null) ? (string)$filters['date_to'] : now()->toDateString();
        $status = filled($filters['document_status'] ?? null) ? (string)$filters['document_status'] : null;
        $supplier = filled($filters['supplier_id'] ?? null) ? (int)$filters['supplier_id'] : null;
        $customer = filled($filters['customer_id'] ?? null) ? (int)$filters['customer_id'] : null;
        $category = filled($filters['category_id'] ?? null) ? (int)$filters['category_id'] : null;

        [$columns,$rows] = match($dataset) {
            'products_barcodes' => [['internal_code','name_ar','name_en','category','model','supplier_codes','barcodes','status'], Product::query()->with(['category','barcodes','productSuppliers'])->when($category,fn($q)=>$q->where('category_id',$category))->orderBy('item_code')->limit(5000)->get()->map(fn($p)=>[$p->item_code,$p->name_ar,$p->name_en,$p->category?->code,$p->model_number,$p->productSuppliers->pluck('supplier_item_code')->filter()->join(' | '),$p->barcodes->pluck('barcode')->filter()->join(' | '),$p->status])->all()],
            'customers_groups' => [['customer_code','name_ar','name_en','group','status'], Customer::query()->visibleTo($user)->with('group')->when($customer,fn($q)=>$q->whereKey($customer))->orderBy('id')->limit(5000)->get()->map(fn($c)=>[$c->customer_code,$c->name_ar,$c->name_en,$c->group?->name_en,$c->status])->all()],
            'suppliers_groups' => [['supplier_code','name_ar','name_en','group','status'], Supplier::query()->with('supplierGroup')->whereHas('supplierGroup',fn($q)=>$q->whereIn('company_id',$companyIds))->when($supplier,fn($q)=>$q->whereKey($supplier))->orderBy('code')->limit(5000)->get()->map(fn($s)=>[$s->code,$s->name_ar,$s->name_en,$s->supplierGroup?->name_en,$s->status])->all()],
            'purchase_orders' => [['document','branch','receiving_store','supplier','date','approval_status','receiving_status','total'], PurchaseOrder::query()->with(['branch','store','supplier'])->where(function($q)use($storeIds){$q->whereIn('store_id',$storeIds)->orWhereIn('branch_id',Store::query()->whereIn('id',$storeIds)->select('branch_id'));})->whereBetween('order_date',[$from,$to])->when($status,fn($q)=>$q->where('status',$status))->when($supplier,fn($q)=>$q->where('supplier_id',$supplier))->orderByDesc('id')->limit(5000)->get()->map(fn($o)=>[$o->po_number,$o->branch?->code,$o->store?->code,$o->supplier?->code,$o->order_date?->toDateString(),$o->status,$o->receivingState(),$o->total_amount])->all()],
            'purchase_invoices_receiving' => [['document','purchase_order','store','supplier','date','status','total'], PurchaseInvoice::query()->with(['purchaseOrder','store','supplier'])->whereIn('store_id',$storeIds)->whereBetween('invoice_date',[$from,$to])->when($status,fn($q)=>$q->where('status',$status))->when($supplier,fn($q)=>$q->where('supplier_id',$supplier))->orderByDesc('id')->limit(5000)->get()->map(fn($i)=>[$i->invoice_number,$i->purchaseOrder?->po_number,$i->store?->code,$i->supplier?->code,$i->invoice_date?->toDateString(),$i->status,$i->total_amount])->all()],
            'opening_inventory' => [['document','branch','store','date','status','lines'], OpeningInventoryDocument::query()->with(['branch','store'])->withCount('lines')->whereIn('store_id',$storeIds)->whereBetween('document_date',[$from,$to])->when($status,fn($q)=>$q->where('status',$status))->orderByDesc('id')->limit(5000)->get()->map(fn($d)=>[$d->document_number,$d->branch?->code,$d->store?->code,$d->document_date?->toDateString(),$d->status,$d->lines_count])->all()],
            'inventory_movements' => [['id','store','product_id','movement_type','quantity','unit_cost','posted_at'], StockMovement::query()->with('store')->whereIn('store_id',$storeIds)->whereBetween('posted_at',[$from.' 00:00:00',$to.' 23:59:59'])->orderByDesc('id')->limit(5000)->get()->map(fn($m)=>[$m->id,$m->store?->code,$m->product_id,$m->movement_type,$m->quantity,$m->unit_cost,$m->posted_at?->toIso8601String()])->all()],
        };
        $normalized = ['dataset'=>$dataset,'date_from'=>$from,'date_to'=>$to,'branch_id'=>$filters['branch_id']??null,'store_id'=>$filters['store_id']??null,'document_status'=>$status,'supplier_id'=>$supplier,'customer_id'=>$customer,'category_id'=>$category];
        return ['dataset'=>$dataset,'filters'=>$normalized,'columns'=>$columns,'rows'=>$rows,'row_count'=>count($rows),'fresh_at'=>now()->toIso8601String()];
    }

    public function fingerprint(array $snapshot): string { unset($snapshot['fresh_at']); return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)); }
}

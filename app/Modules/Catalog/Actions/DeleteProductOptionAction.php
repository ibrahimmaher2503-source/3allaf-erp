<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ProductOptionGroup;
use App\Modules\Catalog\Models\ProductOptionValue;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DeleteProductOptionAction
{
    public function value(int $id, string $mode, ?int $replacementId = null): void
    {
        Gate::authorize('products_categories_brands.delete');
        DB::transaction(function () use ($id,$mode,$replacementId): void {
            $value=ProductOptionValue::query()->lockForUpdate()->findOrFail($id);
            $counts=$this->valueCounts($value->id);
            if(array_sum($counts)===0){$before=$value->getAttributes();$value->delete();$this->audit('delete_product_filter_value',$value,$before,$counts);return;}
            if($mode==='archive'){$before=$value->getAttributes();$value->update(['status'=>'inactive']);$this->audit('archive_product_filter_value',$value,$before,$counts);return;}
            if($mode==='replace'){
                $replacement=ProductOptionValue::query()->where('product_option_group_id',$value->product_option_group_id)->whereKey($replacementId)->where('status','active')->lockForUpdate()->firstOrFail();
                DB::table('product_family_option_values')->where('product_option_value_id',$value->id)->orderBy('id')->each(function($row)use($replacement):void{DB::table('product_family_option_values')->insertOrIgnore(['product_id'=>$row->product_id,'product_option_value_id'=>$replacement->id,'created_at'=>$row->created_at,'updated_at'=>now()]);});
                DB::table('product_variant_values')->where('product_option_value_id',$value->id)->whereIn('product_id',DB::table('product_variant_values')->where('product_option_value_id',$replacement->id)->select('product_id'))->delete();
                DB::table('product_variant_values')->where('product_option_value_id',$value->id)->update(['product_option_value_id'=>$replacement->id]);
                DB::table('product_family_option_values')->where('product_option_value_id',$value->id)->delete();
                $before=$value->getAttributes();$value->delete();$this->audit('replace_delete_product_filter_value',$value,$before,$counts,['replacement_id'=>$replacement->id]);return;
            }
            if($mode==='remove' && $counts['variant']===0){DB::table('product_family_option_values')->where('product_option_value_id',$value->id)->delete();$before=$value->getAttributes();$value->delete();$this->audit('detach_delete_product_filter_value',$value,$before,$counts);return;}
            throw ValidationException::withMessages(['deletion'=>__('Referenced filter values must be replaced or archived. Historical variant assignments cannot be removed.')]);
        });
    }

    public function group(int $id, string $mode): void
    {
        Gate::authorize('products_categories_brands.delete');
        DB::transaction(function () use($id,$mode):void{$group=ProductOptionGroup::query()->with('values')->lockForUpdate()->findOrFail($id);$references=DB::table('product_family_option_groups')->where('product_option_group_id',$id)->count()+DB::table('product_variant_values')->where('product_option_group_id',$id)->count();if($references===0){$before=$group->getAttributes();$group->values()->delete();$group->delete();$this->audit('delete_product_filter_group',$group,$before,['products'=>0]);return;}if($mode==='archive'){$before=$group->getAttributes();$group->update(['status'=>'inactive']);$group->values()->update(['status'=>'inactive']);$this->audit('archive_product_filter_group',$group,$before,['products'=>$references]);return;}throw ValidationException::withMessages(['deletion'=>__('A referenced filter group must be archived to preserve product history.')]);});
    }

    public function valueCounts(int $id): array { return ['family'=>(int)DB::table('product_family_option_values')->where('product_option_value_id',$id)->count(),'variant'=>(int)DB::table('product_variant_values')->where('product_option_value_id',$id)->count()]; }
    private function audit(string $event,object $source,array $before,array $counts,array $metadata=[]):void{app(RecordAuditEvent::class)->execute('master_data',$event,$source,$before,null,metadata:$metadata+['affected_product_assignments'=>array_sum($counts)]);}
}

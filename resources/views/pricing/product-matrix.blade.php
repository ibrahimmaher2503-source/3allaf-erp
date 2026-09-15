<?php
use App\Modules\Catalog\Models\Product; use App\Modules\Platform\Models\Company; use App\Modules\Platform\Models\Store; use App\Modules\Pricing\Actions\SaveProductPriceOverrideAction; use App\Modules\Pricing\Models\PriceList; use App\Modules\Pricing\Services\PriceListResolver; use Flux\Flux; use Illuminate\Support\Facades\Gate; use Livewire\Component;
new class extends Component {
 public int $productId; public ?int $editingListId=null; public string $overrideAmount=''; public string $overrideReason=''; public string $overrideFrom=''; public string $overrideTo='';
 public function mount(Product $product):void{Gate::authorize('pricing_lists.view');$this->productId=$product->id;}
 public function editOverride(int $id):void{Gate::authorize('pricing_lists.overrides');$o=Product::findOrFail($this->productId)->priceOverrides()->where('price_list_id',$id)->first();$this->editingListId=$id;$this->overrideAmount=(string)($o?->amount??'');$this->overrideReason=$o?->reason??'';$this->overrideFrom=$o?->effective_from?->toDateString()??'';$this->overrideTo=$o?->effective_to?->toDateString()??'';}
 public function saveOverride(SaveProductPriceOverrideAction $action):void{$d=$this->validate(['overrideAmount'=>['nullable','decimal:0,3','gt:0'],'overrideReason'=>['required','string','max:2000'],'overrideFrom'=>['nullable','date'],'overrideTo'=>['nullable','date','after_or_equal:overrideFrom']]);$action->execute(Product::findOrFail($this->productId),PriceList::findOrFail($this->editingListId),$d['overrideAmount'],$d['overrideReason'],$d['overrideFrom'],$d['overrideTo']);$this->editingListId=null;Flux::toast(variant:'success',text:__('Product price override saved.'));}
 public function with():array{$product=Product::findOrFail($this->productId);$companyId=(int)Company::where('status','active')->value('id');$resolver=app(PriceListResolver::class);$rows=PriceList::where('company_id',$companyId)->where('status','active')->orderBy('list_number')->get()->map(function($list)use($resolver,$product){try{return['list'=>$list,'resolved'=>$resolver->resolve($product,$list),'error'=>null];}catch(\Throwable $e){return['list'=>$list,'resolved'=>null,'error'=>\App\Support\UserSafeError::message($e)];}});return compact('product','rows');}
}; ?>
<section class="form-section space-y-4" aria-labelledby="product-pricing-title">
    <div>
        <h2 id="product-pricing-title" class="text-lg font-semibold">{{ __('Pricing matrix') }}</h2>
        <p class="text-sm text-zinc-600">{{ __('List 0 comes directly from the Product Card. Overrides win without automatic rounding; removing one restores the percentage calculation.') }}</p>
    </div>
    <div class="table-panel-surface">
        <table class="data-table">
            <thead><tr><th>{{ __('Price list') }}</th><th>{{ __('Percentage') }}</th><th>{{ __('Base price') }}</th><th>{{ __('Pre-round price') }}</th><th>{{ __('Effective price') }}</th><th>{{ __('Source') }}</th><th>{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['list']->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $row['list']->name_ar : $row['list']->name_en }}</td>
                    <td dir="ltr">{{ $row['list']->percentage_increase }}%</td>
                    @if ($row['resolved'])
                        <td dir="ltr">{{ $row['resolved']->basePrice }} {{ __('EGP') }}</td>
                        <td dir="ltr">{{ $row['resolved']->calculatedPrice }} {{ __('EGP') }}</td>
                        <td dir="ltr"><strong>{{ $row['resolved']->finalPrice }} {{ __('EGP') }}</strong></td>
                        <td>{{ $row['resolved']->overridden ? __('Manual override') : __('Calculated') }}</td>
                    @else
                        <td colspan="4" class="text-amber-700">{{ $row['error'] }}</td>
                    @endif
                    <td>
                        @if (! $row['list']->isBase())
                            @can('pricing_lists.overrides')
                                <x-actions.button type="button" wire:click="editOverride({{ $row['list']->id }})" semantic="edit" :label="__('Edit override')" />
                            @endcan
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@if($editingListId)<div class="rounded-xl border p-4 grid gap-3 md:grid-cols-2"><flux:input wire:model="overrideAmount" type="number" step="0.001" :label="__('Manual price (leave empty to remove)')"/><flux:input wire:model="overrideReason" :label="__('Reason')" required/><flux:input wire:model="overrideFrom" type="date" :label="__('Effective from')"/><flux:input wire:model="overrideTo" type="date" :label="__('Effective to')"/><div class="md:col-span-2 flex justify-end"><flux:button variant="primary" wire:click="saveOverride" wire:target="saveOverride" wire:loading.attr="disabled"><span wire:loading.remove wire:target="saveOverride">{{__('Save')}}</span><span wire:loading wire:target="saveOverride">{{__('Saving…')}}</span></flux:button></div></div>@endif</section>

<?php

use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Actions\SavePriceListAction;
use App\Modules\Pricing\Models\PriceList;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pricing Lists')] class extends Component {
    use WithPagination;
    public string $search = ''; public string $statusFilter = 'all'; public bool $showForm = false; public ?int $editingId = null;
    public array $form = ['name_ar'=>'','name_en'=>'','percentage_increase'=>'0','status'=>'active','effective_from'=>'','effective_to'=>'','notes'=>''];
    public function mount(): void { Gate::authorize('pricing_lists.view'); }
    public function openCreate(): void { Gate::authorize('pricing_lists.manage'); $this->editingId=null; $this->form=['name_ar'=>'','name_en'=>'','percentage_increase'=>'0','status'=>'active','effective_from'=>'','effective_to'=>'','notes'=>'']; $this->resetValidation(); $this->showForm=true; }
    public function openEdit(int $id): void { Gate::authorize('pricing_lists.manage'); $list=PriceList::query()->findOrFail($id); abort_if($list->isBase(),422); $this->editingId=$id; $this->form=['name_ar'=>$list->name_ar,'name_en'=>$list->name_en,'percentage_increase'=>(string)$list->percentage_increase,'status'=>$list->status,'effective_from'=>$list->effective_from?->toDateString()??'','effective_to'=>$list->effective_to?->toDateString()??'','notes'=>$list->notes??'']; $this->showForm=true; }
    public function save(SavePriceListAction $action): void { $data=$this->validate(['form.name_ar'=>['required','string','max:255'],'form.name_en'=>['required','string','max:255'],'form.percentage_increase'=>['required','decimal:0,4','min:0','max:9999'],'form.status'=>['required',Rule::in(['active','inactive'])],'form.effective_from'=>['nullable','date'],'form.effective_to'=>['nullable','date','after_or_equal:form.effective_from'],'form.notes'=>['nullable','string','max:2000']])['form']; $action->execute($data,$this->editingId?PriceList::query()->findOrFail($this->editingId):null); $this->showForm=false; Flux::toast(variant:'success',text:__('Price list saved successfully.')); }
    public function with(): array { $companyId=(int)Company::query()->where('status','active')->value('id'); $query=PriceList::query()->where('company_id',$companyId)->withCount(['overrides'])->withCount(['versions'])->when($this->search!=='' ,fn($q)=>$q->where(fn($s)=>$s->where('code','like','%'.$this->search.'%')->orWhere('name_ar','like','%'.$this->search.'%')->orWhere('name_en','like','%'.$this->search.'%')))->when($this->statusFilter!=='all',fn($q)=>$q->where('status',$this->statusFilter))->orderBy('list_number'); return ['lists'=>$query->paginate(15),'outletCounts'=>Store::query()->where('type','selling')->where('status','active')->selectRaw('COALESCE(price_list_id,0) k, count(*) c')->groupBy('k')->pluck('c','k')]; }
};
?>

<section class="space-y-3" aria-labelledby="price-lists-title">
    <x-page-header id="price-lists-title" :title="__('Pricing Lists')" :description="__('Reusable outlet selling prices calculated from Base Price List 0. Percentage lists never compound.')">
        @can('pricing_lists.manage')<x-actions.button type="button" wire:click="openCreate" semantic="create" :label="__('Add price list')">{{ __('Add price list') }}</x-actions.button>@endcan
    </x-page-header>
    <div class="grid grid-cols-1 gap-2 rounded-xl border border-border bg-surface p-2 sm:grid-cols-[minmax(16rem,1fr)_10rem] sm:items-end" data-compact-filter-row><flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" size="sm" /><flux:select wire:model.live="statusFilter" :label="__('Status')" size="sm"><flux:select.option value="all">{{ __('All') }}</flux:select.option><flux:select.option value="active">{{ __('Active') }}</flux:select.option><flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option></flux:select></div>
    <div class="table-panel-surface" data-price-lists-table><table class="data-table"><thead><tr><th>{{ __('Price list') }}</th><th>{{ __('Percentage increase') }}</th><th>{{ __('Effective dates') }}</th><th>{{ __('Overrides') }}</th><th>{{ __('Assigned outlets') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
    @forelse($lists as $list)<tr><td><strong>{{ $list->code }} · {{ str_starts_with(app()->getLocale(), 'ar')?$list->name_ar:$list->name_en }}</strong><small class="block text-zinc-500">{{ str_starts_with(app()->getLocale(), 'ar')?$list->name_en:$list->name_ar }} · {{ __('List :number',['number'=>$list->list_number]) }}</small></td><td dir="ltr">{{ $list->percentage_increase }}%</td><td>{{ $list->effective_from?->toDateString()??__('Immediate') }} — {{ $list->effective_to?->toDateString()??__('Open-ended') }}</td><td>{{ $list->overrides_count }}</td><td>{{ $outletCounts[$list->id]??($list->isBase()?($outletCounts[0]??0):0) }}</td><td><x-status.badge :status="$list->status" /></td><td>@if(!$list->isBase()) @can('pricing_lists.manage')<x-actions.button type="button" wire:click="openEdit({{ $list->id }})" semantic="edit" :label="__('Edit')" />@endcan @else <span class="text-xs text-zinc-500">{{ __('Protected base list') }}</span>@endif</td></tr>@empty<tr><td colspan="7"><x-state.empty :title="__('No price lists match the filters.')" /></td></tr>@endforelse
    </tbody></table></div>{{ $lists->links() }}
    <flux:modal wire:model="showForm" class="md:w-160 space-y-5"><flux:heading size="lg">{{ $editingId?__('Edit price list'):__('Add price list') }}</flux:heading><div class="grid gap-4 md:grid-cols-2"><flux:input wire:model="form.name_ar" :label="__('Arabic name')" required/><flux:input wire:model="form.name_en" :label="__('English name')" required/><flux:input wire:model="form.percentage_increase" type="number" step="0.0001" :label="__('Percentage increase from List 0')" required/><flux:select wire:model="form.status" :label="__('Status')"><flux:select.option value="active">{{__('Active')}}</flux:select.option><flux:select.option value="inactive">{{__('Inactive')}}</flux:select.option></flux:select><flux:input wire:model="form.effective_from" type="date" :label="__('Effective from')"/><flux:input wire:model="form.effective_to" type="date" :label="__('Effective to')"/></div><flux:textarea wire:model="form.notes" :label="__('Notes')"/><div class="flex justify-end gap-3"><flux:button type="button" wire:click="$set('showForm',false)">{{__('Cancel')}}</flux:button><flux:button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save"><span wire:loading.remove wire:target="save">{{__('Save')}}</span><span wire:loading wire:target="save">{{__('Saving…')}}</span></flux:button></div></flux:modal>
</section>

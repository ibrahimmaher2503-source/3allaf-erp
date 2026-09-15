<?php

use App\Models\User;
use App\Modules\Platform\Actions\DecideApprovalSource;
use App\Modules\Platform\Actions\RevokeAttachment;
use App\Modules\Platform\Actions\StoreAttachment;
use App\Modules\Platform\Data\AttachmentSourceReference;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\ApprovalPresentation;
use App\Modules\Platform\Support\UiLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Approval Inbox')] class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $state = 'pending';

    public string $sourceType = '';

    public string $branchId = '';

    public string $storeId = '';

    #[Locked]
    public ?int $selectedApprovalId = null;

    public bool $approvalModalOpen = false;

    public string $decisionReason = '';

    public mixed $evidence = null;

    public string $revokeReason = '';

    public function mount(): void
    {
        Gate::authorize('view-approval-inbox');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'state', 'sourceType', 'branchId', 'storeId'], true)) {
            $this->resetPage();
        }
    }

    public function showApproval(int $approvalId): void
    {
        $approval = $this->baseQuery()->findOrFail($approvalId);
        Gate::authorize('view', $approval);
        $this->selectedApprovalId = $approval->id;
        $this->approvalModalOpen = true;
        $this->decisionReason = '';
        $this->resetValidation();
    }

    public function closeApproval(): void
    {
        $this->approvalModalOpen = false;
        $this->selectedApprovalId = null;
        $this->decisionReason = '';
        $this->evidence = null;
        $this->revokeReason = '';
        $this->resetValidation();
    }

    public function updatedApprovalModalOpen(bool $isOpen): void
    {
        if (! $isOpen && $this->selectedApprovalId !== null) {
            $this->closeApproval();
        }
    }

    public function approve(): void
    {
        try {
            $approval = $this->selectedPendingApproval();
            Gate::authorize('decide', $approval);
            app(DecideApprovalSource::class)->approve($approval);
            session()->flash('approval-success', __('The source was approved and its audit trail was recorded.'));
            $this->closeApproval();
        } catch (AuthorizationException) {
            $this->addError('approval', __('لا تملك صلاحية اعتماد هذا الطلب.'));
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? __('تعذر اعتماد الطلب. راجع البيانات وحاول مرة أخرى.');
            $this->addError('approval', (string) $message);
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('approval', __('تعذر اعتماد الطلب الآن. أعد تحميل الصفحة وحاول مرة أخرى.'));
        }
    }

    public function reject(): void
    {
        try {
            $approval = $this->selectedPendingApproval();
            Gate::authorize('decide', $approval);
            if (! app(DecideApprovalSource::class)->canReject($approval)) {
                $this->addError('decisionReason', __('هذا الطلب لا يدعم الرفض.'));

                return;
            }
            $validated = $this->validate(['decisionReason' => ['required', 'string', 'min:3', 'max:1000']]);
            app(DecideApprovalSource::class)->reject($approval, $validated['decisionReason']);
            session()->flash('approval-success', __('The source was rejected and its audit trail was recorded.'));
            $this->closeApproval();
        } catch (AuthorizationException) {
            $this->addError('approval', __('لا تملك صلاحية رفض هذا الطلب.'));
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $message = collect($errors)->flatten()->first()
                ?? __('تعذر رفض الطلب. راجع البيانات وحاول مرة أخرى.');
            $this->addError(isset($errors['decisionReason']) ? 'decisionReason' : 'approval', (string) $message);
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('approval', __('تعذر رفض الطلب الآن. أعد تحميل الصفحة وحاول مرة أخرى.'));
        }
    }

    public function uploadEvidence(): void
    {
        $approval = $this->selectedPendingApproval();
        Gate::authorize('view', $approval);
        $this->validate(['evidence' => ['required', 'file', 'max:12288']]);
        app(StoreAttachment::class)->execute(
            $this->evidence,
            'approval_evidence',
            new AttachmentSourceReference(
                ApprovalRecord::class,
                (string) $approval->id,
                $approval->branch_id,
                $approval->store_id,
                'private',
            ),
            fn (User $user, AttachmentSourceReference $source): bool => Gate::forUser($user)->allows('view', $approval)
                && $source->sourceType === ApprovalRecord::class
                && $source->sourceId === (string) $approval->id,
        );
        $this->evidence = null;
        session()->flash('approval-evidence-success', __('Approval evidence uploaded securely.'));
    }

    public function revokeEvidence(string $attachmentId): void
    {
        $approval = $this->selectedPendingApproval();
        Gate::authorize('decide', $approval);
        $validated = $this->validate(['revokeReason' => ['required', 'string', 'min:3', 'max:500']]);
        $attachment = Attachment::query()
            ->where('source_type', ApprovalRecord::class)
            ->where('source_id', (string) $approval->id)
            ->findOrFail($attachmentId);
        app(RevokeAttachment::class)->execute(
            $attachment,
            $validated['revokeReason'],
            fn (User $user, Attachment $candidate): bool => Gate::forUser($user)->allows('decide', $approval)
                && $candidate->source_type === ApprovalRecord::class
                && $candidate->source_id === (string) $approval->id,
        );
        $this->revokeReason = '';
    }

    public function render()
    {
        Gate::authorize('view-approval-inbox');
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $base = $this->baseQuery();
        $approvals = (clone $base)
            ->with(['requester:id,name', 'approver:id,name'])
            ->when($this->search !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('uuid', 'like', $search)
                        ->orWhere('source_type', 'like', $search)
                        ->orWhere('source_id', 'like', $search)
                        ->orWhere('requested_action', 'like', $search)
                        ->orWhere('reason_text', 'like', $search);
                });
            })
            ->when($this->state !== '', fn (Builder $query) => $query->where('approval_state', $this->state))
            ->when($this->sourceType !== '', fn (Builder $query) => $query->where('source_type', $this->sourceType))
            ->when($this->branchId !== '', fn (Builder $query) => $query->where('branch_id', (int) $this->branchId))
            ->when($this->storeId !== '', fn (Builder $query) => $query->where('store_id', (int) $this->storeId))
            ->latest('requested_at')
            ->paginate(20);

        $selectedApproval = $this->selectedApprovalId === null
            ? null
            : (clone $base)->with(['requester:id,name', 'approver:id,name'])->find($this->selectedApprovalId);
        if ($selectedApproval !== null) {
            Gate::authorize('view', $selectedApproval);
        }

        return view('platform.system.approval-inbox', [
            'approvals' => $approvals,
            'selectedApproval' => $selectedApproval,
            'sourceTypes' => (clone $base)->distinct()->orderBy('source_type')->pluck('source_type'),
            'branches' => Branch::query()->visibleTo($user)->orderBy('code')->get(),
            'stores' => Store::query()->visibleTo($user)->orderBy('code')->get(),
            'attachments' => $selectedApproval === null ? collect() : Attachment::query()
                ->where('source_type', ApprovalRecord::class)
                ->where('source_id', (string) $selectedApproval->id)
                ->latest()->get(),
            'canDecide' => $selectedApproval !== null && Gate::allows('decide', $selectedApproval),
            'canReject' => $selectedApproval !== null && app(DecideApprovalSource::class)->canReject($selectedApproval),
            'sourceUrl' => $selectedApproval === null ? null : app(DecideApprovalSource::class)->sourceRoute($selectedApproval),
        ]);
    }

    private function selectedPendingApproval(): ApprovalRecord
    {
        $approval = $this->baseQuery()->findOrFail($this->selectedApprovalId);
        if ($approval->approval_state !== ApprovalState::Pending) {
            throw ValidationException::withMessages(['approval' => __('This approval request was already decided. Reload the inbox.')]);
        }

        return $approval;
    }

    private function baseQuery(): Builder
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $query = ApprovalRecord::query()->visibleTo($user);
        if ($user->is_super_admin || $user->hasPermission('audit_logs.view')) {
            return $query;
        }

        $permissionCodes = $user->roles()
            ->where('roles.status', 'active')
            ->with('permissions:id,code,status')
            ->get()
            ->flatMap(fn ($role) => $role->permissions->where('status', 'active')->pluck('code'))
            ->unique()->values()->all();

        return $query->where(function (Builder $visible) use ($user, $permissionCodes): void {
            $visible->where('requester_id', $user->id)
                ->orWhereIn('request_permission', $permissionCodes)
                ->orWhereIn('decision_permission', $permissionCodes);
        });
    }
}; ?>

<x-app.page :title="__('Approval inbox')" :description="__('Review source-linked requests within your permissions and branch or store scope.')" max-width="7xl" class="space-y-5">
    @if (session('approval-success'))
        <flux:callout variant="info" icon="check-circle">{{ session('approval-success') }}</flux:callout>
    @endif

    <x-tables.data-panel :title="__('Approval requests')" :description="__('Decisions call the source domain action, so posting and audit remain atomic.')">
        <x-slot:toolbar>
            <x-tables.filter-bar>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <flux:input wire:model.live.debounce.350ms="search" :label="__('Search')" icon="magnifying-glass" :placeholder="__('UUID, source, action, or reason')" />
                    <flux:select wire:model.live="state" :label="__('State')">
                        <option value="">{{ __('All states') }}</option>
                        @foreach (ApprovalState::cases() as $approvalState)<option value="{{ $approvalState->value }}">{{ UiLabel::status($approvalState->value) }}</option>@endforeach
                    </flux:select>
                    <flux:select wire:model.live="sourceType" :label="__('Source type')"><option value="">{{ __('All source types') }}</option>@foreach($sourceTypes as $type)<option value="{{ $type }}">{{ ApprovalPresentation::source($type) }}</option>@endforeach</flux:select>
                    <flux:select wire:model.live="branchId" :label="__('Branch')"><option value="">{{ __('All visible branches') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->code }}</option>@endforeach</flux:select>
                    <flux:select wire:model.live="storeId" :label="__('Store')"><option value="">{{ __('All visible stores') }}</option>@foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->code }}</option>@endforeach</flux:select>
                </div>
            </x-tables.filter-bar>
        </x-slot:toolbar>

        @if($approvals->isEmpty())
            <x-state.empty :title="__('No approval requests found')" :description="__('No requests match your permissions, scope, and filters.')" icon="check-circle" />
        @else
            <div class="hidden overflow-x-auto md:block">
                <flux:table class="min-w-[980px] table-fixed" aria-label="{{ __('Approval requests') }}">
                    <flux:table.columns><flux:table.column class="w-[130px] whitespace-nowrap">{{ __('Requested') }}</flux:table.column><flux:table.column class="w-[280px]">{{ __('Source') }}</flux:table.column><flux:table.column class="w-[190px]">{{ __('Requester') }}</flux:table.column><flux:table.column class="w-[170px]">{{ __('Scope') }}</flux:table.column><flux:table.column class="w-[120px] whitespace-nowrap">{{ __('State') }}</flux:table.column><flux:table.column class="w-[110px] whitespace-nowrap">{{ __('Actions') }}</flux:table.column></flux:table.columns>
                    <flux:table.rows>@foreach($approvals as $approval)<flux:table.row key="approval-{{ $approval->id }}"><flux:table.cell class="w-[130px] whitespace-nowrap align-top font-mono text-xs">{{ $approval->requested_at->format('Y-m-d H:i') }}</flux:table.cell><flux:table.cell class="w-[280px] max-w-[280px] align-top"><div class="break-words font-medium leading-5">{{ ApprovalPresentation::source($approval->source_type, $approval->source_id) }}</div><div class="break-all font-mono text-xs leading-5 text-text-muted">{{ ApprovalPresentation::action($approval->requested_action) }}</div></flux:table.cell><flux:table.cell class="w-[190px] max-w-[190px] break-words align-top leading-5">{{ $approval->requester?->name }}</flux:table.cell><flux:table.cell class="w-[170px] max-w-[170px] break-words align-top font-mono text-xs leading-5">{{ $approval->branch_id ? __('Branch').' #'.$approval->branch_id : '' }} {{ $approval->store_id ? __('Store').' #'.$approval->store_id : '' }}</flux:table.cell><flux:table.cell class="w-[120px] align-top"><x-status.badge :status="$approval->approval_state->value" /></flux:table.cell><flux:table.cell class="w-[110px] align-top"><x-actions.button semantic="view" :label="__('Review')" size="xs" wire:click="showApproval({{ $approval->id }})">{{ __('Review') }}</x-actions.button></flux:table.cell></flux:table.row>@endforeach</flux:table.rows>
                </flux:table>
            </div>
            <div class="space-y-3 md:hidden">@foreach($approvals as $approval)<x-cards.section-card :title="ApprovalPresentation::source($approval->source_type, $approval->source_id)"><div class="space-y-3 text-sm"><div class="flex items-center justify-between gap-3"><x-status.badge :status="$approval->approval_state->value" /><span class="font-mono text-xs text-text-muted">{{ $approval->requested_at->format('Y-m-d H:i') }}</span></div><p>{{ __('Requester') }}: {{ $approval->requester?->name }}</p><x-actions.button semantic="view" :label="__('Review')" wire:click="showApproval({{ $approval->id }})">{{ __('Review') }}</x-actions.button></div></x-cards.section-card>@endforeach</div>
        @endif
        <x-slot:footer>{{ $approvals->links() }}</x-slot:footer>
    </x-tables.data-panel>

    <flux:modal wire:model="approvalModalOpen" class="max-w-3xl">
        @if($selectedApproval)
            <div class="space-y-5">
                <div><flux:heading size="lg">{{ __('Approval request') }}</flux:heading><flux:text class="mt-1 font-mono text-xs">{{ $selectedApproval->uuid }}</flux:text></div>
                @error('approval')<x-state.error :title="$message" />@enderror
                <dl class="grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-text-muted">{{ __('Source') }}</dt><dd>{{ ApprovalPresentation::source($selectedApproval->source_type, $selectedApproval->source_id) }}</dd></div><div><dt class="text-text-muted">{{ __('State') }}</dt><dd><x-status.badge :status="$selectedApproval->approval_state->value" /></dd></div><div><dt class="text-text-muted">{{ __('Requester') }}</dt><dd>{{ $selectedApproval->requester?->name }}</dd></div><div><dt class="text-text-muted">{{ __('Requested action') }}</dt><dd>{{ ApprovalPresentation::action($selectedApproval->requested_action) }}</dd></div>@if($selectedApproval->reason_text)<div class="sm:col-span-2"><dt class="text-text-muted">{{ __('Reason') }}</dt><dd>{{ $selectedApproval->reason_text }}</dd></div>@endif</dl>
                <flux:button :href="$sourceUrl" variant="subtle" icon="arrow-top-right-on-square">{{ __('Open source') }}</flux:button>

                <section class="space-y-3" aria-labelledby="approval-evidence-heading"><div><h3 id="approval-evidence-heading" class="font-semibold">{{ __('Approval evidence') }}</h3><p class="text-sm text-text-muted">{{ __('Private files, maximum five per request. Every download is reauthorized and audited.') }}</p></div>@if(session('approval-evidence-success'))<flux:callout variant="info" icon="check-circle">{{ session('approval-evidence-success') }}</flux:callout>@endif<div class="flex flex-col gap-3 sm:flex-row sm:items-end"><flux:input wire:model="evidence" type="file" :label="__('Evidence file')" accept="image/jpeg,image/png,image/webp,application/pdf" /><flux:button wire:click="uploadEvidence" wire:loading.attr="disabled" wire:target="evidence,uploadEvidence" icon="arrow-up-tray">{{ __('Upload securely') }}</flux:button></div>@error('evidence')<p class="text-sm text-red-600">{{ $message }}</p>@enderror@if($canDecide && $attachments->contains(fn ($attachment) => $attachment->status->isDeliverable()))<flux:input wire:model="revokeReason" :label="__('Evidence revocation reason')" :description="__('Required before revoking active evidence; the reason is written to the audit trail.')" />@error('revokeReason')<p class="text-sm text-red-600">{{ $message }}</p>@enderror@endif<div class="space-y-2">@forelse($attachments as $attachment)<div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border-subtle p-3"><div><p class="text-sm font-medium">{{ $attachment->original_filename }}</p><p class="text-xs text-text-muted">{{ Number::fileSize($attachment->size_bytes) }} · {{ str($attachment->status->value)->headline() }}</p></div><div class="flex gap-2">@if($attachment->status->isDeliverable())<x-actions.button semantic="download" :label="__('Download')" size="xs" variant="subtle" icon="arrow-down-tray" :href="route('admin.approvals.attachments.download', [$selectedApproval, $attachment])">{{ __('Download') }}</x-actions.button>@if($canDecide)<flux:button size="xs" variant="danger" wire:click="revokeEvidence('{{ $attachment->id }}')" wire:confirm="{{ __('Revoke this evidence file?') }}">{{ __('Revoke') }}</flux:button>@endif@endif</div></div>@empty<p class="text-sm text-text-muted">{{ __('No evidence attached.') }}</p>@endforelse</div></section>

                @if($selectedApproval->approval_state === ApprovalState::Pending && $canDecide)
                    <div class="space-y-3 border-t border-border-subtle pt-4"><flux:textarea wire:model="decisionReason" :label="$selectedApproval->source_type === 'pos_shifts' ? __('Recount reason') : __('Rejection reason')" :description="$selectedApproval->source_type === 'pos_shifts' ? __('Returning a shift for recount rejects this exact approval request and preserves its history.') : __('Required only when rejecting. The source keeps this reason in its immutable history.')" />@error('decisionReason')<p class="text-sm text-red-600">{{ $message }}</p>@enderror<div class="flex flex-wrap justify-end gap-2"><flux:button variant="subtle" wire:click="closeApproval">{{ __('Close') }}</flux:button>@if($canReject)<flux:button variant="danger" wire:click="reject" wire:confirm="{{ $selectedApproval->source_type === 'pos_shifts' ? __('Return this shift for recount?') : __('Reject this source record?') }}">{{ $selectedApproval->source_type === 'pos_shifts' ? __('Request recount') : __('Reject') }}</flux:button>@endif<flux:button variant="primary" wire:click="approve" wire:confirm="{{ __('Approve this source record and execute its domain effects?') }}">{{ $selectedApproval->source_type === 'pos_shifts' ? __('Approve and close') : __('Approve') }}</flux:button></div></div>
                @else<div class="flex justify-end"><flux:button variant="subtle" wire:click="closeApproval">{{ __('Close') }}</flux:button></div>@endif
            </div>
        @endif
    </flux:modal>
</x-app.page>

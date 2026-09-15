<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlatformSettingsApprovalAction
{
    /** @param array<string, mixed> $proposed @param array<string, mixed>|null $before */
    public function request(string $resource, ?int $id, array $proposed, ?array $before = null, ?int $branchId = null, ?int $storeId = null, ?string $reason = null): ApprovalRecord
    {
        $requestPermission = $this->requestPermission($resource);
        Gate::authorize($requestPermission);
        /** @var User $requester */
        $requester = auth()->user() ?? throw new \LogicException('An authenticated requester is required.');

        if ($resource === 'tax_setting') {
            $before = $id === null ? null : $this->snapshot(TaxSetting::query()->findOrFail($id));
        } elseif ($resource === 'document_sequence') {
            if ($id !== null) {
                $sequence = DocumentSequence::visibleTo($requester)->findOrFail($id);
                $this->assertSequenceScopeUnchanged($sequence, $proposed);
                $branchId = $this->sequenceBranchId($requester, $sequence);
                $before = $this->snapshot($sequence);
            } else {
                $branchId = ($proposed['scope_type'] ?? 'company') === 'branch'
                    ? Branch::visibleTo($requester)
                        ->whereKey((int) ($proposed['scope_id'] ?? 0))
                        ->where('status', 'active')
                        ->firstOrFail()
                        ->id
                    : null;
                $before = null;
            }
        } elseif ($resource === 'document_sequence_override') {
            if ($id === null || (int) ($proposed['sequence_id'] ?? 0) !== $id) {
                throw ValidationException::withMessages(['sequence_id' => __('The sequence override target must match its approval source.')]);
            }
            $sequence = DocumentSequence::visibleTo($requester)
                ->whereKey($id)
                ->firstOrFail();
            $branchId = $this->sequenceBranchId($requester, $sequence);
            $before = $this->snapshot($sequence);
        }

        if (in_array($resource, ['branch_delete', 'store_delete', 'store_archive', 'cash_drawer_delete'], true)) {
            [$branchId, $storeId, $before] = $this->masterDeleteScope($resource, $id, $requester, $branchId, $storeId);
        }

        if (in_array($resource, ['store_delete', 'store_archive'], true)) {
            if ($id === null) {
                throw ValidationException::withMessages(['source' => __('An existing location is required for archive approval.')]);
            }
            app(SaveStoreAction::class)->assertStoreDependencyFree($id, 'archive', false);
        }

        $payload = ['resource' => $resource, 'id' => $id, 'proposed' => $proposed, 'before' => $before];
        $sourceHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $sourceId = $resource.':'.($id === null ? 'new:'.substr($sourceHash, 0, 16) : $id);
        $isMasterDelete = in_array($resource, ['branch_delete', 'store_delete', 'store_archive', 'cash_drawer_delete'], true);

        $approval = app(RequestApproval::class)->execute(new ApprovalRequestData(
            sourceType: 'platform_settings',
            sourceId: $sourceId,
            sourceVersion: $id === null ? 'new' : (string) $id,
            requestedAction: $resource,
            requestPermission: $requestPermission,
            branchId: $branchId,
            storeId: $storeId,
            reasonCode: in_array($resource, ['branch_delete', 'store_delete', 'store_archive', 'cash_drawer_delete'], true) ? 'MASTER_DELETE' : null,
            reasonText: $reason,
            limitContext: $payload,
            sourceHash: $sourceHash,
            idempotencyKey: 'platform-settings-'.$sourceHash.($isMasterDelete ? '-'.Str::lower(Str::random(16)) : ''),
            decisionPermission: 'company_settings.approve',
        ));

        if ($requester instanceof User && $requester->canBypassApproval()) {
            $this->approve($approval);
        }

        return $approval->fresh();
    }

    public function approve(ApprovalRecord $record): void
    {
        DB::transaction(function () use ($record): void {
            $record = ApprovalRecord::query()->lockForUpdate()->findOrFail($record->id);
            $context = $record->limit_context ?? [];
            $resource = (string) ($context['resource'] ?? '');
            $this->assertCanonicalApprovalMetadata($record, $context, $resource);
            $this->assertSourceUnchanged($context, $resource);
            if (in_array($resource, ['store_delete', 'store_archive'], true)) {
                $id = isset($context['id']) && $context['id'] !== null ? (int) $context['id'] : null;
                if ($id === null) {
                    throw ValidationException::withMessages(['approval' => __('The archive source location is missing.')]);
                }
                // Re-check while the source row and every known dependency
                // row are locked, immediately before the approval transition.
                app(SaveStoreAction::class)->assertStoreDependencyFree($id, 'archive', true);
            }
            $approved = app(ApproveRequest::class)->execute($record, $record->source_version, $record->source_hash, __('Platform master change approved.'));
            $id = isset($context['id']) && $context['id'] !== null ? (int) $context['id'] : null;
            $proposed = is_array($context['proposed'] ?? null) ? $context['proposed'] : [];

            match ($resource) {
                'tax_setting' => app(SaveLocalSettingsAction::class)->applyApprovedTaxSetting($approved),
                'document_sequence' => app(SaveLocalSettingsAction::class)->applyApprovedDocumentSequence($approved),
                'document_sequence_override' => app(OverrideDocumentSequenceCounter::class)->applyApproved($approved),
                'branch_delete', 'store_delete', 'store_archive', 'cash_drawer_delete' => $this->applyApprovedMasterDelete($resource, $id, $approved),
                default => throw ValidationException::withMessages(['approval' => __('This platform setting approval source is not supported.')]),
            };
        });
    }

    private function sequenceBranchId(User $requester, DocumentSequence $sequence): ?int
    {
        return $sequence->scope_type === 'branch'
            ? Branch::visibleTo($requester)
                ->whereKey($sequence->scope_id)
                ->where('status', 'active')
                ->firstOrFail()
                ->id
            : null;
    }

    /** @return array<string, mixed> */
    private function snapshot(TaxSetting|DocumentSequence $target): array
    {
        $before = $target->getAttributes();
        unset($before['created_at'], $before['updated_at']);

        return $before;
    }

    /** @param array<string, mixed> $proposed */
    private function assertSequenceScopeUnchanged(DocumentSequence $sequence, array $proposed): void
    {
        $scopeType = strtolower(trim((string) ($proposed['scope_type'] ?? '')));
        $scopeId = $scopeType === 'branch' && filled($proposed['scope_id'] ?? null)
            ? (int) $proposed['scope_id']
            : null;

        if ($scopeType !== $sequence->scope_type || $scopeId !== $sequence->scope_id) {
            throw ValidationException::withMessages(['documentSequenceForm.scope_type' => __('Existing document sequence scope cannot be changed through approval.')]);
        }
    }

    /** @return array{0: int, 1: int|null, 2: array<string, mixed>} */
    private function masterDeleteScope(string $resource, ?int $id, User $requester, ?int $branchId, ?int $storeId): array
    {
        if ($id === null) {
            throw ValidationException::withMessages(['source' => __('An existing platform master is required for deletion approval.')]);
        }

        $target = match ($resource) {
            'branch_delete' => Branch::visibleTo($requester)->findOrFail($id),
            'store_delete', 'store_archive' => Store::visibleTo($requester)->findOrFail($id),
            'cash_drawer_delete' => CashDrawer::visibleTo($requester)->findOrFail($id),
        };
        $expectedBranchId = $resource === 'branch_delete' ? $target->id : $target->branch_id;
        $expectedStoreId = $resource === 'store_delete' || $resource === 'store_archive' ? $target->id : $target->store_id;

        if ($target->status !== 'active') {
            throw ValidationException::withMessages(['source' => __('Only active platform masters can receive a delete approval request.')]);
        }

        if ($branchId !== $expectedBranchId || $storeId !== $expectedStoreId) {
            throw ValidationException::withMessages(['scope' => __('Approval scope must match the platform master being changed.')]);
        }

        $before = $target->getAttributes();
        unset($before['created_at'], $before['updated_at']);

        return [$expectedBranchId, $expectedStoreId, $before];
    }

    private function applyApprovedMasterDelete(string $resource, ?int $id, ApprovalRecord $approval): void
    {
        if ($id === null) {
            throw ValidationException::withMessages(['approval' => __('The platform master source is missing.')]);
        }

        match ($resource) {
            'branch_delete' => $this->logicalDeleteBranch($id, $approval),
            'store_delete', 'store_archive' => $this->logicalDeleteStore($id, $approval),
            'cash_drawer_delete' => $this->logicalDeleteCashDrawer($id, $approval),
        };
    }

    private function logicalDeleteBranch(int $id, ApprovalRecord $approval): void
    {
        $branch = Branch::query()->lockForUpdate()->findOrFail($id);
        if ($branch->stores()->where('status', 'active')->exists() || $branch->activeSellingStoreMapping()->exists()) {
            throw new \InvalidArgumentException(__('Cannot delete branch while it has active stores or an active selling store mapping.'));
        }

        $before = $branch->getAttributes();
        $branch->update(['status' => 'inactive']);
        app(RecordAuditEvent::class)->execute(
            category: 'master_data', event: 'delete_branch', source: $branch, before: $before,
            after: ['deleted' => true, 'status' => 'inactive'], branchId: $branch->id,
            metadata: ['logical_delete' => true, 'approval_required' => true, 'approval_record_id' => $approval->id],
        );
    }

    private function logicalDeleteStore(int $id, ApprovalRecord $approval): void
    {
        $store = Store::query()->lockForUpdate()->findOrFail($id);
        app(SaveStoreAction::class)->assertStoreDependencyFree($store->id, 'archive', true);

        $before = $store->getAttributes();
        $store->update(['status' => 'inactive']);
        app(RecordAuditEvent::class)->execute(
            category: 'master_data', event: 'delete_store', source: $store, before: $before,
            after: ['deleted' => true, 'status' => 'inactive'], branchId: $store->branch_id, storeId: $store->id,
            metadata: ['logical_delete' => true, 'approval_required' => true, 'approval_record_id' => $approval->id],
        );
    }

    private function logicalDeleteCashDrawer(int $id, ApprovalRecord $approval): void
    {
        $drawer = CashDrawer::query()->lockForUpdate()->findOrFail($id);
        if (PosShift::query()->active()->where('cash_drawer_id', $drawer->id)->lockForUpdate()->exists()) {
            throw new \InvalidArgumentException(__('Cannot deactivate or reassign a cash drawer while it has an active POS shift. Close the shift before trying again.'));
        }

        $before = $drawer->getAttributes();
        $drawer->update(['status' => 'inactive']);
        app(RecordAuditEvent::class)->execute(
            category: 'master_data', event: 'delete_cash_drawer', source: $drawer, before: $before,
            after: ['deleted' => true, 'status' => 'inactive'], branchId: $drawer->branch_id, storeId: $drawer->store_id,
            metadata: ['logical_delete' => true, 'approval_required' => true, 'approval_record_id' => $approval->id],
        );
    }

    public function reject(ApprovalRecord $record, string $reason): void
    {
        DB::transaction(function () use ($record, $reason): void {
            $record = ApprovalRecord::query()->lockForUpdate()->findOrFail($record->id);
            $context = $record->limit_context ?? [];
            $resource = (string) ($context['resource'] ?? '');
            $this->assertCanonicalApprovalMetadata($record, $context, $resource);

            app(RejectRequest::class)->execute($record, $record->source_version, $reason, $record->source_hash);
        });
    }

    private function requestPermission(string $resource): string
    {
        return match ($resource) {
            'tax_setting', 'document_sequence' => 'company_settings.edit',
            'document_sequence_override' => 'drawers_payments_tax_numbering_printers.override',
            'branch_delete', 'store_delete', 'store_archive' => 'branches_stores.logical_delete',
            'cash_drawer_delete' => 'drawers_payments_tax_numbering_printers.logical_delete',
            default => throw ValidationException::withMessages(['resource' => __('This platform setting is not approval-enabled.')]),
        };
    }

    /** @param array<string, mixed> $context */
    private function assertCanonicalApprovalMetadata(ApprovalRecord $record, array $context, string $resource): void
    {
        $id = isset($context['id']) && $context['id'] !== null ? (int) $context['id'] : null;
        $proposed = $context['proposed'] ?? null;
        $before = $context['before'] ?? null;
        $expectedContext = [
            'resource' => $resource,
            'id' => $id,
            'proposed' => $proposed,
            'before' => $before,
        ];
        $expectedSourceHash = hash('sha256', json_encode($expectedContext, JSON_THROW_ON_ERROR));
        $expectedSourceId = $resource.':'.($id === null ? 'new:'.substr($expectedSourceHash, 0, 16) : $id);
        $expectedSourceVersion = $id === null ? 'new' : (string) $id;
        $reasonCode = in_array($resource, ['branch_delete', 'store_delete', 'store_archive', 'cash_drawer_delete'], true)
            ? 'MASTER_DELETE'
            : null;

        if (! is_array($proposed)
            || ! (is_array($before) || $before === null)
            || $context !== $expectedContext
            || $record->source_type !== 'platform_settings'
            || $record->requested_action !== $resource
            || $record->source_id !== $expectedSourceId
            || $record->source_version !== $expectedSourceVersion
            || $record->source_hash !== $expectedSourceHash
            || $record->request_permission !== $this->requestPermission($resource)
            || $record->decision_permission !== 'company_settings.approve'
            || $record->reason_code !== $reasonCode) {
            throw ValidationException::withMessages(['approval' => __('The approval request metadata does not match its canonical platform target.')]);
        }

        try {
            [$branchId, $storeId] = $this->targetScope($resource, $id, $proposed);
            $canonicalBefore = $this->canonicalBefore($resource, $id);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['approval' => __('The approval request source no longer exists.')]);
        }

        if ($before !== $canonicalBefore) {
            throw ValidationException::withMessages(['approval' => __('The approval request snapshot does not match its canonical platform target.')]);
        }
        if ($resource === 'document_sequence' && $id !== null) {
            $this->assertSequenceScopeUnchanged(DocumentSequence::query()->lockForUpdate()->findOrFail($id), $proposed);
        }

        if (($record->branch_id === null ? null : (int) $record->branch_id) !== $branchId
            || ($record->store_id === null ? null : (int) $record->store_id) !== $storeId) {
            throw ValidationException::withMessages(['approval' => __('The approval request scope does not match its canonical platform target.')]);
        }
    }

    /** @param array<string, mixed>|null $proposed @return array{0: int|null, 1: int|null} */
    private function targetScope(string $resource, ?int $id, ?array $proposed): array
    {
        return match ($resource) {
            'branch_delete' => [Branch::query()->lockForUpdate()->findOrFail($id)->id, null],
            'store_delete', 'store_archive' => (function () use ($id): array {
                $store = Store::query()->lockForUpdate()->findOrFail($id);

                return [$store->branch_id, $store->id];
            })(),
            'cash_drawer_delete' => (function () use ($id): array {
                $drawer = CashDrawer::query()->lockForUpdate()->findOrFail($id);

                return [$drawer->branch_id, $drawer->store_id];
            })(),
            'document_sequence' => $this->sequenceScope($id, $proposed),
            'document_sequence_override' => $this->sequenceScope($id, $proposed),
            'tax_setting' => $this->taxSettingScope($id),
            default => throw ValidationException::withMessages(['approval' => __('This platform setting approval source is not supported.')]),
        };
    }

    /** @param array<string, mixed>|null $proposed @return array{0: int|null, 1: null} */
    private function sequenceScope(?int $id, ?array $proposed): array
    {
        if ($id === null) {
            $scopeType = $proposed['scope_type'] ?? null;
            $scopeId = $proposed['scope_id'] ?? null;

            return [$scopeType === 'branch' ? (int) $scopeId : null, null];
        }

        $sequence = DocumentSequence::query()->lockForUpdate()->findOrFail($id);

        return [$sequence->scope_type === 'branch' ? (int) $sequence->scope_id : null, null];
    }

    /** @return array{0: null, 1: null} */
    private function taxSettingScope(?int $id): array
    {
        if ($id !== null) {
            TaxSetting::query()->lockForUpdate()->findOrFail($id);
        }

        return [null, null];
    }

    /** @return array<string, mixed>|null */
    private function canonicalBefore(string $resource, ?int $id): ?array
    {
        if ($id === null) {
            return null;
        }

        $target = match ($resource) {
            'tax_setting' => TaxSetting::query()->lockForUpdate()->findOrFail($id),
            'document_sequence', 'document_sequence_override' => DocumentSequence::query()->lockForUpdate()->findOrFail($id),
            'branch_delete' => Branch::query()->lockForUpdate()->findOrFail($id),
            'store_delete', 'store_archive' => Store::query()->lockForUpdate()->findOrFail($id),
            'cash_drawer_delete' => CashDrawer::query()->lockForUpdate()->findOrFail($id),
            default => throw ValidationException::withMessages(['approval' => __('This platform setting approval source is not supported.')]),
        };
        $before = $target->getAttributes();
        unset($before['created_at'], $before['updated_at']);

        return $before;
    }

    /** @param array<string, mixed> $data */
    private function applySequenceOverride(array $data, ?int $id): void
    {
        if ($id === null || (int) ($data['sequence_id'] ?? 0) !== $id) {
            throw ValidationException::withMessages(['sequence_id' => __('The sequence override target must match its approval source.')]);
        }

        $sequence = DocumentSequence::query()->lockForUpdate()->findOrFail($id);
        app(OverrideDocumentSequenceCounter::class)->execute($sequence, (int) $data['next_value'], (int) $data['expected_lock_version'], (string) $data['reason']);
    }

    /** @param array<string, mixed> $context */
    private function assertSourceUnchanged(array $context, string $resource): void
    {
        $before = is_array($context['before'] ?? null) ? $context['before'] : null;
        $id = isset($context['id']) && $context['id'] !== null ? (int) $context['id'] : null;
        if ($before === null || $id === null) {
            return;
        }

        $model = match ($resource) {
            'tax_setting' => TaxSetting::class,
            'document_sequence', 'document_sequence_override' => DocumentSequence::class,
            'branch_delete' => Branch::class,
            'store_delete', 'store_archive' => Store::class,
            'cash_drawer_delete' => CashDrawer::class,
            default => null,
        };
        if ($model === null) {
            return;
        }

        $record = $model::query()->lockForUpdate()->find($id);
        $sourceChanged = $record === null;
        if (! $sourceChanged) {
            foreach ($before as $key => $expected) {
                $actual = $record->getRawOriginal($key);
                if (($actual === null || $expected === null) ? $actual !== $expected : (string) $actual !== (string) $expected) {
                    $sourceChanged = true;
                    break;
                }
            }
        }
        if ($sourceChanged) {
            throw ValidationException::withMessages(['approval' => __('The source changed after this approval request. Reload it and submit a new request.')]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\ProductWalletAdjustment;
use App\Modules\Customer\Models\ProductWalletLedger;
use App\Modules\Customer\Support\WalletPolicy;
use App\Modules\Platform\Actions\ApprovalRecordTransition;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ApproveProductWalletAdjustmentAction
{
    public function execute(User $approver, ApprovalRecord $approval, Store $store): ProductWalletLedger
    {
        Gate::forUser($approver)->authorize('decide', $approval);
        abort_unless($approval->source_type === 'product_wallet_adjustments' && $approval->decisionPermission() === 'product_wallet.approve', 403);

        return DB::transaction(function () use ($approver, $approval, $store): ProductWalletLedger {
            $approval = ApprovalRecord::query()->lockForUpdate()->findOrFail($approval->id);
            Gate::forUser($approver)->authorize('decide', $approval);
            $adjustment = ProductWalletAdjustment::query()->lockForUpdate()->findOrFail((int) $approval->source_id);
            if ((int) $adjustment->requested_by === (int) $approver->id && ! $approver->canBypassApproval()) {
                throw ValidationException::withMessages(['approval' => __('The requester cannot approve the same Product Wallet adjustment.')]);
            }
            $store = Store::query()->visibleTo($approver)->whereKey($store->id)->where('status', 'active')->firstOrFail();
            $customer = Customer::query()->visibleFrom($approver, (int) $store->branch_id, (int) $store->id)->whereKey($adjustment->customer_id)->where('status', 'active')->firstOrFail();
            $payloadHash = $this->payloadHash($adjustment, $store);
            $this->assertApprovalSource($approval, $adjustment, $payloadHash);
            $policy = WalletPolicy::for('product');
            WalletPolicy::assertAdjustmentEnabled('product');
            $currency = WalletPolicy::currencyCode($store);

            app(ApprovalRecordTransition::class)->execute(
                $approval,
                ApprovalState::Approved,
                'approval_approved',
                ['approver_id' => $approver->id, 'decided_at' => now(), 'decision_note' => __('Product Wallet adjustment approved.')],
                expectedSourceVersion: (string) $adjustment->lock_version,
                expectedSourceHash: $payloadHash,
                authorize: static function (ApprovalRecord $record) use ($approver): void {
                    Gate::forUser($approver)->authorize('decide', $record);
                },
            );

            Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $before = bcadd((string) (ProductWalletLedger::query()->where('customer_id', $customer->id)->sum('amount') ?: '0'), '0', 4);
            if ($adjustment->operation === 'adjustment') {
                $after = bcadd($before, (string) $adjustment->amount, 4);
                WalletPolicy::assertBalance($policy, $after);
                $entry = ProductWalletLedger::appendEntry([
                    'customer_id' => $customer->id, 'branch_id' => $store->branch_id, 'store_id' => $store->id,
                    'entry_type' => 'adjustment', 'amount' => $adjustment->amount, 'balance_before' => $before,
                    'balance_after' => $after, 'currency_code' => $currency, 'source_type' => 'product_wallet_adjustments',
                    'source_id' => (string) $adjustment->id, 'source_line_id' => $adjustment->source_line_id,
                    'idempotency_key' => 'PRODUCT-WALLET-ADJUSTMENT:'.$adjustment->id,
                    'payload_hash' => WalletPolicy::payloadHash(['adjustment_id' => $adjustment->id, 'stage' => 'adjustment', 'payload_hash' => $payloadHash]),
                    'reference' => $adjustment->source_reference, 'reason' => $adjustment->reason,
                    'created_by' => $approver->id, 'metadata' => ['wallet' => 'product', 'approval_record_id' => $approval->id, 'operation' => 'adjustment'],
                    'created_at' => now(),
                ]);
                $adjustment->transition(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now(), 'decision_note' => __('Product Wallet adjustment approved.')]);
                app(RecordAuditEvent::class)->execute(
                    category: 'customer_value', event: 'product_wallet_adjustment_applied', source: $entry,
                    before: ['balance' => $before], after: ['balance' => $after, 'adjustment_id' => $adjustment->id],
                    branchId: (int) $store->branch_id, storeId: (int) $store->id, reasonText: $adjustment->reason,
                    metadata: ['wallet' => 'product', 'approval_record_id' => $approval->id, 'customer_id' => $customer->id],
                );

                return $entry;
            }

            $target = ProductWalletLedger::query()->lockForUpdate()->findOrFail((int) $adjustment->target_ledger_id);
            if ((int) $target->customer_id !== (int) $customer->id || (int) $target->store_id !== (int) $store->id || $target->entry_type === 'reversal') {
                throw new InvalidArgumentException(__('The correction target is no longer valid for this Product Wallet.'));
            }
            $reversalAfter = bcadd($before, '-'.(string) $target->amount, 4);
            $reversal = ProductWalletLedger::appendEntry([
                'customer_id' => $customer->id, 'branch_id' => $store->branch_id, 'store_id' => $store->id,
                'entry_type' => 'reversal', 'amount' => '-'.(string) $target->amount, 'balance_before' => $before,
                'balance_after' => $reversalAfter, 'currency_code' => $currency, 'source_type' => 'product_wallet_adjustments',
                'source_id' => (string) $adjustment->id, 'source_line_id' => $adjustment->source_line_id,
                'idempotency_key' => 'PRODUCT-WALLET-CORRECTION:'.$adjustment->id.':REVERSAL',
                'payload_hash' => WalletPolicy::payloadHash(['adjustment_id' => $adjustment->id, 'stage' => 'reversal', 'payload_hash' => $payloadHash]),
                'reference' => $adjustment->source_reference, 'reason' => $adjustment->reason,
                'reversal_of_id' => $target->id, 'created_by' => $approver->id,
                'metadata' => ['wallet' => 'product', 'approval_record_id' => $approval->id, 'correction_target_id' => $target->id],
                'created_at' => now(),
            ]);
            $after = bcadd($reversalAfter, (string) $adjustment->amount, 4);
            WalletPolicy::assertBalance($policy, $after);
            $entry = ProductWalletLedger::appendEntry([
                'customer_id' => $customer->id, 'branch_id' => $store->branch_id, 'store_id' => $store->id,
                'entry_type' => 'correction', 'amount' => $adjustment->amount, 'balance_before' => $reversalAfter,
                'balance_after' => $after, 'currency_code' => $currency, 'source_type' => 'product_wallet_adjustments',
                'source_id' => (string) $adjustment->id, 'source_line_id' => $adjustment->source_line_id,
                'idempotency_key' => 'PRODUCT-WALLET-CORRECTION:'.$adjustment->id.':CORRECTION',
                'payload_hash' => WalletPolicy::payloadHash(['adjustment_id' => $adjustment->id, 'stage' => 'correction', 'payload_hash' => $payloadHash]),
                'reference' => $adjustment->source_reference, 'reason' => $adjustment->reason,
                'correction_of_id' => $target->id, 'created_by' => $approver->id,
                'metadata' => ['wallet' => 'product', 'approval_record_id' => $approval->id, 'correction_target_id' => $target->id],
                'created_at' => now(),
            ]);
            $adjustment->transition(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now(), 'decision_note' => __('Product Wallet correction approved.')]);
            foreach ([$reversal, $entry] as $posted) {
                app(RecordAuditEvent::class)->execute(
                    category: 'customer_value', event: 'product_wallet_'.$posted->entry_type.'_posted', source: $posted,
                    before: ['balance' => $posted->balance_before], after: ['balance' => $posted->balance_after, 'adjustment_id' => $adjustment->id],
                    branchId: (int) $store->branch_id, storeId: (int) $store->id, reasonText: $adjustment->reason,
                    metadata: ['wallet' => 'product', 'approval_record_id' => $approval->id, 'correction_target_id' => $target->id],
                );
            }

            return $entry;
        }, 5);
    }

    private function payloadHash(ProductWalletAdjustment $adjustment, Store $store): string
    {
        return WalletPolicy::payloadHash([
            'wallet' => 'product', 'customer_id' => (int) $adjustment->customer_id, 'branch_id' => (int) $adjustment->branch_id,
            'store_id' => (int) $adjustment->store_id, 'operation' => $adjustment->operation, 'amount' => (string) $adjustment->amount,
            'target_ledger_id' => $adjustment->target_ledger_id, 'source_type' => $adjustment->source_type,
            'source_id' => $adjustment->source_id, 'source_line_id' => $adjustment->source_line_id,
            'source_reference' => $adjustment->source_reference, 'reason' => $adjustment->reason,
            'currency_code' => WalletPolicy::currencyCode($store),
        ]);
    }

    private function assertApprovalSource(ApprovalRecord $approval, ProductWalletAdjustment $adjustment, string $payloadHash): void
    {
        if ($approval->source_hash !== $payloadHash || (string) $approval->source_version !== (string) $adjustment->lock_version) {
            throw ValidationException::withMessages(['approval' => __('The Product Wallet approval source changed and must be requested again.')]);
        }
    }
}

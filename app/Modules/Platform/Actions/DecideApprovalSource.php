<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Assets\Actions\ApproveAssetEventAction;
use App\Modules\Assets\Actions\RejectAssetEventAction;
use App\Modules\Assets\Models\AssetEvent;
use App\Modules\Customer\Actions\ApproveLoyaltyAdjustmentAction;
use App\Modules\Customer\Actions\ApprovePartyWalletAdjustmentAction;
use App\Modules\Customer\Actions\ApproveProductWalletAdjustmentAction;
use App\Modules\Customer\Actions\RejectLoyaltyAdjustmentAction;
use App\Modules\Customer\Actions\RejectPartyWalletAdjustmentAction;
use App\Modules\Customer\Actions\RejectProductWalletAdjustmentAction;
use App\Modules\Customer\Models\LoyaltyAdjustment;
use App\Modules\Customer\Models\PartyWalletAdjustment;
use App\Modules\Customer\Models\ProductWalletAdjustment;
use App\Modules\Inventory\Actions\ApproveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\ApproveStockTransferAction;
use App\Modules\Inventory\Actions\ReconcileStockCountAction;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\RejectPriceProposalAction;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\ApprovePurchaseOrderAction;
use App\Modules\Purchasing\Actions\ApprovePurchaseReturnAction;
use App\Modules\Purchasing\Actions\RejectPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\RejectPurchaseReturnAction;
use App\Modules\Retail\Actions\ApproveDiscountAction;
use App\Modules\Retail\Actions\ApproveOpenPriceAction;
use App\Modules\Retail\Actions\RejectDiscountAction;
use App\Modules\Retail\Actions\RejectOpenPriceAction;
use App\Modules\Retail\Actions\ReviewShiftVarianceAction;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Focused routing from the shared inbox to source-owned domain actions.
 * Source actions retain authorization, locks, posting, and audit ownership.
 */
final class DecideApprovalSource
{
    public function approve(ApprovalRecord $record): void
    {
        match ($record->source_type) {
            'platform_settings' => app(PlatformSettingsApprovalAction::class)->approve($record),
            'pricing_labels' => app(ApprovePriceProposalAction::class)->execute(PriceVersion::query()->findOrFail($record->source_id)),
            'purchase_orders' => app(ApprovePurchaseOrderAction::class)->execute((int) $record->source_id, (int) $record->source_version),
            'purchase_invoices' => app(ApprovePurchaseInvoiceAction::class)->execute((int) $record->source_id, (int) $record->source_version),
            'purchase_returns' => app(ApprovePurchaseReturnAction::class)->execute((int) $record->source_id, (int) $record->source_version),
            'inventory_adjustments' => app(ApproveInventoryAdjustmentAction::class)->execute((int) $record->source_id),
            'stock_counts' => app(ReconcileStockCountAction::class)->execute((int) $record->source_id),
            'stock_transfers' => app(ApproveStockTransferAction::class)->execute((int) $record->source_id),
            'loyalty_adjustments' => app(ApproveLoyaltyAdjustmentAction::class)->execute(
                auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                $record,
                Store::query()->findOrFail((int) $record->store_id),
            ),
            'product_wallet_adjustments' => app(ApproveProductWalletAdjustmentAction::class)->execute(
                auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                $record,
                Store::query()->findOrFail((int) $record->store_id),
            ),
            'party_wallet_adjustments' => app(ApprovePartyWalletAdjustmentAction::class)->execute(
                auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                $record,
                Store::query()->findOrFail((int) $record->store_id),
            ),
            'pos_shifts' => app(ReviewShiftVarianceAction::class)->approveAndClose(
                auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                PosShift::query()->findOrFail($record->source_id),
                $record,
                (int) $record->source_version,
            ),
            'pos_open_price' => app(ApproveOpenPriceAction::class)->execute(
                auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                $record,
            ),
            'pos_discount' => app(ApproveDiscountAction::class)->execute(
                auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                $record,
            ),
            'asset_events' => app(ApproveAssetEventAction::class)->execute(
                auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                AssetEvent::query()->findOrFail($record->source_id),
            ),
            default => throw ValidationException::withMessages(['approval' => __('This approval source is not supported by the central inbox.')]),
        };
    }

    public function reject(ApprovalRecord $record, string $reason): void
    {
        DB::transaction(function () use ($record, $reason): void {
            $record = ApprovalRecord::query()->lockForUpdate()->findOrFail($record->id);

            match ($record->source_type) {
                'platform_settings' => app(PlatformSettingsApprovalAction::class)->reject($record, $reason),
                'pricing_labels' => app(RejectPriceProposalAction::class)->execute(PriceVersion::query()->findOrFail($record->source_id), $reason),
                'purchase_invoices' => app(RejectPurchaseInvoiceAction::class)->execute((int) $record->source_id, $reason, (int) $record->source_version),
                'purchase_returns' => app(RejectPurchaseReturnAction::class)->execute((int) $record->source_id, $reason, (int) $record->source_version),
                'loyalty_adjustments' => app(RejectLoyaltyAdjustmentAction::class)->execute(
                    auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                    $record,
                    $reason,
                ),
                'product_wallet_adjustments' => app(RejectProductWalletAdjustmentAction::class)->execute(
                    auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                    $record,
                    $reason,
                ),
                'party_wallet_adjustments' => app(RejectPartyWalletAdjustmentAction::class)->execute(
                    auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                    $record,
                    $reason,
                ),
                'pos_shifts' => app(ReviewShiftVarianceAction::class)->requestRecount(
                    auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                    PosShift::query()->findOrFail($record->source_id),
                    $record,
                    $reason,
                    (int) $record->source_version,
                ),
                'pos_open_price' => app(RejectOpenPriceAction::class)->execute(
                    auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                    $record,
                    $reason,
                ),
                'pos_discount' => app(RejectDiscountAction::class)->execute(
                    auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                    $record,
                    $reason,
                ),
                'asset_events' => app(RejectAssetEventAction::class)->execute(
                    auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                    AssetEvent::query()->findOrFail($record->source_id),
                    $reason,
                ),
                default => throw ValidationException::withMessages(['approval' => __('This source does not expose a rejection transition.')]),
            };
        });
    }

    public function canReject(ApprovalRecord $record): bool
    {
        return in_array($record->source_type, ['platform_settings', 'pricing_labels', 'purchase_invoices', 'purchase_returns', 'loyalty_adjustments', 'product_wallet_adjustments', 'party_wallet_adjustments', 'pos_shifts', 'pos_open_price', 'pos_discount', 'asset_events'], true);
    }

    public function sourceRoute(ApprovalRecord $record): string
    {
        return match ($record->source_type) {
            'platform_settings' => route('admin.approvals'),
            'pricing_labels' => route('pricing.approvals'),
            'purchase_orders' => route('purchasing.orders'),
            'purchase_invoices' => route('purchasing.invoices'),
            'purchase_returns' => route('purchasing.returns.show', $record->source_id),
            'inventory_adjustments' => route('inventory.adjustments'),
            'stock_counts' => route('inventory.counts.reconcile-page', $record->source_id),
            'stock_transfers' => route('inventory.transfers'),
            'loyalty_adjustments' => ($adjustment = LoyaltyAdjustment::query()->find($record->source_id)) !== null
                ? route('customers.loyalty', $adjustment->customer_id)
                : route('admin.approvals'),
            'product_wallet_adjustments' => ($adjustment = ProductWalletAdjustment::query()->find($record->source_id)) !== null
                ? route('customers.product-wallet', $adjustment->customer_id)
                : route('admin.approvals'),
            'party_wallet_adjustments' => ($adjustment = PartyWalletAdjustment::query()->find($record->source_id)) !== null
                ? route('customers.party-wallet', $adjustment->customer_id)
                : route('admin.approvals'),
            'pos_shifts' => route('pos.shift-variance'),
            'pos_open_price' => route('pos'),
            'pos_discount' => route('pos'),
            'asset_events' => route('party.assets.index'),
            default => route('admin.approvals'),
        };
    }
}

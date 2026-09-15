<?php

namespace App\Modules\Pricing\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CreatePriceProposalAction
{
    public function execute(
        Product $product,
        Store $store,
        string $priceListCode,
        string $priceListNameAr,
        string $priceListNameEn,
        string $amount,
        string $sourceType,
        ?string $sourceReference,
        ?string $effectiveFrom,
        ?string $effectiveTo,
        ?string $reasonText,
        ?string $referenceAmount = null,
        bool $openPriceAllowed = false,
        ?string $openPriceMinimum = null,
        ?string $openPriceMaximum = null,
    ): PriceVersion {
        /** @var User $user */
        $user = Auth::user() ?? throw new \LogicException('An authenticated pricing user is required.');
        Gate::forUser($user)->authorize('pricing_labels.create');
        $this->authorizeScope($user, $store);

        if ($product->status !== 'active' || $store->status !== 'active') {
            throw ValidationException::withMessages(['source' => __('The product and store must both be active.')]);
        }
        if (! in_array($sourceType, ['product_card', 'import', 'purchase_context', 'branch_exception'], true)) {
            throw ValidationException::withMessages(['source_type' => __('The proposal source is not supported.')]);
        }
        if ($sourceType === 'branch_exception') {
            Gate::forUser($user)->authorize('pricing_labels.override');
            if (blank($reasonText)) {
                throw ValidationException::withMessages(['reason_text' => __('A reason is required for a branch exception.')]);
            }
        }
        if ((float) $amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('The proposed price must be greater than zero.')]);
        }
        if ($openPriceAllowed) {
            if ($referenceAmount === null || $openPriceMinimum === null || $openPriceMaximum === null) {
                throw ValidationException::withMessages(['open_price' => __('Reference, minimum, and maximum amounts are required when open price is enabled.')]);
            }
            if (bccomp($openPriceMinimum, '0', 4) < 0
                || bccomp($openPriceMaximum, $openPriceMinimum, 4) < 0
                || bccomp($referenceAmount, $openPriceMinimum, 4) < 0
                || bccomp($referenceAmount, $openPriceMaximum, 4) > 0) {
                throw ValidationException::withMessages(['open_price' => __('Open-price bounds must contain the reference amount and form a valid range.')]);
            }
        }
        if ($effectiveFrom !== null && $effectiveTo !== null && $effectiveTo <= $effectiveFrom) {
            throw ValidationException::withMessages(['effective_to' => __('The end of the effective period must be after its start.')]);
        }

        return DB::transaction(function () use ($user, $product, $store, $priceListCode, $priceListNameAr, $priceListNameEn, $amount, $sourceType, $sourceReference, $effectiveFrom, $effectiveTo, $reasonText, $referenceAmount, $openPriceAllowed, $openPriceMinimum, $openPriceMaximum): PriceVersion {
            $list = PriceList::query()->firstOrCreate(
                ['company_id' => $store->company_id, 'code' => $priceListCode],
                ['name_ar' => $priceListNameAr, 'name_en' => $priceListNameEn, 'status' => 'active', 'created_by' => $user->id],
            );
            $list = PriceList::query()->lockForUpdate()->findOrFail($list->id);
            $nextVersion = ((int) $list->versions()->lockForUpdate()->max('version')) + 1;
            $payload = [$product->id, $store->id, $amount, $sourceType, $sourceReference, $effectiveFrom, $effectiveTo, $referenceAmount, $openPriceAllowed, $openPriceMinimum, $openPriceMaximum];

            /** @var PriceVersion $version */
            $version = $list->versions()->create([
                'version' => $nextVersion,
                'state' => 'draft',
                'source_type' => $sourceType,
                'source_reference' => $sourceReference,
                'source_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
                'requested_by' => $user->id,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'reason_text' => $reasonText,
            ]);
            $version->lines()->create([
                'product_id' => $product->id,
                'store_id' => $store->id,
                'branch_id' => $store->branch_id,
                'amount' => $amount,
                'reference_amount' => $referenceAmount,
                'open_price_allowed' => $openPriceAllowed,
                'open_price_minimum' => $openPriceMinimum,
                'open_price_maximum' => $openPriceMaximum,
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'pricing',
                event: 'price_proposal_created',
                source: $version,
                after: ['state' => 'draft', 'version' => $nextVersion, 'product_id' => $product->id, 'store_id' => $store->id, 'source_type' => $sourceType],
                branchId: $store->branch_id,
                storeId: $store->id,
                reasonText: $reasonText,
                metadata: ['price_list_id' => $list->id, 'source_reference' => $sourceReference],
            );

            return $version->load('lines.product', 'priceList');
        });
    }

    private function authorizeScope(User $user, Store $store): void
    {
        abort_unless($user->is_super_admin || Store::query()->visibleTo($user)->whereKey($store->id)->exists(), 403);
    }
}

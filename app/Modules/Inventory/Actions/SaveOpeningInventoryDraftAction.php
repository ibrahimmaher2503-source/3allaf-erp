<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\OpeningInventoryDocument;
use App\Modules\Inventory\Models\ProductStoreAssignment;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class SaveOpeningInventoryDraftAction
{
    /** @param list<array{product_id:mixed,store_id:mixed,quantity:mixed,unit_cost?:mixed}> $lines */
    public function execute(array $lines, ?int $id = null, ?string $notes = null, mixed $companyId = null, mixed $documentDate = null): OpeningInventoryDocument
    {
        Gate::authorize($id === null ? 'inventory_stock_card.create' : 'inventory_stock_card.edit');
        if ($lines === [] || count($lines) > ImportOpeningInventoryWorkbookAction::MAX_ROWS) throw new InvalidArgumentException(__('Opening inventory must contain between 1 and :count rows.', ['count' => ImportOpeningInventoryWorkbookAction::MAX_ROWS]));
        $actor = Auth::user(); abort_unless($actor instanceof User, 403);
        $company = app(AuthorizedCompanyContext::class)->resolve($actor, $companyId ?? request()->input('company_id'));

        return DB::transaction(function () use ($lines, $id, $notes, $actor, $company, $documentDate): OpeningInventoryDocument {
            $document = $id ? OpeningInventoryDocument::query()->lockForUpdate()->findOrFail($id) : null;
            if ($document && ((int) $document->company_id !== (int) $company->id || $document->status !== 'draft')) throw new InvalidArgumentException(__('Only a draft Opening Inventory document in the authorized company can be changed.'));
            $productIds = collect($lines)->pluck('product_id')->map(fn ($value): int => (int) $value)->unique()->values();
            $storeIds = collect($lines)->pluck('store_id')->map(fn ($value): int => (int) $value)->unique()->values();
            $products = Product::query()->with('parent')->whereIn('id', $productIds)->where('status', 'active')->lockForUpdate()->get()->keyBy('id');
            $stores = Store::query()->visibleTo($actor)->with('branch:id,company_id')->whereIn('id', $storeIds)->where('company_id', $company->id)->where('status', 'active')->whereIn('type', ['warehouse', 'selling'])->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== $productIds->count() || $stores->count() !== $storeIds->count() || $stores->contains(fn (Store $store): bool => (int) $store->branch?->company_id !== (int) $company->id)) abort(403, __('One or more products or locations are outside the authorized opening-inventory scope.'));
            if ($storeIds->count() !== 1) throw new InvalidArgumentException(__('Choose one warehouse or store for each opening inventory document.'));
            $headerStore = $stores->first();
            $existingMovements = StockMovement::query()->whereIn('product_id', $productIds)->whereIn('store_id', $storeIds)->select(['product_id', 'store_id'])->distinct()->get()->keyBy(fn ($movement): string => $movement->product_id.':'.$movement->store_id);
            $assignedProductIds = ProductStoreAssignment::query()->where('company_id', $company->id)->whereIn('product_id', $productIds)->distinct()->pluck('product_id')->mapWithKeys(fn ($productId): array => [(int) $productId => true]);
            $activeAssignments = ProductStoreAssignment::query()->where('company_id', $company->id)->where('status', 'active')->whereIn('product_id', $productIds)->whereIn('store_id', $storeIds)->get(['product_id', 'store_id'])->keyBy(fn (ProductStoreAssignment $assignment): string => $assignment->product_id.':'.$assignment->store_id);

            $seen = []; $normalized = [];
            foreach ($lines as $row) {
                $product = $products->get((int) ($row['product_id'] ?? 0)); $store = $stores->get((int) ($row['store_id'] ?? 0));
                if (! $product || ! $store) abort(403);
                if ($product->product_type === 'service' || $product->isFamily() || ! $product->isSellable()) throw new InvalidArgumentException(__('Service products and variation families cannot receive opening inventory.'));
                app(AssertInventoryStoreScope::class)->execute($store->id);
                $quantity = $this->quantity($row['quantity'] ?? null);
                if (bccomp($quantity, '0', 6) <= 0 || bccomp(bcmod($quantity, '1', 6), '0', 6) !== 0) throw new InvalidArgumentException(__('Opening quantities must be positive whole numbers.'));
                $key = $product->id.':'.$store->id;
                if (isset($seen[$key])) throw new InvalidArgumentException(__('The same product and inventory location may appear only once.'));
                $seen[$key] = true;
                if ($assignedProductIds->has($product->id) && ! $activeAssignments->has($key)) throw new InvalidArgumentException(__('Assign this product to the selected inventory location before entering an opening quantity.'));
                if ($existingMovements->has($key)) throw new InvalidArgumentException(__('Opening inventory is blocked because this product and location already have inventory movements.'));
                $cost = $this->unitCost($row['unit_cost'] ?? $product->average_cost);
                $normalized[] = ['product_id' => $product->id, 'store_id' => $store->id, 'quantity' => $quantity, 'unit_cost' => $cost, 'total_value' => bcmul($quantity, $cost, 4)];
            }
            $date = filled($documentDate) ? CarbonImmutable::createFromFormat('Y-m-d', (string) $documentDate)->toDateString() : now()->toDateString();
            if (! $document) {
                $number = app(AllocateDocumentNumber::class)->executeForBranch('opening_inventory', (int) $headerStore->branch_id);
                $document = OpeningInventoryDocument::query()->create(['company_id' => $company->id, 'branch_id' => $headerStore->branch_id, 'store_id' => $headerStore->id, 'document_number' => $number, 'document_date' => $date, 'status' => 'draft', 'notes' => $notes, 'created_by' => $actor->id, 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(), 'lock_version' => 1]); $before = null;
            } else {
                $before = $document->only(['status', 'lock_version']); $document->update(['branch_id' => $headerStore->branch_id, 'store_id' => $headerStore->id, 'document_date' => $date, 'notes' => $notes, 'lock_version' => $document->lock_version + 1]); $document->lines()->delete();
            }
            foreach (array_chunk($normalized, 500) as $chunk) $document->lines()->createMany($chunk);
            app(RecordAuditEvent::class)->execute('inventory', $id ? 'update_opening_inventory_draft' : 'create_opening_inventory_draft', $document, $before, $document->fresh()->only(['document_number', 'status', 'lock_version']), metadata: ['line_count' => count($normalized), 'company_id' => $company->id]);
            return $document->fresh();
        });
    }

    private function quantity(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d{1,6})?$/', $value)) throw new InvalidArgumentException(__('Opening quantity must be a valid decimal with up to six places.'));
        return bcadd($value, '0', 6);
    }

    private function unitCost(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $value)) throw new InvalidArgumentException(__('Unit cost must be zero or greater with up to four decimal places.'));
        return bcadd($value, '0', 4);
    }
}

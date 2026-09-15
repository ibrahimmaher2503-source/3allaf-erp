<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\OpeningInventoryDocument;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use InvalidArgumentException;

final class ReverseOpeningInventoryAction
{
    public function execute(int $id, string $reason): OpeningInventoryDocument
    {
        Gate::authorize('inventory_stock_card.reverse');
        $reason = trim($reason);
        if ($reason === '') throw new InvalidArgumentException(__('A reversal reason is required.'));
        $actor = Auth::user(); abort_unless($actor instanceof User, 403);
        $company = app(AuthorizedCompanyContext::class)->resolve($actor, request()->input('company_id'));
        return DB::transaction(function () use ($id, $reason, $company): OpeningInventoryDocument {
            $original = OpeningInventoryDocument::query()->lockForUpdate()->findOrFail($id);
            abort_unless((int) $original->company_id === (int) $company->id, 404);
            if ($original->status !== 'approved' || $original->reversal_of_id) throw new InvalidArgumentException(__('Only an approved original opening inventory document can be reversed.'));
            $store = $original->store_id ? \App\Modules\Platform\Models\Store::query()->findOrFail($original->store_id) : \App\Modules\Platform\Models\Store::query()->findOrFail($original->lines()->value('store_id'));
            $reversal = OpeningInventoryDocument::query()->create(['company_id' => $original->company_id, 'branch_id' => $store->branch_id, 'store_id' => $store->id, 'document_number' => app(AllocateDocumentNumber::class)->executeForBranch('opening_inventory', (int) $store->branch_id), 'document_date' => now()->toDateString(), 'status' => 'draft', 'notes' => $reason, 'created_by' => Auth::id(), 'reversal_of_id' => $original->id, 'idempotency_key' => (string) Str::uuid(), 'lock_version' => 1]);
            $original->lines()->orderBy('id')->chunkById(500, function ($lines) use ($original, $reversal): void {
                $movementKeys = $lines->map(fn ($line): string => 'opening-inventory:'.$original->id.':'.$line->id);
                $movements = StockMovement::query()->whereIn('idempotency_key', $movementKeys)->get()->keyBy('idempotency_key');
                foreach ($lines as $line) {
                    $reversalLine = $reversal->lines()->create(['product_id' => $line->product_id, 'store_id' => $line->store_id, 'quantity' => bcsub('0', (string) $line->quantity, 6), 'unit_cost' => $line->unit_cost, 'total_value' => bcsub('0', (string) $line->total_value, 4)]);
                    $movement = $movements->get('opening-inventory:'.$original->id.':'.$line->id);
                    if ($movement === null) throw new InvalidArgumentException(__('The original opening inventory ledger movement is missing; reversal was blocked.'));
                    app(PostInventoryMovement::class)->execute($line->product_id, $line->store_id, (string) $reversalLine->quantity, 'opening_inventory_reversal', (string) $line->unit_cost, 'opening-inventory-reversal:'.$original->id.':'.$line->id, OpeningInventoryDocument::class, $reversal->id, $reversalLine->id, false, $movement->id);
                }
            });
            $reversal->update(['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => now(), 'lock_version' => 2]);
            $original->mutateApprovedDocument(['status' => 'reversed', 'reversed_by' => Auth::id(), 'reversed_at' => now(), 'reversal_reason' => $reason, 'lock_version' => $original->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('inventory', 'reverse_opening_inventory', $original, ['status' => 'approved'], ['status' => 'reversed', 'reversal_id' => $reversal->id], reasonText: $reason);
            return $reversal->fresh();
        });
    }
}

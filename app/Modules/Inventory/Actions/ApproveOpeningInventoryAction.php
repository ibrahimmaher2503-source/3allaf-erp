<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\OpeningInventoryDocument;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ApproveOpeningInventoryAction
{
    public function execute(int $id): OpeningInventoryDocument
    {
        Gate::authorize('inventory_stock_card.approve');
        $actor = Auth::user(); abort_unless($actor instanceof User, 403);
        $company = app(AuthorizedCompanyContext::class)->resolve($actor, request()->input('company_id'));
        return DB::transaction(function () use ($id, $company): OpeningInventoryDocument {
            $document = OpeningInventoryDocument::query()->lockForUpdate()->findOrFail($id);
            abort_unless((int) $document->company_id === (int) $company->id, 404);
            if ((int) $document->created_by === (int) Auth::id() && ! Auth::user()?->canBypassApproval()) throw new InvalidArgumentException(__('The creator cannot approve the same Opening Inventory document.'));
            if ($document->status === 'approved') return $document;
            if ($document->status !== 'draft') throw new InvalidArgumentException(__('Only a draft opening inventory document can be approved.'));
            $lineCount = $document->lines()->count();
            if ($lineCount === 0) throw new InvalidArgumentException(__('Opening inventory must contain at least one row.'));
            $document->lines()->orderBy('id')->chunkById(500, function ($lines) use ($document): void {
                foreach ($lines as $line) {
                    app(AssertInventoryStoreScope::class)->execute($line->store_id);
                    app(PostInventoryMovement::class)->execute($line->product_id, $line->store_id, (string) $line->quantity, 'opening_inventory', (string) $line->unit_cost, 'opening-inventory:'.$document->id.':'.$line->id, OpeningInventoryDocument::class, $document->id, $line->id, requireFirstMovement: true);
                }
            });
            $before = $document->only(['status', 'lock_version']);
            $document->update(['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => now(), 'lock_version' => $document->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('inventory', 'approve_opening_inventory', $document, $before, $document->only(['status', 'approved_by', 'approved_at', 'lock_version']), metadata: ['line_count' => $lineCount]);
            return $document->fresh();
        });
    }
}

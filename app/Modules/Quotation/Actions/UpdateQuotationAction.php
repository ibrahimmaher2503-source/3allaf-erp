<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Quotation\Models\Quotation;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateQuotationAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $user, Quotation $quotation, array $data): Quotation
    {
        Gate::forUser($user)->authorize('quotations.edit');
        abort_unless($user->is_super_admin || $user->canAccessBranch($quotation->branch_id) || $user->canAccessStore($quotation->store_id), 403);
        $lines = app(CreateQuotationAction::class)->validateLines($quotation->activity_type, (array) ($data['lines'] ?? []));
        if ($lines === []) throw ValidationException::withMessages(['lines' => __('At least one compatible quotation line is required.')]);
        if (filled($data['customer_id'] ?? null)) {
            abort_unless(Customer::query()->whereKey($data['customer_id'])->whereHas('scopes', fn ($scope) => $scope->where('branch_id', $quotation->branch_id)->orWhere('store_id', $quotation->store_id))->exists(), 404);
        }

        return DB::transaction(function () use ($user, $quotation, $data, $lines): Quotation {
            $locked = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') throw ValidationException::withMessages(['quotation' => __('Only draft quotations can be edited.')]);
            $before = $locked->only(['customer_id', 'valid_until', 'terms', 'notes', 'subtotal', 'total']);
            $subtotal = 0.0;
            $locked->lines()->delete();
            foreach ($lines as $index => $line) {
                $lineTotal = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
                $subtotal += $lineTotal;
                $locked->lines()->create([...$line, 'line_number' => $index + 1, 'line_total' => $lineTotal]);
            }
            $locked->mutate([
                'customer_id' => $data['customer_id'] ?? null,
                'valid_until' => $data['valid_until'],
                'terms' => $data['terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'updated_by' => $user->id,
                'lock_version' => $locked->lock_version + 1,
            ]);
            app(RecordAuditEvent::class)->execute('quotations', 'quotation_updated', $locked, before: $before, after: $locked->only(['customer_id', 'valid_until', 'terms', 'notes', 'subtotal', 'total']), branchId: $locked->branch_id, storeId: $locked->store_id, metadata: ['line_count' => count($lines), 'non_posting' => true]);
            return $locked->fresh('lines');
        }, 5);
    }
}

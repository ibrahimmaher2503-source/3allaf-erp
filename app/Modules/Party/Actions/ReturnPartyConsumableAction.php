<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Party\Models\PartyConsumableIssue;
use App\Modules\Party\Models\PartyConsumableReturn;
use App\Modules\Party\Models\PartyConsumableReturnLine;
use App\Modules\Party\Models\PartyOperatingOrderLine;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use App\Support\ProductQuantity;

final class ReturnPartyConsumableAction
{
    public function execute(User $actor, PartyConsumableIssue $issue, PartyOperatingOrderLine $line, string $quantity, string $idempotencyKey): PartyConsumableReturn
    {
        Gate::forUser($actor)->authorize('party_operating_orders_consumables.create');
        $quantity = $this->quantity($quantity);
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') throw new InvalidArgumentException(__('A Party return idempotency key is required.'));
        try {
            return DB::transaction(function () use ($actor, $issue, $line, $quantity, $idempotencyKey): PartyConsumableReturn {
                $issue = PartyConsumableIssue::query()->with('order')->lockForUpdate()->findOrFail($issue->id);
                $line = PartyOperatingOrderLine::query()->lockForUpdate()->findOrFail($line->id);
                $issueLine = $issue->lines()->where('party_operating_order_line_id', $line->id)->lockForUpdate()->firstOrFail();
                if ((int) $line->party_operating_order_id !== (int) $issue->party_operating_order_id || ! in_array($issue->order->status, ['released', 'in_progress'], true)) throw new InvalidArgumentException(__('Only an active Party order issue can be returned.'));
                $existing = PartyConsumableReturn::query()->where('idempotency_key', $idempotencyKey)->with('lines.issueLine')->lockForUpdate()->first();
                if ($existing !== null) {
                    $this->assertSameReplay($existing, $issue, $line, $quantity);
                    return $existing;
                }
                $eligible = bcsub((string) $line->issued_quantity, bcadd((string) $line->returned_quantity, (string) $line->consumed_quantity, 6), 6);
                if (bccomp($quantity, $eligible, 6) > 0) throw new InvalidArgumentException(__('Returned quantity exceeds unused issued Party consumables.'));
                $store = $issue->order->store;
                $before = (string) (StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $store->id)->lockForUpdate()->value('on_hand') ?? '0.000000');
                $return = PartyConsumableReturn::query()->create(['party_operating_order_id' => $issue->party_operating_order_id, 'party_consumable_issue_id' => $issue->id, 'store_id' => $store->id, 'status' => 'approved', 'created_by' => $actor->id, 'idempotency_key' => $idempotencyKey]);
                $returnLine = PartyConsumableReturnLine::query()->create(['party_consumable_return_id' => $return->id, 'party_consumable_issue_line_id' => $issueLine->id, 'product_id' => $line->product_id, 'quantity' => $quantity]);
                $movement = app(PostInventoryMovement::class)->execute((int) $line->product_id, (int) $store->id, $quantity, 'party_consumable_return', null, 'party-consumable-return:'.$return->id, 'party_consumable', $return->id, $returnLine->id);
                $returnLine->attachMovement((int) $movement->id);
                $newReturned = bcadd((string) $line->returned_quantity, $quantity, 6);
                $line->update(['returned_quantity' => $newReturned, 'consumed_quantity' => bcsub((string) $line->issued_quantity, $newReturned, 6)]);
                $after = (string) StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $store->id)->value('on_hand');
                app(RecordAuditEvent::class)->execute('party', 'party_consumable_returned', $return, ['on_hand' => $before], ['on_hand' => $after, 'quantity' => $quantity], (int) $issue->order->branch_id, (int) $store->id, reasonText: 'Referenced unused return', metadata: ['order_id' => $issue->party_operating_order_id, 'issue_id' => $issue->id, 'issue_line_id' => $issueLine->id, 'movement_id' => $movement->id]);
                return $return->load('lines');
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = PartyConsumableReturn::query()->where('idempotency_key', $idempotencyKey)->with('lines.issueLine')->first();
            if ($existing !== null) {
                $this->assertSameReplay($existing, $issue, $line, $quantity);
                return $existing;
            }
            throw $exception;
        }
    }

    private function assertSameReplay(PartyConsumableReturn $existing, PartyConsumableIssue $issue, PartyOperatingOrderLine $line, string $quantity): void
    {
        $existingLine = $existing->lines->first();
        if ($existingLine === null || (int) $existing->party_consumable_issue_id !== (int) $issue->id || (int) ($existingLine->issueLine?->party_operating_order_line_id ?? 0) !== (int) $line->id || bccomp((string) $existingLine->quantity, $quantity, 6) !== 0) {
            throw new InvalidArgumentException(__('This Party return idempotency key was already used with different data.'));
        }
    }

    private function quantity(string $value): string
    {
        $value = ProductQuantity::normalize($value);
        if (bccomp($value, '0', 0) <= 0) throw new InvalidArgumentException(__('Party return quantity must be positive.'));
        return (string) $value;
    }
}

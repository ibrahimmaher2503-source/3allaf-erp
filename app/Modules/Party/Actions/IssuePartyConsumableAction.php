<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Party\Models\PartyConsumableIssue;
use App\Modules\Party\Models\PartyConsumableIssueLine;
use App\Modules\Party\Models\PartyOperatingOrder;
use App\Modules\Party\Models\PartyOperatingOrderLine;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use App\Support\ProductQuantity;

final class IssuePartyConsumableAction
{
    public function execute(User $actor, PartyOperatingOrder $order, PartyOperatingOrderLine $line, string $quantity, string $idempotencyKey): PartyConsumableIssue
    {
        Gate::forUser($actor)->authorize('party_operating_orders_consumables.create');
        $quantity = $this->quantity($quantity);
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') throw new InvalidArgumentException(__('A Party issue idempotency key is required.'));
        try {
            return DB::transaction(function () use ($actor, $order, $line, $quantity, $idempotencyKey): PartyConsumableIssue {
                $order = PartyOperatingOrder::query()->lockForUpdate()->findOrFail($order->id);
                $line = PartyOperatingOrderLine::query()->lockForUpdate()->findOrFail($line->id);
                if ((int) $line->party_operating_order_id !== (int) $order->id || $line->line_type !== 'consumable') throw new InvalidArgumentException(__('Only a Party consumable order line can be issued.'));
                if (! in_array($order->status, ['released', 'in_progress'], true)) throw new InvalidArgumentException(__('Consumables can only be issued for a released Party order.'));
                $store = Store::query()->visibleTo($actor)->whereKey($order->store_id)->where('status', 'active')->firstOrFail();
                if ($store->type !== 'party') throw new InvalidArgumentException(__('Party consumables must be issued from a Party store.'));
                $existing = PartyConsumableIssue::query()->where('idempotency_key', $idempotencyKey)->with('lines')->lockForUpdate()->first();
                if ($existing !== null) {
                    $this->assertSameReplay($existing, $order, $line, $quantity);
                    return $existing;
                }
                $available = bcsub((string) $line->planned_quantity, bcadd((string) $line->issued_quantity, (string) $line->returned_quantity, 6), 6);
                if (bccomp($quantity, $available, 6) > 0) throw new InvalidArgumentException(__('Issued quantity cannot exceed the planned Party consumable quantity.'));
                $before = (string) (StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $store->id)->lockForUpdate()->value('on_hand') ?? '0.000000');
                $issue = PartyConsumableIssue::query()->create(['party_operating_order_id' => $order->id, 'store_id' => $store->id, 'status' => 'approved', 'created_by' => $actor->id, 'idempotency_key' => $idempotencyKey]);
                $issueLine = PartyConsumableIssueLine::query()->create(['party_consumable_issue_id' => $issue->id, 'party_operating_order_line_id' => $line->id, 'product_id' => $line->product_id, 'quantity' => $quantity]);
                $movement = app(PostInventoryMovement::class)->execute((int) $line->product_id, (int) $store->id, '-'.$quantity, 'party_consumable_issue', null, 'party-consumable-issue:'.$issue->id, 'party_consumable', $issue->id, $issueLine->id);
                $issueLine->attachMovement((int) $movement->id);
                $line->update(['issued_quantity' => bcadd((string) $line->issued_quantity, $quantity, 6)]);
                if ($order->status === 'released') $order->update(['status' => 'in_progress', 'lock_version' => $order->lock_version + 1]);
                $after = (string) StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $store->id)->value('on_hand');
                app(RecordAuditEvent::class)->execute('party', 'party_consumable_issued', $issue, ['on_hand' => $before], ['on_hand' => $after, 'quantity' => $quantity], (int) $order->branch_id, (int) $store->id, metadata: ['order_id' => $order->id, 'line_id' => $line->id, 'movement_id' => $movement->id, 'source_type' => 'party_consumable']);
                return $issue->load('lines');
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = PartyConsumableIssue::query()->where('idempotency_key', $idempotencyKey)->with('lines')->first();
            if ($existing !== null) {
                $this->assertSameReplay($existing, $order, $line, $quantity);
                return $existing;
            }
            throw $exception;
        }
    }

    private function assertSameReplay(PartyConsumableIssue $existing, PartyOperatingOrder $order, PartyOperatingOrderLine $line, string $quantity): void
    {
        $existingLine = $existing->lines->first();
        if ($existingLine === null || (int) $existing->party_operating_order_id !== (int) $order->id || (int) $existingLine->party_operating_order_line_id !== (int) $line->id || bccomp((string) $existingLine->quantity, $quantity, 6) !== 0) {
            throw new InvalidArgumentException(__('This Party issue idempotency key was already used with different data.'));
        }
    }

    private function quantity(string $value): string
    {
        $value = ProductQuantity::normalize($value);
        if (bccomp($value, '0', 0) <= 0) throw new InvalidArgumentException(__('Party consumable quantity must be positive.'));
        return (string) $value;
    }
}

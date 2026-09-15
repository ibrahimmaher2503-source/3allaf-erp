<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\GiftCard;
use App\Modules\Retail\Models\GiftCardLedger;
use App\Modules\Retail\Models\GiftCardPrintEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GiftCardAction
{
    public function issue(User $actor, string $amount, int $branchId, int $storeId, string $sourceType, string $sourceId, string $idempotencyKey, ?string $sourceReference = null, ?int $holderCustomerId = null, ?string $currencyCode = 'EGP', ?\DateTimeInterface $validUntil = null): GiftCard
    {
        $this->authorize($actor, 'gift_cards.issue');
        abort_unless(Store::query()->visibleTo($actor)->whereKey($storeId)->where('branch_id', $branchId)->exists(), 403);
        if (bccomp($amount, '0.00', 2) <= 0) throw ValidationException::withMessages(['amount' => __('Gift Card value must be greater than zero.')]);
        $amount = bcadd($amount, '0', 2);
        $currencyCode = strtoupper((string) $currencyCode);
        $sourceReference = $this->nullableText($sourceReference);
        return DB::transaction(function () use ($actor, $amount, $branchId, $storeId, $sourceType, $sourceId, $idempotencyKey, $sourceReference, $holderCustomerId, $currencyCode, $validUntil): GiftCard {
            $existing = GiftCard::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if (! $this->sameIssueRequest($existing, $amount, $branchId, $storeId, $sourceType, $sourceId, $sourceReference, $holderCustomerId, $currencyCode, $validUntil)) $this->idempotencyConflict();
                return $existing->load('ledger');
            }
            $card = GiftCard::query()->create([
                'identifier' => 'GC-'.strtoupper(Str::random(28)), 'status' => 'active', 'issued_value' => $amount, 'balance' => $amount,
                'currency_code' => strtoupper((string) $currencyCode), 'holder_customer_id' => $holderCustomerId, 'branch_id' => $branchId, 'store_id' => $storeId,
                'issued_by' => $actor->id, 'source_type' => $sourceType, 'source_id' => $sourceId, 'source_reference' => $sourceReference,
                'valid_from' => now(), 'valid_until' => $validUntil, 'idempotency_key' => $idempotencyKey, 'lock_version' => 1,
            ]);
            $this->ledger($card, $actor, 'issue', $amount, '0.00', $amount, $sourceType, $sourceId, $sourceReference, null, $idempotencyKey, []);
            app(RecordAuditEvent::class)->execute('retail', 'gift_card_issued', $card, null, ['identifier' => $card->identifier, 'amount' => $amount, 'balance' => $amount], $branchId, $storeId, metadata: ['actor_id' => $actor->id, 'source_type' => $sourceType, 'source_id' => $sourceId]);
            return $card->load('ledger');
        }, 5);
    }

    public function redeem(User $actor, GiftCard $card, string $amount, string $idempotencyKey, ?string $sourceType = null, ?string $sourceId = null, ?string $sourceReference = null): GiftCardLedger
    {
        $this->authorize($actor, 'gift_cards.redeem');
        abort_unless(GiftCard::query()->visibleTo($actor)->whereKey($card->id)->exists(), 403);
        if (bccomp($amount, '0.00', 2) <= 0) throw ValidationException::withMessages(['amount' => __('Redemption amount must be greater than zero.')]);
        $amount = bcadd($amount, '0', 2);
        $sourceReference = $this->nullableText($sourceReference);
        return DB::transaction(function () use ($actor, $card, $amount, $idempotencyKey, $sourceType, $sourceId, $sourceReference): GiftCardLedger {
            $existing = GiftCardLedger::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                if (! $this->sameLedgerRequest($existing, $card, 'redeem', '-'.$amount, $sourceType, $sourceId, $sourceReference, null)) $this->idempotencyConflict();
                return $existing;
            }
            $locked = GiftCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            $this->expireIfNeeded($locked, $actor);
            if ($locked->status !== 'active' && $locked->status !== 'partially_used') throw ValidationException::withMessages(['card' => __('This Gift Card is not redeemable.')]);
            if (bccomp($amount, (string) $locked->balance, 2) > 0) throw ValidationException::withMessages(['amount' => __('The Gift Card balance is insufficient.')]);
            $before = (string) $locked->balance;
            $after = bcsub($before, $amount, 2);
            $status = bccomp($after, '0.00', 2) === 0 ? 'fully_used' : 'partially_used';
            $locked->update(['balance' => $after, 'status' => $status, 'lock_version' => (int) $locked->lock_version + 1]);
            $entry = $this->ledger($locked, $actor, 'redeem', '-'.$amount, $before, $after, $sourceType, $sourceId, $sourceReference, null, $idempotencyKey, []);
            app(RecordAuditEvent::class)->execute('retail', 'gift_card_redeemed', $locked, ['balance' => $before, 'status' => $locked->getOriginal('status')], ['balance' => $after, 'status' => $status, 'amount' => $amount], (int) $locked->branch_id, (int) $locked->store_id, metadata: ['actor_id' => $actor->id, 'source_type' => $sourceType, 'source_id' => $sourceId]);
            return $entry;
        }, 5);
    }

    public function void(User $actor, GiftCard $card, string $reason, string $idempotencyKey): GiftCard
    {
        $this->authorize($actor, 'gift_cards.void');
        return $this->zeroBalance($actor, $card, 'void', 'voided', $reason, $idempotencyKey);
    }

    public function expire(User $actor, GiftCard $card, string $idempotencyKey): GiftCard
    {
        $this->authorize($actor, 'gift_cards.expire');
        return $this->zeroBalance($actor, $card, 'expire', 'expired', __('Gift Card expired.'), $idempotencyKey);
    }

    public function print(User $actor, GiftCard $card, string $idempotencyKey, string $format = 'thermal', ?string $reason = null): GiftCardPrintEvent
    {
        $this->authorize($actor, 'gift_cards.print');
        abort_unless(GiftCard::query()->visibleTo($actor)->whereKey($card->id)->exists(), 403);
        $format = in_array($format, ['thermal', 'a4'], true) ? $format : 'thermal';
        $reason = $this->nullableText($reason);
        return DB::transaction(function () use ($actor, $card, $idempotencyKey, $format, $reason): GiftCardPrintEvent {
            $existing = GiftCardPrintEvent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                if ((int) $existing->gift_card_id !== (int) $card->id || $existing->format !== $format || $this->nullableText($existing->reason) !== $reason) $this->idempotencyConflict();
                return $existing;
            }
            $locked = GiftCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            $event = GiftCardPrintEvent::query()->create([
                'gift_card_id' => $locked->id, 'printed_by' => $actor->id, 'format' => $format,
                'is_reprint' => $locked->printEvents()->exists(), 'reason' => $reason, 'idempotency_key' => $idempotencyKey, 'printed_at' => now(),
            ]);
            app(RecordAuditEvent::class)->execute('retail', $event->is_reprint ? 'gift_card_reprinted' : 'gift_card_printed', $locked, null, [
                'identifier' => $locked->identifier, 'is_reprint' => $event->is_reprint, 'balance' => $locked->balance,
            ], (int) $locked->branch_id, (int) $locked->store_id, reasonText: $reason, metadata: ['actor_id' => $actor->id]);
            return $event;
        }, 5);
    }

    private function zeroBalance(User $actor, GiftCard $card, string $event, string $status, string $reason, string $idempotencyKey): GiftCard
    {
        abort_unless(GiftCard::query()->visibleTo($actor)->whereKey($card->id)->exists(), 403);
        return DB::transaction(function () use ($actor, $card, $event, $status, $reason, $idempotencyKey): GiftCard {
            $existing = GiftCardLedger::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                if (! $this->sameLedgerRequest($existing, $card, $event, null, null, null, null, $reason)) $this->idempotencyConflict();
                return $card->fresh('ledger');
            }
            $locked = GiftCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['voided', 'expired'], true)) throw ValidationException::withMessages(['card' => __('This Gift Card is already closed.')]);
            $before = (string) $locked->balance;
            $locked->update(['balance' => '0.00', 'status' => $status, 'void_reason' => $reason, 'voided_by' => $event === 'void' ? $actor->id : null, 'voided_at' => $event === 'void' ? now() : null, 'lock_version' => (int) $locked->lock_version + 1]);
            $this->ledger($locked, $actor, $event, '-'.$before, $before, '0.00', null, null, null, $reason, $idempotencyKey, []);
            app(RecordAuditEvent::class)->execute('retail', 'gift_card_'.$event, $locked, ['status' => $locked->getOriginal('status'), 'balance' => $before], ['status' => $status, 'balance' => '0.00'], (int) $locked->branch_id, (int) $locked->store_id, reasonText: $reason, metadata: ['actor_id' => $actor->id]);
            return $locked->fresh('ledger');
        }, 5);
    }

    private function expireIfNeeded(GiftCard $card, User $actor): void
    {
        if ($card->valid_until !== null && $card->valid_until->isPast() && in_array($card->status, ['active', 'partially_used'], true)) {
            $before = (string) $card->balance;
            $card->update(['balance' => '0.00', 'status' => 'expired', 'lock_version' => (int) $card->lock_version + 1]);
            $this->ledger($card, $actor, 'expire', '-'.$before, $before, '0.00', null, null, null, __('Gift Card expired.'), 'expiry:'.$card->id.':'.$card->valid_until?->timestamp, []);
            app(RecordAuditEvent::class)->execute('retail', 'gift_card_expire', $card, ['balance' => $before], ['balance' => '0.00', 'status' => 'expired'], (int) $card->branch_id, (int) $card->store_id, metadata: ['actor_id' => $actor->id]);
        }
    }

    private function ledger(GiftCard $card, User $actor, string $event, string $amount, string $before, string $after, ?string $sourceType, ?string $sourceId, ?string $sourceReference, ?string $reason, string $idempotencyKey, array $metadata): GiftCardLedger
    {
        return GiftCardLedger::query()->create(['gift_card_id' => $card->id, 'event_type' => $event, 'amount' => $amount, 'balance_before' => $before, 'balance_after' => $after, 'source_type' => $sourceType, 'source_id' => $sourceId, 'source_reference' => $sourceReference, 'reason' => $reason, 'created_by' => $actor->id, 'idempotency_key' => $idempotencyKey, 'metadata' => $metadata, 'created_at' => now()]);
    }

    private function sameIssueRequest(GiftCard $card, string $amount, int $branchId, int $storeId, string $sourceType, string $sourceId, ?string $sourceReference, ?int $holderCustomerId, string $currencyCode, ?\DateTimeInterface $validUntil): bool
    {
        return bccomp((string) $card->issued_value, $amount, 2) === 0
            && (int) $card->branch_id === $branchId
            && (int) $card->store_id === $storeId
            && $card->source_type === $sourceType
            && (string) $card->source_id === $sourceId
            && $this->nullableText($card->source_reference) === $sourceReference
            && ($card->holder_customer_id === null ? null : (int) $card->holder_customer_id) === $holderCustomerId
            && $card->currency_code === $currencyCode
            && $this->sameDateTime($card->valid_until, $validUntil);
    }

    private function sameLedgerRequest(GiftCardLedger $ledger, GiftCard $card, string $event, ?string $amount, ?string $sourceType, ?string $sourceId, ?string $sourceReference, ?string $reason): bool
    {
        return (int) $ledger->gift_card_id === (int) $card->id
            && $ledger->event_type === $event
            && ($amount === null || bccomp((string) $ledger->amount, $amount, 2) === 0)
            && $this->nullableText($ledger->source_type) === $sourceType
            && ($ledger->source_id === null ? null : (string) $ledger->source_id) === $sourceId
            && $this->nullableText($ledger->source_reference) === $sourceReference
            && $this->nullableText($ledger->reason) === $reason;
    }

    private function sameDateTime(?\DateTimeInterface $stored, ?\DateTimeInterface $requested): bool
    {
        if ($stored === null || $requested === null) return $stored === $requested;

        return $stored->format('Y-m-d H:i:s') === $requested->format('Y-m-d H:i:s');
    }

    private function nullableText(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function idempotencyConflict(): never
    {
        throw ValidationException::withMessages(['idempotency_key' => __('This idempotency key was already used with a different Gift Card request.')]);
    }

    private function authorize(User $actor, string $permission): void { abort_unless($actor->is_super_admin || $actor->can($permission), 403); }
}

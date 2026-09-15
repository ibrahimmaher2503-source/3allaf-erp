<?php

declare(strict_types=1);

namespace App\Modules\CashControl\Actions;

use App\Models\User;
use App\Modules\CashControl\Models\CashAccount;
use App\Modules\CashControl\Models\CashTransaction;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RecordCashTransactionAction
{
    public function execute(User $actor, CashAccount $account, string $amount, string $transactionType, string $description, string $idempotencyKey, ?string $date = null, ?string $sourceType = null, ?int $sourceId = null, ?string $reference = null, ?CashTransaction $reversalOf = null): CashTransaction
    {
        Gate::forUser($actor)->authorize('shifts_cash_movements.approve');
        $amount = $this->signedMoney($amount);
        $transactionType = trim($transactionType);
        $description = trim($description);
        $idempotencyKey = trim($idempotencyKey);
        $date = $this->date($date ?? now()->toDateString());
        $sourceType = filled($sourceType) ? trim((string) $sourceType) : null;
        $reference = filled($reference) ? trim((string) $reference) : null;
        if ($transactionType === '' || $description === '' || $idempotencyKey === '') throw new InvalidArgumentException(__('Cash transaction type, description, and idempotency key are required.'));
        if (($sourceType === null) !== ($sourceId === null)) throw new InvalidArgumentException(__('Cash transaction source type and source id must be supplied together.'));
        if (($reversalOf === null && $transactionType === 'reversal') || ($reversalOf !== null && $transactionType !== 'reversal')) throw new InvalidArgumentException(__('Cash reversal transactions must reference the original transaction.'));

        $payload = ['cash_account_id' => (int) $account->id, 'amount' => $amount, 'transaction_type' => $transactionType, 'description' => $description, 'date' => $date, 'source_type' => $sourceType, 'source_id' => $sourceId, 'reference' => $reference, 'reversal_of_id' => $reversalOf?->id];
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            return DB::transaction(function () use ($actor, $account, $amount, $transactionType, $description, $idempotencyKey, $date, $sourceType, $sourceId, $reference, $reversalOf, $payloadHash): CashTransaction {
                $account = CashAccount::query()
                    ->whereIn('company_id', Store::query()->visibleTo($actor)->select('company_id'))
                    ->lockForUpdate()
                    ->findOrFail($account->id);
                $existing = CashTransaction::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing !== null) return $this->replay($existing, $payloadHash);
                if ($account->status !== 'active') throw new InvalidArgumentException(__('Cash transactions require an active cash account.'));

                $original = $reversalOf === null ? null : CashTransaction::query()->lockForUpdate()->findOrFail($reversalOf->id);
                if ($original !== null) {
                    if ((int) $original->cash_account_id !== (int) $account->id || $original->reversal_of_id !== null) throw new InvalidArgumentException(__('A cash reversal must reference an original transaction in the same account.'));
                    if ($original->reversal()->lockForUpdate()->exists()) throw new InvalidArgumentException(__('This cash transaction was already reversed.'));
                    if (bccomp($amount, bcsub('0', (string) $original->amount, 4), 4) !== 0) throw new InvalidArgumentException(__('A cash reversal must exactly negate the original amount.'));
                }

                $transaction = CashTransaction::query()->create([
                    'cash_account_id' => $account->id, 'transaction_date' => $date, 'amount' => $amount,
                    'transaction_type' => $transactionType, 'source_type' => $sourceType, 'source_id' => $sourceId,
                    'reversal_of_id' => $original?->id, 'idempotency_key' => $idempotencyKey, 'payload_hash' => $payloadHash,
                    'description' => $description, 'reference' => $reference, 'created_by' => $actor->id,
                ]);
                app(RecordAuditEvent::class)->execute('cash_control', $original === null ? 'cash_transaction_recorded' : 'cash_transaction_reversed', $transaction, after: $transaction->only(['cash_account_id', 'amount', 'transaction_type', 'source_type', 'source_id', 'reversal_of_id']), reasonText: $description, metadata: ['actor_id' => $actor->id, 'idempotency_key' => $idempotencyKey]);

                return $transaction;
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = CashTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) return $this->replay($existing, $payloadHash);
            throw $exception;
        }
    }

    private function replay(CashTransaction $transaction, string $payloadHash): CashTransaction
    {
        if (! hash_equals((string) $transaction->payload_hash, $payloadHash)) throw new InvalidArgumentException(__('This cash transaction idempotency key was already used with different data.'));
        return $transaction;
    }

    /** @return numeric-string */
    private function signedMoney(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', $value) || bccomp($value, '0', 4) === 0) throw new InvalidArgumentException(__('Cash transaction amount must be a non-zero signed decimal.'));
        return bcadd($value, '0', 4);
    }

    private function date(string $value): string
    {
        $valid = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
        if (! $valid) throw new InvalidArgumentException(__('Cash transaction date is invalid.'));
        return $value;
    }
}

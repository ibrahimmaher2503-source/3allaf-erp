<?php

declare(strict_types=1);

namespace App\Modules\CashControl\Actions;

use App\Models\User;
use App\Modules\CashControl\Models\CashAccount;
use App\Modules\CashControl\Models\Expense;
use App\Modules\CashControl\Models\ExpenseCategory;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RecordExpenseAction
{
    public function execute(User $actor, Company $company, ExpenseCategory $category, string $amount, string $description, string $idempotencyKey, ?string $date = null, ?PaymentMethod $paymentMethod = null, ?CashAccount $cashAccount = null, ?string $reference = null): Expense
    {
        Gate::forUser($actor)->authorize('shifts_cash_movements.approve');
        $amount = $this->money($amount);
        $description = trim($description);
        $idempotencyKey = trim($idempotencyKey);
        $date = $this->date($date ?? now()->toDateString());
        if ($description === '' || $idempotencyKey === '') {
            throw new InvalidArgumentException(__('Expense description and idempotency key are required.'));
        }

        $payload = ['company_id' => (int) $company->id, 'category_id' => (int) $category->id, 'amount' => $amount, 'description' => $description, 'date' => $date, 'payment_method_id' => $paymentMethod?->id, 'cash_account_id' => $cashAccount?->id, 'reference' => trim((string) $reference)];
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            return DB::transaction(function () use ($actor, $company, $category, $amount, $description, $idempotencyKey, $date, $paymentMethod, $cashAccount, $reference, $payloadHash): Expense {
                $company = Company::query()
                    ->whereIn('id', Store::query()->visibleTo($actor)->select('company_id'))
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->findOrFail($company->id);
                $existing = Expense::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    return $this->replay($existing, $payloadHash);
                }
                $category = ExpenseCategory::query()->where('company_id', $company->id)->where('active', true)->lockForUpdate()->findOrFail($category->id);
                $cashAccount = $cashAccount === null ? null : CashAccount::query()->where('company_id', $company->id)->where('status', 'active')->lockForUpdate()->findOrFail($cashAccount->id);
                $paymentMethod = $paymentMethod === null ? null : PaymentMethod::query()->where('status', 'active')->findOrFail($paymentMethod->id);
                if ($paymentMethod?->isCash() && $cashAccount === null) {
                    throw new InvalidArgumentException(__('Cash expenses require a cash account.'));
                }
                if (! $paymentMethod?->isCash() && $cashAccount !== null) {
                    throw new InvalidArgumentException(__('Non-cash expenses cannot use a cash account.'));
                }
                if ($cashAccount !== null && (strtoupper((string) $cashAccount->currency_code) !== strtoupper((string) $company->currency_code) || $cashAccount->type !== 'cash')) {
                    throw new InvalidArgumentException(__('Cash expense account must match the company currency and be a cash account.'));
                }

                $expense = Expense::query()->create([
                    'company_id' => $company->id, 'expense_category_id' => $category->id, 'cash_account_id' => $cashAccount?->id,
                    'payment_method_id' => $paymentMethod?->id, 'expense_date' => $date, 'amount' => $amount,
                    'description' => $description, 'reference' => filled($reference) ? trim((string) $reference) : null,
                    'status' => 'approved', 'idempotency_key' => $idempotencyKey, 'payload_hash' => $payloadHash,
                    'created_by' => $actor->id, 'approved_by' => $actor->id, 'approved_at' => now(),
                ]);
                if ($cashAccount !== null) {
                    app(RecordCashTransactionAction::class)->execute($actor, $cashAccount, bcsub('0', $amount, 4), 'expense', $description, 'expense:'.$expense->id.':cash', $date, Expense::class, $expense->id, $reference);
                }
                app(RecordAuditEvent::class)->execute('cash_control', 'expense_recorded', $expense, after: $expense->only(['company_id', 'expense_category_id', 'cash_account_id', 'amount', 'expense_date']), reasonText: $description, metadata: ['actor_id' => $actor->id, 'idempotency_key' => $idempotencyKey]);

                return $expense->load(['category', 'cashAccount', 'paymentMethod']);
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Expense::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->replay($existing, $payloadHash);
            }
            throw $exception;
        }
    }

    private function replay(Expense $expense, string $payloadHash): Expense
    {
        if (! hash_equals((string) $expense->payload_hash, $payloadHash)) {
            throw new InvalidArgumentException(__('This expense idempotency key was already used with different data.'));
        }

        return $expense->loadMissing(['category', 'cashAccount', 'paymentMethod']);
    }

    /** @return numeric-string */
    private function money(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', $value) || bccomp($value, '0', 4) <= 0) {
            throw new InvalidArgumentException(__('Expense amount must be a positive decimal.'));
        }

        return bcadd($value, '0', 4);
    }

    private function date(string $value): string
    {
        $valid = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
        if (! $valid) {
            throw new InvalidArgumentException(__('Expense date is invalid.'));
        }

        return $value;
    }
}

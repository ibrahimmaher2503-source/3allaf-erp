<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CashControl\Models\CashAccount;
use App\Modules\CashControl\Models\CashTransaction;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Actions\RecordCashMovementAction;
use App\Modules\Retail\Actions\ReviewShiftVarianceAction;
use App\Modules\Retail\Actions\SubmitBlindShiftCloseAction;
use App\Modules\Retail\Models\CashMovement;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SalePayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

final class P0ShiftTreasuryTransferTest extends TestCase
{
    use DatabaseTransactions;

    public function test_approval_transfers_counted_cash_and_prior_safe_deposits_once_without_moving_opening_float(): void
    {
        [$shift, $approval, $reviewer, $account] = $this->submittedShift('600.00', '100.00', true);

        Auth::login($reviewer);
        app(ReviewShiftVarianceAction::class)->approveAndClose($reviewer, $shift, $approval, $shift->lock_version);

        $transaction = CashTransaction::query()->where('source_type', PosShift::class)->where('source_id', $shift->id)->sole();
        self::assertSame($account->id, $transaction->cash_account_id);
        self::assertSame('1000.0000', $transaction->amount);
        self::assertSame('shift_treasury_transfer', $transaction->transaction_type);
        self::assertSame(1, CashTransaction::query()->where('idempotency_key', 'pos-shift:'.$shift->id.':treasury-transfer')->count());
        self::assertSame(1, CashMovement::query()->where('shift_id', $shift->id)->count());
    }

    public function test_zero_transfer_closes_without_creating_a_treasury_transaction(): void
    {
        [$shift, $approval, $reviewer] = $this->submittedShift('80.00', '100.00');
        Auth::login($reviewer);

        app(ReviewShiftVarianceAction::class)->approveAndClose($reviewer, $shift, $approval, $shift->lock_version);

        self::assertSame('closed', $shift->fresh()->status->value);
        self::assertSame(0, CashTransaction::query()->where('source_type', PosShift::class)->where('source_id', $shift->id)->count());
    }

    public function test_invalid_treasury_configuration_rolls_back_approval_and_close(): void
    {
        foreach (['missing', 'inactive', 'wrong_company', 'wrong_currency', 'wrong_type'] as $case) {
            [$shift, $approval, $reviewer, $account] = $this->submittedShift('150.00', '100.00');
            $drawer = $shift->cashDrawer;

            if ($case === 'missing') {
                $drawer->update(['treasury_cash_account_id' => null]);
            } elseif ($case === 'inactive') {
                $account->update(['status' => 'inactive']);
            } elseif ($case === 'wrong_company') {
                $other = Company::factory()->create();
                $account->update(['company_id' => $other->id]);
            } elseif ($case === 'wrong_currency') {
                $account->update(['currency_code' => 'USD']);
            } else {
                $account->update(['type' => 'bank']);
            }

            Auth::login($reviewer);
            try {
                app(ReviewShiftVarianceAction::class)->approveAndClose($reviewer, $shift, $approval, $shift->lock_version);
                self::fail('Invalid treasury case did not block: '.$case);
            } catch (InvalidArgumentException) {
                self::assertNotSame('closed', $shift->fresh()->status->value, $case);
                self::assertSame('pending', $approval->fresh()->approval_state->value, $case);
                self::assertSame(0, CashTransaction::query()->where('source_type', PosShift::class)->where('source_id', $shift->id)->count(), $case);
            }
        }
    }

    /** @return array{PosShift, ApprovalRecord, User, CashAccount, User} */
    private function submittedShift(string $actual, string $opening, bool $withSaleAndSafeDeposit = false): array
    {
        $company = Company::factory()->create(['currency_code' => 'EGP']);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $account = CashAccount::query()->create([
            'company_id' => $company->id, 'code' => 'SAFE-'.$branch->id,
            'name_ar' => 'الخزنة', 'name_en' => 'Treasury', 'type' => 'cash',
            'currency_code' => 'EGP', 'status' => 'active',
        ]);
        $drawer = CashDrawer::factory()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'store_id' => $store->id,
            'treasury_cash_account_id' => $account->id,
        ]);
        $cashier = User::factory()->create(['is_super_admin' => true]);
        $reviewer = User::factory()->create(['is_super_admin' => true]);
        DB::table('document_sequences')->insert([
            'document_type' => 'shift_close', 'scope_type' => 'branch', 'scope_id' => $branch->id,
            'scope_key' => 'branch:'.$branch->id, 'prefix' => 'SHC-', 'padding_length' => 6,
            'next_value' => 1, 'reset_rule' => 'never', 'status' => 'active', 'lock_version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Auth::login($cashier);
        $shift = app(OpenShiftAction::class)->execute($cashier, $drawer, $opening, 'shift-open-'.$drawer->id);
        if ($withSaleAndSafeDeposit) {
            $method = PaymentMethod::factory()->create();
            $sale = Sale::factory()->paid()->create([
                'branch_id' => $shift->branch_id, 'store_id' => $shift->store_id,
                'cash_drawer_id' => $shift->cash_drawer_id, 'shift_id' => $shift->id,
                'cashier_id' => $cashier->id, 'subtotal' => '1000.00', 'total' => '1000.00',
                'paid_total' => '1000.00', 'payable_total' => '1000.00', 'outstanding_amount' => '0.0000',
            ]);
            SalePayment::query()->create([
                'sale_id' => $sale->id, 'payment_method_id' => $method->id,
                'method_code' => $method->code, 'method_type' => $method->type,
                'amount' => '1000.00', 'tendered_amount' => '1000.00', 'change_amount' => '0.00',
                'idempotency_key' => 'shift-sale-payment-'.$shift->id, 'created_by' => $cashier->id,
            ]);
            app(RecordCashMovementAction::class)->execute($cashier, $shift, CashMovement::TYPE_SAFE_DEPOSIT, '500.00', 'Deposit during shift', 'shift-safe-deposit-'.$shift->id);
        }
        app(SubmitBlindShiftCloseAction::class)->execute($cashier, $shift, $actual, [], 'shift-close-'.$shift->id);
        $shift = $shift->fresh();

        return [$shift, $shift->varianceApprovalRecord, $reviewer, $account, $cashier];
    }
}

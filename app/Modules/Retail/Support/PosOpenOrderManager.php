<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Data\PosContext;
use App\Modules\Retail\Models\PosOpenOrder;
use App\Modules\Retail\Models\PosOpenOrderLine;
use App\Modules\Retail\Models\PosOpenOrderPayment;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Durable, scope-locked server state for the cashier's simultaneous POS orders. */
final class PosOpenOrderManager
{
    public const SESSION_KEY = 'pos.active_open_order_uuid';

    public const MAX_OPEN_ORDERS = 50;

    public function active(Request $request, User $cashier, PosContext $context, bool $create = true): ?PosOpenOrder
    {
        if (! $context->isReady()) {
            return null;
        }

        $uuid = $request->session()->get(self::SESSION_KEY);
        $order = is_string($uuid) && $uuid !== ''
            ? $this->scope($cashier, $context)->where('uuid', $uuid)->first()
            : null;
        $order ??= $this->scope($cashier, $context)->orderByDesc('last_activity_at')->orderByDesc('id')->first();

        if ($order === null && $create) {
            $order = $this->create($cashier, $context);
        }
        if ($order !== null) {
            $request->session()->put(self::SESSION_KEY, $order->uuid);
        }

        return $order;
    }

    /** @return Collection<int, PosOpenOrder> */
    public function orders(User $cashier, PosContext $context): Collection
    {
        if (! $context->isReady()) {
            return collect();
        }

        return $this->scope($cashier, $context)
            ->with(['customer:id,name_ar,name_en,phone_display', 'paymentDrafts.paymentMethod'])
            ->withCount('lines')
            ->orderBy('sequence_number')
            ->limit(self::MAX_OPEN_ORDERS)
            ->get();
    }

    public function create(User $cashier, PosContext $context, ?string $creationToken = null): PosOpenOrder
    {
        if (! $context->isReady() || $context->store === null || $context->shift === null || $context->drawer === null || $context->branch === null) {
            throw new InvalidArgumentException($context->disabledReason ?? __('Open an assigned cashier shift before creating an order.'));
        }

        return DB::transaction(function () use ($cashier, $context, $creationToken): PosOpenOrder {
            PosShift::query()->lockForUpdate()->findOrFail($context->shift->id);
            if ($creationToken !== null) {
                if (! Str::isUuid($creationToken)) {
                    throw new InvalidArgumentException(__('The new-order request is invalid.'));
                }
                $existing = $this->scope($cashier, $context)->where('checkout_token', $creationToken)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }
            $count = $this->scope($cashier, $context)->count();
            if ($count >= self::MAX_OPEN_ORDERS) {
                throw new InvalidArgumentException(__('Complete or cancel an open order before creating another one.'));
            }

            $sequence = ((int) PosOpenOrder::query()
                ->where('shift_id', $context->shift->id)
                ->where('cashier_id', $cashier->id)
                ->max('sequence_number')) + 1;

            $order = PosOpenOrder::query()->create([
                'uuid' => (string) Str::uuid(),
                'company_id' => (int) $context->store->company_id,
                'branch_id' => (int) $context->branch->id,
                'store_id' => (int) $context->store->id,
                'cash_drawer_id' => (int) $context->drawer->id,
                'shift_id' => (int) $context->shift->id,
                'cashier_id' => (int) $cashier->id,
                'sequence_number' => $sequence,
                'status' => 'open',
                'checkout_token' => $creationToken ?? (string) Str::uuid(),
                'last_activity_at' => now(),
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'retail', event: 'pos_open_order_created', source: $order,
                after: ['uuid' => $order->uuid, 'sequence_number' => $sequence, 'shift_id' => $order->shift_id],
                branchId: $order->branch_id, storeId: $order->store_id,
                metadata: ['cashier_id' => $cashier->id, 'cash_drawer_id' => $order->cash_drawer_id],
            );

            return $order;
        });
    }

    public function switch(Request $request, User $cashier, PosContext $context, string $uuid): PosOpenOrder
    {
        $order = $this->scope($cashier, $context)->where('uuid', $uuid)->first();
        if ($order === null) {
            throw new InvalidArgumentException(__('This open order is no longer available in the current cashier context.'));
        }

        $request->session()->put(self::SESSION_KEY, $order->uuid);
        $this->hydrateSession($request, $order);

        return $order;
    }

    public function cancel(Request $request, User $cashier, PosContext $context, string $uuid, ?string $reason = null): void
    {
        DB::transaction(function () use ($cashier, $context, $uuid, $reason): void {
            $order = $this->scope($cashier, $context)->where('uuid', $uuid)->lockForUpdate()->first();
            if ($order === null) {
                throw new InvalidArgumentException(__('This open order is no longer available in the current cashier context.'));
            }
            $before = $order->only(['status', 'revision']);
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'revision' => $order->revision + 1,
                'last_activity_at' => now(),
            ]);
            app(RecordAuditEvent::class)->execute(
                category: 'retail', event: 'pos_open_order_cancelled', source: $order,
                before: $before, after: $order->only(['status', 'revision', 'cancelled_at']),
                branchId: $order->branch_id, storeId: $order->store_id,
                reasonText: filled($reason) ? trim((string) $reason) : __('Cashier cancelled the open order.'),
                metadata: ['cashier_id' => $cashier->id, 'line_count' => $order->lines()->count()],
            );
        });

        if ($request->session()->get(self::SESSION_KEY) === $uuid) {
            $this->forgetSessionDraft($request);
        }
    }

    public function hydrateSession(Request $request, PosOpenOrder $order): void
    {
        $request->session()->put(self::SESSION_KEY, $order->uuid);
        $request->session()->put('pos.checkout_token', $order->checkout_token);
        $request->session()->put('pos.tax_applicable', (bool) $order->tax_applicable);
        $order->customer_id === null
            ? $request->session()->forget('pos.customer_id')
            : $request->session()->put('pos.customer_id', $order->customer_id);
        $request->session()->put('pos.cart', $order->lines()->orderBy('line_position')->get()->map(function (PosOpenOrderLine $line): array {
            $payload = $line->draft_payload;
            $payload['product_id'] = (int) $line->product_id;
            $payload['quantity'] = (string) $line->quantity;

            return $payload;
        })->all());
    }

    public function persistSession(Request $request, User $cashier, PosContext $context): PosOpenOrder
    {
        $order = $this->active($request, $cashier, $context);
        if ($order === null) {
            throw new InvalidArgumentException($context->disabledReason ?? __('The active order is unavailable.'));
        }

        /** @var array<int, array<string, mixed>> $cart */
        $cart = array_values($request->session()->get('pos.cart', []));
        DB::transaction(function () use ($order, $cart): void {
            $locked = PosOpenOrder::query()->open()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->lines()->delete();
            foreach ($cart as $position => $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $quantity = (string) ($line['quantity'] ?? '0');
                if ($productId <= 0 || bccomp($quantity, '0', 6) <= 0) {
                    throw new InvalidArgumentException(__('The open order contains an invalid product line.'));
                }
                PosOpenOrderLine::query()->create([
                    'pos_open_order_id' => $locked->id,
                    'product_id' => $productId,
                    'line_position' => $position + 1,
                    'quantity' => $quantity,
                    'draft_payload' => $line,
                ]);
            }
            $locked->update(['revision' => $locked->revision + 1, 'last_activity_at' => now()]);
        });

        return $order->fresh(['lines', 'paymentDrafts']);
    }

    public function setTax(Request $request, User $cashier, PosContext $context, bool $applicable): void
    {
        $order = $this->active($request, $cashier, $context);
        $this->updateOpen($order, ['tax_applicable' => $applicable]);
        $request->session()->put('pos.tax_applicable', $applicable);
    }

    public function setCustomer(Request $request, User $cashier, PosContext $context, ?Customer $customer): void
    {
        $order = $this->active($request, $cashier, $context);
        $this->updateOpen($order, ['customer_id' => $customer?->id]);
        $customer === null
            ? $request->session()->forget('pos.customer_id')
            : $request->session()->put('pos.customer_id', $customer->id);
    }

    /** @param array<string, mixed>|null $totals */
    public function updateTotals(?PosOpenOrder $order, ?array $totals): void
    {
        if ($order === null || $totals === null || $order->status !== 'open') {
            return;
        }
        PosOpenOrder::query()->open()->whereKey($order->id)->update([
            'subtotal' => $totals['subtotal'] ?? '0.00',
            'discount_total' => $totals['discount_total'] ?? '0.00',
            'tax_total' => $totals['tax_total'] ?? '0.00',
            'total' => $totals['total'] ?? '0.00',
            'last_activity_at' => now(),
        ]);
    }

    /** @param array<int, array<string, mixed>> $lines */
    public function savePaymentDraft(Request $request, User $cashier, PosContext $context, string $mode, ?string $notes, array $lines): void
    {
        if (! in_array($mode, ['cash', 'card', 'split', 'other', 'credit'], true) || count($lines) > 12 || ($mode === 'credit' && $lines !== [])) {
            throw new InvalidArgumentException(__('The payment draft is invalid.'));
        }
        $order = $this->active($request, $cashier, $context);
        if ($order === null) {
            throw new InvalidArgumentException(__('The active order is unavailable.'));
        }

        DB::transaction(function () use ($order, $mode, $notes, $lines): void {
            $locked = PosOpenOrder::query()->open()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->paymentDrafts()->delete();
            foreach (array_values($lines) as $position => $line) {
                $methodId = filled($line['method_id'] ?? null) ? (int) $line['method_id'] : null;
                if ($methodId !== null && ! PaymentMethod::query()->whereKey($methodId)->where('status', 'active')->exists()) {
                    throw new InvalidArgumentException(__('One selected payment method is no longer active.'));
                }
                PosOpenOrderPayment::query()->create([
                    'pos_open_order_id' => $locked->id,
                    'payment_method_id' => $methodId,
                    'line_position' => $position + 1,
                    'amount' => $this->nullableMoney($line['amount'] ?? null),
                    'tendered_amount' => $this->nullableMoney($line['tendered'] ?? null),
                    'safe_reference' => PaymentReferenceGuard::normalize($line['evidence_reference'] ?? null),
                    'gift_card_identifier' => filled($line['gift_card_identifier'] ?? null) ? trim((string) $line['gift_card_identifier']) : null,
                ]);
            }
            $locked->update([
                'payment_mode' => $mode,
                'notes' => filled($notes) ? trim((string) $notes) : null,
                'revision' => $locked->revision + 1,
                'last_activity_at' => now(),
            ]);
        });
    }

    public function findScoped(User $cashier, PosContext $context, string $uuid): ?PosOpenOrder
    {
        return $this->scope($cashier, $context)->where('uuid', $uuid)->first();
    }

    private function updateOpen(?PosOpenOrder $order, array $attributes): void
    {
        if ($order === null || $order->status !== 'open') {
            throw new InvalidArgumentException(__('The active order is unavailable.'));
        }
        DB::transaction(function () use ($order, $attributes): void {
            $locked = PosOpenOrder::query()->open()->whereKey($order->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new InvalidArgumentException(__('The active order is unavailable.'));
            }
            $locked->update($attributes + ['revision' => $locked->revision + 1, 'last_activity_at' => now()]);
        });
    }

    private function nullableMoney(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $money = DecimalMoney::round((string) $value, 2, __('A payment amount must be a valid number.'));
        if (bccomp($money, '0', 2) < 0) {
            throw new InvalidArgumentException(__('Payment values cannot be negative.'));
        }

        return $money;
    }

    private function scope(User $cashier, PosContext $context): Builder
    {
        if (! $context->isReady() || $context->store === null || $context->branch === null || $context->drawer === null || $context->shift === null) {
            return PosOpenOrder::query()->whereRaw('1 = 0');
        }

        return PosOpenOrder::query()->open()
            ->where('company_id', $context->store->company_id)
            ->where('branch_id', $context->branch->id)
            ->where('store_id', $context->store->id)
            ->where('cash_drawer_id', $context->drawer->id)
            ->where('shift_id', $context->shift->id)
            ->where('cashier_id', $cashier->id);
    }

    private function forgetSessionDraft(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_KEY, 'pos.cart', 'pos.checkout_token', 'pos.tax_applicable', 'pos.customer_id',
        ]);
    }
}

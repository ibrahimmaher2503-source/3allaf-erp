<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $overpaid = DB::table('sales as sales')
            ->joinSub(
                DB::table('sale_payments')
                    ->selectRaw('sale_id, SUM(amount) AS paid_amount')
                    ->groupBy('sale_id'),
                'payments',
                'payments.sale_id',
                '=',
                'sales.id',
            )
            ->where('sales.status', 'approved')
            ->whereColumn('payments.paid_amount', '>', 'sales.payable_total')
            ->exists();

        if ($overpaid) {
            throw new RuntimeException('Cannot reconcile sale settlement snapshots while an approved sale is overpaid.');
        }

        DB::table('sales')
            ->where('status', 'approved')
            ->select(['id', 'payable_total'])
            ->orderBy('id')
            ->chunkById(500, function ($sales): void {
                $payments = DB::table('sale_payments')
                    ->whereIn('sale_id', $sales->pluck('id'))
                    ->selectRaw('sale_id, SUM(amount) AS paid_amount')
                    ->groupBy('sale_id')
                    ->pluck('paid_amount', 'sale_id');

                foreach ($sales as $sale) {
                    $paid = (string) ($payments[$sale->id] ?? '0.00');
                    $outstanding = bcsub((string) $sale->payable_total, $paid, 4);

                    DB::table('sales')->where('id', $sale->id)->update([
                        'paid_total' => $paid,
                        'outstanding_amount' => $outstanding,
                        'payment_status' => bccomp($outstanding, '0', 4) === 0
                            ? 'paid'
                            : (bccomp($paid, '0', 2) > 0 ? 'partial' : 'unpaid'),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Data reconciliation is intentionally irreversible. Restoring stale
        // settlement snapshots would be less accurate than leaving them intact.
    }
};

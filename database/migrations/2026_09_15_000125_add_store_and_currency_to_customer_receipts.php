<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $nonEgp = DB::table('customer_receipt_allocations')
            ->join('sales', 'sales.id', '=', 'customer_receipt_allocations.sale_id')
            ->where(function ($query): void {
                $query->whereNull('sales.currency_code')->orWhere('sales.currency_code', '!=', 'EGP');
            })
            ->exists();
        if ($nonEgp) {
            throw new RuntimeException('Customer receipt currency backfill requires an owner decision for non-EGP historical allocations.');
        }

        Schema::table('customer_receipts', function (Blueprint $table): void {
            $table->foreignId('store_id')->nullable()->after('customer_id')->constrained('stores')->restrictOnDelete();
            $table->char('currency_code', 3)->nullable()->after('receipt_date');
            $table->index(['store_id', 'receipt_date', 'status'], 'customer_receipts_scope_balance_idx');
        });

        DB::table('customer_receipts')->update(['currency_code' => 'EGP']);
        DB::table('customer_receipts')->orderBy('id')->chunkById(500, function ($receipts): void {
            foreach ($receipts as $receipt) {
                $contexts = DB::table('customer_receipt_allocations')
                    ->join('sales', 'sales.id', '=', 'customer_receipt_allocations.sale_id')
                    ->where('customer_receipt_allocations.customer_receipt_id', $receipt->id)
                    ->distinct()
                    ->get(['sales.store_id']);

                if ($contexts->count() === 1) {
                    DB::table('customer_receipts')->where('id', $receipt->id)->update([
                        'store_id' => $contexts->first()->store_id,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table): void {
            $table->dropForeign(['store_id']);
            $table->dropIndex('customer_receipts_scope_balance_idx');
            $table->dropColumn(['store_id', 'currency_code']);
        });
    }
};

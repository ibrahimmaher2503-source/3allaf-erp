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
        if (DB::table('stock_transfer_lines')->whereRaw('quantity_received + difference_quantity > quantity_dispatched')->exists()) {
            throw new RuntimeException('Invalid stock transfer quantities must be reconciled before adding receipt events.');
        }
        if (DB::table('stock_transfer_lines as lines')
            ->join('stock_transfers as transfers', 'transfers.id', '=', 'lines.stock_transfer_id')
            ->whereIn('transfers.status', ['received', 'difference_review'])
            ->whereRaw('lines.quantity_received + lines.difference_quantity <> lines.quantity_dispatched')
            ->exists()) {
            throw new RuntimeException('Terminal stock transfers with unresolved quantities must be reconciled before adding receipt events.');
        }

        Schema::create('stock_transfer_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->restrictOnDelete();
            $table->string('receipt_number', 60)->unique();
            $table->string('status', 30);
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at');
            $table->string('difference_type', 40)->nullable();
            $table->text('difference_reason')->nullable();
            $table->string('idempotency_key', 160)->unique();
            $table->char('payload_hash', 64);
            $table->timestamps();
            $table->index(['stock_transfer_id', 'received_at']);
        });

        Schema::create('stock_transfer_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_receipt_id')->constrained('stock_transfer_receipts')->restrictOnDelete();
            $table->foreignId('stock_transfer_line_id')->constrained('stock_transfer_lines')->restrictOnDelete();
            $table->decimal('quantity_received', 20, 6);
            $table->decimal('difference_quantity', 20, 6)->default(0);
            $table->timestamps();
            $table->unique(['stock_transfer_receipt_id', 'stock_transfer_line_id'], 'transfer_receipt_line_unique');
            $table->index('stock_transfer_line_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_transfer_receipts') && DB::table('stock_transfer_receipts')->exists()) {
            throw new RuntimeException('Stock transfer receipt events exist; restore a backup or use a forward migration.');
        }
        Schema::dropIfExists('stock_transfer_receipt_lines');
        Schema::dropIfExists('stock_transfer_receipts');
    }
};

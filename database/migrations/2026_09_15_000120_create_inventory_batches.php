<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('batch_number', 120);
            $table->date('production_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['product_id', 'batch_number']);
            $table->index(['product_id', 'status', 'expiry_date'], 'inventory_batches_product_expiry_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('batch_id')->nullable()->after('store_id')->constrained('inventory_batches')->restrictOnDelete();
            $table->index(['batch_id', 'posted_at'], 'stock_movements_batch_posted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('stock_movements_batch_posted_idx');
            $table->dropConstrainedForeignId('batch_id');
        });
        Schema::dropIfExists('inventory_batches');
    }
};

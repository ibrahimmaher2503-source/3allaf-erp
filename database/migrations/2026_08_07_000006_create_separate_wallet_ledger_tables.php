<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['product_wallet_ledger', 'party_wallet_ledger'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->string('entry_type', 40);
                $table->decimal('amount', 20, 4);
                $table->string('currency_code', 12)->nullable();
                $table->string('source_type', 120)->nullable();
                $table->string('source_id', 120)->nullable();
                $table->string('source_line_id', 120)->nullable();
                $table->string('idempotency_key', 190)->unique();
                $table->string('reference', 190)->nullable();
                $table->text('reason')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['source_type', 'source_id']);
                $table->index(['entry_type', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('party_wallet_ledger');
        Schema::dropIfExists('product_wallet_ledger');
    }
};

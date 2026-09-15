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
        foreach (['product_wallet_ledger', 'party_wallet_ledger'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->foreignId('customer_id')->after('id')->constrained('customers')->restrictOnDelete();
                $table->foreignId('branch_id')->after('customer_id')->constrained('branches')->restrictOnDelete();
                $table->foreignId('store_id')->after('branch_id')->constrained('stores')->restrictOnDelete();
                $table->char('payload_hash', 64)->after('idempotency_key');
                $table->decimal('balance_before', 20, 4)->after('amount');
                $table->decimal('balance_after', 20, 4)->after('balance_before');
                $table->unsignedBigInteger('reversal_of_id')->nullable()->after('reason');
                $table->unsignedBigInteger('correction_of_id')->nullable()->after('reversal_of_id');

                $table->index(['customer_id', 'created_at'], $tableName.'_customer_created_index');
                $table->index(['customer_id', 'source_type', 'source_id'], $tableName.'_customer_source_index');
                $table->foreign('reversal_of_id', $tableName.'_reversal_fk')->references('id')->on($tableName)->restrictOnDelete();
                $table->foreign('correction_of_id', $tableName.'_correction_fk')->references('id')->on($tableName)->restrictOnDelete();
            });

            // The readiness migration allowed nullable source fields. Existing
            // deployments must not keep a financial row without an owner,
            // source, actor, currency, or deterministic balance snapshot.
            DB::statement("ALTER TABLE `{$tableName}` MODIFY `currency_code` VARCHAR(12) NOT NULL");
            DB::statement("ALTER TABLE `{$tableName}` MODIFY `source_type` VARCHAR(120) NOT NULL");
            DB::statement("ALTER TABLE `{$tableName}` MODIFY `source_id` VARCHAR(120) NOT NULL");
            DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$tableName}_created_by_foreign`");
            DB::statement("ALTER TABLE `{$tableName}` MODIFY `created_by` BIGINT UNSIGNED NOT NULL");
            DB::statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT");
            DB::statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_amount_sign_check` CHECK ((`entry_type` IN ('credit', 'debit', 'settlement', 'adjustment', 'reversal', 'correction')) AND `amount` <> 0)");
        }

        $this->createAdjustmentTable('product_wallet_adjustments', 'product_wallet_ledger');
        $this->createAdjustmentTable('party_wallet_adjustments', 'party_wallet_ledger');
    }

    private function createAdjustmentTable(string $tableName, string $ledgerTable): void
    {
        Schema::create($tableName, function (Blueprint $table) use ($ledgerTable, $tableName): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('operation', 30);
            $table->decimal('amount', 20, 4);
            $table->foreignId('target_ledger_id')->nullable()->constrained($ledgerTable)->restrictOnDelete();
            $table->string('source_type', 120);
            $table->string('source_id', 120);
            $table->string('source_line_id', 120)->nullable();
            $table->string('source_reference', 190)->nullable();
            $table->text('reason');
            $table->string('status', 30)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('approval_record_id')->nullable()->unique()->constrained('approval_records')->restrictOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->char('payload_hash', 64);
            $table->unsignedInteger('lock_version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'created_at'], $tableName.'_customer_status_index');
            $table->index(['branch_id', 'store_id', 'status'], $tableName.'_scope_status_index');
            $table->index(['source_type', 'source_id'], $tableName.'_source_index');
        });

        DB::statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_operation_check` CHECK (`operation` IN ('adjustment', 'correction'))");
        DB::statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_status_check` CHECK (`status` IN ('pending', 'approved', 'rejected', 'cancelled'))");
        DB::statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_amount_check` CHECK (`amount` <> 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('party_wallet_adjustments');
        Schema::dropIfExists('product_wallet_adjustments');

        foreach (['product_wallet_ledger', 'party_wallet_ledger'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$tableName}_reversal_fk`");
            DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$tableName}_correction_fk`");
            DB::statement("ALTER TABLE `{$tableName}` DROP CHECK `{$tableName}_amount_sign_check`");
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName.'_customer_created_index');
                $table->dropIndex($tableName.'_customer_source_index');
                $table->dropConstrainedForeignId('customer_id');
                $table->dropConstrainedForeignId('branch_id');
                $table->dropConstrainedForeignId('store_id');
                $table->dropColumn(['payload_hash', 'balance_before', 'balance_after', 'reversal_of_id', 'correction_of_id']);
            });
            DB::statement("ALTER TABLE `{$tableName}` MODIFY `currency_code` VARCHAR(12) NULL");
            DB::statement("ALTER TABLE `{$tableName}` MODIFY `source_type` VARCHAR(120) NULL");
            DB::statement("ALTER TABLE `{$tableName}` MODIFY `source_id` VARCHAR(120) NULL");
            DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$tableName}_created_by_foreign`");
            DB::statement("ALTER TABLE `{$tableName}` MODIFY `created_by` BIGINT UNSIGNED NULL");
            DB::statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL");
        }
    }
};

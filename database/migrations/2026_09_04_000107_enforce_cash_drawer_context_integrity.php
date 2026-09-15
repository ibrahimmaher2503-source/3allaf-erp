<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const POSITIVE_COMPANY_CHECK = 'cash_drawers_company_id_positive_check';

    public function up(): void
    {
        $this->auditAndBackfillFromStore();

        Schema::table('cash_drawers', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
        });

        Schema::table('cash_drawers', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
        });

        DB::statement(sprintf(
            'ALTER TABLE cash_drawers ADD CONSTRAINT %s CHECK (company_id > 0)',
            self::POSITIVE_COMPANY_CHECK,
        ));
    }

    public function down(): void
    {
        DB::statement(sprintf(
            'ALTER TABLE cash_drawers DROP CONSTRAINT %s',
            self::POSITIVE_COMPANY_CHECK,
        ));

        Schema::table('cash_drawers', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
        });

        Schema::table('cash_drawers', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });
    }

    private function auditAndBackfillFromStore(): void
    {
        $companyIds = DB::table('companies')->orderBy('id')->pluck('id')->mapWithKeys(
            static fn (mixed $id): array => [(int) $id => true],
        );
        $branches = DB::table('branches')->orderBy('id')->get(['id', 'company_id'])->keyBy(
            static fn (object $branch): int => (int) $branch->id,
        );
        $stores = DB::table('stores')->orderBy('id')->get(['id', 'company_id', 'branch_id'])->keyBy(
            static fn (object $store): int => (int) $store->id,
        );

        $updates = [];
        $issues = [];

        foreach (DB::table('cash_drawers')->orderBy('id')->get(['id', 'code', 'company_id', 'branch_id', 'store_id']) as $drawer) {
            $drawerId = (int) $drawer->id;
            $drawerCompanyId = (int) $drawer->company_id;
            $drawerBranchId = (int) $drawer->branch_id;
            $drawerStoreId = (int) $drawer->store_id;

            if ($drawerStoreId < 1) {
                $branch = $branches->get($drawerBranchId);
                if ($drawerCompanyId < 1
                    || ! $companyIds->has($drawerCompanyId)
                    || $branch === null
                    || (int) $branch->company_id !== $drawerCompanyId) {
                    $issues[] = $this->issue($drawer, 'missing_store_or_invalid_existing_company_branch');
                }

                continue;
            }

            $store = $stores->get($drawerStoreId);
            if ($store === null) {
                $issues[] = $this->issue($drawer, 'store_not_found');

                continue;
            }

            $storeCompanyId = (int) $store->company_id;
            $storeBranchId = (int) $store->branch_id;
            $branch = $branches->get($storeBranchId);
            if ($storeCompanyId < 1 || ! $companyIds->has($storeCompanyId)) {
                $issues[] = $this->issue($drawer, 'store_company_not_found');
            } elseif ($storeBranchId < 1 || $branch === null) {
                $issues[] = $this->issue($drawer, 'store_branch_not_found');
            } elseif ((int) $branch->company_id !== $storeCompanyId) {
                $issues[] = $this->issue($drawer, 'store_branch_company_mismatch');
            } elseif ($drawerBranchId !== $storeBranchId) {
                $issues[] = $this->issue($drawer, 'drawer_store_branch_mismatch');
            } elseif ($drawerCompanyId !== $storeCompanyId) {
                // A valid store whose branch agrees on the same company is the
                // sole unambiguous authority for company ownership.
                $updates[$drawerId] = $storeCompanyId;
            }
        }

        if ($issues !== []) {
            throw new RuntimeException(
                'Cash drawer integrity audit failed; no drawers were changed. Correct the reported records and rerun: '.json_encode($issues, JSON_UNESCAPED_SLASHES),
            );
        }

        DB::transaction(function () use ($updates): void {
            foreach ($updates as $drawerId => $companyId) {
                DB::table('cash_drawers')->where('id', $drawerId)->update([
                    'company_id' => $companyId,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /** @return array{id: int, code: string, reason: string} */
    private function issue(object $drawer, string $reason): array
    {
        return [
            'id' => (int) $drawer->id,
            'code' => (string) $drawer->code,
            'reason' => $reason,
        ];
    }
};

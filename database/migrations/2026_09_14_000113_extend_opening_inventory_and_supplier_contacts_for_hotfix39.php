<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opening_inventory_documents', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->after('branch_id')->constrained('stores')->nullOnDelete();
            $table->date('document_date')->nullable()->after('document_number')->index();
            $table->index(['company_id', 'branch_id', 'status'], 'opening_inventory_company_branch_status_index');
        });

        Schema::table('supplier_contacts', function (Blueprint $table): void {
            $table->string('mobile', 50)->nullable()->after('phone');
            $table->text('notes')->nullable()->after('whatsapp');
            $table->index(['supplier_id', 'role', 'mobile'], 'supplier_contacts_role_mobile_index');
        });

        $now = now();
        foreach (DB::table('branches')->select(['id', 'code'])->get() as $branch) {
            DB::table('document_sequences')->insertOrIgnore([
                'document_type' => 'opening_inventory',
                'scope_type' => 'branch',
                'scope_id' => $branch->id,
                'scope_key' => 'branch:'.$branch->id,
                'prefix' => strtoupper(trim((string) $branch->code)).'-OI-',
                'padding_length' => 6,
                'next_value' => 1,
                'reset_rule' => 'never',
                'status' => 'active',
                'lock_version' => 1,
                'policy_notes' => 'Hotfix39 opening inventory: branch/type continuous independent sequence; no annual reset.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Hotfix39 additions are nullable and intentionally retained so an application
        // rollback to Hotfix38 remains backward compatible with captured master data.
    }
};

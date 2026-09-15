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
        if (! Schema::hasColumn('tax_settings', 'treatment')) {
            Schema::table('tax_settings', function (Blueprint $table): void {
                $table->string('treatment')->default('standard')->after('rate');
            });
        }
        if (! Schema::hasColumn('tax_settings', 'is_default')) {
            Schema::table('tax_settings', function (Blueprint $table): void {
                $table->boolean('is_default')->default(false)->after('treatment');
            });
        }
        if (! collect(Schema::getIndexes('tax_settings'))->contains(fn (array $index): bool => ($index['name'] ?? null) === 'tax_settings_effective_default_index')) {
            Schema::table('tax_settings', function (Blueprint $table): void {
                $table->index(['status', 'is_default', 'effective_from'], 'tax_settings_effective_default_index');
            });
        }

        DB::table('tax_settings')->update([
            'treatment' => DB::raw("CASE WHEN rate = 0 THEN 'zero_rated' ELSE 'standard' END"),
        ]);
        $defaultId = DB::table('tax_settings')->where('status', 'active')->orderBy('id')->value('id');
        if ($defaultId !== null) {
            DB::table('tax_settings')->where('id', $defaultId)->update(['is_default' => true]);
        }

        if (! Schema::hasColumn('sales', 'tax_treatment_snapshot')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->string('tax_treatment_snapshot')->nullable()->after('tax_rate_snapshot');
            });
        }

        if (! Schema::hasColumn('document_sequences', 'scope_type')) {
            Schema::table('document_sequences', function (Blueprint $table): void {
                $table->string('scope_type')->default('company')->after('document_type');
            });
        }
        if (! Schema::hasColumn('document_sequences', 'scope_id')) {
            Schema::table('document_sequences', function (Blueprint $table): void {
                $table->unsignedBigInteger('scope_id')->nullable()->after('scope_type');
            });
        }
        if (! Schema::hasColumn('document_sequences', 'scope_key')) {
            Schema::table('document_sequences', function (Blueprint $table): void {
                $table->string('scope_key')->default('company')->after('scope_id');
            });
        }
        if (! Schema::hasColumn('document_sequences', 'last_reset_period')) {
            Schema::table('document_sequences', function (Blueprint $table): void {
                $table->string('last_reset_period')->nullable()->after('reset_rule');
            });
        }

        DB::table('document_sequences')->update([
            'scope_type' => 'company',
            'scope_id' => null,
            'scope_key' => 'company',
        ]);

        if (! collect(Schema::getIndexes('document_sequences'))->contains(fn (array $index): bool => ($index['name'] ?? null) === 'document_sequences_scope_index')) {
            Schema::table('document_sequences', function (Blueprint $table): void {
                $table->index(['scope_type', 'scope_id'], 'document_sequences_scope_index');
            });
        }

        if (! collect(Schema::getForeignKeys('document_sequences'))->contains(fn (array $foreignKey): bool => ($foreignKey['name'] ?? null) === 'document_sequences_scope_branch_fk')) {
            Schema::table('document_sequences', function (Blueprint $table): void {
                $table->foreign('scope_id', 'document_sequences_scope_branch_fk')
                    ->references('id')->on('branches')->restrictOnDelete();
            });
        }

        $indexes = collect(Schema::getIndexes('document_sequences'));
        $legacyIndexExists = $indexes->contains(fn (array $index): bool => ($index['name'] ?? null) === 'document_sequences_document_type_unique');
        $compositeIndexExists = $indexes->contains(fn (array $index): bool => ($index['name'] ?? null) === 'document_sequences_document_scope_unique');

        if ($legacyIndexExists || ! $compositeIndexExists) {
            Schema::table('document_sequences', function (Blueprint $table) use ($legacyIndexExists, $compositeIndexExists): void {
                if ($legacyIndexExists) {
                    $table->dropUnique('document_sequences_document_type_unique');
                }
                if (! $compositeIndexExists) {
                    $table->unique(['document_type', 'scope_key'], 'document_sequences_document_scope_unique');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('document_sequences', function (Blueprint $table): void {
            $table->dropUnique('document_sequences_document_scope_unique');
            $table->dropForeign('document_sequences_scope_branch_fk');
            $table->dropIndex('document_sequences_scope_index');
            $table->dropColumn(['scope_type', 'scope_id', 'scope_key', 'last_reset_period']);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn('tax_treatment_snapshot');
        });

        Schema::table('tax_settings', function (Blueprint $table): void {
            $table->dropIndex('tax_settings_effective_default_index');
            $table->dropColumn(['treatment', 'is_default']);
        });
    }
};

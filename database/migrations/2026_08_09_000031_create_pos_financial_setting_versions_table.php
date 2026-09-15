<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only owner-configurable POS financial values (DEC-066 / DEC-065).
     *
     * Mirrors `customer_policy_setting_versions`. Kept separate from
     * `financial_setting_versions` (purchasing) so the POS domain owns its
     * own values, per the DEC-064 precedent against cross-domain reuse.
     */
    public function up(): void
    {
        Schema::create('pos_financial_setting_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120);
            $table->text('value')->nullable();
            $table->string('value_type', 20)->default('text');
            $table->unsignedInteger('version');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['key', 'version']);
            $table->index(['key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_financial_setting_versions');
    }
};

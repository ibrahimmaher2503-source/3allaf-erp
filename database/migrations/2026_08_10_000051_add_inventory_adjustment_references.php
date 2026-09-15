<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table): void {
            $table->foreignId('reversal_of_id')->nullable()->after('reversed_by')->constrained('inventory_adjustments')->restrictOnDelete();
            $table->text('reversal_reason')->nullable()->after('reversed_at');
            $table->index(['reversal_of_id', 'status'], 'inventory_adjustments_reversal_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table): void {
            $table->dropIndex('inventory_adjustments_reversal_status_index');
            $table->dropForeign(['reversal_of_id']);
            $table->dropColumn(['reversal_of_id', 'reversal_reason']);
        });
    }
};

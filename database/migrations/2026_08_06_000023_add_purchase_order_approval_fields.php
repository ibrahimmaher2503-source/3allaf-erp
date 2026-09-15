<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->timestamp('approved_at')->nullable()->after('submitted_by');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->index(['status', 'approved_at']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['status', 'approved_at']);
            $table->dropColumn(['approved_at', 'approved_by']);
        });
    }
};

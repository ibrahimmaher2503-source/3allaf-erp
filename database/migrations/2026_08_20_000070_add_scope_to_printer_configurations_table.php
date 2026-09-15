<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printer_configurations', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->after('branch_id')->constrained('stores')->nullOnDelete();
            $table->index(['branch_id', 'store_id', 'is_default', 'status'], 'printer_scope_default_index');
        });
    }

    public function down(): void
    {
        Schema::table('printer_configurations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('store_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropIndex('printer_scope_default_index');
        });
    }
};

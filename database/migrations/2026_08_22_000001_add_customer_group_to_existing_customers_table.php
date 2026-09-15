<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('customers', 'customer_group_id')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('customer_group_id')->nullable()->after('created_store_id')->constrained('customer_groups')->nullOnDelete();
            $table->index(['customer_group_id', 'status'], 'customers_group_status_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('customers', 'customer_group_id')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign(['customer_group_id']);
            $table->dropIndex('customers_group_status_index');
            $table->dropColumn('customer_group_id');
        });
    }
};

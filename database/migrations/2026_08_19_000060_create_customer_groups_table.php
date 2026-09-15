<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('customer_groups')->restrictOnDelete();
            $table->string('name_ar', 190);
            $table->string('name_en', 190);
            $table->string('status', 30)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'name_ar'], 'customer_group_company_name_ar_unique');
            $table->unique(['company_id', 'name_en'], 'customer_group_company_name_en_unique');
            $table->index(['company_id', 'parent_id', 'status']);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('customer_group_id')->nullable()->after('created_store_id')->constrained('customer_groups')->nullOnDelete();
            $table->index(['customer_group_id', 'status'], 'customers_group_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign(['customer_group_id']);
            $table->dropIndex('customers_group_status_index');
            $table->dropColumn('customer_group_id');
        });

        Schema::dropIfExists('customer_groups');
    }
};

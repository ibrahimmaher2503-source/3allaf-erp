<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('supplier_groups')->restrictOnDelete();
            $table->string('name_ar', 190);
            $table->string('name_en', 190)->nullable();
            $table->string('status', 30)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'name_ar'], 'supplier_group_company_name_ar_unique');
            $table->unique(['company_id', 'name_en'], 'supplier_group_company_name_en_unique');
            $table->index(['company_id', 'parent_id', 'status']);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->foreignId('supplier_group_id')->nullable()->after('status')->constrained('supplier_groups')->nullOnDelete();
            $table->index(['supplier_group_id', 'status'], 'suppliers_group_status_index');
        });

        Schema::create('supplier_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('role', 40);
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('whatsapp', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 30)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['supplier_id', 'role', 'status']);
            $table->index(['supplier_id', 'is_primary']);
        });

        Schema::create('supplier_communication_destinations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('purpose', 40);
            $table->string('channel', 30);
            $table->string('destination', 255);
            $table->string('label', 190)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 30)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['supplier_id', 'purpose', 'channel', 'status'], 'supplier_destinations_lookup_index');
            $table->index(['supplier_id', 'purpose', 'is_primary'], 'supplier_destinations_primary_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_communication_destinations');
        Schema::dropIfExists('supplier_contacts');
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropForeign(['supplier_group_id']);
            $table->dropIndex('suppliers_group_status_index');
            $table->dropColumn('supplier_group_id');
        });
        Schema::dropIfExists('supplier_groups');
    }
};

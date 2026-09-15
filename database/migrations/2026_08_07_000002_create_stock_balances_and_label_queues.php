<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_queues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_version_id')->constrained('price_versions')->restrictOnDelete();
            $table->foreignId('price_line_id')->constrained('price_lines')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('printer_configuration_id')->nullable()->constrained('printer_configurations')->nullOnDelete();
            $table->unsignedInteger('required_quantity')->default(0);
            $table->unsignedInteger('printed_quantity')->default(0);
            $table->string('status', 20)->default('pending');
            $table->string('template_name', 120)->nullable();
            $table->string('paper_size', 40)->nullable();
            $table->string('generation_key', 160)->unique();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['product_id', 'store_id', 'status']);
            $table->index(['price_version_id', 'price_line_id']);
        });

        Schema::create('label_print_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('label_queue_id')->constrained('label_queues')->restrictOnDelete();
            $table->foreignId('printer_configuration_id')->nullable()->constrained('printer_configurations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 20);
            $table->string('idempotency_key', 160)->unique();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('copies')->default(1);
            $table->text('reason')->nullable();
            $table->timestamp('printed_at');
            $table->timestamps();

            $table->index(['label_queue_id', 'printed_at']);
            $table->index(['printer_configuration_id', 'printed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_print_events');
        Schema::dropIfExists('label_queues');
    }
};

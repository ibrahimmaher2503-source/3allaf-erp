<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('customer_import_batches', function(Blueprint $t){$t->id();$t->foreignId('created_by')->constrained('users');$t->string('original_filename');$t->string('mode');$t->string('status');$t->json('headers')->nullable();$t->unsignedInteger('total_rows')->default(0);$t->unsignedInteger('valid_rows')->default(0);$t->unsignedInteger('invalid_rows')->default(0);$t->timestamp('approved_at')->nullable();$t->timestamps();}); Schema::create('customer_import_rows',function(Blueprint $t){$t->id();$t->foreignId('customer_import_batch_id')->constrained()->cascadeOnDelete();$t->unsignedInteger('row_number');$t->json('raw_data');$t->json('mapped_data')->nullable();$t->json('errors')->nullable();$t->string('status');$t->foreignId('customer_id')->nullable()->constrained();$t->timestamps();}); }
 public function down(): void { Schema::dropIfExists('customer_import_rows'); Schema::dropIfExists('customer_import_batches'); }
};

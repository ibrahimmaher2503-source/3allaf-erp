<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('supplier_import_batches', function(Blueprint $t){$t->id();$t->foreignId('created_by')->constrained('users');$t->string('original_filename');$t->string('storage_path');$t->string('sha256',64);$t->string('mode',30);$t->string('status',40);$t->json('headers');$t->json('column_mapping')->nullable();$t->unsignedInteger('total_rows')->default(0);$t->unsignedInteger('valid_rows')->default(0);$t->unsignedInteger('invalid_rows')->default(0);$t->timestamp('approved_at')->nullable();$t->timestamps();$t->index(['created_by','sha256']);});
 Schema::create('supplier_import_rows', function(Blueprint $t){$t->id();$t->foreignId('supplier_import_batch_id')->constrained()->cascadeOnDelete();$t->unsignedInteger('row_number');$t->json('raw_data');$t->json('mapped_data')->nullable();$t->json('errors')->nullable();$t->string('status',30);$t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();$t->timestamps();$t->index(['supplier_import_batch_id','status']);}); }
 public function down(): void { Schema::dropIfExists('supplier_import_rows'); Schema::dropIfExists('supplier_import_batches'); }
};

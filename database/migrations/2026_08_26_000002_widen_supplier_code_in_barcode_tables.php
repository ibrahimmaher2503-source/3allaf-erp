<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barcode_sequences', function (Blueprint $table): void {
            $table->string('supplier_code', 12)->change();
        });
        Schema::table('barcodes', function (Blueprint $table): void {
            $table->string('supplier_code', 12)->nullable()->change();
        });
    }

    public function down(): void
    {
        $hasLongSequenceCode = DB::table('barcode_sequences')->whereRaw('CHAR_LENGTH(supplier_code) > 4')->exists();
        $hasLongBarcodeCode = DB::table('barcodes')->whereNotNull('supplier_code')->whereRaw('CHAR_LENGTH(supplier_code) > 4')->exists();

        if ($hasLongSequenceCode || $hasLongBarcodeCode) {
            throw new \RuntimeException('Cannot narrow supplier_code to 4 characters while longer values exist.');
        }

        Schema::table('barcode_sequences', function (Blueprint $table): void {
            $table->string('supplier_code', 4)->change();
        });
        Schema::table('barcodes', function (Blueprint $table): void {
            $table->string('supplier_code', 4)->nullable()->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('code', 100);
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('document_type', 60);
            $table->string('paper_size', 30);
            $table->string('language', 10);
            $table->text('header_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'document_type', 'paper_size', 'language', 'status'], 'print_templates_lookup');
        });

        Schema::table('printer_configurations', function (Blueprint $table): void {
            $table->foreignId('print_template_id')->nullable()->after('template_name')->constrained('print_templates')->nullOnDelete();
        });

        $companyId = DB::table('companies')->orderBy('id')->value('id');
        if ($companyId !== null) {
            $now = now();
            foreach ([
                ['default_thermal', 'فاتورة مبيعات حرارية', 'Thermal sales invoice', 'sales_invoice', '80mm', 'bilingual'],
                ['gift_receipt', 'إيصال هدية حراري', 'Thermal gift receipt', 'gift_receipt', '80mm', 'bilingual'],
                ['return_receipt', 'إيصال مرتجع حراري', 'Thermal return receipt', 'sales_return', '80mm', 'bilingual'],
                ['shift_closing', 'إيصال إغلاق وردية', 'Shift closing receipt', 'shift_closing', '80mm', 'bilingual'],
                ['barcode_label', 'ملصق باركود 50 × 30 مم', '50 × 30 mm barcode label', 'barcode_label', 'label_50x30mm', 'bilingual'],
            ] as [$code, $ar, $en, $document, $paper, $language]) {
                DB::table('print_templates')->insertOrIgnore([
                    'company_id' => $companyId, 'code' => $code, 'name_ar' => $ar, 'name_en' => $en,
                    'document_type' => $document, 'paper_size' => $paper, 'language' => $language,
                    'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            DB::table('printer_configurations')->orderBy('id')->eachById(function (object $printer) use ($companyId): void {
                $templateId = DB::table('print_templates')->where('company_id', $companyId)->where('code', $printer->template_name)->value('id');
                if ($templateId !== null) {
                    DB::table('printer_configurations')->where('id', $printer->id)->update(['print_template_id' => $templateId]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('printer_configurations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('print_template_id');
        });
        Schema::dropIfExists('print_templates');
    }
};

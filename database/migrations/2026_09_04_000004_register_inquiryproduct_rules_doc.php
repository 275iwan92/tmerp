<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Daftarkan docs/inquiry_product_costing/inquiryproduct_rules.md ke
// sys_doc_reference -- permintaan user 2026-09-04: "buatkan md khusus utk
// inquiry product, aturannya, UI UX nya, tipenya". Sort_order 172, tepat
// setelah inquiryproduct_flow.md (171) krn topik & related_menu_code sama
// (SALES_INQ) -- geser wo_generation_delta yg sebelumnya di slot itu ke 173
// TIDAK dilakukan (biarkan collision minor, sort_order cuma urutan tampil,
// bukan unique key).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if ($sys->table('sys_doc_reference')->where('file_name', 'inquiryproduct_rules.md')->exists()) {
            return;
        }

        $sys->table('sys_doc_reference')->insert([
            'file_name' => 'inquiryproduct_rules.md',
            'title' => 'Inquiry Product — Aturan, UI/UX, Tipe Dokumen (living reference)',
            'category' => 'REFERENCE',
            'related_menu_code' => 'SALES_INQ',
            'description' => 'Melengkapi tier1/tier2/delta_revise/flow: UI/UX konkret 3 layar (BARU/REVISI/REKALKULASI), status implementasi Artwork Engine, keputusan ProCost sbg konsumen parameter Inquiry+ProDev.',
            'sort_order' => 172,
            'activate' => true,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'inquiryproduct_rules.md')->delete();
    }
};

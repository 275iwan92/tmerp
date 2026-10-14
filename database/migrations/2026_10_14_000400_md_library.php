<?php

/*
 * REMARK
 * Tujuan       : Perpustakaan dokumen usang: isi lengkap tiap .md yang diarsipkan disimpan di database dengan pencarian teks (FULLTEXT), supaya topiknya bisa dicari lagi saat kesulitan; baris daftar dokumennya (sys_doc_reference) yang berkasnya diarsipkan dinonaktifkan.
 * Sumber hulu  : php artisan md:library add <folder> (default C:\Users\iwanp\tmerp_unused; alasan dari MANIFEST.md di folder itu).
 * Input        : -
 * Output       : tm2sys.sys_md_library (file_name, original_path unik, title, summary, body, reason, archived_at, FULLTEXT title+summary+body); sys_doc_reference.activate = 0 untuk berkas yang diarsipkan 2026-10-14.
 * Dipakai oleh : App\Console\Commands\MdLibrary (add, search, show).
 * Status       : Jalankan di ddt* (--env=mockup); di tm2* saat rilis atas izin Jo.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Berkas docs/ yang diarsipkan 2026-10-14 (kelompok usang + Tier 4 v1 + 11 flow v1 + 4 buangan Jo 13-Okt + 15 standar UI v1 digabung ke ui_standard_v1.md).
    private const FILES = [
        'Blueprint_Arsitektur_dan_Enterprise_Resource_Planning__ERP__Industri_Offset_Printing_dan_Packaging.md',
        'System Architecture & Specification Blueprint Offset Printing and Packaging.md',
        'dispatch_tier4.md', 'procurement_tier4.md', 'telma_onboarding.md', 'md_inventory.md', 'md_pasti_usang.md',
        'ringkasanstatus.md', 'attendancepayroll_tier4.md', 'masterproduct_tier4.md', 'production_scheduling_tier4.md',
        'recruitment_tier4.md', 'flow_finance_accounting.md', 'flow_hrm.md', 'flow_inquiry_product.md', 'flow_inventory.md',
        'flow_master_data.md', 'flow_procurement.md', 'flow_production_scheduling.md', 'flow_recruitment_internal.md',
        'flow_recruitment_portal_public.md', 'flow_sales_order_delivery.md', 'flow_system_setup.md',
        'flowengine_tier4.md', 'customerinvoice_review.md', 'claude_md_archive_2026_10_07.md', 'help_writing_findings_2026_10_02.md',
        'componentstandard.md', 'FORM_UI_STANDARD.md', 'ganttboardstandard.md', 'griduistandard.md', 'kanbanboardstandard.md',
        'printnotastandard.md', 'reportstandard.md', 'sidebarmenustandard.md', 'spreadsheetgridstandard.md',
        'uistandard_component_catalog_index.md', 'uistandard_component_form.md', 'uistandard_component_gantt_kanban.md',
        'uistandard_component_grid.md', 'uistandard_component_other.md', 'uistandard_component_print.md',
    ];

    public function up(): void
    {
        $schema = Schema::connection('tm2sys');

        if (! $schema->hasTable('sys_md_library')) {
            $schema->create('sys_md_library', function (Blueprint $t) {
                $t->id('id_md_library');
                $t->string('file_name', 200);
                $t->string('original_path', 400)->unique();   // relatif terhadap folder arsip
                $t->string('title', 200)->nullable();
                $t->string('summary', 600)->nullable();
                $t->unsignedInteger('line_count')->default(0);
                $t->longText('body');
                $t->string('reason', 500)->nullable();        // alasan diarsipkan (dari MANIFEST.md)
                $t->date('archived_at')->nullable();
                $t->timestamps();
                $t->fullText(['title', 'summary', 'body']);
            });
        }

        // Tabel pendaftaran dokumen bisa belum ada di database tertentu; hanya dinonaktifkan, tidak dihapus.
        if ($schema->hasTable('sys_doc_reference')) {
            DB::connection('tm2sys')->table('sys_doc_reference')->whereIn('file_name', self::FILES)->update(['activate' => 0]);
        }
    }

    public function down(): void
    {
        Schema::connection('tm2sys')->dropIfExists('sys_md_library');

        if (Schema::connection('tm2sys')->hasTable('sys_doc_reference')) {
            DB::connection('tm2sys')->table('sys_doc_reference')->whereIn('file_name', self::FILES)->update(['activate' => 1]);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Konvensi penamaan file docs/*.md /.yaml (keputusan user 2026-08-26): nama
// file = label submodule, lowercase, tanpa spasi, digabung jadi satu kata,
// + "_tier<n>" / "_state" / "_rules" dst sebagai suffix jenis dokumen. Kolom
// & tabel ini menjadikan sys_app_menu (bukan tebakan Claude Code) sumber
// kebenaran nama file docs terkait tiap submodule.
//
// - sys_app_menu.doc_slug: HANYA diisi utk code yang punya SATU keluarga
//   dokumen jelas. File asli = {doc_slug}_tier{n}.md, {doc_slug}_state.yaml,
//   dst. Boleh sama antar >1 code kalau memang 1 dokumen membahas beberapa
//   submodule sekaligus (mis. Presensi & Payroll digabung satu dok).
// - sys_doc_reference: tabel baru utk dokumen yang BUKAN 1:1 milik satu menu
//   item (aturan global, standar UI, engine lintas-modul, referensi teknis)
//   -- 1 baris = 1 file fisik di /docs.
//
// Modul HR juga belum lengkap di sys_app_menu (baru HRM_EMPLOYEE/HRM_ROSTER)
// padahal scope Finance & HR/Payroll/Recruitment sudah dibuka user
// (DEC-SCOPE-OPEN 2026-08-26, lihat globalstarthere.md) -- tambah 4 kode
// baru sekalian: HRM_PAYROLL, HRM_ATTENDANCE, HRM_RECRUITMENT (belum
// dibangun, NOT_BUILT) dan HRM_SELFSERVICE (portal karyawan SUDAH live via
// route self-service.index, tapi belum resmi ada di menu tree -- cuma link
// terpisah di main-menu.blade.php, lihat migration ini + itemRoutes()).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        Schema::connection('tm2sys')->table('sys_app_menu', function (Blueprint $table) {
            $table->string('doc_slug', 80)->nullable()
                ->comment('Basis nama file docs/*.md /.yaml terkait, TANPA _tierN & ekstensi. File aktual = {doc_slug}_tier{n}.md, {doc_slug}_state.yaml, dst. Bisa sama antar >1 code (1 dok membahas >1 submodule).');
        });

        Schema::connection('tm2sys')->create('sys_doc_reference', function (Blueprint $table) {
            $table->id();
            $table->string('file_name', 120)->comment('Nama file fisik lengkap di /docs, mis. globalrules.yaml');
            $table->string('title', 150);
            $table->string('category', 40)->comment('GLOBAL_RULE / STANDARD / ARCHITECTURE / ENGINE / REFERENCE / SECURITY');
            $table->string('related_menu_code', 30)->nullable()->comment('Referensi lunak ke sys_app_menu.code kalau ada kaitan tak langsung (BUKAN FK -- boleh NULL)');
            $table->string('description', 255)->nullable();
            $table->smallInteger('sort_order')->default(999);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        // --- kode HR baru (scope sudah dibuka -- DEC-SCOPE-OPEN 2026-08-26) ---
        $hrmMenuId = $sys->table('sys_app_menu')->where('code', 'HRM')->value('id');
        $now = now();

        if ($hrmMenuId) {
            $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

            $newHrmItems = [
                ['code' => 'HRM_ATTENDANCE', 'label' => 'Presensi', 'sort' => 30, 'status' => 'NOT_BUILT'],
                ['code' => 'HRM_PAYROLL', 'label' => 'Payroll', 'sort' => 40, 'status' => 'NOT_BUILT'],
                ['code' => 'HRM_RECRUITMENT', 'label' => 'Recruitment', 'sort' => 50, 'status' => 'NOT_BUILT'],
                ['code' => 'HRM_SELFSERVICE', 'label' => 'Self-Service Karyawan', 'sort' => 60, 'status' => 'READY_FOR_QC'],
            ];

            foreach ($newHrmItems as $item) {
                if ($sys->table('sys_app_menu')->where('code', $item['code'])->exists()) {
                    continue;
                }

                $sys->table('sys_app_menu')->insert([
                    'id' => $nextId++,
                    'parent_id' => $hrmMenuId,
                    'code' => $item['code'],
                    'label' => $item['label'],
                    'menu_type' => 'item',
                    'object_type' => 'form',
                    'target_kind' => 'app',
                    'sort_order' => $item['sort'],
                    'is_active' => true,
                    'status_code' => $item['status'],
                    'status_updated_by' => $item['status'] === 'READY_FOR_QC' ? 'claude_code' : null,
                    'status_updated_at' => $item['status'] === 'READY_FOR_QC' ? $now : null,
                    'created_at' => $now,
                ]);
            }
        }

        // --- doc_slug: code dengan 1 keluarga dokumen jelas ---
        $docSlugs = [
            'SALES' => 'sales',
            'SALES_INQ' => 'inquiryproduct',
            'SALES_COST' => 'productcosting',
            'MD_PROD' => 'masterproduct',
            'MD_CUST' => 'customer',
            'PROC' => 'procurement',
            'PROC_RCV' => 'purchasereceive',
            'INV' => 'inventory',
            'FINACC' => 'financeaccounting',
            'FIN_DP' => 'downpayment',
            'HRM_EMPLOYEE' => 'masteremployee',
            'HRM_ATTENDANCE' => 'attendancepayroll',
            'HRM_PAYROLL' => 'attendancepayroll',
            'HRM_SELFSERVICE' => 'selfservice',
        ];

        foreach ($docSlugs as $code => $slug) {
            $sys->table('sys_app_menu')->where('code', $code)->update(['doc_slug' => $slug]);
        }

        // --- dokumen non-menu ("aturan main" / lintas-modul) ---
        $docs = [
            ['file_name' => 'globalrules.yaml', 'title' => 'Aturan Universal Semua Modul', 'category' => 'GLOBAL_RULE', 'related_menu_code' => null, 'sort_order' => 10],
            ['file_name' => 'globalstarthere.md', 'title' => 'Pintu Masuk Dokumentasi (Start Here)', 'category' => 'GLOBAL_RULE', 'related_menu_code' => null, 'sort_order' => 20],
            ['file_name' => 'globalarchitecture_tier0.md', 'title' => 'Rancangan Global Arsitektur (Tier 0)', 'category' => 'ARCHITECTURE', 'related_menu_code' => null, 'sort_order' => 30],
            ['file_name' => 'griduistandard.md', 'title' => 'Standar Tampilan Grid (Master & Worklist)', 'category' => 'STANDARD', 'related_menu_code' => null, 'sort_order' => 40],
            ['file_name' => 'sidebarmenustandard.md', 'title' => 'Standar Struktur Menu Sidebar', 'category' => 'STANDARD', 'related_menu_code' => null, 'sort_order' => 50],
            ['file_name' => 'prefixlist.md', 'title' => 'Daftar Prefix Tabel (Naming Convention)', 'category' => 'STANDARD', 'related_menu_code' => null, 'sort_order' => 60],
            ['file_name' => 'queue.md', 'title' => 'Antrean Kerja Claude Code', 'category' => 'GLOBAL_RULE', 'related_menu_code' => null, 'sort_order' => 70],
            ['file_name' => 'checklistmockuperp.md', 'title' => 'Master Checklist Mockup ERP', 'category' => 'GLOBAL_RULE', 'related_menu_code' => null, 'sort_order' => 80],
            ['file_name' => 'flowengine_tier1.md', 'title' => 'Flow Engine — Business Concept & Scope (Tier 1)', 'category' => 'ENGINE', 'related_menu_code' => null, 'sort_order' => 90],
            ['file_name' => 'flowengine_tier2.md', 'title' => 'Flow Engine — Business Logic & Rules (Tier 2)', 'category' => 'ENGINE', 'related_menu_code' => null, 'sort_order' => 91],
            ['file_name' => 'flowengine_tier3.md', 'title' => 'Flow Engine — Database & Data Process Flow (Tier 3)', 'category' => 'ENGINE', 'related_menu_code' => null, 'sort_order' => 92],
            ['file_name' => 'flowengine_tier4.md', 'title' => 'Flow Engine — UI/UX & Interaction Design (Tier 4)', 'category' => 'ENGINE', 'related_menu_code' => null, 'sort_order' => 93],
            ['file_name' => 'openingapp_tier1.md', 'title' => 'Opening App — System Flow & Process Matrix (Tier 1)', 'category' => 'ENGINE', 'related_menu_code' => 'SETUP', 'sort_order' => 100],
            ['file_name' => 'openingapp_tier2.md', 'title' => 'Opening App — Business Logic & Rules (Tier 2)', 'category' => 'ENGINE', 'related_menu_code' => 'SETUP', 'sort_order' => 101],
            ['file_name' => 'openingapp_tier3.md', 'title' => 'Opening App — DDL, Relasi & Seed (Tier 3)', 'category' => 'ENGINE', 'related_menu_code' => 'SETUP', 'sort_order' => 102],
            ['file_name' => 'openingapp_tier4.md', 'title' => 'Opening App — Mockup & UI/UX Spec (Tier 4)', 'category' => 'ENGINE', 'related_menu_code' => 'SETUP', 'sort_order' => 103],
            ['file_name' => 'openingapp_instruksi_layout.md', 'title' => 'Instruksi Layout Opening App', 'category' => 'ENGINE', 'related_menu_code' => 'SETUP', 'sort_order' => 104],
            ['file_name' => 'security_brief.md', 'title' => 'Brief Modul Security: Login → Main Menu', 'category' => 'SECURITY', 'related_menu_code' => 'SETUP', 'sort_order' => 110],
            ['file_name' => 'security_defaultpassword_spec.md', 'title' => 'Spec Default Password & Must Change Password', 'category' => 'SECURITY', 'related_menu_code' => 'SETUP', 'sort_order' => 111],
            ['file_name' => 'geomaster_ringkasan.md', 'title' => 'Ringkasan Domain Master Wilayah (geo_)', 'category' => 'REFERENCE', 'related_menu_code' => 'MDATA', 'sort_order' => 120],
            ['file_name' => 'productionprocess_lampiran_a.md', 'title' => 'Urutan Proses Standar Offset & Packaging', 'category' => 'REFERENCE', 'related_menu_code' => 'PROD', 'sort_order' => 130],
            ['file_name' => 'itemmaster_delta.md', 'title' => 'Delta Item Master — Field Fisik & Linkage Akuntansi', 'category' => 'REFERENCE', 'related_menu_code' => 'MD_RAW', 'sort_order' => 140],
            ['file_name' => 'purchasereceive_delta_multipo.md', 'title' => 'Delta Purchase Receive — Multi-PO per SPB', 'category' => 'REFERENCE', 'related_menu_code' => 'PROC_RCV', 'sort_order' => 150],
            ['file_name' => 'customer_fix_nav_address.md', 'title' => 'Fix Master Customer — Navigasi Tab & Alamat Wilayah', 'category' => 'REFERENCE', 'related_menu_code' => 'MD_CUST', 'sort_order' => 160],
            ['file_name' => 'inquiryproduct_delta_revise.md', 'title' => 'Delta Revise Scope & Relasi CO Snapshot', 'category' => 'REFERENCE', 'related_menu_code' => 'SALES_INQ', 'sort_order' => 170],
            ['file_name' => 'inquiryproduct_flow.md', 'title' => 'Siklus Inquiry → PO → Master Product', 'category' => 'REFERENCE', 'related_menu_code' => 'SALES_INQ', 'sort_order' => 171],
        ];

        foreach ($docs as $doc) {
            $sys->table('sys_doc_reference')->insert($doc + ['is_active' => true, 'created_at' => $now]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        Schema::connection('tm2sys')->dropIfExists('sys_doc_reference');

        $sys->table('sys_app_menu')->whereIn('code', [
            'HRM_ATTENDANCE', 'HRM_PAYROLL', 'HRM_RECRUITMENT', 'HRM_SELFSERVICE',
        ])->delete();

        Schema::connection('tm2sys')->table('sys_app_menu', function (Blueprint $table) {
            $table->dropColumn('doc_slug');
        });
    }
};

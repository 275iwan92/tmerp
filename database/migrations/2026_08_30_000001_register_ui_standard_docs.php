<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Mendaftarkan 3 dokumen standar UI/UX ke sys_doc_reference (2026-08-30):
// - kanbanboardstandard.md (baru, ekstraksi Pipeline Board + Monitoring Dashboard)
// - admindashboardstandard.md (baru, standar navigasi panel Filament /admin)
// - uistandardindex.md (baru, index pintu masuk semua *standard.md UI/UX)
// PLUS mendaftarkan FORM_UI_STANDARD.md yang TERNYATA belum pernah didaftarkan
// (ditemukan sesi ini saat audit sebelum bikin index) meski file-nya sudah
// lama ada & dipakai (lihat cross-reference di ganttboardstandard.md/
// printnotastandard.md) -- gap murni administratif, bukan perubahan isi file.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $rows = [
            [
                'file_name' => 'FORM_UI_STANDARD.md',
                'title' => 'Standar Tampilan Form/Entry (header, tombol Back)',
                'category' => 'STANDARD',
                'related_menu_code' => null,
                'description' => 'Header navy + tombol Back pill untuk semua layar form/entry (CO Entry, PR/PO/PV, Master Warehouse, dst). Sudah lama dipakai, baru terdaftar di sini.',
                'sort_order' => 41,
                'activate' => true,
                'created_at' => now(),
            ],
            [
                'file_name' => 'kanbanboardstandard.md',
                'title' => 'Standar Kanban Board (Pola Reusable)',
                'category' => 'STANDARD',
                'related_menu_code' => null,
                'description' => 'Pola drag-drop kartu antar kolom status/kategori (x-data draggedId, dragover.prevent, validasi server ketat) diekstrak dari Pipeline Board + Monitoring Dashboard.',
                'sort_order' => 47,
                'activate' => true,
                'created_at' => now(),
            ],
            [
                'file_name' => 'admindashboardstandard.md',
                'title' => 'Standar Admin Dashboard (Panel Filament /admin)',
                'category' => 'STANDARD',
                'related_menu_code' => null,
                'description' => 'Pemetaan Module->Cluster/Group->navigationGroup/Item->Resource, auth bridge login/pswd, ->topNavigation(), dan cara aman bikin Resource baru tanpa crash generator.',
                'sort_order' => 48,
                'activate' => true,
                'created_at' => now(),
            ],
            [
                'file_name' => 'uistandardindex.md',
                'title' => 'Index Standar UI/UX (Pintu Masuk)',
                'category' => 'STANDARD',
                'related_menu_code' => null,
                'description' => 'Peta semua dokumen *standard.md UI/UX (grid/form/nota/gantt/kanban/admin dashboard) + kapan masing-masing wajib dibaca.',
                'sort_order' => 39,
                'activate' => true,
                'created_at' => now(),
            ],
        ];

        foreach ($rows as $row) {
            if (! $sys->table('sys_doc_reference')->where('file_name', $row['file_name'])->exists()) {
                $sys->table('sys_doc_reference')->insert($row);
            }
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->whereIn('file_name', [
            'FORM_UI_STANDARD.md',
            'kanbanboardstandard.md',
            'admindashboardstandard.md',
            'uistandardindex.md',
        ])->delete();
    }
};

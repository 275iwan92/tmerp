<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menutup celah akses pada Laporan Manajemen: modulnya sudah punya menu
// (sys_app_menu code=MGMT) tapi TIDAK PERNAH didaftarkan ke sec_module --
// akibatnya EnsureModuleAccess tidak bisa mengecek apa pun (modul tak
// dikenal) dan 4 route /laporan/* ditemukan TANPA middleware module: sama
// sekali, bisa dibuka siapa saja yang sudah login.
//
// min_mgmt_level = 5 (Direksi) -- dashboard ini eksplisit utk Top Management,
// levelnya disamakan dgn FINACC (yg sudah LOCKED Direksi-only sebelumnya)
// krn Laporan Manajemen menggabungkan omset+pembelian+AP lintas modul, tidak
// kalah sensitif dari laporan finance satu modul. HPP/margin TIDAK ditarik
// layar ini (sudah diverifikasi), tapi nilai transaksi gabungan tetap wajar
// dibatasi ke level tertinggi.
//
// Permission HANYA diberikan ke grup Direksi (bukan Manager Operasional/
// Sales/Purchasing/Finance) -- ini keputusan otorisasi bisnis ("siapa boleh
// lihat data gabungan lintas modul"), bukan hal teknis yang pantas diputuskan
// sendiri. Kalau Manager tertentu perlu akses, cukup tambah 1 baris
// sec_group_permission, tidak perlu ubah kode.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $now = now();

        $moduleId = $sys->table('sec_module')->insertGetId([
            'parent_id' => null,
            'module_code' => 'MGMT',
            'module_name' => 'Laporan Manajemen',
            'icon' => 'fa-solid fa-chart-line',
            'route_prefix' => 'laporan',
            'min_mgmt_level' => 5,
            'is_reportable' => 1,
            'sort_order' => 15,
            'activate' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => 'migration',
        ], 'id_module');

        $direksi = $sys->table('sec_group')->where('group_name', 'Direksi')->value('id_group');

        if ($direksi) {
            $sys->table('sec_group_permission')->insert([
                'group_id' => $direksi,
                'module_id' => $moduleId,
                'can_access' => 1,
                'can_input' => 0, // laporan read-only, tidak ada create/edit
                'can_approve' => 0, // tidak ada alur approval di layar ini
                'can_export' => 1, // wajar Direksi bisa ekspor utk rapat/presentasi
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 'migration',
            ]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $moduleId = $sys->table('sec_module')->where('module_code', 'MGMT')->value('id_module');

        if ($moduleId) {
            $sys->table('sec_group_permission')->where('module_id', $moduleId)->delete();
            $sys->table('sec_module')->where('id_module', $moduleId)->delete();
        }
    }
};

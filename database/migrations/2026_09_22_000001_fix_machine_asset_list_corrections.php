<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Koreksi user atas daftar aset mesin 2026-09-21:
// 1. Laminator Huawei (LAM-02) sudah TERJUAL -> dihapus (bukan dinonaktifkan;
//    user eksplisit "dihapus tidak papa"). Tidak ada routing/rate/WO yang
//    merujuknya; 1 work center + 48 baris roster di-NULL-kan machine_id-nya
//    (stasiun LAMINATING tetap ada lewat work_center_id, mesinnya saja yang
//    hilang). Penomoran LAM lain TIDAK digeser -- gap disengaja supaya kode
//    yang sudah dicatat orang tidak berubah.
// 2. Folder Gluer ikut nomor mesin di lantai produksi: Daxiang = FGL-03,
//    YDM = FGL-04 (bukan FGL-01/FGL-02 hasil urut tanggal datang).
return new class extends Migration
{
    public function up(): void
    {
        $mst = DB::connection('tm2mst');

        $lam02 = $mst->table('prod_machine')->where('machine_code', 'LAM-02')->value('machine_id');
        if ($lam02) {
            $mst->table('emp_work_center')->where('machine_id', $lam02)->update(['machine_id' => null]);
            $mst->table('emp_shift_roster')->where('machine_id', $lam02)->update(['machine_id' => null]);
            $mst->table('prod_machine')->where('machine_id', $lam02)->delete();
        }

        // Tukar lewat kode sementara: FGL-01 -> FGL-03 dan FGL-02 -> FGL-04 aman
        // dilakukan berurutan (tujuannya belum dipakai), tapi machine_code UNIQUE
        // jadi urutannya tetap dijaga eksplisit.
        foreach (['FGL-01' => 'FGL-03', 'FGL-02' => 'FGL-04'] as $from => $to) {
            $mst->table('prod_machine')->where('machine_code', $from)->update([
                'machine_code' => $to,
                'updated_at' => now(),
                'updated_by' => 'system:asset-list-fix',
            ]);
        }
    }

    // Mesin yang sudah terjual tidak dibuat ulang; hanya kode Folder Gluer
    // dikembalikan supaya rollback tidak menyisakan kode yang tidak dikenal.
    public function down(): void
    {
        $mst = DB::connection('tm2mst');

        foreach (['FGL-03' => 'FGL-01', 'FGL-04' => 'FGL-02'] as $from => $to) {
            $mst->table('prod_machine')->where('machine_code', $from)->update(['machine_code' => $to]);
        }
    }
};

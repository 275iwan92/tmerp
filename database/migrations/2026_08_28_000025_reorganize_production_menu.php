<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Permintaan user 2026-08-28: tata ulang menu Production.
// - Master: tambah 3 item baru (Master Proses/Mesin/Kru) setelah Master
//   Card & Layout yang sudah ada (keduanya TETAP, bukan roadmap sembarangan
//   -- Master Card = bagian authority chain LOCKED, DB-nya sudah siap).
//   Master Mesin (MD_MACHINE) dipindah PENUH dari menu Master Data (MDATA)
//   ke sini -- dihapus dari MDATA, bukan reuse ganda.
// - Planning: isi tetap sama, cuma sekarang cuma diapit 2 grup lain
//   (Support & Output) alih-alih 3 (Master/Planning/Maintenance/Output).
// - Maintenance group (PROD_MTN) di-relabel "Support" (code -> PROD_SUPPORT,
//   posisi/sort_order TETAP di bawah Planning) dan ditambah 1 item baru
//   "Jadwal Shift" yang reuse layar HRM Shift Roster (HRM_ROSTER, route
//   hrm.shift-roster) -- tetap juga muncul di menu HRM, bukan dipindah.
//   2 item lama (Schedule/Preventive Maintenance) TETAP ada, cuma
//   sort_order digeser ke bawah Jadwal Shift.
// - Output: tidak berubah.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        // --- Master: tambah Master Proses & Master Kru (placeholder, belum digarap) ---
        $sys->table('sys_app_menu')->insert([
            [
                'id' => $nextId,
                'parent_id' => 410, // PROD_MASTER
                'code' => 'PROD_MST_PROCESS',
                'label' => 'Master Proses',
                'menu_type' => 'item',
                'object_type' => 'grid',
                'target_kind' => 'app',
                'sort_order' => 30,
                'activate' => true,
                'status_code' => 'NOT_BUILT',
                'created_at' => now(),
            ],
            [
                'id' => $nextId + 1,
                'parent_id' => 410, // PROD_MASTER
                'code' => 'PROD_MST_CREW',
                'label' => 'Master Kru',
                'menu_type' => 'item',
                'object_type' => 'grid',
                'target_kind' => 'app',
                'sort_order' => 50,
                'activate' => true,
                'status_code' => 'NOT_BUILT',
                'created_at' => now(),
            ],
            [
                'id' => $nextId + 2,
                'parent_id' => 430, // PROD_MTN (relabel jadi Support di bawah)
                'code' => 'PROD_SHIFT_ROSTER',
                'label' => 'Jadwal Shift',
                'menu_type' => 'item',
                'object_type' => 'grid',
                'target_kind' => 'app',
                'sort_order' => 10,
                'activate' => true,
                'status_code' => 'READY_FOR_QC',
                'created_at' => now(),
            ],
        ]);

        // --- Master Mesin: pindah penuh dari Master Data (MDATA) ke Production > Master ---
        $sys->table('sys_app_menu')->where('code', 'MD_MACHINE')->update([
            'parent_id' => 410, // PROD_MASTER
            'sort_order' => 40,
        ]);

        // --- Maintenance -> Support (posisi tetap, di bawah Planning) ---
        $sys->table('sys_app_menu')->where('code', 'PROD_MTN')->update([
            'code' => 'PROD_SUPPORT',
            'label' => 'Support',
        ]);
        $sys->table('sys_app_menu')->where('code', 'PROD_MTN_SCH')->update(['sort_order' => 20]);
        $sys->table('sys_app_menu')->where('code', 'PROD_MTN_PREV')->update(['sort_order' => 30]);
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->where('code', 'PROD_SHIFT_ROSTER')->delete();
        $sys->table('sys_app_menu')->where('code', 'PROD_MST_PROCESS')->delete();
        $sys->table('sys_app_menu')->where('code', 'PROD_MST_CREW')->delete();

        $sys->table('sys_app_menu')->where('code', 'MD_MACHINE')->update([
            'parent_id' => 5, // MDATA
            'sort_order' => 70,
        ]);

        $sys->table('sys_app_menu')->where('code', 'PROD_SUPPORT')->update([
            'code' => 'PROD_MTN',
            'label' => 'Maintenance',
        ]);
        $sys->table('sys_app_menu')->where('code', 'PROD_MTN_SCH')->update(['sort_order' => 10]);
        $sys->table('sys_app_menu')->where('code', 'PROD_MTN_PREV')->update(['sort_order' => 20]);
    }
};

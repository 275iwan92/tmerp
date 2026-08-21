<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// INV_TIER_3.md §10-11 -- seed Flow Engine (IN/OUT/AD/OP/RTP) + registrasi menu.
// Prasyarat INV-FLOW-POSITION: "Kepala Gudang" belum ada di org_position (baru
// ada HELPER GUDANG LV1-3 bucket legacy) -- dibuat di sini, level SPV di bawah
// Manajer Operasional (sudah ada, position_id dipakai Procurement). Sesuai
// AskUserQuestion sebelumnya: threshold approval TINGGI (5jt/25jt) dan
// backdate 7 hari dipakai apa adanya dari sys_constant yang sudah diseed
// migration sebelumnya -- bukan keputusan baru di sini.
return new class extends Migration
{
    public function up(): void
    {
        $mst = DB::connection('tm2mst');
        $sys = DB::connection('tm2sys');

        $gudangDeptId = $mst->table('org_department')->where('department_name', 'Gudang')->value('department_id');
        $spvLevelId = $mst->table('org_level')->where('level_code', 'SPV')->value('level_id');

        $kepalaGudangId = $mst->table('org_position')->where('position_code', 'KA-GUDANG')->value('position_id');
        if (! $kepalaGudangId && $gudangDeptId && $spvLevelId) {
            $kepalaGudangId = $mst->table('org_position')->insertGetId([
                'department_id' => $gudangDeptId,
                'level_id' => $spvLevelId,
                'position_code' => 'KA-GUDANG',
                'position_name' => 'Kepala Gudang',
                'salary_hidden_from_hr' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'system',
                'updated_by' => 'system',
            ]);
        }

        $manajerOpsId = $mst->table('org_position')->where('position_code', 'MGR-OPS')->value('position_id');
        $direksiId = $mst->table('org_position')->where('position_code', 'DIREKSI-OPS')->value('position_id');

        // -- flow_config --
        $configs = [
            ['module_code' => 'IN', 'division_id' => null, 'total_step' => 2, 'notes' => 'Stock In -- otomatis (PV/WO) & manual berpersetujuan'],
            ['module_code' => 'OUT', 'division_id' => null, 'total_step' => 1, 'notes' => 'Stock Out ke Work Order -- konfirmasi gudang'],
            ['module_code' => 'AD', 'division_id' => null, 'total_step' => 3, 'notes' => 'Stock Adjustment -- berjenjang menurut nilai'],
            ['module_code' => 'OP', 'division_id' => null, 'total_step' => 3, 'notes' => 'Stock Opname -- berjenjang menurut nilai'],
            ['module_code' => 'RTP', 'division_id' => null, 'total_step' => 1, 'notes' => 'Return from Production -- konfirmasi gudang'],
        ];

        $configIds = [];
        foreach ($configs as $c) {
            $id = $mst->table('flow_config')->where('module_code', $c['module_code'])->whereNull('division_id')->value('flow_config_id');
            if (! $id) {
                $id = $mst->table('flow_config')->insertGetId($c + [
                    'is_active' => true, 'created_by' => 'system', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $configIds[$c['module_code']] = $id;
        }

        // -- flow_config_step -- (AUTO steps: type AUTO, position_id null, sistem lompat langsung)
        $steps = [
            ['module_code' => 'IN', 'step_no' => 1, 'status_from' => 'DRAFT', 'status_to' => 'POSTED', 'step_type' => 'AUTO', 'position_id' => null, 'min_amount' => null, 'max_amount' => null, 'label' => 'Membuat'],
            ['module_code' => 'IN', 'step_no' => 2, 'status_from' => 'SUBMITTED', 'status_to' => 'POSTED', 'step_type' => 'APPROVAL', 'position_id' => $kepalaGudangId, 'min_amount' => 0, 'max_amount' => null, 'label' => 'Menyetujui'],
            ['module_code' => 'OUT', 'step_no' => 1, 'status_from' => 'DRAFT', 'status_to' => 'POSTED', 'step_type' => 'AUTO', 'position_id' => null, 'min_amount' => null, 'max_amount' => null, 'label' => 'Menyerahkan'],
            ['module_code' => 'RTP', 'step_no' => 1, 'status_from' => 'DRAFT', 'status_to' => 'POSTED', 'step_type' => 'AUTO', 'position_id' => null, 'min_amount' => null, 'max_amount' => null, 'label' => 'Menerima'],
            ['module_code' => 'AD', 'step_no' => 1, 'status_from' => 'SUBMITTED', 'status_to' => 'APPROVED_L1', 'step_type' => 'APPROVAL', 'position_id' => $kepalaGudangId, 'min_amount' => 0, 'max_amount' => 5000000, 'label' => 'Memeriksa'],
            ['module_code' => 'AD', 'step_no' => 2, 'status_from' => 'APPROVED_L1', 'status_to' => 'APPROVED_L2', 'step_type' => 'APPROVAL', 'position_id' => $manajerOpsId, 'min_amount' => 5000000, 'max_amount' => 25000000, 'label' => 'Mengetahui'],
            ['module_code' => 'AD', 'step_no' => 3, 'status_from' => 'APPROVED_L2', 'status_to' => 'POSTED', 'step_type' => 'APPROVAL', 'position_id' => $direksiId, 'min_amount' => 25000000, 'max_amount' => null, 'label' => 'Menyetujui'],
            ['module_code' => 'OP', 'step_no' => 1, 'status_from' => 'SUBMITTED', 'status_to' => 'APPROVED_L1', 'step_type' => 'APPROVAL', 'position_id' => $kepalaGudangId, 'min_amount' => 0, 'max_amount' => 5000000, 'label' => 'Memeriksa'],
            ['module_code' => 'OP', 'step_no' => 2, 'status_from' => 'APPROVED_L1', 'status_to' => 'APPROVED_L2', 'step_type' => 'APPROVAL', 'position_id' => $manajerOpsId, 'min_amount' => 5000000, 'max_amount' => 25000000, 'label' => 'Mengetahui'],
            ['module_code' => 'OP', 'step_no' => 3, 'status_from' => 'APPROVED_L2', 'status_to' => 'POSTED', 'step_type' => 'APPROVAL', 'position_id' => $direksiId, 'min_amount' => 25000000, 'max_amount' => null, 'label' => 'Menyetujui'],
        ];

        foreach ($steps as $s) {
            $configId = $configIds[$s['module_code']];
            $exists = $mst->table('flow_config_step')->where('flow_config_id', $configId)->where('step_no', $s['step_no'])->exists();
            if ($exists) {
                continue;
            }
            $mst->table('flow_config_step')->insert([
                'flow_config_id' => $configId,
                'step_no' => $s['step_no'],
                'status_from' => $s['status_from'],
                'status_to' => $s['status_to'],
                'step_type' => $s['step_type'],
                'position_id' => $s['position_id'],
                'min_amount' => $s['min_amount'],
                'max_amount' => $s['max_amount'],
                'label' => $s['label'],
                'status_to_reject' => $s['step_type'] === 'APPROVAL' ? 'REVISION_NEEDED' : null,
                'created_by' => 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // -- §11: registrasi menu --
        // KOREKSI: draf pertama migration ini menaruh item baru di bawah modul
        // MDATA (asumsi keliru). Ternyata modul INV (id=3) + grup INV_TRX
        // (id=310, "Transaksi") & INV_RPT (id=380, "Stok & Laporan") SUDAH
        // di-pre-seed sejak awal (menu ScriptCase lama) -- persis sesuai
        // parent_id 310/380 yang dipakai teks asli Tier 3 §11. INV_CARD (383)
        // juga sudah ada, dipakai apa adanya, tidak dibuat ulang. INV_ONHAND
        // tidak dibuat -- Tier 3 §11 sendiri tidak mendaftarkannya (cuma
        // disebut di route map Tier 4 §1.1), INV_RAW(381)/INV_FG(382) yang
        // sudah ada kemungkinan besar perannya, keputusan menyusul saat Tier 4
        // digarap. required_object dibiarkan NULL, sama seperti seluruh item
        // lain di modul ini (MD_RAW/MD_WH/PROC_RCV) -- gate akses per-item
        // bukan lewat kolom ini (cuma dipakai di level module).
        $trxGroupId = $sys->table('sys_app_menu')->where('code', 'INV_TRX')->value('id');
        $rptGroupId = $sys->table('sys_app_menu')->where('code', 'INV_RPT')->value('id');

        $menuItems = [
            ['code' => 'INV_IN', 'label' => 'Stock In', 'parent' => $trxGroupId, 'sort' => 5],
            ['code' => 'INV_OUT', 'label' => 'Stock Out', 'parent' => $trxGroupId, 'sort' => 6],
            ['code' => 'INV_RTP', 'label' => 'Return from Production', 'parent' => $trxGroupId, 'sort' => 7],
            ['code' => 'INV_ADJ2', 'label' => 'Stock Adjustment & Opname', 'parent' => $trxGroupId, 'sort' => 8],
            ['code' => 'INV_APRV', 'label' => 'Persetujuan Penyesuaian', 'parent' => $trxGroupId, 'sort' => 9],
            ['code' => 'INV_PERIOD', 'label' => 'Periode Stok', 'parent' => $rptGroupId, 'sort' => 50],
        ];

        // sys_app_menu.id BUKAN auto_increment (INT PK polos, diverifikasi langsung) --
        // dialokasikan manual berurutan dari MAX(id) yang ada.
        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        foreach ($menuItems as $m) {
            $exists = $sys->table('sys_app_menu')->where('code', $m['code'])->exists();
            if ($exists || ! $m['parent']) {
                continue;
            }
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId,
                'parent_id' => $m['parent'],
                'code' => $m['code'],
                'label' => $m['label'],
                'menu_type' => 'item',
                'sort_order' => $m['sort'],
                'is_active' => true,
                'status_code' => 'NOT_BUILT',
                'required_object' => null,
            ]);
            $nextId++;
        }

        // Menu ScriptCase lama INV_MOVE/INV_ADJ (form_stock_move.php/form_stock_adj.php)
        // dipensiunkan sesuai instruksi eksplisit Tier 3 §11 -- diganti INV_IN/INV_OUT
        // (mutasi terpisah per arah) dan INV_ADJ2 (Adjustment & Opname).
        $sys->table('sys_app_menu')->whereIn('code', ['INV_MOVE', 'INV_ADJ'])->update(['is_active' => false]);
    }

    public function down(): void
    {
        // Seed data -- tidak reversibel otomatis, hapus manual bila perlu.
    }
};

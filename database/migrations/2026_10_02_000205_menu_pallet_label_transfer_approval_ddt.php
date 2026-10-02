<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Putaran 2 Inventory & Warehouse (user 2026-10-02), HANYA database dummy ddt* (menunggu user menilai di mockup.test):
//   menu  Inventory > Label & QR Code > Print Pallet Label (QR)   -> layar label-pallet
//   menu  Inventory > Warehouse > Stock Transfer Approval Center  -> layar approval-tf
//   Setting Approval modul TF (Stock Transfer) dengan satu level (Operational Manager), TIDAK AKTIF: "approval di-skip dulu,
//   fasilitasnya dibuat dan bisa diaktifkan kapan saja" (Setup > Setting Approval > TF > Activate).
return new class extends Migration
{
    private const BY = 'claude (v2 build)';

    private function isDummy(): bool
    {
        return str_starts_with(DB::connection('tm2sys')->getDatabaseName(), 'ddt');
    }

    public function up(): void
    {
        if (! $this->isDummy()) {
            return;
        }
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $menu()->where('code', 'INV_LBL_ITEM1')->update(['target' => 'v2:label-pallet', 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY, 'status_updated_at' => now()]);
        $wh = $menu()->where('code', 'INV_WH')->first();
        if ($wh && ! $menu()->where('code', 'INV_WH_TF_APPR')->exists()) {
            $menu()->insert(['parent_id' => $wh->id, 'code' => 'INV_WH_TF_APPR', 'label' => 'Stock Transfer Approval Center', 'icon' => 'fa-solid fa-stamp', 'menu_type' => 'item',
                'object_type' => 'form', 'target' => 'v2:approval-tf', 'sort_order' => 30, 'activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY,
                'status_updated_at' => now(), 'created_at' => now(), 'doc_slug' => 'warehouselayout']);
        }

        $mst = DB::connection('tm2mst');
        if (! $mst->table('flow_config')->where('module_code', 'TF')->exists()) {
            // Pemeriksa = jabatan yang sama dengan level 1 Stock Adjustment (jabatan itu ada pemegangnya, jadi begitu diaktifkan
            // langsung jalan); "Kepala Gudang" belum punya pemegang. User mengganti jabatannya di Setting Approval.
            $head = $mst->table('flow_config_step')->where('flow_config_id', $mst->table('flow_config')->where('module_code', 'AD')->value('flow_config_id'))
                ->where('step_type', 'APPROVAL')->where('activate', 1)->orderBy('step_no')->value('position_id')
                ?? $mst->table('org_position')->where('position_name', 'Kepala Gudang')->where('activate', 1)->value('position_id');
            $id = $mst->table('flow_config')->insertGetId(['module_code' => 'TF', 'total_step' => $head ? 1 : 0, 'final_status' => 'CONFIRMED', 'reject_status' => 'REJECTED',
                'notes' => 'Stock Transfer between bins / warehouses. Switched off: Confirm moves the stock at once. Activate to make every transfer wait for approval.',
                'activate' => 0, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
            if ($head) {
                $mst->table('flow_config_step')->insert(['flow_config_id' => $id, 'step_no' => 1, 'step_type' => 'APPROVAL', 'status_from' => 'SUBMITTED', 'status_to' => 'SUBMITTED',
                    'status_to_reject' => 'REJECTED', 'position_id' => $head, 'min_amount' => 0, 'max_amount' => null, 'print_label' => 'Menyetujui', 'activate' => 1,
                    'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
            }
        }
    }

    public function down(): void
    {
        if (! $this->isDummy()) {
            return;
        }
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $menu()->where('code', 'INV_LBL_ITEM1')->update(['target' => null, 'status_code' => 'NOT_BUILT']);
        $menu()->where('code', 'INV_WH_TF_APPR')->delete();
        $mst = DB::connection('tm2mst');
        $ids = $mst->table('flow_config')->where('module_code', 'TF')->pluck('flow_config_id');
        if ($ids->isNotEmpty() && ! $mst->table('flow_log')->whereIn('flow_config_id', $ids)->exists()) {
            $mst->table('flow_config_step')->whereIn('flow_config_id', $ids)->delete();
            $mst->table('flow_config')->whereIn('flow_config_id', $ids)->delete();
        }
    }
};

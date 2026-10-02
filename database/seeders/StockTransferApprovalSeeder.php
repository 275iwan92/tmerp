<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fasilitas approval Stock Transfer (user 2026-10-02: "approval di-skip tapi fasilitas approval dibuat saja dan bisa
// diaktifkan kapan saja"): Setting Approval modul TF dengan satu level, TIDAK AKTIF, + menu Stock Transfer Approval Center.
// Butuh Setting Approval v2 (kolom flow_config.final_status, migration 2026_09_30_000009); database yang belum punya
// dilewati dengan pesan. Aman diulang (yang sudah ada tidak diubah):
//   php artisan db:seed --class=StockTransferApprovalSeeder --force
// Selama modul TF tidak ada atau tidak aktif, Confirm di Stock Transfer langsung memindahkan stok.
class StockTransferApprovalSeeder extends Seeder
{
    private const BY = 'claude (v2 build)';

    public function run(): void
    {
        $this->command?->line(self::install());
    }

    public static function install(): string
    {
        $mst = DB::connection('tm2mst');
        if (! Schema::connection('tm2mst')->hasColumn('flow_config', 'final_status')) {
            return 'Setting Approval v2 is not installed on '.$mst->getDatabaseName().' yet: Stock Transfer approval skipped (Confirm moves the stock at once).';
        }
        $done = [];
        if (! $mst->table('flow_config')->where('module_code', 'TF')->exists()) {
            // Pemeriksa = jabatan level 1 Stock Adjustment (ada pemegangnya); "Kepala Gudang" belum punya pemegang.
            // User mengganti jabatannya di Setup > Setting Approval.
            $position = $mst->table('flow_config_step')->where('flow_config_id', $mst->table('flow_config')->where('module_code', 'AD')->value('flow_config_id'))
                ->where('step_type', 'APPROVAL')->where('activate', 1)->orderBy('step_no')->value('position_id')
                ?? $mst->table('org_position')->where('position_name', 'Kepala Gudang')->where('activate', 1)->value('position_id');
            $id = $mst->table('flow_config')->insertGetId(['module_code' => 'TF', 'total_step' => $position ? 1 : 0, 'final_status' => 'CONFIRMED', 'reject_status' => 'REJECTED',
                'notes' => 'Stock Transfer between bins / warehouses. Switched off: Confirm moves the stock at once. Activate to make every transfer wait for approval.',
                'activate' => 0, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
            if ($position) {
                $mst->table('flow_config_step')->insert(['flow_config_id' => $id, 'step_no' => 1, 'step_type' => 'APPROVAL', 'status_from' => 'SUBMITTED', 'status_to' => 'SUBMITTED',
                    'status_to_reject' => 'REJECTED', 'position_id' => $position, 'min_amount' => 0, 'max_amount' => null, 'print_label' => 'Menyetujui', 'activate' => 1,
                    'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
            }
            $done[] = 'approval setting TF (switched off)';
        }
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $wh = $menu()->where('code', 'INV_WH')->first();
        if ($wh && ! $menu()->where('code', 'INV_WH_TF_APPR')->exists()) {
            $menu()->insert(['parent_id' => $wh->id, 'code' => 'INV_WH_TF_APPR', 'label' => 'Stock Transfer Approval Center', 'icon' => 'fa-solid fa-stamp', 'menu_type' => 'item',
                'object_type' => 'form', 'target' => 'v2:approval-tf', 'sort_order' => 30, 'activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY,
                'status_updated_at' => now(), 'created_at' => now(), 'doc_slug' => 'warehouselayout']);
            $done[] = 'menu Stock Transfer Approval Center';
        }

        return $done ? 'Installed on '.$mst->getDatabaseName().': '.implode(', ', $done).'.' : 'Stock Transfer approval is already installed on '.$mst->getDatabaseName().'.';
    }
}

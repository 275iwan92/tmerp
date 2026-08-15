<?php

namespace Database\Seeders;

use App\Models\Sys\SecGroup;
use App\Models\Sys\SecModule;
use App\Models\Sys\SysAppMenu;
use Illuminate\Database\Seeder;

/**
 * sec_module (unit IZIN) sudah punya 7 kode kanonik sejak seed dasar
 * tm2sys.sql: SALES, PROC, INV, PROD, MDATA, FINACC, SETUP — sinkron by
 * design dengan sys_app_menu (layer tampilan menu, 7 modul yang sama).
 * Seeder ini:
 *  1. Tambah 1 sec_module baru: COSTING — gate /inquiry/{id}/costing
 *     terpisah dari SALES supaya HPP/margin tidak otomatis kebuka untuk
 *     Sales biasa (Sisi A/B, CLAUDE.md §4). Grant default: DIREKSI saja.
 *  2. Isi sys_app_menu.required_object di 7 baris module dengan identitas
 *     kode module-nya sendiri (SALES->SALES dst) supaya visibilitas menu
 *     bisa difilter dari izin sec_module yang sudah ada.
 */
class SysAppMenuSeeder extends Seeder
{
    public function run(): void
    {
        $costing = SecModule::query()->updateOrCreate(
            ['module_code' => 'COSTING'],
            ['parent_id' => null, 'module_name' => 'Costing (HPP)', 'icon' => 'fa-solid fa-calculator', 'route_prefix' => 'inquiry-costing', 'min_mgmt_level' => 2, 'is_reportable' => true, 'sort_order' => 15, 'is_active' => true]
        );

        foreach (['SALES', 'PROC', 'INV', 'PROD', 'MDATA', 'FINACC', 'SETUP'] as $moduleCode) {
            SysAppMenu::query()->where('code', $moduleCode)->update(['required_object' => $moduleCode]);
        }

        // Costing: default hanya Direksi (selain is_admin yang selalu bypass).
        $direksi = SecGroup::query()->where('group_code', 'DIREKSI')->first();

        if ($direksi) {
            $direksi->permissions()->updateOrCreate(
                ['group_id' => $direksi->id_group, 'module_id' => $costing->id_module],
                ['can_access' => true, 'can_input' => true, 'can_approve' => true, 'can_export' => true]
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Sys\SecGroup;
use App\Models\Sys\SecModule;
use App\Models\Sys\SysAppMenu;
use Illuminate\Database\Seeder;

/**
 * sys_app_menu (layer tampilan menu) sudah terisi penuh sejak awal (lihat
 * docs/tm2sys.sql) — 7 modul mockup: SALES, PROC, INV, PROD, MDATA, FINACC,
 * SETUP. Tapi sec_module (unit IZIN, LOCKED 2 level, TIER_3A_SECURITY.sql)
 * baru punya 6 baris: INQUIRY, HPP, PROOF, MASTER, PRODUKSI, FINANCE — PROC,
 * INV, dan SECURITY (padanan SETUP) belum ada. Seeder ini:
 *  1. Tambah 3 sec_module baru (PROC, INV, SECURITY) supaya semua modul di
 *     sys_app_menu punya padanan izin (default DENY sampai digrant eksplisit).
 *  2. Isi sys_app_menu.required_object di 7 baris module supaya visibilitas
 *     menu bisa difilter dari izin sec_module yang sudah ada.
 *  3. Grant DIREKSI akses ke SECURITY (padanan SetUser::canSetup()).
 */
class SysAppMenuSeeder extends Seeder
{
    public function run(): void
    {
        $proc = SecModule::query()->updateOrCreate(
            ['module_code' => 'PROC'],
            ['parent_id' => null, 'module_name' => 'Procurement', 'icon' => 'fa-solid fa-cart-shopping', 'route_prefix' => 'procurement', 'min_mgmt_level' => 1, 'is_reportable' => false, 'sort_order' => 50, 'is_active' => true]
        );

        $inv = SecModule::query()->updateOrCreate(
            ['module_code' => 'INV'],
            ['parent_id' => null, 'module_name' => 'Inventory', 'icon' => 'fa-solid fa-boxes-stacked', 'route_prefix' => 'inventory', 'min_mgmt_level' => 1, 'is_reportable' => false, 'sort_order' => 60, 'is_active' => true]
        );

        $security = SecModule::query()->updateOrCreate(
            ['module_code' => 'SECURITY'],
            ['parent_id' => null, 'module_name' => 'Setup & Security', 'icon' => 'fa-solid fa-gear', 'route_prefix' => 'security', 'min_mgmt_level' => 5, 'is_reportable' => false, 'sort_order' => 90, 'is_active' => true]
        );

        // required_object -> sec_module.module_code (dipakai SecUser::visibleAppMenuModules()).
        $map = [
            'SALES' => 'INQUIRY',
            'PROC' => $proc->module_code,
            'INV' => $inv->module_code,
            'PROD' => 'PRODUKSI',
            'MDATA' => 'MASTER',
            'FINACC' => 'FINANCE',
            'SETUP' => $security->module_code,
        ];

        foreach ($map as $menuCode => $moduleCode) {
            SysAppMenu::query()->where('code', $menuCode)->update(['required_object' => $moduleCode]);
        }

        // Setup/Security: default hanya Direksi (selain is_admin yang selalu bypass).
        $direksi = SecGroup::query()->where('group_code', 'DIREKSI')->first();

        if ($direksi) {
            $direksi->permissions()->updateOrCreate(
                ['group_id' => $direksi->id_group, 'module_id' => $security->id_module],
                ['can_access' => true, 'can_input' => true, 'can_approve' => true, 'can_export' => true]
            );
        }
    }
}

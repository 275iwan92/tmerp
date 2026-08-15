<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Bug: SysAppMenuSeeder lama membuat sec_module duplikat 'SECURITY' (harusnya
// pakai 'SETUP' yang sudah ada dari seed dasar tm2sys.sql) karena
// SecUser::canSetup() salah cek kode modul. Grant DIREKSI nempel ke
// 'SECURITY', dan sys_app_menu.required_object untuk SALES/PROD/MDATA/FINACC
// masih menunjuk kode lama (INQUIRY/PRODUKSI/MASTER/FINANCE) yang sudah tak
// ada di sec_module — akibatnya visibleAppMenuCodes() tak pernah cocok untuk
// non-admin. Migration ini: konsolidasi ke SETUP, tambah modul COSTING baru
// (gate /inquiry/{id}/costing terpisah dari SALES — Sisi A/B, CLAUDE.md §4),
// dan betulkan required_object ke identitas kode kanonik.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $setupId = $sys->table('sec_module')->where('module_code', 'SETUP')->value('id_module');
        $securityId = $sys->table('sec_module')->where('module_code', 'SECURITY')->value('id_module');

        if ($securityId) {
            $sys->table('sec_group_permission')
                ->where('module_id', $securityId)
                ->get()
                ->each(function ($perm) use ($sys, $setupId) {
                    $exists = $sys->table('sec_group_permission')
                        ->where('group_id', $perm->group_id)
                        ->where('module_id', $setupId)
                        ->exists();

                    if (! $exists) {
                        $sys->table('sec_group_permission')->insert([
                            'group_id' => $perm->group_id,
                            'module_id' => $setupId,
                            'can_access' => $perm->can_access,
                            'can_input' => $perm->can_input,
                            'can_approve' => $perm->can_approve,
                            'can_export' => $perm->can_export,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });

            $sys->table('sec_group_permission')->where('module_id', $securityId)->delete();
            $sys->table('sec_module')->where('id_module', $securityId)->delete();
        }

        $costingId = $sys->table('sec_module')->where('module_code', 'COSTING')->value('id_module');

        if (! $costingId) {
            $costingId = $sys->table('sec_module')->insertGetId([
                'module_code' => 'COSTING',
                'module_name' => 'Costing (HPP)',
                'icon' => 'fa-solid fa-calculator',
                'route_prefix' => 'inquiry-costing',
                'min_mgmt_level' => 2,
                'is_reportable' => true,
                'sort_order' => 15,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $direksiId = $sys->table('sec_group')->where('group_code', 'DIREKSI')->value('id_group');

        if ($direksiId) {
            $sys->table('sec_group_permission')->updateOrInsert(
                ['group_id' => $direksiId, 'module_id' => $costingId],
                ['can_access' => true, 'can_input' => true, 'can_approve' => true, 'can_export' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        foreach (['SALES', 'PROD', 'MDATA', 'FINACC', 'SETUP'] as $code) {
            $sys->table('sys_app_menu')
                ->where('menu_type', 'module')
                ->where('code', $code)
                ->update(['required_object' => $code]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        foreach (['SALES' => 'INQUIRY', 'PROD' => 'PRODUKSI', 'MDATA' => 'MASTER', 'FINACC' => 'FINANCE', 'SETUP' => 'SECURITY'] as $code => $oldRequiredObject) {
            $sys->table('sys_app_menu')
                ->where('menu_type', 'module')
                ->where('code', $code)
                ->update(['required_object' => $oldRequiredObject]);
        }

        $costingId = $sys->table('sec_module')->where('module_code', 'COSTING')->value('id_module');

        if ($costingId) {
            $sys->table('sec_group_permission')->where('module_id', $costingId)->delete();
            $sys->table('sec_module')->where('id_module', $costingId)->delete();
        }

        $setupId = $sys->table('sec_module')->where('module_code', 'SETUP')->value('id_module');

        if ($setupId && ! $sys->table('sec_module')->where('module_code', 'SECURITY')->exists()) {
            $securityId = $sys->table('sec_module')->insertGetId([
                'module_code' => 'SECURITY',
                'module_name' => 'Setup & Security',
                'icon' => 'fa-solid fa-gear',
                'route_prefix' => 'security',
                'min_mgmt_level' => 5,
                'is_reportable' => false,
                'sort_order' => 90,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sys->table('sec_group_permission')
                ->where('module_id', $setupId)
                ->get()
                ->each(function ($perm) use ($sys, $securityId) {
                    $sys->table('sec_group_permission')->insert([
                        'group_id' => $perm->group_id,
                        'module_id' => $securityId,
                        'can_access' => $perm->can_access,
                        'can_input' => $perm->can_input,
                        'can_approve' => $perm->can_approve,
                        'can_export' => $perm->can_export,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
        }
    }
};

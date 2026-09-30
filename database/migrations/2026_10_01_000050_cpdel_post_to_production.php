<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Aturan user 2026-10-01 (docs/globaluserrules.md A.3): baris CPDEL dari CO Confirmed di-"Post to Production" (= WO lahir);
// sesudah itu baru boleh ditarik ke DO / Surat Jalan (jalan pintas pekerjaan cepat ~30 menit). Hasil produksi tetap wajib
// di-input (acuan KPI) -> laporan "Shipped Without Production Output". Status kirim (PLANNED/SHIPPED) tidak diubah:
// posted_at terpisah supaya aturan Split/Gabung/DO lama yang membaca status tetap benar.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('co_order_delivery', function (Blueprint $t) {
            $t->dateTime('posted_at')->nullable()->after('split_from_id')->comment('Post to Production (WO dibuat); syarat ditarik ke DO');
            $t->string('posted_by', 50)->nullable()->after('posted_at');
        });

        $sys = DB::connection('tm2sys');
        $now = now();
        if (! $sys->table('sys_constant')->where('constant_key', 'QUICK_JOB_MINUTES')->exists()) {
            $sys->table('sys_constant')->insert(['constant_key' => 'QUICK_JOB_MINUTES', 'category_code' => 'PRODUCTION', 'constant_group' => 'CPDEL',
                'constant_number' => 30, 'activate' => true, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'system',
                'description' => 'Quick job: work that takes about this many minutes may go to Delivery Order right after Post to Production (reference only; changes with real data)']);
        }

        $menu = fn (array $r) => $sys->table('sys_app_menu')->where('code', $r['code'])->exists() ? null
            : $sys->table('sys_app_menu')->insert($r + ['target_kind' => 'app', 'activate' => 1, 'created_at' => $now,
                'status_code' => isset($r['target']) ? 'READY_FOR_QC' : 'NOT_BUILT', 'status_updated_by' => 'claude (v2 build)']);
        $id = fn (string $code) => $sys->table('sys_app_menu')->where('code', $code)->value('id');
        if ($id('PROD')) {
            $menu(['code' => 'PROD_GRP_RPT', 'parent_id' => $id('PROD'), 'label' => 'Report', 'icon' => 'fa-solid fa-chart-column', 'menu_type' => 'group', 'sort_order' => 90]);
            $menu(['code' => 'PROD_RPT_NO_OUTPUT', 'parent_id' => $id('PROD_GRP_RPT'), 'label' => 'Shipped Without Production Output', 'icon' => 'fa-solid fa-triangle-exclamation',
                'menu_type' => 'item', 'object_type' => 'report', 'target' => 'v2:prod-no-output', 'sort_order' => 10]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sys_app_menu')->whereIn('code', ['PROD_RPT_NO_OUTPUT', 'PROD_GRP_RPT'])->delete();
        $sys->table('sys_constant')->where('constant_key', 'QUICK_JOB_MINUTES')->delete();
        Schema::connection('tm2sdi')->table('co_order_delivery', fn (Blueprint $t) => $t->dropColumn(['posted_at', 'posted_by']));
    }
};

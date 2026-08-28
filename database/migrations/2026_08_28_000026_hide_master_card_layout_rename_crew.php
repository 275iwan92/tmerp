<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Permintaan user 2026-08-28 (lanjutan reorganisasi menu Production):
// - Label PROD_MST_CREW jadi "Master Crew" (bukan "Master Kru").
// - Master Card & Layout disembunyikan dulu dari sidebar (activate=false),
//   BUKAN dihapus -- datanya tetap ada di sys_app_menu, tinggal dinyalakan
//   lagi kapan pun (mis. saat Master Card mulai digarap).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->where('code', 'PROD_MST_CREW')->update([
            'label' => 'Master Crew',
        ]);

        $sys->table('sys_app_menu')->whereIn('code', ['PROD_CARD', 'PROD_LAYOUT'])->update([
            'activate' => false,
        ]);
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->where('code', 'PROD_MST_CREW')->update([
            'label' => 'Master Kru',
        ]);

        $sys->table('sys_app_menu')->whereIn('code', ['PROD_CARD', 'PROD_LAYOUT'])->update([
            'activate' => true,
        ]);
    }
};

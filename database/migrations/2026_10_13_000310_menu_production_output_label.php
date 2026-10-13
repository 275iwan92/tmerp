<?php

/*
 * REMARK
 * Tujuan       : Item menu Operator Queue diganti nama Production Output (layar entry hasil produksi untuk crew login).
 * Sumber hulu  : sys_app_menu (tm2sys), kode PROD_OPERATOR, target v2:production-operator.
 * Input        : -
 * Output       : label PROD_OPERATOR = Production Output (tetap tersembunyi seperti menu PPIC lain).
 * Dipakai oleh : AppMenu (menu v2).
 * Status       : Jalankan di ddt* (--env=mockup); di tm2* saat rilis.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_OPERATOR')->update(['label' => 'Production Output']);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_OPERATOR')->update(['label' => 'Operator Queue']);
    }
};

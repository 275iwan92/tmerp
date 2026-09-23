<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// UI-13: UM_SETUP hanya tampil utk non-admin (visibility setup_non_admin) tapi
// menunjuk SET_USER yang khusus admin -- dan semua layar Setup ber-middleware
// 'admin' -- jadi tidak pernah tampil utk siapa pun. Dinonaktifkan, bukan dihapus.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_user_menu')->where('code', 'UM_SETUP')->update(['activate' => 0]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_user_menu')->where('code', 'UM_SETUP')->update(['activate' => 1]);
    }
};

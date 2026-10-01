<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Nama menu "Lookup Product Market" -> "Master Product Market" (user 2026-10-02). Hanya mengganti label yang masih
// bernama lama, supaya nama yang sudah diubah user lewat layar menu tidak ditimpa.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('label', 'Lookup Product Market')->update(['label' => 'Master Product Market']);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('label', 'Master Product Market')->update(['label' => 'Lookup Product Market']);
    }
};

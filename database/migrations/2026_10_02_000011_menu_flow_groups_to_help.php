<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Grup menu "Flow" dipindah SEMENTARA ke Help / F1 (user 2026-10-02: "flow ini bagus tapi taruh sementara di help, nanti saya
// pikirkan ulang"). Baris menunya hanya dimatikan, tidak dihapus, supaya mudah dihidupkan lagi; tombolnya ada di halaman
// Bantuan (resources/views/v2/home.blade.php).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const GROUPS = ['PREORDER_FLOWGRP', 'SALES_FLOWGRP'];

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', self::GROUPS)->update(['activate' => 0]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', self::GROUPS)->update(['activate' => 1]);
    }
};

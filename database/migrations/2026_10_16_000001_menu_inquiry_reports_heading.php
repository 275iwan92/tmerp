<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* REMARK
   Tujuan       : Menu Inquiry Product: judul "Reports" (menu_type=heading) di bawah Inquiry Recalc, lalu All Inquiries sebagai laporan di bawahnya (Jo 2026-10-16).
   Sumber hulu  : sys_app_menu (tm2sys; di mockup.test = ddtsys).
   Input        : baris PRE_INQGRP, SALES_INQ.
   Output       : enum menu_type + 'heading'; baris PRE_INQ_REPORTS; SALES_INQ urutan 60, object_type report.
   Dipakai oleh : App\Support\V2\AppMenu::nodes (heading -> prop group Layer Menu).
   Status       : Aktif. */
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        $db = DB::connection('tm2sys');
        $db->statement("ALTER TABLE sys_app_menu MODIFY menu_type ENUM('module','group','item','heading') NOT NULL DEFAULT 'item'");
        $parent = $db->table('sys_app_menu')->where('code', 'PRE_INQGRP')->value('id');
        if (! $parent) {
            return;
        }
        if (! $db->table('sys_app_menu')->where('code', 'PRE_INQ_REPORTS')->exists()) {
            $db->table('sys_app_menu')->insert(['parent_id' => $parent, 'code' => 'PRE_INQ_REPORTS', 'label' => 'Reports', 'icon' => null,
                'menu_type' => 'heading', 'target_kind' => 'app', 'sort_order' => 50, 'activate' => 1, 'status_code' => 'READY_FOR_QC']);
        }
        $db->table('sys_app_menu')->where('code', 'SALES_INQ')->update(['sort_order' => 60, 'object_type' => 'report']);
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');
        $db->table('sys_app_menu')->where('code', 'SALES_INQ')->update(['sort_order' => 10, 'object_type' => 'form']);
        $db->table('sys_app_menu')->where('code', 'PRE_INQ_REPORTS')->delete();
        $db->statement("ALTER TABLE sys_app_menu MODIFY menu_type ENUM('module','group','item') NOT NULL DEFAULT 'item'");
    }
};

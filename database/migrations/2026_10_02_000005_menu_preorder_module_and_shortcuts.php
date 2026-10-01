<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Scope modul (user 2026-10-02): Inquiry + Pro-Dev + Pro-Cost + Quotation = SATU menu besar sendiri (modul menu PREORDER)
// yang punya saklar `activate` sendiri; mati -> menu & route-nya tertutup, Customer Order / CPDEL / Delivery (SALES) tetap jalan.
// Izin tetap memakai modul SALES (required_object), jadi tidak ada perubahan matriks hak akses.
// Master yang dibutuhkan muncul sebagai PINTASAN = baris menu biasa ber-target sama dengan baris di Master Data (izin MDATA):
// satu layar, tanpa salinan. (Kolom shortcut_of yang sempat dibuat di sini dibuang lagi oleh migrasi 000006.)
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const SHORTCUTS = ['MD_CUST' => 'PRE_MD_CUST', 'MD_PROD' => 'PRE_MD_PROD', 'MD_RAW' => 'PRE_MD_RAW'];

    public function up(): void
    {
        $db = DB::connection('tm2sys');
        if (! Schema::connection('tm2sys')->hasColumn('sys_app_menu', 'shortcut_of')) {
            Schema::connection('tm2sys')->table('sys_app_menu', function ($t) {
                $t->unsignedBigInteger('shortcut_of')->nullable()->after('parent_id');
            });
        }
        $grp = $db->table('sys_app_menu')->where('code', 'SALES_INQGRP')->first();
        if (! $grp || $db->table('sys_app_menu')->where('code', 'PREORDER')->exists()) {
            return;
        }

        $mod = $db->table('sys_app_menu')->insertGetId([
            'parent_id' => null, 'code' => 'PREORDER', 'label' => 'Inquiry & Quotation', 'short_label' => 'INQ', 'icon' => $grp->icon,
            'menu_type' => 'module', 'sort_order' => 9, 'activate' => 1, 'required_object' => 'SALES', 'status_code' => 'READY_FOR_QC',
        ]);
        $db->table('sys_app_menu')->where('parent_id', $grp->id)->update(['parent_id' => $mod]);
        $db->table('sys_app_menu')->where('id', $grp->id)->update(['activate' => 0]);

        $mdGrp = $db->table('sys_app_menu')->insertGetId([
            'parent_id' => $mod, 'code' => 'PREORDER_MDGRP', 'label' => 'Master Data', 'icon' => 'fa-solid fa-database',
            'menu_type' => 'group', 'sort_order' => 800, 'activate' => 1, 'status_code' => 'READY_FOR_QC',
        ]);
        $n = 10;
        foreach (self::SHORTCUTS as $origin => $code) {
            $o = $db->table('sys_app_menu')->where('code', $origin)->first();
            if (! $o) {
                continue;
            }
            $db->table('sys_app_menu')->insert([
                'parent_id' => $mdGrp, 'shortcut_of' => $o->id, 'code' => $code, 'label' => $o->label, 'trans_key' => $o->trans_key,
                'icon' => $o->icon, 'menu_type' => 'item', 'object_type' => $o->object_type, 'target' => $o->target,
                'sort_order' => $n, 'activate' => 1, 'required_object' => 'MDATA', 'status_code' => 'READY_FOR_QC',
            ]);
            $n += 10;
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');
        $mod = $db->table('sys_app_menu')->where('code', 'PREORDER')->first();
        $grp = $db->table('sys_app_menu')->where('code', 'SALES_INQGRP')->first();
        if (! $mod || ! $grp) {
            return;
        }
        $mdGrp = $db->table('sys_app_menu')->where('code', 'PREORDER_MDGRP')->value('id');
        $db->table('sys_app_menu')->whereIn('code', array_values(self::SHORTCUTS))->delete();
        $db->table('sys_app_menu')->where('code', 'PREORDER_MDGRP')->delete();
        $db->table('sys_app_menu')->where('parent_id', $mod->id)->where('id', '!=', $mdGrp)->update(['parent_id' => $grp->id]);
        $db->table('sys_app_menu')->where('id', $grp->id)->update(['activate' => 1]);
        $db->table('sys_app_menu')->where('id', $mod->id)->delete();
    }
};

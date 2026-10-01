<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Modul Inquiry & Quotation (menu PREORDER) dilengkapi (user 2026-10-02: "all menu related with this module available in one
// module"): layar yang dipakai Inquiry -> Pro-Dev -> Pro-Cost -> Quotation tapi belum bisa dibuka dari modul ini.
// Semua = baris data (menu = data). Pintasan menyalin label/ikon/target baris asalnya; izin tetap modul pemilik layar.
// Baris tanpa target = layar v1, alamatnya di App\Support\Menu\MenuRoutes (kode baris ini).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    // kode => [kode induk, kode baris asal (pintasan) | null, urutan, izin, kolom sendiri]
    private const ROWS = [
        'PRE_PROD_ACC' => ['SALES_PRODEVGRP', null, 40, null, ['label' => 'ACC Digital / Proof Digital', 'icon' => 'fa-solid fa-stamp', 'object_type' => 'form', 'target' => 'v2:product-acc']],
        'PRE_COST_INSTANT' => ['PREORDER', null, 35, 'COSTING', ['label' => 'Pro-Cost Instant', 'icon' => 'fa-solid fa-bolt', 'object_type' => 'form']],
        'PRE_MD_MATCAT' => ['PREORDER_MDGRP', 'MD_MATCAT', 40, 'MDATA', []],
        'PRE_MD_PROCESS' => ['PREORDER_MDGRP', 'PROD_MST_PROCESS', 50, 'PROD', []],
        'PRE_MD_MACHINE' => ['PREORDER_MDGRP', 'MD_MACHINE', 60, 'PROD', []],
        'PRE_MD_UOM' => ['PREORDER_MDGRP', 'MD_UOM', 70, 'MDATA', ['target' => null]],
        'PRE_MD_CURRENCY' => ['PREORDER_MDGRP', 'MD_CURRENCY', 80, 'MDATA', []],
        'PRE_MD_EXCHRATE' => ['PREORDER_MDGRP', 'MD_EXCHRATE', 90, 'MDATA', []],
        'PRE_SET_CONSTANTS' => ['PREORDER_MDGRP', 'SET_CONSTANTS', 100, 'SETUP', []],
        'PRE_F_SALES' => ['PREORDER_FLOWGRP', 'F-SALES', 5, null, []],
    ];

    public function up(): void
    {
        $menu = DB::connection('tm2sys')->table('sys_app_menu');
        if (! (clone $menu)->where('code', 'PREORDER')->exists()) {
            return;
        }
        foreach (self::ROWS as $code => [$parent, $origin, $sort, $permission, $own]) {
            $parentId = (clone $menu)->where('code', $parent)->value('id');
            $o = $origin ? (clone $menu)->where('code', $origin)->first() : null;
            if (! $parentId || ($origin && ! $o) || (clone $menu)->where('code', $code)->exists()) {
                continue;
            }
            (clone $menu)->insert($own + [
                'parent_id' => $parentId, 'code' => $code, 'label' => $o?->label, 'trans_key' => $o?->trans_key, 'icon' => $o?->icon,
                'menu_type' => 'item', 'object_type' => $o?->object_type, 'target' => $o?->target, 'sort_order' => $sort, 'activate' => 1,
                'required_object' => $permission, 'status_code' => $o?->status_code ?? 'READY_FOR_QC',
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', array_keys(self::ROWS))->delete();
    }
};

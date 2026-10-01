<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu Inquiry / Pro-Dev / Pro-Cost dipisah per jenis dokumen (user 2026-10-02, rulebook A.20): alurnya berbeda
// (New & Revise: Inquiry -> Pro-Dev -> Pro-Cost; Recalc: Inquiry -> Pro-Cost), jadi officer tidak perlu memilah sendiri.
// Tabelnya tetap satu (iqp_inquiry); tiap menu = saringan jenis AND stat_confirm_*. Menu = data: hanya baris sys_app_menu.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    // kode => [label, ikon, urutan]
    private const GROUPS = [
        'PRE_INQGRP' => ['Inquiry Product', 'fa-solid fa-inbox', 10],
        'PRE_COSTGRP' => ['Pro-Cost', 'fa-solid fa-scale-balanced', 30],
    ];

    // kode => [kode induk, label, ikon, target, urutan, izin]
    private const ITEMS = [
        'PRE_INQ_NEW' => ['PRE_INQGRP', 'Inquiry New', 'fa-solid fa-file-circle-plus', 'v2:inquiry-list-new', 20, null],
        'PRE_INQ_REVISE' => ['PRE_INQGRP', 'Inquiry Revise', 'fa-solid fa-file-pen', 'v2:inquiry-revise', 30, null],
        'PRE_INQ_RECALC' => ['PRE_INQGRP', 'Inquiry Recalc', 'fa-solid fa-rotate', 'v2:inquiry-recalc', 40, null],
        'PRE_PRODEV_NEW' => ['SALES_PRODEVGRP', 'Pro-Dev New', 'fa-solid fa-pen-ruler', 'v2:prodev-new', 1, null],
        'PRE_PRODEV_REVISE' => ['SALES_PRODEVGRP', 'Pro-Dev Revise', 'fa-solid fa-ruler-combined', 'v2:prodev-revise', 2, null],
        'PRE_COST_REVISE' => ['PRE_COSTGRP', 'Pro-Cost Revise', 'fa-solid fa-square-root-variable', 'v2:sales-procost-revise', 20, 'COSTING'],
        'PRE_COST_RECALC' => ['PRE_COSTGRP', 'Pro-Cost Recalc', 'fa-solid fa-arrows-rotate', 'v2:sales-procost-recalc', 30, 'COSTING'],
    ];

    // Baris lama: kode => [kode induk baru, label lama => label baru, urutan baru, ikon baru | null]
    private const MOVED = [
        'SALES_INQ' => ['PRE_INQGRP', 'Inquiry Product', 'All Inquiries', 10, null],
        'SALES_IMPO' => ['SALES_PRODEVGRP', 'Pro-Dev Worklist', 'Pro-Dev Manual', 10, 'fa-solid fa-hand'],
        'SALES_COST' => ['PRE_COSTGRP', 'Pro-Cost', 'Pro-Cost New', 10, null],
        'PRE_COST_INSTANT' => ['PRE_COSTGRP', 'Pro-Cost Instant', 'Pro-Cost Instant', 40, null],
    ];

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $root = $menu()->where('code', 'PREORDER')->value('id');
        $template = $menu()->where('code', 'SALES_PRODEVGRP')->first();
        if (! $root || ! $template) {
            return;
        }
        $id = fn (string $code) => $menu()->where('code', $code)->value('id');

        foreach (self::GROUPS as $code => [$label, $icon, $sort]) {
            if (! $id($code)) {
                $menu()->insert(['id' => null, 'parent_id' => $root, 'code' => $code, 'label' => $label, 'icon' => $icon, 'sort_order' => $sort] + (array) $template);
            }
        }
        foreach (self::MOVED as $code => [$parent, $old, $new, $sort, $icon]) {
            $menu()->where('code', $code)->update(['parent_id' => $id($parent), 'sort_order' => $sort] + ($icon ? ['icon' => $icon] : []));
            // Label yang sudah diubah user lewat layar menu tidak ditimpa.
            $menu()->where('code', $code)->where('label', $old)->update(['label' => $new]);
        }
        foreach (self::ITEMS as $code => [$parent, $label, $icon, $target, $sort, $permission]) {
            if (! $id($code) && $id($parent)) {
                $menu()->insert(['parent_id' => $id($parent), 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => 'form',
                    'target' => $target, 'target_kind' => 'app', 'sort_order' => $sort, 'activate' => 1, 'required_object' => $permission, 'status_code' => 'READY_FOR_QC']);
            }
        }
    }

    public function down(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $root = $menu()->where('code', 'PREORDER')->value('id');
        $menu()->whereIn('code', array_keys(self::ITEMS))->delete();
        foreach (self::MOVED as $code => [$parent, $old, $new]) {
            $menu()->where('code', $code)->where('label', $new)->update(['label' => $old]);
            if ($parent !== 'SALES_PRODEVGRP' && $root) {
                $menu()->where('code', $code)->update(['parent_id' => $root]);
            }
        }
        $menu()->whereIn('code', array_keys(self::GROUPS))->delete();
    }
};

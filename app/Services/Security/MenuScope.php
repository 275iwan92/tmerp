<?php

namespace App\Services\Security;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysAppMenu;
use Illuminate\Support\Collection;

/*
 * REMARK
 * Tujuan       : Batas menu per kode untuk grup "bercakupan": hak modul terlalu kasar (Pro-Dev ada di modul Sales), jadi grup ini hanya melihat cabang menu yang disebut (Jo 2026-10-24).
 * Sumber hulu  : sec_user_group (kode grup) dan pohon sys_app_menu (parent_id).
 * Input        : Pengguna dan baris menu aktif.
 * Output       : null = tanpa batas; selain itu daftar kode menu yang boleh tampil (cabang yang disebut beserta turunan dan induknya, plus item GENERAL).
 * Dipakai oleh : App\Support\V2\AppMenu::modules(). Hanya menyaring tampilan menu (seperti PayrollAccess::menuAllowed); hak modul dan rute tidak berubah.
 * Status       : Aktif. Migrasi 2026_10_24_000400 membuat grup dan memasang pengguna.
 */
final class MenuScope
{
    // Kode grup => kode simpul menu akar yang boleh dilihat. Pengguna dengan beberapa grup bercakupan mendapat gabungannya.
    public const SCOPES = [
        'PRODEV_STAFF' => ['SALES_PRODEVGRP'],
        'MENU_PROCOST' => ['PRE_COSTGRP'],
        'MENU_SALES_INQ' => ['PRE_INQGRP', 'SALES_QUOT', 'SALES_INQGRP_RPT', 'SALES_ORDGRP', 'SALES_ORDGRP_RPT', 'SALES_DLVGRP', 'SALES_CRMGRP', 'CRM_RPTGRP'],
    ];

    public static function codes(SecUser $user, Collection $rows): ?array
    {
        if ($user->is_admin) {
            return null;
        }
        $roots = collect(self::SCOPES)->only($user->groups()->pluck('group_code')->all())->flatten();
        if ($roots->isEmpty()) {
            return null;
        }

        $children = $rows->groupBy('parent_id');
        $parent = $rows->pluck('parent_id', 'id');
        $code = $rows->pluck('code', 'id');
        $keep = [];
        $add = function ($id) use (&$add, &$keep, $children, $code) { // simpul + semua turunannya
            $keep[$code[$id]] = true;
            foreach ($children->get($id, []) as $c) {
                $add($c->id);
            }
        };
        foreach ($rows->filter(fn (SysAppMenu $r) => $roots->contains($r->code) || $r->required_object === SecUser::PUBLIC_MODULE) as $r) {
            $add($r->id);
            for ($p = $parent[$r->id]; $p; $p = $parent[$p] ?? null) { // induknya, supaya cabang terjangkau
                $keep[$code[$p]] = true;
            }
        }

        return $keep;
    }
}

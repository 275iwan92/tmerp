<?php

/*
 * REMARK
 * Tujuan       : Menjaga bahwa tiap item menu aktif dan terlihat punya layar (target v2 yang terdaftar, atau pemetaan MenuRoutes), dan tidak
 *                ada modul bersarang: pintu kosong "Layar untuk menu ini belum ada" tidak boleh muncul lagi.
 * Sumber hulu  : tm2sys.sys_app_menu (ddtsys di mockup), App\Support\V2\Screens / Grids, App\Support\Menu\MenuRoutes.
 * Input        : -
 * Output       : Lulus bila daftar item bermasalah kosong; gagal menyebut kode menu yang bermasalah.
 * Dipakai oleh : php artisan test (dan php artisan parity:check bersama).
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Sys\SysAppMenu;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use App\Support\V2\Screens;
use Tests\TestCase;

class MenuHasScreenTest extends TestCase
{
    public function test_every_visible_menu_item_has_a_screen_and_no_module_is_nested(): void
    {
        $rows = SysAppMenu::query()->get()->keyBy('id');
        $map = MenuRoutes::map();
        $visible = function ($i) use ($rows) {
            for ($p = $i; $p; $p = $rows->get($p->parent_id)) {
                if (! $p->activate || ($p->hide_in_menu ?? 0)) {
                    return false;
                }
            }

            return true;
        };

        $bad = [];
        foreach ($rows as $r) {
            if ($r->menu_type === 'module' && $r->parent_id) {
                $bad[] = "{$r->code}: modul bersarang";
            }
            if (in_array($r->menu_type, ['module', 'group'], true) || ! $visible($r)) {
                continue;
            }
            $t = (string) $r->target;
            if (str_starts_with($t, 'v2:')) {
                $slug = substr($t, 3);
                if (! (Screens::screen($slug) ?? Grids::screen($slug))) {
                    $bad[] = "{$r->code}: slug v2 tidak terdaftar ($t)";
                }
            } elseif (! str_starts_with($t, '/') && ! isset($map[$r->code])) {
                $bad[] = "{$r->code}: tanpa layar";
            }
        }

        $this->assertSame([], $bad);
    }
}

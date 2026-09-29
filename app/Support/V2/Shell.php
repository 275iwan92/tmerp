<?php

namespace App\Support\V2;

use App\Models\Sys\SysAppMenu;
use App\Support\Menu\MenuRoutes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Shell v2 (Menu v2 + tab MDI). Alamatnya pendek: /menu di situs v2 (mockup.test, APP_UI=v2, keputusan user
// 2026-09-29); di situs v1 tetap /v2 sebagai pratinjau. Layar menu hanya boleh tampil sebagai TAB di dalam shell.
final class Shell
{
    public static function isV2Site(): bool
    {
        return config('app.ui') === 'v2';
    }

    public static function url(array $query = []): string
    {
        $base = self::isV2Site() ? route('menu') : route('v2.home');

        return $query ? $base.'?'.http_build_query($query) : $base;
    }

    public static function view(Request $r)
    {
        return view('v2.home', ['modules' => AppMenu::modules(Auth::user()), 'user' => AppMenu::user(Auth::user()), 'open' => $r->query('open')]);
    }

    // Kode item menu (sys_app_menu.code / item v2) yang menampilkan request ini sebagai tab; null = bukan layar menu
    // (cetak, unduhan, pop-up, dsb. -- dibiarkan terbuka sendiri).
    public static function codeFor(Request $r): ?string
    {
        $route = $r->route();

        return match ($route?->getName()) {
            'v2.item' => $route->parameter('code'),
            'v2.screen' => SysAppMenu::query()->where('target', 'v2:'.$route->parameter('slug'))->value('code'),
            'v2.flow' => 'F-'.$route->parameter('module'),
            'v2.dictionary' => 'V2-dictionary',
            default => self::v1Code($r),
        };
    }

    private static function v1Code(Request $r): ?string
    {
        $path = '/'.ltrim($r->path(), '/');
        foreach (MenuRoutes::map() as $code => $url) {
            if ((parse_url($url, PHP_URL_PATH) ?: '/') === $path) {
                return $code;
            }
        }

        return null;
    }
}

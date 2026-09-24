<?php

namespace App\Support\V2;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysAppMenu;
use App\Support\Localization\Translator;
use App\Support\Menu\MenuRoutes;

// Menu v2 (x-dl::layer-shell) dari sys_app_menu (di mockup.test = ddtsys). Isi menu = induk di database, bukan kode.
// Hak akses: modul lewat SecUser::visibleAppMenuCodes() (sama dengan v1), PLUS required_object di grup/item
// (mis. Pro-Cost hanya grup yang punya akses COSTING). Admin melihat semua.
// Layar tiap item: target 'v2:<slug>' -> layar v2; selain itu layar v1 tmerp (MenuRoutes) supaya tetap bisa di-UAT.
final class AppMenu
{
    // status_code (progres v2) -> lencana menu
    private const BADGE = [
        'READY_FOR_QC' => ['Review', 'partial'],
        'LOCKED' => ['Approved', 'done'],
    ];

    public static function modules(SecUser $user): array
    {
        $rows = SysAppMenu::query()->where('activate', true)->orderBy('sort_order')->orderBy('id')->get();
        $children = $rows->whereNotNull('parent_id')->groupBy('parent_id');
        $allowed = $user->is_admin ? null : $user->accessibleModules()->keys();
        // Direksi boleh melihat menu operasional (aturan user 2026-09-25); pendaratan awalnya Laporan Manajemen (login).
        $visible = $user->visibleAppMenuCodes();
        $ok = fn (SysAppMenu $n) => $allowed === null || $n->required_object === null || $allowed->contains($n->required_object);
        $routes = MenuRoutes::map();

        $modules = [];
        foreach ($rows->where('menu_type', 'module')->whereIn('code', $visible) as $m) {
            $out = [];
            foreach ($children->get($m->id, collect())->filter($ok) as $c) {
                if ($c->menu_type === 'group') {
                    $items = $children->get($c->id, collect())->filter($ok)->map(fn ($i) => self::item($i, $routes))->values()->all();
                    if ($items) {
                        $out[] = ['label' => self::label($c), 'items' => $items];
                    }
                } else {
                    $out[] = self::item($c, $routes);
                }
            }
            foreach (self::extraGroups($m->code) as $g) {
                $out[] = $g;
            }
            if ($out) {
                $modules[] = ['code' => $m->code, 'short' => $m->short_label, 'label' => self::label($m), 'icon' => $m->icon ?: 'fa-solid fa-folder', 'children' => $out];
            }
        }

        if ($user->is_admin) {
            $modules[] = ['code' => 'DEV', 'short' => 'DEV', 'label' => 'Developer', 'icon' => 'fa-solid fa-code', 'children' => [
                ['label' => 'Database', 'items' => [['code' => 'V2-dictionary', 'label' => 'Data Dictionary', 'icon' => 'fa-solid fa-database', 'url' => route('v2.dictionary'), 'type' => 'report']]],
            ]];
        }

        return $modules;
    }

    private static function item(SysAppMenu $i, array $routes): array
    {
        $v2 = str_starts_with((string) $i->target, 'v2:');
        [$badge, $tone] = $v2 ? (self::BADGE[$i->status_code] ?? ['Belum', null])
            : (isset($routes[$i->code]) ? ['v1', null] : ['Belum', null]);

        return ['code' => $i->code, 'label' => self::label($i), 'icon' => $i->icon ?: null, 'type' => $i->object_type ?: 'form',
            'url' => route('v2.item', $i->code), 'badge' => $badge, 'badgeTone' => $tone];
    }

    private static function label(SysAppMenu $n): string
    {
        return $n->trans_key ? Translator::t($n->trans_key, $n->label) : $n->label;
    }

    // Grup Alur (perjalanan dokumen antar layar) -- milik v2, bukan baris sys_app_menu.
    private static function extraGroups(string $module): array
    {
        return match ($module) {
            'SALES' => [['label' => 'Alur', 'items' => [
                ['code' => 'F-SALES', 'label' => 'Alur Sales: Inquiry → CPDEL', 'icon' => 'fa-solid fa-diagram-project', 'url' => route('v2.flow', 'SALES'), 'type' => 'report'],
                ['code' => 'F-COSTING', 'label' => 'Alur Costing: ProDev → ProCost → Publish', 'icon' => 'fa-solid fa-diagram-project', 'url' => route('v2.flow', 'COSTING'), 'type' => 'report'],
            ]]],
            default => [],
        };
    }

    // URL tujuan satu item: layar v2 (target v2:slug), layar v1, atau null (belum ada layar).
    public static function resolve(string $code): array
    {
        $i = SysAppMenu::query()->where('code', $code)->first();
        abort_unless($i, 404);
        if (str_starts_with((string) $i->target, 'v2:')) {
            return ['v2', route('v2.screen', substr($i->target, 3)), $i];
        }
        $v1 = MenuRoutes::map()[$code] ?? null;

        return $v1 ? ['v1', $v1, $i] : ['none', null, $i];
    }

    public static function user(SecUser $u): array
    {
        return ['name' => $u->name, 'email' => (string) $u->email, 'role' => $u->groups()->pluck('group_name')->implode(', ') ?: ($u->is_admin ? 'Administrator' : ''),
            'developer' => (bool) $u->is_admin];
    }
}

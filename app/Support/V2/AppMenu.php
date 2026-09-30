<?php

namespace App\Support\V2;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysAppMenu;
use App\Support\Localization\Translator;
use App\Support\Menu\MenuRoutes;
use Illuminate\Support\Facades\DB;

// Menu v2 (x-dl::layer-shell) dari sys_app_menu (di mockup.test = ddtsys). Isi menu = induk di database, bukan kode.
// Hak akses: modul lewat SecUser::visibleAppMenuCodes() (sama dengan v1), PLUS required_object di grup/item
// (mis. Pro-Cost hanya grup yang punya akses COSTING). Admin melihat semua.
// Layar tiap item: target 'v2:<slug>' -> layar v2; selain itu layar v1 tmerp (MenuRoutes) supaya tetap bisa di-UAT.
final class AppMenu
{
    // Lencana layar v2 (user 2026-10-01): A hijau = sudah di-approve user (status LOCKED, hanya user yang mengunci);
    // R kuning = sudah dibangun tapi belum direview user (semua status selain LOCKED).
    private const APPROVED = ['A', 'done'];

    private const REVIEW = ['R', 'partial'];

    public static function modules(SecUser $user): array
    {
        $rows = SysAppMenu::query()->where('activate', true)->orderBy('sort_order')->orderBy('id')->get();
        $children = $rows->whereNotNull('parent_id')->groupBy('parent_id');
        $allowed = $user->is_admin ? null : $user->accessibleModules()->keys();
        // Direksi boleh melihat menu operasional (aturan user 2026-09-25); pendaratan awalnya Laporan Manajemen (login).
        $visible = $user->visibleAppMenuCodes();
        $ok = fn (SysAppMenu $n) => $allowed === null || $n->required_object === null || $n->required_object === SecUser::PUBLIC_MODULE
            || $allowed->contains($n->required_object);
        $routes = MenuRoutes::map();

        $modules = [];
        foreach ($rows->where('menu_type', 'module')->whereIn('code', $visible) as $m) {
            $out = [];
            foreach ($children->get($m->id, collect())->filter($ok) as $c) {
                if ($c->menu_type === 'group') {
                    $items = self::nodes($c, $children, $ok, $routes);
                    if ($items) {
                        $out[] = ['label' => self::label($c), 'icon' => $c->icon ?: 'fa-solid fa-folder', 'items' => $items];
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

        if (self::user($user)['developer']) {
            $modules[] = ['code' => 'DEV', 'short' => 'DEV', 'label' => 'Developer', 'icon' => 'fa-solid fa-code', 'children' => [
                ...($user->is_admin ? [['label' => 'Setting', 'items' => [['code' => 'V2-mail', 'label' => 'Setting E-mail (SMTP)', 'icon' => 'fa-solid fa-envelope', 'url' => route('v2.settings.mail'), 'type' => 'form']]]] : []),
                ['label' => 'Database', 'items' => [['code' => 'V2-dictionary', 'label' => 'Data Dictionary', 'icon' => 'fa-solid fa-database', 'url' => route('v2.dictionary'), 'type' => 'report'],
                    // Hanya di situs dummy (database ddt*): baseline & reset data UAT.
                    ...(str_starts_with(DB::connection('tm2sdi')->getDatabaseName(), 'ddt') ? [['code' => 'V2-dummy', 'label' => 'Data Dummy (Reset)', 'icon' => 'fa-solid fa-rotate-left', 'url' => route('v2.dummy.index'), 'type' => 'report']] : [])]],
            ]];
        }

        return $modules;
    }

    // Isi satu grup. Grup di dalam grup (mis. Inquiry & Quotation > Pro-Dev > ...) = submenu bertingkat
    // (user 2026-09-26), bukan diratakan: node ber-'items' yang dibuka Layer Menu sebagai panel berikutnya.
    private static function nodes(SysAppMenu $group, $children, callable $ok, array $routes): array
    {
        return $children->get($group->id, collect())->filter($ok)->map(function ($n) use ($children, $ok, $routes) {
            if ($n->menu_type !== 'group') {
                return self::item($n, $routes);
            }
            $items = self::nodes($n, $children, $ok, $routes);

            return $items ? ['code' => $n->code, 'label' => self::label($n), 'icon' => $n->icon ?: 'fa-solid fa-folder', 'items' => $items] : null;
        })->filter()->values()->all();
    }

    private static function item(SysAppMenu $i, array $routes): array
    {
        $v2 = str_starts_with((string) $i->target, 'v2:');
        [$badge, $tone] = $v2 ? ($i->status_code === 'LOCKED' ? self::APPROVED : self::REVIEW)
            : (isset($routes[$i->code]) ? ['v1', null] : ['Belum', 'todo']);

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
            'SALES' => [['label' => 'Alur', 'icon' => 'fa-solid fa-diagram-project', 'items' => [
                ['code' => 'F-SALES', 'label' => 'Alur Sales: Inquiry → CPDEL', 'icon' => 'fa-solid fa-diagram-project', 'url' => route('v2.flow', 'SALES'), 'type' => 'report'],
                ['code' => 'F-COSTING', 'label' => 'Alur Costing: ProDev → ProCost → Publish', 'icon' => 'fa-solid fa-diagram-project', 'url' => route('v2.flow', 'COSTING'), 'type' => 'report'],
                ['code' => 'G-PRODUCT', 'label' => 'Pedoman: Master Product & Revisi', 'icon' => 'fa-solid fa-book', 'url' => route('v2.guide', 'product-master'), 'type' => 'report'],
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

    // Mode Developer (F10) hanya untuk Administrator (is_admin) atau anggota grup security DEVELOPER.
    public const DEVELOPER_GROUP = 'DEVELOPER';

    public static function user(SecUser $u): array
    {
        $groups = $u->groups()->get(['group_code', 'group_name']);

        return ['name' => $u->name, 'email' => (string) $u->email, // Kartu profil = nama lengkap saja (user 2026-09-28: layar dilihat pemiliknya sendiri).
            'role' => null,
            'email_url' => route('v2.account.email'), 'email_verified' => (bool) $u->email_verified,
            'developer' => (bool) $u->is_admin || $groups->contains('group_code', self::DEVELOPER_GROUP)];
    }
}

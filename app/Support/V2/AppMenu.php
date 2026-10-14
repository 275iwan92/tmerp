<?php

/*
 * REMARK
 * Tujuan       : Menu v2 dari sys_app_menu; item berstatus hide_in_menu keluar dari sidebar tetapi layarnya tetap bisa dibuka lewat URL.
 * Sumber hulu  : sys_app_menu (kolom activate, hide_in_menu, target).
 * Input        : Pengguna aktif dan hak modul.
 * Output       : Pohon menu untuk x-dl::layer-shell.
 * Dipakai oleh : Layout v2 (resources/views/v2/layout.blade.php).
 * Status       : Aktif.
 */

namespace App\Support\V2;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysAppMenu;
use App\Services\Payroll\PayrollAccess;
use App\Support\Localization\Dictionary;
use App\Support\Localization\Translator;
use App\Support\Menu\MenuRoutes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $rows = SysAppMenu::query()->where('activate', true)
            ->when(self::hasHideFlag(), fn ($q) => $q->where('hide_in_menu', false))
            ->orderBy('sort_order')->orderBy('id')->get();
        $children = $rows->whereNotNull('parent_id')->groupBy('parent_id');
        $allowed = $user->is_admin ? null : $user->accessibleModules()->keys();
        // Direksi boleh melihat menu operasional (aturan user 2026-09-25); pendaratan awalnya Laporan Manajemen (login).
        $visible = $user->visibleAppMenuCodes();
        $ok = fn (SysAppMenu $n) => PayrollAccess::menuAllowed($n->code, $user) && ($allowed === null || $n->required_object === null
            || $n->required_object === SecUser::PUBLIC_MODULE || $allowed->contains($n->required_object));
        // "General" is an access flag, not a menu (user 2026-10-04): a module the user has no right to still shows, but only
        // its branches marked GENERAL (groups without a right of their own pass so the GENERAL items inside are reachable).
        $okPublic = fn (SysAppMenu $n) => $n->required_object === SecUser::PUBLIC_MODULE || ($n->menu_type === 'group' && $n->required_object === null);
        $routes = MenuRoutes::map();

        $modules = [];
        foreach ($rows->where('menu_type', 'module') as $m) {
            $pass = $visible->contains($m->code) ? $ok : $okPublic;
            $out = [];
            foreach ($children->get($m->id, collect())->filter($pass) as $c) {
                if ($c->menu_type === 'group') {
                    $items = self::nodes($c, $children, $pass, $routes);
                    if ($items) {
                        $out[] = ['label' => self::label($c), 'icon' => $c->icon ?: 'fa-solid fa-folder', 'items' => $items];
                    }
                } else {
                    $out[] = self::item($c, $routes);
                }
            }
            if ($out) {
                $modules[] = ['code' => $m->code, 'short' => $m->short_label, 'label' => self::label($m), 'icon' => $m->icon ?: 'fa-solid fa-folder', 'children' => $out];
            }
        }

        if (self::user($user)['developer']) {
            $modules[] = ['code' => 'DEV', 'short' => 'DEV', 'label' => t('Developer'), 'icon' => 'fa-solid fa-code', 'children' => [
                ...($user->is_admin ? [['label' => t('Setting'), 'items' => [['code' => 'V2-mail', 'label' => t('E-mail Setting (SMTP)'), 'icon' => 'fa-solid fa-envelope', 'url' => route('v2.settings.mail'), 'type' => 'form']]]] : []),
                ['label' => t('Database'), 'items' => [['code' => 'V2-dictionary', 'label' => t('Data Dictionary'), 'icon' => 'fa-solid fa-database', 'url' => route('v2.dictionary'), 'type' => 'report'],
                    // Hanya di situs dummy (database ddt*): baseline & reset data UAT.
                    ...(str_starts_with(DB::connection('tm2sdi')->getDatabaseName(), 'ddt') ? [['code' => 'V2-dummy', 'label' => t('Dummy Data (Reset)'), 'icon' => 'fa-solid fa-rotate-left', 'url' => route('v2.dummy.index'), 'type' => 'report'],
                        ['code' => 'V2-dummy-mastercard', 'label' => t('Master Card Dummy (PDF)'), 'icon' => 'fa-solid fa-file-pdf', 'url' => route('v2.dummy.mastercard'), 'type' => 'report']] : [])]],
                // Daftar engine: input, output, cara kerja (App\Support\Engines\EngineRegistry; sama dengan devlib.test > Engines).
                ['label' => t('Engines'), 'items' => [['code' => 'V2-engines', 'label' => t('Engine Catalog'), 'icon' => 'fa-solid fa-gears', 'url' => route('v2.engines'), 'type' => 'report']]],
            ]];
        }

        return $modules;
    }

    // Nama modul seperti tampil di Layer Menu (sys_app_menu), supaya breadcrumb layar memakai label yang sama dan tautannya
    // bisa membuka cabang menu itu (mis. kode PROD = "Manufacture", bukan "Production" dari katalog layar). null = tidak ada.
    public static function moduleLabel(string $code): ?string
    {
        $m = SysAppMenu::query()->where('menu_type', 'module')->where('code', $code)->first();

        return $m ? self::label($m) : null;
    }

    // Column arrives with a migration; the live site (tm2sys) may not have it yet.
    private static function hasHideFlag(): bool
    {
        static $has = null;

        return $has ??= Schema::connection('tm2sys')->hasColumn('sys_app_menu', 'hide_in_menu');
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
        $v2 = str_starts_with((string) $i->target, 'v2:') || str_starts_with((string) $i->target, '/');
        [$badge, $tone] = $v2 ? ($i->status_code === 'LOCKED' ? self::APPROVED : self::REVIEW)
            : (isset($routes[$i->code]) ? ['v1', null] : ['To Do', 'todo']);

        return ['code' => $i->code, 'label' => self::label($i), 'icon' => $i->icon ?: null, 'type' => $i->object_type ?: 'form',
            'url' => route('v2.item', $i->code), 'badge' => $badge, 'badgeTone' => $tone];
    }

    private static function label(SysAppMenu $n): string
    {
        // Label menu ditulis English di sys_app_menu dan diterjemahkan kamus saat tampil (LOCKED 2026-10-01). Kunci
        // Translator v1 (trans_key) masih dihormati bila punya terjemahan sendiri.
        if ($n->trans_key && ($v = Translator::t($n->trans_key, $n->label)) !== $n->label) {
            return $v;
        }

        return Dictionary::t($n->label, 'ui', null, 'menu');
    }

    // URL tujuan satu item: layar v2 (target v2:slug), layar v1, atau null (belum ada layar).
    public static function resolve(string $code): array
    {
        $i = SysAppMenu::query()->where('code', $code)->first();
        abort_unless($i, 404);
        abort_unless(self::active($i, SysAppMenu::query()->get()->keyBy('id')), 404);
        if (str_starts_with((string) $i->target, '/')) {
            return ['v2', url($i->target), $i];
        }
        if (str_starts_with((string) $i->target, 'v2:')) {
            return ['v2', route('v2.screen', substr($i->target, 3)), $i];
        }
        $v1 = MenuRoutes::map()[$code] ?? null;

        return $v1 ? ['v1', $v1, $i] : ['none', null, $i];
    }

    // Layar tanpa baris menu sendiri (form dari tombol di grid, dsb.) ikut hidup-mati layar induknya.
    private const PART_OF = ['inquiry-new' => 'inquiry', 'inquiry-revise' => 'inquiry', 'inquiry-recalc' => 'inquiry', 'nota-inquiry' => 'inquiry',
        'sales-price' => 'sales-procost', 'procost' => 'sales-procost', 'procost-instant' => 'sales-procost'];

    // Menu = data yang menunjuk ke layar (cara ScriptCase): layar boleh muncul di banyak menu, cukup tambah baris ber-target sama.
    // Layar terbuka selama masih ada SATU baris menu aktif (beserta semua induknya) yang menunjuk ke situ; modul dimatikan ->
    // layarnya tertutup kecuali modul lain juga memakainya. Layar yang tidak dikelola menu selalu terbuka.
    public static function open(string $slug): bool
    {
        $by = SysAppMenu::query()->get()->keyBy('id');
        $rows = $by->where('target', 'v2:'.(self::PART_OF[$slug] ?? $slug));

        return $rows->isEmpty() || $rows->contains(fn (SysAppMenu $r) => self::active($r, $by));
    }

    private static function active(SysAppMenu $i, $by): bool
    {
        for ($p = $i; $p; $p = $by->get($p->parent_id)) {
            if (! $p->activate) {
                return false;
            }
        }

        return true;
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

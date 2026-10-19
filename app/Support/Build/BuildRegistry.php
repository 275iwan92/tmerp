<?php

namespace App\Support\Build;

use App\Support\Engines\EngineRegistry;
use App\Support\V2\Grid\DbLookup;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\Finder;

// Papan Build Progress (user 2026-10-05): angka pembangunan v2 untuk dipantau bersama (user, agen Iwan, agen Jo) di
// halaman depan devlib.test. Semua angka DIHITUNG dari menu (sys_app_menu), daftar grid, daftar engine dan berkas kode;
// hanya FEATURES dan NOTES yang ditulis tangan (fitur tidak bisa dihitung dari kode) -- ubah di sini, lalu
// `php artisan build:catalog --env=mockup`.
final class BuildRegistry
{
    // [ikon, teks English]. Tambah satu baris tiap fitur lintas-modul selesai dibangun.
    public const FEATURES = [
        ['fa-language', 'Multi-language + dictionary'],
        ['fa-stamp', 'Approval flow, all documents'],
        ['fa-print', 'Print A5 / A4 + QR + Email'],
        ['fa-comments', 'Chat'],
        ['fa-robot', 'Offline assistant'],
        ['fa-table-cells-large', 'Opening widget board'],
        ['fa-warehouse', 'Warehouse map + pallets'],
        ['fa-tags', 'Labels: location, item, pallet'],
        ['fa-palette', 'Themes 4 modes x 3 colours'],
        ['fa-layer-group', 'Layer Menu from database'],
        ['fa-window-restore', 'Side Screen (float, resize)'],
        ['fa-code', 'Developer mode F10'],
        ['fa-sitemap', 'Item code builder (Category 1-5)'],
        ['fa-flask', 'Dummy stage sets + Reset'],
        ['fa-life-ring', 'Help & support + tickets'],
        ['fa-shield-halved', 'Guard tests (ratchet)'],
    ];

    // Pekerjaan yang belum ada dan tidak terlihat dari menu.
    public const NOTES = [
        'Payroll encryption: decided, not installed',
        '3D mockup generator for Inquiry Product',
    ];

    // Dua agen pengembang (user 2026-10-05). Commit ditandai trailer `Agent: Iwan` / `Agent: Jo`; commit tanpa tanda =
    // "Before tracking" (git lama tidak bisa membedakan keduanya -- jangan ditebak dari hari).
    public const AGENTS = ['iwan' => 'Agent Iwan', 'jo' => 'Agent Jo'];

    private const DAYS = 14;

    // Tag di repo DevLib pada commit hasil pemisahan dari tmerp (2026-10-19): commit sesudahnya belum ada di git tmerp.
    private const DEVLIB_SPLIT = 'split-from-tmerp';

    private const CONNECTIONS = ['tm2mst', 'tm2sdi', 'tm2pur', 'tm2inv', 'tm2prd', 'tm2acc', 'tm2hrm', 'tm2hrr', 'tm2pyr', 'tm2mnt', 'tm2sys'];

    public static function snapshot(): array
    {
        $modules = self::modules();
        $sum = fn (string $k) => array_sum(array_column($modules, $k));
        $grids = collect((new \ReflectionClass(Grids::class))->getConstant('ALL'));
        $tables = self::tables();
        $engines = EngineRegistry::all();

        return [
            'generated' => now()->format('Y-m-d H:i'),
            'database' => DB::connection('tm2sys')->getDatabaseName(),
            'totals' => [
                'objects' => (new Finder)->files()->in(self::devlibDir().'/src/Components')->name('*.php')->count() - 1,   // tanpa induk abstrak DevlibComponent
                'modules' => count($modules),
                'modules_done' => count(array_filter($modules, fn ($m) => $m['v2'] === $m['total'])),
                'screens' => $sum('total'), 'v2' => $sum('v2'), 'v1' => $sum('v1'), 'not_built' => $sum('not_built'),
                'waiting_qc' => $sum('waiting_qc'), 'locked' => $sum('locked'),
                'forms' => $sum('form'), 'grids' => $sum('grid'), 'reports' => $sum('report'),
                'grid_classes' => $grids->count(),
                'lookups' => $grids->filter(fn ($c) => is_subclass_of($c, DbLookup::class))->count(),
                'report_grids' => $grids->filter(fn ($c) => str_ends_with($c, 'Report'))->count(),
                'engines' => count($engines),
                'engine_groups' => count(array_unique(array_column($engines, 'group'))),
                'tables' => array_sum($tables),
                'databases' => count(array_filter($tables)),
                'models' => self::files('app/Models'),
                'migrations' => self::files('database/migrations'),
                'tests' => self::files('tests', '*Test.php'),
                'dictionary' => DB::connection('tm2sys')->table('sys_dictionary')->count(),
                'languages' => DB::connection('tm2sys')->table('sys_languages')->count(),
                'commits' => (int) self::git('rev-list --count HEAD') + (int) self::devlibGit('rev-list --count '.self::DEVLIB_SPLIT.'..HEAD'),
                'since' => self::git('log --reverse --format=%ad --date=short', true),
            ],
            'modules' => $modules,
            ...self::work(),
            'items' => self::items($engines),
            'engines' => self::engines($engines),
            'features' => self::FEATURES,
            'notes' => self::NOTES,
        ];
    }

    // Satu baris per modul menu: layar aktif yang punya tujuan. v2 = target 'v2:<slug>'; selain itu NOT_BUILT = belum
    // dibangun, sisanya masih membuka layar v1. waiting_qc / locked = status kesiapan (LOCKED hanya oleh user).
    private static function modules(): array
    {
        $rows = collect(DB::connection('tm2sys')->select('select id, parent_id, label, object_type, target, activate, status_code, sort_order from sys_app_menu'))->keyBy('id');
        $out = [];
        foreach ($rows as $r) {
            if (! $r->activate || ! $r->target) {
                continue;
            }
            $root = $r;
            for ($i = 0; $root->parent_id && isset($rows[$root->parent_id]) && $i < 10; $i++) {
                $root = $rows[$root->parent_id];
            }
            $m = &$out[$root->id];
            $m ??= ['label' => $root->label, 'sort' => (int) $root->sort_order, 'total' => 0, 'v2' => 0, 'v1' => 0, 'not_built' => 0,
                'waiting_qc' => 0, 'locked' => 0, 'form' => 0, 'grid' => 0, 'report' => 0];
            $m['total']++;
            $status = $r->status_code ?? 'NOT_BUILT';
            $m[str_starts_with($r->target, 'v2:') ? 'v2' : ($status === 'NOT_BUILT' ? 'not_built' : 'v1')]++;
            $m['waiting_qc'] += (int) ($status === 'READY_FOR_QC');
            $m['locked'] += (int) ($status === 'LOCKED');
            if (in_array($r->object_type, ['form', 'grid', 'report'], true)) {
                $m[$r->object_type]++;
            }
            unset($m);
        }
        usort($out, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return array_map(fn ($m) => array_diff_key($m, ['sort' => 1]), $out);
    }

    private static function engines(array $engines): array
    {
        $out = [];
        foreach ($engines as $e) {
            $out[$e['group']] ??= ['group' => $e['group'], 'items' => []];
            $out[$e['group']]['items'][] = ['key' => $e['key'], 'name' => $e['name'], 'purpose' => $e['purpose'], 'mode' => $e['mode'],
                'used' => count($e['used'] ?? []), 'tests' => count($e['tests'] ?? [])];
        }

        return array_values($out);
    }

    /** @var array<string, array{first: string, first_by: ?string, last: string, last_by: ?string}>|null riwayat per berkas dari git */
    private static ?array $fileLog = null;

    // Siapa mengerjakan apa dan kapan, dari git: per agen = commit, hari aktif, terakhir aktif, berkas yang disentuh per
    // jenis (layar v2, objek DevLib, engine, test); 'days' = commit per hari 14 hari terakhir; 'weeks' = per minggu, seluruh riwayat.
    private static function work(): array
    {
        $format = ['git', 'log', '--date=short', '--name-only', '--format=@@%ad%x1f%(trailers:key=Agent,valueonly,separator=%x2C)'];
        $r = Process::path(base_path())->run($format);
        $dev = Process::path(self::devlibDir())->run([...$format, self::DEVLIB_SPLIT.'..HEAD']);
        // DevLib hidup di repo sendiri (Herd\devlib); riwayat sebelum pemisahan ada di git tmerp, sesudahnya di repo itu.
        // Berkasnya diberi awalan lama packages/devlib/ supaya satu kunci per objek. Terbaru dulu: log DevLib didahulukan.
        $devLines = $dev->successful() ? array_map(fn ($l) => $l === '' || str_starts_with($l, '@@') ? $l : 'packages/devlib/'.$l, preg_split('/\R/', $dev->output())) : [];
        $engineFiles = array_column(EngineRegistry::catalog(), 'file');
        $kind = fn (string $f) => match (true) {
            str_starts_with($f, 'resources/views/v2/') || str_starts_with($f, 'app/Support/V2/Grid/') || str_starts_with($f, 'app/Support/V2/Data/') => 'screens',
            str_starts_with($f, 'packages/devlib/src/Components/') => 'objects',
            in_array($f, $engineFiles, true) => 'engines',
            str_starts_with($f, 'tests/') => 'tests',
            default => null,
        };
        $blank = ['commits' => 0, 'days' => [], 'last' => null, 'screens' => [], 'objects' => [], 'engines' => [], 'tests' => []];
        $agents = array_fill_keys(array_keys(self::AGENTS), $blank);
        $untracked = 0;
        $perDay = $perWeek = $files = [];
        $who = $date = null;
        foreach ([...$devLines, ...($r->successful() ? preg_split('/\R/', $r->output()) : [])] as $line) {
            if (str_starts_with($line, '@@')) {
                [$date, $tag] = array_pad(explode("\x1f", substr($line, 2)), 2, '');
                $who = strtolower(trim(explode(',', $tag)[0]));
                $who = isset($agents[$who]) ? $who : null;
                $week = date('o-\WW', strtotime($date));
                $perDay[$date][$who ?? 'other'] = ($perDay[$date][$who ?? 'other'] ?? 0) + 1;
                $perWeek[$week][$who ?? 'other'] = ($perWeek[$week][$who ?? 'other'] ?? 0) + 1;
                if ($who === null) {
                    $untracked++;

                    continue;
                }
                $agents[$who]['commits']++;
                $agents[$who]['days'][$date] = true;
                $agents[$who]['last'] = max($agents[$who]['last'] ?? $date, $date);
            } elseif ($line !== '' && $date !== null) {
                // terbaru dulu: 'last' diisi sekali, 'first' ditimpa terus sampai commit tertua
                $files[$line] = ['first' => $date, 'first_by' => $who] + ($files[$line] ?? ['last' => $date, 'last_by' => $who]);
                if ($who !== null && ($k = $kind($line))) {
                    $agents[$who][$k][$line] = true;
                }
            }
        }
        self::$fileLog = $files;
        $keys = [...array_keys(self::AGENTS), 'other'];
        $row = fn (array $counts) => array_map(fn ($k) => $counts[$k] ?? 0, array_combine($keys, $keys));
        $days = [];
        for ($i = self::DAYS - 1; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $days[] = ['date' => $d] + $row($perDay[$d] ?? []);
        }
        ksort($perWeek);

        return [
            'agents' => array_map(fn ($key, $a) => ['key' => $key, 'name' => self::AGENTS[$key], 'commits' => $a['commits'], 'days' => count($a['days']), 'last' => $a['last'],
                'screens' => count($a['screens']), 'objects' => count($a['objects']), 'engines' => count($a['engines']), 'tests' => count($a['tests'])], array_keys($agents), $agents),
            'untracked' => $untracked,
            'days' => $days,
            'weeks' => array_map(fn ($w, $c) => ['week' => $w] + $row($c), array_keys($perWeek), $perWeek),
        ];
    }

    // Daftar hasil kerja yang dinilai user di devlib.test (Executive Summary > By Object): tiap layar menu (form / grid /
    // report), engine, objek DevLib dan fitur. built / changed = commit pertama / terakhir berkasnya; *_by = agen bila
    // commit itu bertanda, null = sebelum pelacakan. Dipanggil sesudah work() (memakai riwayat berkas yang sama).
    private static function items(array $engines): array
    {
        $log = self::$fileLog ?? [];
        $hist = function (array $paths) use ($log) {
            $h = array_values(array_filter(array_map(fn ($f) => $log[$f] ?? null, $paths)));
            if (! $h) {
                return ['built_at' => null, 'built_by' => null, 'changed_at' => null, 'changed_by' => null];
            }
            usort($h, fn ($a, $b) => $a['first'] <=> $b['first']);
            $first = $h[0];
            usort($h, fn ($a, $b) => $b['last'] <=> $a['last']);

            return ['built_at' => $first['first'], 'built_by' => $first['first_by'], 'changed_at' => $h[0]['last'], 'changed_by' => $h[0]['last_by']];
        };
        $rel = fn (string $class) => str_replace('\\', '/', ltrim(str_replace(base_path(), '', (new \ReflectionClass($class))->getFileName()), '\\/'));

        $gridFile = [];
        foreach ((new \ReflectionClass(Grids::class))->getConstant('ALL') as $class) {
            try {
                $gridFile[app($class)->slug()] = $rel($class);
            } catch (\Throwable) {
                // grid yang tidak bisa dibuat tanpa konteks: dilewati, layarnya tetap terdaftar lewat menu
            }
        }

        $out = [];
        $rows = collect(DB::connection('tm2sys')->select('select id, parent_id, code, label, object_type, target, activate, status_code, sort_order from sys_app_menu'))->keyBy('id');
        foreach ($rows->sortBy('sort_order') as $r) {
            if (! $r->activate || ! $r->target) {
                continue;
            }
            $root = $r;
            for ($i = 0; $root->parent_id && isset($rows[$root->parent_id]) && $i < 10; $i++) {
                $root = $rows[$root->parent_id];
            }
            $slug = str_starts_with($r->target, 'v2:') ? substr($r->target, 3) : null;
            $paths = $slug ? array_filter(["resources/views/v2/screens/{$slug}.blade.php", $gridFile[$slug] ?? null]) : [];
            $out[] = ['type' => strtoupper($r->object_type ?: 'screen'), 'key' => 'menu:'.($r->code ?: $r->id), 'name' => $r->label, 'module' => $root->label,
                'qc' => $slug ? ($r->status_code ?? 'NOT_BUILT') : (($r->status_code ?? 'NOT_BUILT') === 'NOT_BUILT' ? 'NOT_BUILT' : 'V1')] + $hist($paths);
        }
        foreach ($engines as $e) {
            $file = isset($e['class']) ? $rel($e['class']) : $e['file'];
            $out[] = ['type' => 'ENGINE', 'key' => 'engine:'.$e['key'], 'name' => $e['name'], 'module' => $e['group'], 'qc' => 'READY_FOR_QC'] + $hist([$file]);
        }
        foreach ((new Finder)->files()->in(self::devlibDir().'/src/Components')->name('*.php')->sortByName() as $f) {
            $path = 'packages/devlib/src/Components/'.str_replace('\\', '/', $f->getRelativePathname());
            $name = str_replace(['/', '.php'], [' · ', ''], str_replace('\\', '/', $f->getRelativePathname()));
            if ($name !== 'DevlibComponent') {
                $out[] = ['type' => 'OBJECT', 'key' => 'object:'.$name, 'name' => $name, 'module' => 'DevLib', 'qc' => 'READY_FOR_QC'] + $hist([$path]);
            }
        }
        foreach (self::FEATURES as [$icon, $text]) {
            $out[] = ['type' => 'FEATURE', 'key' => 'feature:'.$text, 'name' => $text, 'module' => 'Features', 'qc' => 'READY_FOR_QC'] + $hist([]);
        }

        return $out;
    }

    private static function tables(): array
    {
        $out = [];
        foreach (self::CONNECTIONS as $cn) {
            $db = DB::connection($cn)->getDatabaseName();
            $out[$db] = (int) DB::connection($cn)->selectOne("select count(*) c from information_schema.tables where table_schema = ? and table_type = 'BASE TABLE'", [$db])->c;
        }

        return $out;
    }

    private static function files(string $dir, string $name = '*.php'): int
    {
        return (new Finder)->files()->in(base_path($dir))->name($name)->count();
    }

    // Akar paket DevLib (repo sendiri di samping tmerp), dicari dari kelas paketnya supaya tidak bergantung pada nama folder.
    private static function devlibDir(): string
    {
        return str_replace('\\', '/', dirname((string) realpath((new \ReflectionClass(\Devlib\DevlibServiceProvider::class))->getFileName()), 2));
    }

    private static function devlibGit(string $args): ?string
    {
        return self::git($args, false, self::devlibDir());
    }

    private static function git(string $args, bool $firstLine = false, ?string $dir = null): ?string
    {
        $r = Process::path($dir ?? base_path())->run('git '.$args);
        if (! $r->successful()) {
            return null;
        }
        $out = trim($r->output());

        return $firstLine ? strtok($out, "\n") : $out;
    }
}

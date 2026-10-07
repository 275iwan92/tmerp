<?php

namespace App\Support;

use Devlib\Components\EngineCatalog;
use Devlib\Components\LayerShell;
use Devlib\Locale;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Executive Summary devlib.test (user 2026-10-05). Angka pembangunan = berkas yang dibuat tmerp
// (`php artisan build:catalog --env=mockup` -> resources/data/v2/build.json); daftar hasil kerja + rating = database devlib
// (dev_item, dev_rating). sync() menyalin daftar dari berkas ke dev_item tiap berkasnya berubah.
final class Summary
{
    // slug => [label, ikon, pertanyaan yang dijawab]
    public const PAGES = [
        'overview' => ['Overview', 'fa-solid fa-gauge-high', 'Where does the project stand today?'],
        'module' => ['By Module', 'fa-solid fa-layer-group', 'Which module is complete, which is not, and what is still missing?'],
        'object' => ['By Object', 'fa-solid fa-shapes', 'Every form, grid, report, engine and DevLib object: who built it, when, and the rating.'],
        'engine' => ['By Engine', 'fa-solid fa-gears', 'Is each engine used and tested?'],
        'feature' => ['By Feature', 'fa-solid fa-puzzle-piece', 'Which cross-module features exist, and how are they rated?'],
        'agent' => ['By Agent', 'fa-solid fa-user-gear', 'Who did the most, and how good was it?'],
        'time' => ['By Time', 'fa-solid fa-calendar-days', 'When was the work done?'],
        'quality' => ['Quality & Rating', 'fa-solid fa-star', 'How satisfied is the user with what was built?'],
        'open' => ['Open Work', 'fa-solid fa-triangle-exclamation', 'What is not built or not reviewed yet?'],
    ];

    public const TYPES = ['FORM' => 'Form', 'GRID' => 'Grid', 'REPORT' => 'Report', 'DASHBOARD' => 'Dashboard', 'ENGINE' => 'Engine', 'OBJECT' => 'Object', 'FEATURE' => 'Feature'];

    public const QC = ['LOCKED' => ['Locked', 'success'], 'READY_FOR_QC' => ['Waiting QC', 'warn'], 'V1' => ['Still v1', 'info'], 'NOT_BUILT' => ['Not built', 'neutral']];

    public const AGENTS = ['iwan' => 'Agent Iwan', 'jo' => 'Agent Jo'];

    public static function build(): array
    {
        return Catalog::build();
    }

    // Modul menu "Executive Summary" (kode MGMT = view kedua Layer Menu, ikon tukar Operational | Executive Summary).
    public static function menu(): array
    {
        $items = [];
        foreach (self::PAGES as $slug => [$label, $icon]) {
            $items[] = ['code' => 'S-'.$slug, 'label' => $label, 'icon' => $icon, 'url' => route('summary', ['page' => $slug]), 'type' => 'report'];
        }

        // Agent History (user 2026-10-07): submenu tersendiri untuk catatan pengingat agen Iwan / Jo.
        $unread = AgentHistory::unread();
        $items[] = ['code' => 'S-history', 'label' => 'Historical', 'icon' => 'fa-solid fa-clock-rotate-left', 'url' => route('history'), 'type' => 'grid'] + ($unread ? ['badge' => (string) $unread, 'badgeTone' => 'partial'] : []);

        $items[] = ['code' => 'S-agent-log', 'label' => 'Agent Log', 'icon' => 'fa-solid fa-list-check', 'url' => route('agent-log'), 'type' => 'grid'];

        return ['code' => LayerShell::REPORT_MODULE, 'short' => 'EX', 'label' => 'Executive Summary', 'icon' => 'fa-solid fa-chart-line', 'children' => $items];
    }

    public static function sync(): void
    {
        $file = base_path('../resources/data/v2/build.json');
        $stamp = is_file($file) ? filemtime($file) : 0;
        if (! $stamp || Cache::get('summary.synced') === $stamp) {
            return;
        }
        $now = now();
        $rows = array_map(fn ($i) => [
            'item_type' => $i['type'], 'item_key' => $i['key'], 'name' => mb_substr($i['name'], 0, 190), 'module' => $i['module'], 'qc_status' => $i['qc'],
            'built_by' => $i['built_by'], 'built_at' => $i['built_at'], 'changed_by' => $i['changed_by'], 'changed_at' => $i['changed_at'],
            'activate' => true, 'created_at' => $now, 'updated_at' => $now,
        ], self::build()['items'] ?? []);
        if ($rows) {
            DB::table('dev_item')->update(['activate' => false]);
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('dev_item')->upsert($chunk, ['item_key'], ['item_type', 'name', 'module', 'qc_status', 'built_by', 'built_at', 'changed_by', 'changed_at', 'activate', 'updated_at']);
            }
        }
        Cache::forever('summary.synced', $stamp);
    }

    // Semua item aktif + penilaian TERAKHIR-nya (rating, note, rated_by, rated_at) dan jumlah penilaian.
    public static function items(): array
    {
        self::sync();
        $last = DB::table('dev_rating as r')->whereRaw('r.id_rating = (select max(x.id_rating) from dev_rating x where x.item_id = r.item_id)');

        return DB::table('dev_item as i')->leftJoinSub($last, 'r', 'r.item_id', '=', 'i.id_item')->where('i.activate', true)
            ->orderByRaw("field(i.item_type, 'FORM', 'GRID', 'REPORT', 'DASHBOARD', 'ENGINE', 'OBJECT', 'FEATURE')")->orderBy('i.module')->orderBy('i.name')
            ->get(['i.*', 'r.rating', 'r.note', 'r.rated_by', 'r.rated_at',
                DB::raw('(select count(*) from dev_rating c where c.item_id = i.id_item) as ratings')])
            ->map(fn ($r) => (array) $r)->all();
    }

    public static function rate(int $itemId, int $rating, ?string $note, string $by): array
    {
        $item = DB::table('dev_item')->where('id_item', $itemId)->first() ?? abort(404);
        abort_unless($rating >= 1 && $rating <= 5, 422);
        DB::table('dev_rating')->insert(['item_id' => $itemId, 'rating' => $rating, 'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            'agent' => $item->changed_by ?? $item->built_by, 'rated_by' => $by, 'rated_at' => now()]);

        return ['ok' => true, 'ratings' => DB::table('dev_rating')->where('item_id', $itemId)->count()];
    }

    // Data satu halaman Executive Summary.
    public static function page(string $page, array $user): array
    {
        [$label, , $question] = self::PAGES[$page];
        $build = self::build();
        $items = self::items();
        $ratings = self::ratings($items);
        $t = $build['totals'] ?? [];
        $num = fn ($v, int $d = 0) => $v === null ? '-' : Locale::number((float) $v, $d);
        $data = ['page' => $page, 'label' => $label, 'question' => $question, 'build' => $build, 'items' => $items, 'ratings' => $ratings,
            'canRate' => (bool) ($user['developer'] ?? false), 'open' => self::open($build, $ratings),
            'pace' => array_sum(array_map(fn ($d) => $d['iwan'] + $d['jo'] + $d['other'], array_slice($build['days'] ?? [], -7)))];

        if ($page === 'module') {
            $data['modules'] = array_map(function (array $m) use ($items, $num) {
                $mine = array_values(array_filter($items, fn ($i) => $i['module'] === $m['label'] && str_starts_with($i['item_key'], 'menu:')));
                $rated = array_values(array_filter($mine, fn ($i) => $i['rating'] !== null));

                return $m + [
                    'state' => $m['v2'] === $m['total'] ? 'Built' : ($m['v2'] > 0 ? 'Partial' : 'Not started'),
                    'screens' => $num($m['v2']).' / '.$num($m['total']), 'pct' => Locale::percent($m['total'] ? $m['v2'] / $m['total'] * 100 : 0, 0),
                    'rated' => count($rated), 'avg' => $rated ? $num(array_sum(array_column($rated, 'rating')) / count($rated), 1) : '-',
                    'missing' => implode(', ', array_filter([$m['not_built'] ? $m['not_built'].' not built' : null, $m['v1'] ? $m['v1'].' still v1' : null])),
                ];
            }, $build['modules'] ?? []);
        }
        if ($page === 'engine') {
            $modes = EngineCatalog::MODES;
            $data['engines'] = array_merge([], ...array_map(fn ($g) => array_map(fn ($e) => $e + ['group' => $g['group'], 'mode' => $modes[$e['mode']][0] ?? $e['mode'],
                'health' => ($e['tests'] ?? 0) > 0 ? 'Tested' : 'No test registered'], $g['items']), $build['engines'] ?? []));
        }
        if ($page === 'agent') {
            $agents = collect($build['agents'] ?? [])->keyBy('key');
            $cell = fn (string $k, string $f) => $num($agents[$k][$f] ?? 0);
            $built = fn (?string $a, ?array $types = null) => count(array_filter($items, fn ($i) => $i['built_by'] === $a && ($types === null || in_array($i['item_type'], $types, true))));
            $r = $ratings['agents'];
            $rows = [
                ['measure' => 'Commits', 'iwan' => $cell('iwan', 'commits'), 'jo' => $cell('jo', 'commits'), 'other' => $num($build['untracked'] ?? 0)],
                ['measure' => 'Active days', 'iwan' => $cell('iwan', 'days'), 'jo' => $cell('jo', 'days'), 'other' => '-'],
                ['measure' => 'Last active', 'iwan' => ($agents['iwan']['last'] ?? null) ? Locale::date($agents['iwan']['last']) : '-', 'jo' => ($agents['jo']['last'] ?? null) ? Locale::date($agents['jo']['last']) : '-', 'other' => '-'],
                ['measure' => 'Screens first built (form, grid, report)', 'iwan' => $num($built('iwan', ['FORM', 'GRID', 'REPORT', 'DASHBOARD'])), 'jo' => $num($built('jo', ['FORM', 'GRID', 'REPORT', 'DASHBOARD'])), 'other' => $num($built(null, ['FORM', 'GRID', 'REPORT', 'DASHBOARD']))],
                ['measure' => 'Engines first built', 'iwan' => $num($built('iwan', ['ENGINE'])), 'jo' => $num($built('jo', ['ENGINE'])), 'other' => $num($built(null, ['ENGINE']))],
                ['measure' => 'DevLib objects first built', 'iwan' => $num($built('iwan', ['OBJECT'])), 'jo' => $num($built('jo', ['OBJECT'])), 'other' => $num($built(null, ['OBJECT']))],
                ['measure' => 'Screen files touched', 'iwan' => $cell('iwan', 'screens'), 'jo' => $cell('jo', 'screens'), 'other' => '-'],
                ['measure' => 'Test files touched', 'iwan' => $cell('iwan', 'tests'), 'jo' => $cell('jo', 'tests'), 'other' => '-'],
                ['measure' => 'Items rated by the user', 'iwan' => $num($r['iwan']['rated']), 'jo' => $num($r['jo']['rated']), 'other' => $num($r['other']['rated'])],
                ['measure' => 'Average rating', 'iwan' => $num($r['iwan']['avg'], 1), 'jo' => $num($r['jo']['avg'], 1), 'other' => $num($r['other']['avg'], 1)],
                ['measure' => 'Rated 3 stars or less', 'iwan' => $num($r['iwan']['low']), 'jo' => $num($r['jo']['low']), 'other' => $num($r['other']['low'])],
            ];
            $data['agentRows'] = $rows;
        }
        if ($page === 'time') {
            $builtPerWeek = [];
            foreach ($items as $i) {
                if ($i['built_at']) {
                    $w = date('o-\WW', strtotime($i['built_at']));
                    $builtPerWeek[$w] = ($builtPerWeek[$w] ?? 0) + 1;
                }
            }
            $data['weekRows'] = array_reverse(array_map(fn ($w) => $w + ['total' => $w['iwan'] + $w['jo'] + $w['other'], 'built' => $builtPerWeek[$w['week']] ?? 0], $build['weeks'] ?? []));
        }

        return $data;
    }

    // Pekerjaan terbuka: dihitung dari modul, daftar rating dan catatan aplikasi.
    public static function open(array $build, array $ratings): array
    {
        $t = $build['totals'] ?? [];
        $out = [];
        foreach ($build['modules'] ?? [] as $m) {
            if ($m['not_built'] > 0) {
                $out[] = ['kind' => 'Not built', 'text' => $m['label'].': screens not built', 'count' => $m['not_built']];
            }
            if ($m['v1'] > 0) {
                $out[] = ['kind' => 'Still v1', 'text' => $m['label'].': screens still open the old v1 screen', 'count' => $m['v1']];
            }
        }
        if (($t['waiting_qc'] ?? 0) > 0) {
            $out[] = ['kind' => 'Review', 'text' => 'Screens built, waiting for user QC', 'count' => $t['waiting_qc']];
        }
        if ($ratings['total'] - $ratings['rated'] > 0) {
            $out[] = ['kind' => 'Review', 'text' => 'Items not rated yet', 'count' => $ratings['total'] - $ratings['rated']];
        }
        if ($ratings['low']) {
            $out[] = ['kind' => 'Fix', 'text' => 'Items rated 3 stars or less', 'count' => count($ratings['low'])];
        }
        foreach ($build['notes'] ?? [] as $note) {
            $out[] = ['kind' => 'Note', 'text' => $note, 'count' => null];
        }

        return $out;
    }

    // Ringkasan rating: sebaran bintang (penilaian terakhir per item), rata-rata, per agen, per jenis.
    public static function ratings(array $items): array
    {
        $rated = array_values(array_filter($items, fn ($i) => $i['rating'] !== null));
        $avg = fn (array $list) => $list ? round(array_sum(array_column($list, 'rating')) / count($list), 1) : null;
        $dist = array_fill_keys([5, 4, 3, 2, 1], 0);
        foreach ($rated as $i) {
            $dist[(int) $i['rating']]++;
        }
        $byAgent = [];
        foreach ([...array_keys(self::AGENTS), null] as $a) {
            $mine = array_values(array_filter($rated, fn ($i) => ($i['changed_by'] ?? $i['built_by']) === $a));
            $byAgent[$a ?? 'other'] = ['rated' => count($mine), 'avg' => $avg($mine), 'low' => count(array_filter($mine, fn ($i) => $i['rating'] <= 3))];
        }
        $byType = [];
        foreach (self::TYPES as $t => $label) {
            $all = array_values(array_filter($items, fn ($i) => $i['item_type'] === $t));
            $mine = array_values(array_filter($all, fn ($i) => $i['rating'] !== null));
            if ($all) {
                $byType[] = ['type' => $label, 'items' => count($all), 'rated' => count($mine), 'avg' => $avg($mine), 'low' => count(array_filter($mine, fn ($i) => $i['rating'] <= 3))];
            }
        }

        return ['total' => count($items), 'rated' => count($rated), 'avg' => $avg($rated), 'dist' => $dist, 'agents' => $byAgent, 'types' => $byType,
            'low' => array_values(array_filter($rated, fn ($i) => $i['rating'] <= 3))];
    }
}

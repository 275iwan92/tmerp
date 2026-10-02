<?php

namespace App\Support;

// Contoh papan widget (x-dl::widget-board) untuk devlib.test: isi widget diambil dari profil Management di
// DashboardData (angka contoh), susunan papan disimpan di sesi browser -- situs DevLib tanpa database.
// Di aplikasi (tmerp): katalog = tabel sys_widget, susunan = sys_user_widget, angka = kelas widget yang membaca database.
class BoardDemo
{
    private const SESSION = 'devlib_board';

    private const TEMPLATE = [['revenue', 3], ['margin', 3], ['overdue', 3], ['on_time', 3], ['trend', 8], ['targets', 4], ['pipeline', 6], ['ranking', 6]];

    private const SIZES = [3, 4, 6, 8, 12];

    private const SESSION_PERIOD = 'devlib_board_period';

    // kunci periode papan => [label, kunci periode di DashboardData]
    private const PERIODS = ['daily' => ['Daily', 'daily'], 'weekly' => ['Weekly', 'weekly'], 'monthly' => ['Monthly', 'monthly'],
        'quarterly' => ['Quarterly', 'q3'], 'semester' => ['Half-yearly', 'q6'], 'yearly' => ['Yearly', 'q12']];

    public static function period(): string
    {
        $p = (string) session(self::SESSION_PERIOD, 'monthly');

        return isset(self::PERIODS[$p]) ? $p : 'monthly';
    }

    /** @return array<string, array<string, mixed>> semua widget katalog contoh, kunci = key */
    public static function all(): array
    {
        $d = DashboardData::forLogin('carrij');
        $p = self::PERIODS[self::period()][1];
        $kpi = fn (int $i, string $key, string $title, string $group) => ['key' => $key, 'title' => $title, 'group' => $group, 'type' => 'kpi', 'size' => 3,
            'icon' => $d['kpis'][$p][$i]['icon'], 'tone' => $d['kpis'][$p][$i]['tone'], 'data' => $d['kpis'][$p][$i], 'description' => 'One number with trend and target.'];
        $t = $d['trend'][$p];
        $pl = $d['pipeline'][$p];

        return collect([
            $kpi(0, 'revenue', 'Company Revenue', 'Sales'),
            $kpi(1, 'margin', 'Gross Margin', 'Finance'),
            $kpi(2, 'overdue', 'Overdue Receivables', 'Finance'),
            $kpi(3, 'on_time', 'On-time Delivery', 'Delivery'),
            $kpi(4, 'new_orders', 'New Customer Orders', 'Sales'),
            ['key' => 'trend', 'title' => 'Revenue: Target vs Actual', 'caption' => 'Bars = actual, line = target', 'group' => 'Sales', 'type' => 'chart', 'size' => 8, 'icon' => 'fa-chart-column',
                'description' => 'Bars = actual, line = target, per month.',
                'data' => ['chart' => 'combo', 'format' => 'money', 'height' => '320px', 'labels' => $t['labels'],
                    'series' => [['name' => 'Actual', 'data' => $t['actual'], 'type' => 'bar', 'tone' => 'accent'], ['name' => 'Target', 'data' => $t['target'], 'type' => 'line', 'tone' => 'danger']]]],
            ['key' => 'targets', 'title' => 'Targets by Area', 'caption' => 'This period against target', 'group' => 'Management', 'type' => 'progress', 'size' => 4, 'icon' => 'fa-bullseye',
                'description' => 'One row per business target with its achievement.', 'data' => ['rows' => $d['targets'][$p]['rows']]],
            ['key' => 'pipeline', 'title' => 'Sales Pipeline', 'caption' => 'Document value in this period', 'group' => 'Sales', 'type' => 'chart', 'size' => 6, 'icon' => 'fa-filter',
                'description' => 'Funnel from Inquiry to Customer Order.',
                'data' => ['chart' => 'funnel', 'format' => 'money', 'height' => '260px', 'labels' => $pl['labels'], 'series' => [['name' => 'Value', 'data' => $pl['values']]]]],
            ['key' => 'ranking', 'title' => 'Target Achievement per Sales', 'caption' => 'This period, highest first', 'group' => 'Sales', 'type' => 'ranking', 'size' => 6, 'icon' => 'fa-ranking-star',
                'description' => 'Each sales person against the personal target.', 'data' => ['name_label' => 'Sales', 'format' => 'money', 'rows' => $d['ranking'][$p]['rows']]],
            ['key' => 'status', 'title' => 'Customer Orders by Status', 'caption' => 'This year', 'group' => 'Sales', 'type' => 'chart', 'size' => 4, 'icon' => 'fa-chart-pie',
                'description' => 'Donut: Draft, Confirmed, Closed, Cancelled.',
                'data' => ['chart' => 'donut', 'height' => '240px', 'labels' => ['Draft', 'Confirmed', 'Closed', 'Cancelled'], 'series' => [['name' => 'Orders', 'data' => [12, 41, 96, 5]]]]],
            ['key' => 'shortage', 'title' => 'Material Shortage', 'caption' => 'Materials that need attention', 'group' => 'Inventory', 'type' => 'list', 'size' => 4, 'icon' => 'fa-triangle-exclamation',
                'description' => 'Short materials with the first date they are needed.',
                'data' => ['rows' => [['title' => 'Ivory 310 gsm 79 x 109', 'sub' => 'needed 05-Okt-2026 · CO2600021', 'value' => '35.700 SHEET', 'tone' => 'danger'],
                    ['title' => 'Lamination film', 'sub' => 'needed 16-Des-2025 · CO2600027', 'value' => '84.294 M2', 'tone' => 'danger'],
                    ['title' => 'Duplex 250 gsm 100 x 65', 'sub' => 'needed 16-Des-2025 · CO2600024', 'value' => '5.250 SHEET', 'tone' => 'warn']]]],
            ['key' => 'jobdesc', 'title' => 'My Job Description', 'caption' => 'General Manager', 'group' => 'General', 'type' => 'text', 'size' => 6, 'icon' => 'fa-briefcase',
                'description' => 'Long text shown as written, line breaks kept.',
                'data' => ['text' => "1. Lead the daily operation of the plant.\n2. Approve purchase orders and customer order changes.\n3. Review the weekly production and delivery report.", 'empty' => 'Nothing to show']],
            ['key' => 'quote', 'title' => 'Quote of the Day', 'group' => 'General', 'type' => 'quote', 'size' => 12, 'icon' => 'fa-quote-left',
                'description' => 'One line per day of the year.', 'data' => ['text' => 'Kesaktian sebuah tim ada pada kekompakannya.', 'author' => '', 'day' => now()->dayOfYear, 'days' => 365]],
        ])->keyBy('key')->all();
    }

    /** @return array<int, array{0: string, 1: int}> [key, size] berurutan */
    private static function layout(): array
    {
        return session(self::SESSION, self::TEMPLATE);
    }

    public static function props(): array
    {
        $all = self::all();
        $layout = collect(self::layout())->filter(fn ($l) => isset($all[$l[0]]));
        $on = $layout->pluck(0)->all();

        return [
            'widgets' => $layout->map(fn ($l) => array_replace($all[$l[0]], ['size' => $l[1]]))->values()->all(),
            'catalog' => collect($all)->map(fn ($w) => ['key' => $w['key'], 'title' => $w['title'], 'description' => $w['description'], 'group' => $w['group'],
                'icon' => $w['icon'], 'type' => $w['type'], 'installed' => in_array($w['key'], $on, true)])->values()->all(),
            'periods' => array_map(fn (array $p) => $p[0], self::PERIODS), 'period' => self::period(),
        ];
    }

    public static function apply(?string $action, ?string $key): void
    {
        $layout = self::layout();
        $i = collect($layout)->search(fn ($l) => $l[0] === $key);
        $found = $i !== false;
        $step = fn (int $size, int $d) => self::SIZES[max(0, min(count(self::SIZES) - 1, array_search($size, self::SIZES, true) + $d))];

        if ($action === 'period') {
            session([self::SESSION_PERIOD => isset(self::PERIODS[(string) $key]) ? $key : 'monthly']);

            return;
        }
        if ($action === 'reset') {
            $layout = self::TEMPLATE;
        } elseif ($action === 'add' && ! $found && isset(self::all()[$key])) {
            $layout[] = [$key, self::all()[$key]['size']];
        } elseif ($action === 'move' && $found) {
            $row = $layout[$i];
            array_splice($layout, $i, 1);
            $at = collect($layout)->search(fn ($l) => $l[0] === request('to'));
            array_splice($layout, $at === false ? count($layout) : $at, 0, [$row]);
        } elseif ($action === 'remove' && $found) {
            array_splice($layout, $i, 1);
        } elseif ($action === 'left' && $found && $i > 0) {
            [$layout[$i - 1], $layout[$i]] = [$layout[$i], $layout[$i - 1]];
        } elseif ($action === 'right' && $found && $i < count($layout) - 1) {
            [$layout[$i + 1], $layout[$i]] = [$layout[$i], $layout[$i + 1]];
        } elseif (in_array($action, ['wider', 'narrower'], true) && $found) {
            $layout[$i][1] = $step($layout[$i][1], $action === 'wider' ? 1 : -1);
        }
        session([self::SESSION => array_values($layout)]);
    }
}

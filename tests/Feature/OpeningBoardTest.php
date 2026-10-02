<?php

namespace Tests\Feature;

use App\Assistant\ErpAsker;
use App\Assistant\Metrics\BoardWidgets;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysBoardTemplate;
use App\Models\Sys\SysKpiTarget;
use App\Models\Sys\SysUserBoard;
use App\Models\Sys\SysUserWidget;
use App\Support\V2\Board\Board;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\WidgetCatalog;
use App\Support\V2\Board\Widgets\OrderValue;
use Assistant\MetricCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Papan widget opening screen (App\Support\V2\Board): susunan dari template, aksi Customize, hak pasang, angka terhadap target,
// dan perintah lewat asisten. User uji TIDAK disimpan (id di luar jangkauan nyata); yang tertulis hanya baris papan miliknya
// (sys_user_board, sys_user_widget) dan satu target tahun 2099 -- semuanya dihapus di tearDown. Dilewati bila tabel papan
// belum ada di database ini (tm2* sebelum disetujui user).
class OpeningBoardTest extends TestCase
{
    private const ADMIN_ID = 999999001;

    private const STAFF_ID = 999999002;

    private const FAR_MONTH = '2099-01-01';

    private SecUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Board::ready()) {
            $this->markTestSkipped('Tabel papan widget (sys_user_widget) belum ada di database ini.');
        }
        $this->admin = $this->user(self::ADMIN_ID, true);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        if (Board::ready()) {
            SysUserWidget::query()->whereIn('user_id', [self::ADMIN_ID, self::STAFF_ID])->delete();
            SysUserBoard::query()->whereIn('user_id', [self::ADMIN_ID, self::STAFF_ID])->delete();
            SysKpiTarget::query()->whereYear('period_month', 2099)->delete();
            if (Schema::connection('tm2sys')->hasTable('sys_assistant_message')) {
                DB::connection('tm2sys')->table('sys_assistant_message')->whereIn('user_id', [(string) self::ADMIN_ID, (string) self::STAFF_ID])->delete();
            }
        }
        parent::tearDown();
    }

    private function user(int $id, bool $admin): SecUser
    {
        return (new SecUser)->forceFill(['id_user' => $id, 'login' => 'board.test.'.$id, 'name' => 'Board Test', 'is_admin' => $admin]);
    }

    /** @return array<int, string> */
    private function keys(SecUser $user): array
    {
        return Board::layout($user)->pluck('widget_key')->all();
    }

    public function test_first_open_copies_the_template(): void
    {
        $template = SysBoardTemplate::query()->where('profile_code', Board::OVERALL)->where('activate', true)->orderBy('sort_order')->get();
        $active = array_keys(app(WidgetCatalog::class)->all());
        $expected = $template->pluck('widget_key')->filter(fn ($k) => in_array($k, $active, true))->values()->all();

        $this->assertNotEmpty($expected, 'Template OVERALL kosong.');
        $this->assertSame($expected, $this->keys($this->admin));
        $this->assertSame($template->firstWhere('widget_key', $expected[0])->size, Board::layout($this->admin)->first()->size);
        // Dibuka lagi: tidak disalin ulang.
        $this->assertSame($expected, $this->keys($this->admin));
        $this->assertSame(1, SysUserBoard::query()->where('user_id', self::ADMIN_ID)->count());
    }

    public function test_customize_actions_change_the_saved_layout(): void
    {
        $start = $this->keys($this->admin);
        $free = collect(array_keys(app(WidgetCatalog::class)->all()))->diff($start)->first();
        $this->assertNotNull($free, 'Semua widget sudah ada di template; test butuh satu widget di luar template.');

        $this->assertNull(Board::apply($this->admin, 'add', $free));
        $this->assertSame([...$start, $free], $this->keys($this->admin));
        $this->assertSame('This widget is already on your board.', Board::apply($this->admin, 'add', $free));

        $this->assertNull(Board::apply($this->admin, 'left', $free));
        $moved = $this->keys($this->admin);
        $this->assertSame($free, $moved[count($moved) - 2]);
        $this->assertSame($start[count($start) - 1], $moved[count($moved) - 1]);

        $size = fn () => (int) SysUserWidget::query()->where('user_id', self::ADMIN_ID)->where('widget_key', $free)->value('size');
        foreach (range(1, 6) as $i) {
            Board::apply($this->admin, 'wider', $free);
        }
        $this->assertSame(12, $size());
        foreach (range(1, 6) as $i) {
            Board::apply($this->admin, 'narrower', $free);
        }
        $this->assertSame(3, $size());

        $this->assertNull(Board::apply($this->admin, 'remove', $free));
        $this->assertSame($start, $this->keys($this->admin));
        $this->assertSame('This widget is not on your board.', Board::apply($this->admin, 'remove', $free));
        $this->assertSame('Unknown board action.', Board::apply($this->admin, 'explode', $free));

        // Seret (move): widget terakhir ke depan widget pertama, lalu ke ujung papan (tanpa tujuan).
        $last = $start[count($start) - 1];
        $this->assertNull(Board::apply($this->admin, 'move', $last, $start[0]));
        $this->assertSame([$last, ...array_slice($start, 0, -1)], $this->keys($this->admin));
        $this->assertNull(Board::apply($this->admin, 'move', $last, null));
        $this->assertSame($start, $this->keys($this->admin));

        Board::apply($this->admin, 'remove', $start[0]);
        $this->assertNull(Board::apply($this->admin, 'reset', null));
        $this->assertSame($start, $this->keys($this->admin));
    }

    public function test_a_widget_needs_the_right_to_install_it(): void
    {
        // Tanpa grup security: tidak punya hak modul mana pun dan tidak berhak melihat harga jual.
        $staff = $this->user(self::STAFF_ID, false);
        $catalog = app(WidgetCatalog::class);

        $this->assertNull(Board::profile($staff));
        $this->assertFalse($catalog->allowed($staff, 'order_value'));
        $this->assertTrue($catalog->allowed($staff, 'quote_of_day'));
        $this->assertSame('This widget is not available for your account.', Board::apply($staff, 'add', 'order_value'));
        $this->assertNotContains('order_value', $this->keys($staff));
        $this->assertNotContains('order_value', array_column(Board::props($staff)['catalog'], 'key'));
    }

    public function test_numbers_agree_with_the_tables_and_read_the_target(): void
    {
        $n = Numbers::make();
        $from = $n->now->startOfYear();
        $to = $n->now->endOfYear();
        $direct = (float) DB::connection('tm2sdi')->table('co_order as o')->join('co_order_item as i', 'i.order_id', '=', 'o.id_order')
            ->whereBetween('o.co_date', [$from->toDateString(), $to->toDateString()])->where('o.stat_cancel', 0)->whereIn('o.doc_status', Numbers::CONFIRMED)->sum('i.subtotal');
        $this->assertEqualsWithDelta($direct, (float) $n->confirmed($from, $to)->sum('value'), 0.01);
        $this->assertEqualsWithDelta((float) $n->confirmed($n->month(11), $n->now->endOfMonth())->sum('value'), array_sum(array_column($n->trend(12), 'value')), 0.01);

        // Rincian tahap: Confirmed + Draft + menunggu approval = semua order tidak batal di rentang itu.
        $all = $n->orders($from, $to);
        $split = Numbers::split($all);
        $this->assertEqualsWithDelta($direct, $split['confirmed'], 0.01);
        $this->assertEqualsWithDelta((float) $all->sum('value'), $split['confirmed'] + $split['draft'] + $split['waiting'], 0.01);
        $this->assertSame($all->count(), $split['n_confirmed'] + $split['n_draft'] + $split['n_waiting']);

        // Bulan tanpa data sama sekali (2099): angka nol, target dari Setup > KPI Targets.
        $far = fn (string $period = 'monthly') => new Numbers(CarbonImmutable::parse('2099-01-15 10:00:00'), $period);
        $widget = new OrderValue;
        $this->assertNull($widget->build($far(), $this->admin)['data']['target']);
        SysKpiTarget::create(['target_key' => 'REVENUE', 'period_month' => self::FAR_MONTH, 'sales_person_id' => 0, 'target_value' => 123456789, 'activate' => true, 'created_by' => 'test']);
        SysKpiTarget::create(['target_key' => 'REVENUE', 'period_month' => '2099-02-01', 'sales_person_id' => 0, 'target_value' => 1000, 'activate' => true, 'created_by' => 'test']);
        $data = $widget->build($far(), $this->admin)['data'];
        $this->assertSame(123456789.0, $data['target']);
        $this->assertSame(0.0, $data['value']);
        $this->assertCount(7, $data['spark']);
        $this->assertStringContainsString('Confirmed', $data['note']);
        $this->assertStringContainsString('Draft', $data['note']);

        // Target diisi per bulan: kuartal / tahun menjumlah bulan-bulannya, harian / mingguan tanpa target.
        $this->assertSame(123457789.0, $far('quarterly')->periodTarget('REVENUE'));
        $this->assertSame(123457789.0, $far('yearly')->periodTarget('REVENUE'));
        $this->assertNull($far('weekly')->periodTarget('REVENUE'));
        $this->assertNull($far('daily')->periodTarget('REVENUE'));
    }

    public function test_period_ranges_follow_the_calendar(): void
    {
        $at = fn (string $period) => new Numbers(CarbonImmutable::parse('2026-10-02 16:00:00'), $period);
        $range = fn (string $period, int $back = 0) => array_map(fn (CarbonImmutable $d) => $d->toDateString(), $at($period)->range($back));

        $this->assertSame(['2026-10-02', '2026-10-02'], $range('daily'));
        $this->assertSame(['2026-09-28', '2026-10-04'], $range('weekly'));
        $this->assertSame(['2026-10-01', '2026-10-31'], $range('monthly'));
        $this->assertSame(['2026-10-01', '2026-12-31'], $range('quarterly'));
        $this->assertSame(['2026-07-01', '2026-12-31'], $range('semester'));
        $this->assertSame(['2026-01-01', '2026-12-31'], $range('yearly'));
        // Periode sebelumnya.
        $this->assertSame(['2026-10-01', '2026-10-01'], $range('daily', 1));
        $this->assertSame(['2026-09-01', '2026-09-30'], $range('monthly', 1));
        $this->assertSame(['2026-07-01', '2026-09-30'], $range('quarterly', 1));
        $this->assertSame(['2026-01-01', '2026-06-30'], $range('semester', 1));
        $this->assertSame(['2025-01-01', '2025-12-31'], $range('yearly', 1));
        // Periode yang tidak dikenal = bulanan.
        $this->assertSame('monthly', $at('century')->period);
        $this->assertCount(14, $at('daily')->trend());
        $this->assertCount(5, $at('yearly')->trend());
    }

    public function test_period_choice_is_saved_per_user(): void
    {
        $this->assertSame('monthly', Board::period($this->admin));
        $this->assertNull(Board::apply($this->admin, 'period', 'yearly'));
        $this->assertSame('yearly', Board::period($this->admin));
        $this->assertSame('Unknown period.', Board::apply($this->admin, 'period', 'century'));
        $this->assertSame('yearly', Board::period($this->admin));
        // Reset to Template mengembalikan susunan, bukan periode.
        Board::apply($this->admin, 'reset', null);
        $this->assertSame('yearly', Board::period($this->admin));
        $this->assertStringContainsString('data-wb-period="yearly"', Board::html($this->admin));
    }

    public function test_every_widget_builds_for_every_period(): void
    {
        $catalog = app(WidgetCatalog::class)->all();
        $this->assertGreaterThanOrEqual(30, count($catalog), 'Katalog widget kurang dari 30.');
        foreach (array_keys(Numbers::PERIODS) as $period) {
            $n = Numbers::make($period);
            foreach ($catalog as $key => $entry) {
                $out = $entry['widget']->build($n, $this->admin);
                $this->assertIsArray($out['data'] ?? null, "Widget {$key} ({$period}) tidak mengembalikan data.");
                $this->assertContains($entry['widget']->type(), ['kpi', 'chart', 'progress', 'ranking', 'list', 'quote'], $key);
            }
        }
    }

    public function test_money_numbers_are_consistent_with_each_other(): void
    {
        $n = Numbers::make('yearly');
        // Saldo kas = semua kas masuk dikurangi semua kas keluar sampai hari ini (pindah buku antar akun kas saling menghapus).
        $all = $n->cashFlow(CarbonImmutable::parse('1990-01-01'), $n->now);
        $this->assertEqualsWithDelta(array_sum(array_column($n->cashBalances(), 'balance')), $all['in'] - $all['out'], 0.01);
        // Laba rugi setahun = jumlah laba rugi per bulan tahun itu.
        $year = $n->profitLoss($n->from(), $n->to());
        $months = Numbers::make('monthly');
        $income = 0.0;
        $expense = 0.0;
        for ($m = $n->from(); $m->lessThanOrEqualTo($n->to()); $m = $m->addMonthNoOverflow()) {
            $pl = $months->profitLoss($m, $m->endOfMonth());
            $income += $pl['income'];
            $expense += $pl['expense'];
        }
        $this->assertEqualsWithDelta($year['income'], $income, 0.01);
        $this->assertEqualsWithDelta($year['expense'], $expense, 0.01);
        // Hutang lewat jatuh tempo + belum jatuh tempo = seluruh sisa hutang.
        $ap = $n->payables();
        $this->assertEqualsWithDelta((float) $ap->sum('outstanding'), (float) $ap->where('days_overdue', '>', 0)->sum('outstanding') + (float) $ap->where('days_overdue', '<=', 0)->sum('outstanding'), 0.01);
    }

    public function test_area_dashboards_show_their_template_and_overall_is_arranged_by_the_owner(): void
    {
        $codes = array_column(Board::dashboards($this->admin), 'code');
        $this->assertSame(Board::OVERALL, $codes[0]);
        foreach (['SALES', 'PURCHASING', 'AR_AP', 'CASHFLOW', 'PAYROLL', 'HR'] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertSame(Board::OVERALL, Board::dashboard($this->admin));
        $overall = Board::props($this->admin);
        $this->assertNotEmpty($overall['catalog'], 'Overall harus membawa daftar centang.');

        $this->assertNull(Board::apply($this->admin, 'dashboard', 'SALES'));
        $this->assertSame('SALES', Board::dashboard($this->admin));
        $sales = Board::props($this->admin);
        $template = SysBoardTemplate::query()->where('profile_code', 'SALES')->where('activate', true)->orderBy('sort_order')->pluck('widget_key')->all();
        $this->assertSame($template, array_column($sales['widgets'], 'key'));
        $this->assertSame([], $sales['catalog'], 'Dashboard bidang tidak punya daftar centang.');
        $html = Board::html($this->admin);
        $this->assertStringNotContainsString('data-wb-toggle', $html);
        $this->assertStringContainsString('data-wb-dash="SALES"', $html);

        // Susunan Overall tidak berubah karena membuka dashboard lain.
        $this->assertSame(array_column($overall['widgets'], 'key'), $this->keys($this->admin));
        $this->assertSame('This dashboard is not available for your account.', Board::apply($this->admin, 'dashboard', 'NOPE'));
        $this->assertNull(Board::apply($this->admin, 'dashboard', Board::OVERALL));
        $this->assertStringContainsString('data-wb-toggle', Board::html($this->admin));

        // Tanpa hak modul: hanya Overall; widget gaji tidak boleh dipasang walau lewat daftar centang.
        $staff = $this->user(self::STAFF_ID, false);
        $this->assertSame([Board::OVERALL], array_column(Board::dashboards($staff), 'code'));
        $this->assertFalse(app(WidgetCatalog::class)->allowed($staff, 'payroll_cost'));
        $this->assertSame('This widget is not available for your account.', Board::apply($staff, 'add', 'payroll_cost'));
    }

    public function test_board_renders_every_widget_of_the_layout(): void
    {
        $props = Board::props($this->admin);
        $this->assertSame($this->keys($this->admin), array_column($props['widgets'], 'key'));
        $html = Board::html($this->admin);
        $this->assertStringContainsString('data-dl-wboard', $html);
        foreach ($this->keys($this->admin) as $key) {
            $this->assertStringContainsString('data-key="'.$key.'"', $html);
        }
    }

    public function test_assistant_installs_and_removes_a_widget(): void
    {
        $command = new BoardWidgets;
        $asker = new ErpAsker($this->admin);
        $this->assertNotContains('receivables_aging', $this->keys($this->admin));

        $reply = $command->answer($asker, 'tolong pasang widget umur piutang');
        $this->assertSame('board', $reply->refresh);
        $this->assertContains('receivables_aging', $this->keys($this->admin));

        $again = $command->answer($asker, 'add widget receivables aging');
        $this->assertNull($again->refresh);

        $reply = $command->answer($asker, 'lepas widget umur piutang');
        $this->assertSame('board', $reply->refresh);
        $this->assertNotContains('receivables_aging', $this->keys($this->admin));

        $list = $command->answer($asker, 'widget apa saja');
        $this->assertNull($list->refresh);
        $this->assertNotEmpty($list->rows);

        // "buka dashboard sales" = ganti dashboard; perintah widget sesudahnya tetap bekerja pada papan Overall.
        $open = $command->answer($asker, 'buka dashboard sales');
        $this->assertSame('board', $open->refresh);
        $this->assertSame('SALES', Board::dashboard($this->admin));
        $command->answer($asker, 'buka dashboard overall');
        $this->assertSame(Board::OVERALL, Board::dashboard($this->admin));

        // Jabatan tanpa papan: ditolak, tidak ada yang tertulis.
        $denied = $command->answer(new ErpAsker($this->user(self::STAFF_ID, false)), 'pasang widget kata harian');
        $this->assertSame('DENIED', $denied->kind);
        $this->assertSame(0, SysUserWidget::query()->where('user_id', self::STAFF_ID)->count());
    }

    public function test_board_command_wins_over_a_number_question(): void
    {
        if (! Schema::connection('tm2sys')->hasTable('sys_assistant_metric')) {
            $this->markTestSkipped('Tabel asisten belum ada di database ini.');
        }
        $catalog = app(MetricCatalog::class);
        $this->assertSame('board_widgets', $catalog->match('lepas widget sales today')['key'] ?? null);
        $this->assertSame('sales_today', $catalog->match('sales today')['key'] ?? null);
    }
}

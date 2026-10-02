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
use App\Support\V2\Board\Widgets\RevenueMonth;
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
            SysKpiTarget::query()->whereDate('period_month', self::FAR_MONTH)->delete();
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
        $template = SysBoardTemplate::query()->where('profile_code', Board::MANAGEMENT)->where('activate', true)->orderBy('sort_order')->get();
        $active = array_keys(app(WidgetCatalog::class)->all());
        $expected = $template->pluck('widget_key')->filter(fn ($k) => in_array($k, $active, true))->values()->all();

        $this->assertNotEmpty($expected, 'Template MANAGEMENT kosong.');
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
        $this->assertFalse($catalog->allowed($staff, 'revenue_month'));
        $this->assertTrue($catalog->allowed($staff, 'quote_of_day'));
        $this->assertSame('This widget is not available for your account.', Board::apply($staff, 'add', 'revenue_month'));
        $this->assertNotContains('revenue_month', $this->keys($staff));
        $this->assertNotContains('revenue_month', array_column(Board::props($staff)['catalog'], 'key'));
    }

    public function test_numbers_agree_with_the_tables_and_read_the_target(): void
    {
        $n = Numbers::make();
        $from = $n->now->startOfYear();
        $to = $n->now->endOfYear();
        $direct = (float) DB::connection('tm2sdi')->table('co_order as o')->join('co_order_item as i', 'i.order_id', '=', 'o.id_order')
            ->whereBetween('o.co_date', [$from->toDateString(), $to->toDateString()])->where('o.stat_cancel', 0)->whereIn('o.doc_status', Numbers::CONFIRMED)->sum('i.subtotal');
        $this->assertEqualsWithDelta($direct, (float) $n->confirmed($from, $to)->sum('value'), 0.01);
        $this->assertEqualsWithDelta((float) $n->confirmed($n->month(11), $n->now->endOfMonth())->sum('value'), array_sum($n->monthlyOrderValue(12)), 0.01);

        // Bulan tanpa data sama sekali (2099): angka nol, target dari Setup > KPI Targets.
        $far = new Numbers(CarbonImmutable::parse('2099-01-15 10:00:00'));
        $widget = new RevenueMonth;
        $this->assertNull($widget->build($far, $this->admin)['data']['target']);
        SysKpiTarget::create(['target_key' => 'REVENUE', 'period_month' => self::FAR_MONTH, 'sales_person_id' => 0, 'target_value' => 123456789, 'activate' => true, 'created_by' => 'test']);
        $data = $widget->build(new Numbers(CarbonImmutable::parse('2099-01-15 10:00:00')), $this->admin)['data'];
        $this->assertSame(123456789.0, $data['target']);
        $this->assertSame(0.0, $data['value']);
        $this->assertCount(7, $data['spark']);
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

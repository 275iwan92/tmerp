<?php

namespace Tests\Feature;

use App\Models\Inventory\WhTask;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysAppMenu;
use App\Support\V2\Data\WarehouseTaskBoard;
use Tests\TestCase;

// Warehouse > Task Board (wh-task-board): papan baca-saja. Menulis satu baris wh_task uji ke database sungguhan, ditandai created_by dan dibersihkan di tearDown().
class WarehouseTaskBoardTest extends TestCase
{
    private const BY = 'test_task_board';

    protected function setUp(): void
    {
        parent::setUp();
        WhTask::query()->where('created_by', self::BY)->delete();
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
    }

    protected function tearDown(): void
    {
        WhTask::query()->where('created_by', self::BY)->delete();
        $this->assertSame(0, WhTask::query()->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    private function task(string $type, string $status, array $extra = []): WhTask
    {
        return WhTask::query()->create(['task_no' => 'ZZ'.substr(uniqid(), -8), 'task_type' => $type, 'status' => $status, 'stock_class' => 'RM', 'item_id' => 1, 'item_name' => 'Board test item',
            'qty' => 5, 'uom_code' => 'KG', 'created_by' => self::BY] + $extra);
    }

    public function test_the_board_has_three_columns_and_puts_tasks_by_status_with_a_link_to_their_screen(): void
    {
        $open = $this->task('PUTAWAY', 'OPEN');
        $done = $this->task('COUNT', 'DONE');
        $cancelled = $this->task('MOVE', 'CANCELLED');

        $board = WarehouseTaskBoard::board();
        $this->assertSame(['open', 'progress', 'done'], array_column($board['columns'], 'key'));
        $cards = collect($board['cards'])->keyBy('id');
        $this->assertSame('open', $cards['task:'.$open->id_task]['col']);
        $this->assertStringContainsString('wh-receiving-putaway?task='.$open->id_task, $cards['task:'.$open->id_task]['url']);
        $this->assertSame('done', $cards['task:'.$done->id_task]['col']);
        $this->assertStringContainsString('wh-count-tasks?id='.$done->id_task, $cards['task:'.$done->id_task]['url']);
        $this->assertFalse($cards->has('task:'.$cancelled->id_task), 'Cancelled tasks are not on the board.');
    }

    public function test_the_screen_is_a_read_only_kanban(): void
    {
        $open = $this->task('RECEIVE', 'OPEN', ['is_urgent' => true]);
        $page = $this->get(route('v2.screen', 'wh-task-board'))->assertOk()->assertSee('Task Board')->assertSee('data-readonly="1"', false)->assertSee('task:'.$open->id_task, false);
        $page->assertSee('data-add-card="0"', false);
    }

    public function test_the_screen_is_in_the_warehouse_menu(): void
    {
        $item = SysAppMenu::query()->where('code', 'WH_TASK_BOARD')->first();
        if (! $item) {
            $this->markTestSkipped('The Task Board menu row is not installed in this database yet.');
        }
        $this->assertSame('v2:wh-task-board', $item->target);
        $this->assertSame('WH', SysAppMenu::query()->find(SysAppMenu::query()->find($item->parent_id)->parent_id)->code);
    }
}

<?php

namespace Tests\Feature;

use Devlib\Lang;
use Devlib\Components\DevlibComponent;
use Devlib\DevlibServiceProvider;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

// x-dl::gantt as the PPIC production scheduling board: new optional props reach the JS config, the old props alone still render.
// Renders only; inserts no data.
class GanttBoardTest extends TestCase
{
    private function config(string $html): array
    {
        $this->assertSame(1, preg_match('/data-config="([^"]*)"/', $html, $m), 'data-config not found');

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_old_props_alone_still_render_with_neutral_new_config(): void
    {
        $html = Blade::render('<x-dl::gantt :rows="[[\'key\' => \'a\', \'label\' => \'A\']]" :tasks="[[\'id\' => 1, \'row\' => \'a\', \'label\' => \'T\', \'start\' => \'08:00\', \'end\' => \'10:00\']]" :unscheduled="1" />');
        $cfg = $this->config($html);
        $this->assertSame(8, $cfg['start']);
        $this->assertSame(20, $cfg['end']);
        $this->assertNull($cfg['originDate']);
        $this->assertNull($cfg['lockUntil']);
        $this->assertNull($cfg['nowAt']);
        $this->assertNull($cfg['impactUrl']);
        $this->assertNull($cfg['moveUrl']);
        $this->assertStringContainsString('data-g2-submit', $html);
        $this->assertStringNotContainsString('data-g2-draft', $html);
    }

    public function test_production_props_reach_the_js_config(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-dl::gantt :start="6" :end="54" origin-date="2026-10-08" lock-until="16:00" :now-at="10.5" impact-url="/i" move-url="/m" draft-label="DRAFT 1 change" :submit-label="null"
                :rows="[['key' => 'a', 'label' => 'A', 'load' => 86, 'bottleneck' => true, 'down' => [['from' => 34, 'to' => 38, 'label' => 'Maintenance']]]]"
                :tasks="[['id' => 1, 'row' => 'a', 'label' => 'T', 'start' => '30:00', 'end' => '38:00', 'setup' => 45, 'gates' => ['ok', 'wait', 'block'], 'forecastEnd' => 40.5, 'eligible' => ['a'], 'locked' => true]]" />
            BLADE);
        $cfg = $this->config($html);
        $this->assertSame('2026-10-08', $cfg['originDate']);
        $this->assertSame('16:00', $cfg['lockUntil']);
        $this->assertSame(10.5, $cfg['nowAt']);
        $this->assertSame('/i', $cfg['impactUrl']);
        $this->assertSame('/m', $cfg['moveUrl']);
        $this->assertSame(54, $cfg['end']);
        $this->assertSame(86, $cfg['rows'][0]['load']);
        $this->assertTrue($cfg['rows'][0]['bottleneck']);
        $this->assertSame('Maintenance', $cfg['rows'][0]['down'][0]['label']);
        $task = $cfg['tasks'][0];
        $this->assertSame(45, $task['setup']);
        $this->assertSame(['ok', 'wait', 'block'], $task['gates']);
        $this->assertSame(40.5, $task['forecastEnd']);
        $this->assertSame(['a'], $task['eligible']);
        $this->assertTrue($task['locked']);
        $this->assertStringContainsString('data-g2-draft>DRAFT 1 change<', $html);
        $this->assertStringNotContainsString('data-g2-submit', $html);
    }

    public function test_js_and_dictionary_carry_the_new_texts(): void
    {
        $js = DevlibServiceProvider::bundle();
        foreach (['originDate', 'lockedFor', 'impactUrl', 'gt_not_eligible', 'data-g2-fc', 'dl-gantt__setup'] as $needle) {
            $this->assertStringContainsString($needle, $js, $needle);
        }
        foreach (['gt_task_locked', 'gt_not_eligible', 'gt_locked_until', 'gt_impact_title', 'gt_move_draft', 'gt_over_min', 'gt_over_h', 'gt_bottleneck'] as $key) {
            $this->assertArrayHasKey($key, Lang::all(), $key);
        }
    }
}

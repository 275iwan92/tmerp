<?php

namespace Tests\Feature;

use App\Support\V2\Data\Warehouse;
use Devlib\Components\WhMap;
use Tests\TestCase;

// Lapisan opsional x-dl::wh-map (alokasi pick list, gambar docs/mockups/wh_v201_map_allocation.jpg): peta tanpa lapisan harus sama seperti sebelumnya.
// Tanpa data bisnis: denah kecil dibuat di sini, jadi tidak ada yang ditulis ke database.
class WarehouseMapLayerTest extends TestCase
{
    private function floors(): array
    {
        return [['no' => 1, 'label' => 'Floor 1', 'zones' => [['id' => 1, 'code' => 'PPR', 'name' => 'Paper', 'tone' => 'accent', 'x' => 1, 'y' => 1, 'w' => 8, 'h' => 6, 'cells' => [
            ['x' => 2, 'y' => 2, 'w' => 2, 'h' => 2, 'label' => 'L01', 'bins' => [11]],
            ['x' => 5, 'y' => 2, 'w' => 2, 'h' => 2, 'label' => 'L02', 'bins' => [12]],
        ]]]]];
    }

    private function bins(): array
    {
        return [11 => ['code' => 'L01', 'status' => 'IN_USE'], 12 => ['code' => 'L02', 'status' => 'EMPTY']];
    }

    private function html(array $layer): string
    {
        return (string) $this->blade('<x-dl::wh-map id="t" :width="10" :height="8" :floors="$floors" :bins="$bins" :layer="$layer" />', ['floors' => $this->floors(), 'bins' => $this->bins(), 'layer' => $layer]);
    }

    public function test_a_map_without_a_layer_draws_nothing_extra(): void
    {
        $this->assertStringNotContainsString('dl-whmap__layer', $this->html([]));
    }

    public function test_the_layer_numbers_the_bins_draws_the_route_and_fills_the_side_panel(): void
    {
        $html = $this->html(['marks' => [['bin' => 11, 'text' => '1', 'tone' => 'pick'], ['bin' => 99, 'text' => '2', 'tone' => 'pick']], 'route' => [11], 'end' => 12, 'title' => 'Allocation · Pick List PK1',
            'rows' => [['no' => '1', 'bin' => 'L01', 'qty' => '5 kg']], 'notes' => ['Route: L01 → L02']]);
        $this->assertStringContainsString('dl-whmap__lmark--pick', $html);
        $this->assertSame(1, substr_count($html, 'dl-whmap__lmark dl-whmap__lmark--pick'), 'A bin that is not on this map gets no number.');
        $this->assertStringContainsString('dl-whmap__lroute', $html);
        $this->assertStringContainsString('Allocation · Pick List PK1', $html);
        $this->assertStringContainsString('Route: L01', $html);
    }

    public function test_layer_point_is_the_cell_centre_and_unknown_bins_have_none(): void
    {
        $map = new WhMap(floors: $this->floors(), bins: $this->bins(), width: 10, height: 8);
        $this->assertEquals([30, 30], $map->layerPoint(11, 1));
        $this->assertNull($map->layerPoint(11, 2));
        $this->assertNull($map->layerPoint(99, 1));
    }

    public function test_a_putaway_mark_is_a_dashed_p_circle_and_notes_alone_fill_the_panel(): void
    {
        $html = $this->html(['marks' => [['bin' => 12, 'text' => 'P', 'tone' => 'put']], 'title' => 'Putaway', 'notes' => ['WT2600001 → L02']]);
        $this->assertSame(1, substr_count($html, 'dl-whmap__lmark dl-whmap__lmark--put'));
        $this->assertStringContainsString('>P</text>', $html);
        $this->assertStringNotContainsString('dl-whmap__lroute', $html);
        $this->assertStringContainsString('WT2600001 → L02', $html);
    }

    public function test_the_layer_helper_returns_nothing_when_neither_layer_is_asked_for(): void
    {
        $this->assertSame([], Warehouse::layer(null, false));
        $this->assertSame([], Warehouse::layer(999999999, false));
    }

    public function test_the_putaway_layer_only_has_put_marks_one_per_bin(): void
    {
        $layer = Warehouse::putawayLayer();
        foreach ($layer['marks'] ?? [] as $m) {
            $this->assertSame('put', $m['tone']);
            $this->assertSame('P', $m['text']);
        }
        $this->assertCount(count($layer['marks'] ?? []), array_unique(array_column($layer['marks'] ?? [], 'bin')));
    }

    public function test_an_unknown_pick_list_gives_no_layer(): void
    {
        $this->assertSame([], Warehouse::pickLayer(999999999));
    }
}

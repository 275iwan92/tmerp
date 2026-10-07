<?php

namespace Tests\Feature;

use App\Models\Sdi\IqpCosting;
use App\Models\Sys\SecUser;
use App\Support\V2\Grid\Sales\CostingInstantGrid;
use Tests\TestCase;

// Pro-Cost Instant v2 (C4): daftar hanya memuat costing mode INSTANT; + New Record membuat draft kosong lalu memakainya ulang.
class CostingInstantGridTest extends TestCase
{
    /** @var list<int> */
    private array $created = [];

    protected function tearDown(): void
    {
        IqpCosting::query()->whereIn('id_costing', $this->created)->delete();
        parent::tearDown();
    }

    public function test_new_creates_blank_draft_and_reuses_it(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $before = IqpCosting::query()->where('mode', IqpCosting::MODE_INSTANT)->pluck('id_costing')->all();

        $first = $this->actingAs($admin)->get(route('v2.procost-instant.new'));
        $first->assertRedirect();
        $id = (int) basename(parse_url($first->headers->get('Location'), PHP_URL_PATH));
        if (! in_array($id, $before, true)) {
            $this->created[] = $id;
        }
        $draft = IqpCosting::findOrFail($id);
        $this->assertSame(IqpCosting::MODE_INSTANT, $draft->mode);
        $this->assertSame('DRAFT', $draft->doc_status);

        $second = $this->actingAs($admin)->get(route('v2.procost-instant.new'));
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
    }

    public function test_grid_lists_only_instant_rows(): void
    {
        $modes = array_unique(array_map(fn ($r) => IqpCosting::find($r['id'])->mode, (new CostingInstantGrid)->rows()));
        $this->assertSame([IqpCosting::MODE_INSTANT], array_values($modes) ?: [IqpCosting::MODE_INSTANT]);
        $this->assertStringContainsString('{id}', (new CostingInstantGrid)->openUrl());
    }
}

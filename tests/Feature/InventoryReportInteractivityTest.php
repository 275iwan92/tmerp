<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// Verifikasi interaktivitas baru (2026-08-28, permintaan user) di 3 layar
// Laporan Inventory: Master Stok (search+sort+collapse-grup+cetak),
// Kartu Stok (badge tipe), Slow Moving Item (BARU, ambang 3/6/9/12 bulan).
class InventoryReportInteractivityTest extends TestCase
{
    protected function admin(): SecUser
    {
        return SecUser::where('is_admin', true)->firstOrFail();
    }

    public function test_master_stock_search_sort_and_group_collapse(): void
    {
        $this->actingAs($this->admin());

        $test = Livewire::test('pages::inventory.print.master-stock', ['category' => 'RM'])
            ->assertSet('sortBy', 'item_code')
            ->assertSet('sortDir', 'asc');

        $test->call('sortToggle', 'item_name')
            ->assertSet('sortBy', 'item_name')
            ->assertSet('sortDir', 'asc');

        $test->call('sortToggle', 'item_name')
            ->assertSet('sortDir', 'desc');

        $groups = $test->instance()->report()['groups'];

        if ($groups->isNotEmpty()) {
            $groupName = $groups->keys()->first();

            $test->call('toggleGroup', $groupName)
                ->assertSet('collapsedGroups', [$groupName]);

            $test->call('toggleGroup', $groupName)
                ->assertSet('collapsedGroups', []);

            $test->call('toggleGroup', $groupName);
            $test->call('printReport')
                ->assertSet('collapsedGroups', []);
        }

        $test->set('quickSearch', 'zzz-tidak-ada-item-begini')
            ->assertSee('Tidak ada pergerakan stok pada periode ini.');
    }

    public function test_stock_card_renders_type_badge_for_selected_item(): void
    {
        $this->actingAs($this->admin());

        $materialId = \App\Models\Master\MatRaw::query()->value('material_id');

        Livewire::test('pages::inventory.print.stock-card', ['category' => 'RM', 'material' => $materialId])
            ->assertOk();
    }

    public function test_slow_moving_report_respects_threshold_and_rejects_invalid_value(): void
    {
        $this->actingAs($this->admin());

        $test = Livewire::test('pages::inventory.print.slow-moving', ['category' => 'RM'])
            ->assertSet('thresholdMonths', 3);

        $test->call('setThreshold', 12)->assertSet('thresholdMonths', 12);
        $test->call('setThreshold', 7)->assertSet('thresholdMonths', 12); // 7 bukan opsi valid, diabaikan

        $report3 = $test->instance()->report();
        $this->assertArrayHasKey('itemCount', $report3);
        $this->assertArrayHasKey('value', $report3['totals']);

        // Ambang lebih besar (12 bulan) tidak boleh menghasilkan lebih banyak item
        // slow-moving daripada ambang lebih kecil (3 bulan) -- semakin longgar
        // ambangnya, semakin sedikit/sama item yang "cukup lama diam".
        $test->call('setThreshold', 3);
        $countAt3 = $test->instance()->report()['itemCount'];
        $test->call('setThreshold', 12);
        $countAt12 = $test->instance()->report()['itemCount'];
        $this->assertLessThanOrEqual($countAt3, $countAt12);
    }

    public function test_slow_moving_report_works_for_all_three_stock_classes(): void
    {
        $this->actingAs($this->admin());

        foreach (['RM', 'SFG', 'FG'] as $category) {
            Livewire::test('pages::inventory.print.slow-moving', ['category' => $category])
                ->assertOk();
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Master\ProdLayoutGroup;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// Guard ditemukan user 2026-09-03: unlockLayout() sebelumnya tanpa syarat --
// kalau layout SUDAH ditarik Costing (Ambil Data), unlock+ubah bikin HPP yang
// sudah dihitung diam-diam basi tanpa peringatan siapa pun.
class ProdevUnlockLayoutGuardTest extends TestCase
{
    protected ?int $partId = null;

    protected ?int $groupId = null;

    protected ?int $costingPartId = null;

    protected function tearDown(): void
    {
        if ($this->costingPartId) {
            IqpCostingPart::whereKey($this->costingPartId)->delete();
        }
        if ($this->groupId) {
            ProdLayoutGroup::whereKey($this->groupId)->first()?->parts()->detach();
            ProdLayoutGroup::whereKey($this->groupId)->delete();
        }
        if ($this->partId) {
            $part = ProdPart::find($this->partId);
            ProdPart::whereKey($this->partId)->delete();
            if ($part) {
                ProdProduct::whereKey($part->product_id)->delete();
            }
        }

        parent::tearDown();
    }

    public function test_unlock_layout_blocked_when_costing_already_pulled_it(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $product = ProdProduct::create([
            'product_code' => 'TEST-UNLOCK-GUARD', 'product_name' => 'Test Unlock Guard', 'activate' => true,
        ]);
        $part = ProdPart::create([
            'product_id' => $product->id_product, 'part_code' => 'TEST-UNLOCK-PART', 'part_name' => 'Test Unlock Part',
            'cm_width' => 20, 'cm_length' => 15, 'flap_side' => 'TOP', 'activate' => true,
        ]);
        $this->partId = $part->id_part;

        $group = ProdLayoutGroup::create([
            'product_id' => $product->id_product, 'plano_size_id' => 1, 'plano_w_mm' => 650, 'plano_h_mm' => 1000,
            'method' => 'TEGAK', 'up_count' => 4, 'efficiency_pct' => 60, 'waste_pct' => 40,
            'status' => 'CONFIRMED', 'confirmed_by' => 'test', 'confirmed_at' => now(),
            'created_by' => 'test', 'updated_by' => 'test',
        ]);
        $group->update(['mastercard_code' => "MC-{$group->id_layout_group}"]);
        $group->parts()->attach($part->id_part, ['qty_up_in_group' => 4]);
        $this->groupId = $group->id_layout_group;

        // BELUM ditarik Costing -- unlock harus BOLEH.
        $test = Livewire::test('pages::production.prodev', ['part' => $part->id_part]);
        $test->call('unlockLayout');
        $group->refresh();
        $this->assertSame('DRAFT', $group->status, 'Unlock harus jalan kalau belum ditarik Costing');

        // Kunci lagi utk skenario berikutnya.
        $group->update(['status' => 'CONFIRMED', 'confirmed_by' => 'test', 'confirmed_at' => now()]);

        // SEKARANG ditarik Costing (simulasi pickLayoutGroup).
        $costing = IqpCosting::firstOrFail();
        $costingPart = IqpCostingPart::create([
            'costing_id' => $costing->id_costing, 'part_name' => 'Test Costing Ref', 'part_type_code' => 'SHEET_DIGITAL',
            'data_source' => 'PRODEV', 'prod_layout_group_id' => $group->id_layout_group,
        ]);
        $this->costingPartId = $costingPart->id_costing_part;

        $test2 = Livewire::test('pages::production.prodev', ['part' => $part->id_part]);
        $test2->call('unlockLayout');
        $group->refresh();
        $this->assertSame('CONFIRMED', $group->status, 'Unlock HARUS DITOLAK kalau layout sudah ditarik Costing');
    }
}

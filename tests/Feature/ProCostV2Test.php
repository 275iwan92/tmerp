<?php

namespace Tests\Feature;

use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use App\Services\Costing\CostingEntryService as Entry;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

// Layar ProCost v2 Mode Integrasi (procost): Save mengirim hanya field yang berubah ke CostingEntryService, tombol alur
// (Hitung, Submit, Publish), pemulihan isian saran engine, dan gembok status. Membuat inquiry uji sendiri lalu menghapusnya.
class ProCostV2Test extends TestCase
{
    protected ?IqpInquiry $inquiry = null;

    protected function tearDown(): void
    {
        if ($this->inquiry) {
            $sdi = DB::connection('tm2sdi');
            $ids = $sdi->table('iqp_costing')->where('inquiry_id', $this->inquiry->id_inquiry)->pluck('id_costing');
            $partIds = $sdi->table('iqp_costing_part')->whereIn('costing_id', $ids)->pluck('id_costing_part');
            $sdi->table('iqp_costing_part_coating')->whereIn('costing_part_id', $partIds)->delete();
            $sdi->table('iqp_costing_recommendation')->whereIn('costing_id', $ids)->delete();
            $sdi->table('iqp_costing_part')->whereIn('costing_id', $ids)->delete();
            $sdi->table('iqp_costing_price_tier')->whereIn('costing_id', $ids)->delete();
            $sdi->table('iqp_costing')->whereIn('id_costing', $ids)->delete();
            $sdi->table('iqp_part')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            $sdi->table('iqp_tier')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            $sdi->table('iqp_inquiry')->where('id_inquiry', $this->inquiry->id_inquiry)->delete();
        }

        parent::tearDown();
    }

    private function inquiry(): IqpInquiry
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        Livewire::test('pages::inquiry.form', ['mode' => 'NEW'])
            ->set('inquiry_date', now()->toDateString())
            ->set('product_draft_name', 'Box Test ProCost V2')
            ->call('setProspect', true)
            ->set('prospect_name', 'Prospek Test ProCost V2')
            ->set('tiers.0.qty', 1000)
            ->set('parts.0.part_name', 'Body')
            ->call('save')
            ->assertHasNoErrors();

        return $this->inquiry = IqpInquiry::where('product_draft_name', 'Box Test ProCost V2')->latest('id_inquiry')->firstOrFail();
    }

    public function test_view_only_never_creates_a_costing_and_open_creates_one(): void
    {
        $inq = $this->inquiry();

        $this->get('/v2/s/procost?inquiry='.$inq->id_inquiry.'&view=1')->assertOk()->assertSee('view only');
        $this->assertSame(0, IqpCosting::where('inquiry_id', $inq->id_inquiry)->count(), 'Passive navigation must not create a draft.');

        $this->get('/v2/s/procost?inquiry='.$inq->id_inquiry)->assertOk();
        $this->assertSame(1, IqpCosting::where('inquiry_id', $inq->id_inquiry)->count());
    }

    public function test_save_applies_only_changed_fields_then_the_flow_buttons_work(): void
    {
        $inq = $this->inquiry();
        $c = Entry::open($inq->id_inquiry);
        $part = Entry::addPart($c);
        $tier = $c->priceTiers()->first();

        $url = route('v2.procost.save', $inq->id_inquiry);
        $this->post($url, [
            'action' => 'save', 'part_id' => $part->id_costing_part, 'part_no' => 1, 'tier_id' => $tier->id_price_tier, 'ppn_pct' => '12',
            'tier' => [$tier->id_price_tier => ['margin_pct' => '21', 'tolerance_pct' => (string) $tier->tolerance_pct]],
            'part' => [$part->id_costing_part => ['part_name' => 'Body V2', 'i_jumlah_warna' => '3']],
        ])->assertRedirect();

        $c->refresh();
        $this->assertEquals(12, (float) $c->ppn_pct);
        $this->assertEquals(21, (float) $tier->refresh()->margin_pct);
        $this->assertSame('Body V2', $part->refresh()->part_name);
        $this->assertSame(3, (int) $part->i_jumlah_warna);

        // Hitung -> CALCULATED -> Submit -> PENDING_APPROVAL (terkunci) -> Publish (admin) -> PUBLISHED + inquiry PRICE_CALCULATED.
        $this->post($url, ['action' => 'hitung', 'part_id' => $part->id_costing_part])->assertRedirect();
        $this->assertSame('CALCULATED', $c->refresh()->doc_status);
        $this->post($url, ['action' => 'submit'])->assertRedirect();
        $this->assertSame('PENDING_APPROVAL', $c->refresh()->doc_status);

        $this->post($url, ['action' => 'save', 'part_id' => $part->id_costing_part, 'ppn_pct' => '5', 'part' => [$part->id_costing_part => ['part_name' => 'Locked change']]])->assertRedirect();
        $this->assertEquals(12, (float) $c->refresh()->ppn_pct, 'A submitted costing is locked.');
        $this->assertSame('Body V2', $part->refresh()->part_name);

        $this->post($url, ['action' => 'publish'])->assertRedirect();
        $this->assertSame('PUBLISHED', $c->refresh()->doc_status);
        $this->assertTrue((bool) $c->published_flag);
        $this->assertSame('PRICE_CALCULATED', $inq->refresh()->sub_state);
    }

    public function test_emptied_suggested_box_is_restored_with_a_notice_and_accept_zero_makes_it_zero(): void
    {
        $inq = $this->inquiry();
        $c = Entry::open($inq->id_inquiry);
        $part = Entry::addPart($c);
        Entry::updatePart($c, $part->id_costing_part, 'part_name', 'Kalender Majoin');
        $before = (float) $part->refresh()->i_ongkos_cetak_rim;
        if ($before <= 0) {
            $this->markTestSkipped('The engine suggested no print rate for this part name in this database.');
        }

        $this->post(route('v2.procost.save', $inq->id_inquiry), ['action' => 'save', 'part_id' => $part->id_costing_part, 'part' => [$part->id_costing_part => ['i_ongkos_cetak_rim' => '']]])
            ->assertRedirect()->assertSessionHas('procost_notices');
        $this->assertEquals($before, (float) $part->refresh()->i_ongkos_cetak_rim);

        $this->post(route('v2.procost.accept-zero', $inq->id_inquiry), ['part' => $part->id_costing_part, 'field' => 'i_ongkos_cetak_rim'])->assertRedirect();
        $this->assertEquals(0, (float) $part->refresh()->i_ongkos_cetak_rim);
    }

    public function test_only_direksi_or_admin_can_publish(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $this->assertTrue(Entry::canPublish());

        $plain = SecUser::where('activate', true)->where('is_admin', 0)->get()->first(fn ($u) => ! $u->hasGroup('DIREKSI'));
        if ($plain) {
            $this->actingAs($plain);
            $this->assertFalse(Entry::canPublish());
        }
    }
}

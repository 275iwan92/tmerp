<?php

namespace Tests\Feature;

use App\Models\Master\MatRaw;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class InquiryMaterialSmokeTest extends TestCase
{
    protected ?MatRaw $material = null;

    protected ?IqpInquiry $inquiry = null;

    protected function tearDown(): void
    {
        if ($this->inquiry) {
            DB::connection('tm2sdi')->table('iqp_part_material')
                ->whereIn('part_id', DB::connection('tm2sdi')->table('iqp_part')->where('inquiry_id', $this->inquiry->id_inquiry)->pluck('id_part'))
                ->delete();
            DB::connection('tm2sdi')->table('iqp_part')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            DB::connection('tm2sdi')->table('iqp_tier')->where('inquiry_id', $this->inquiry->id_inquiry)->delete();
            DB::connection('tm2sdi')->table('iqp_inquiry')->where('id_inquiry', $this->inquiry->id_inquiry)->delete();
        }

        if ($this->material) {
            DB::connection('tm2mst')->table('mat_raw')->where('material_id', $this->material->material_id)->delete();
        }

        parent::tearDown();
    }

    public function test_form_renders_and_saves_material_and_color(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        // GET smoke: full initial render must not throw.
        $this->get('/inquiry/new?mode=NEW')->assertOk();

        $this->material = MatRaw::create([
            'material_code' => 'TEST-DPX-310',
            'material_name' => 'Duplex 310 gsm TEST',
            'item_type' => 'MATERIAL',
            'category1' => 'DUPLEX',
            'brand' => 'IK',
            'gram_weight' => 310,
            'cm_length' => 79,
            'cm_width' => 109,
            'activate' => true,
        ]);

        Livewire::test('pages::inquiry.form', ['mode' => 'NEW'])
            ->set('inquiry_date', now()->toDateString())
            ->set('product_draft_name', 'Box Test Material')
            ->set('is_prospect', true)
            ->call('setProspect', true)
            ->set('prospect_name', 'Prospek Test')
            ->set('tiers.0.qty', 1000)
            ->set('parts.0.part_name', 'Body')
            ->set('parts.0.cfg_color', '4/1')
            ->set('parts.0.cfg_ink', 'Pantone 485 C')
            ->set('parts.0.materials.0.query', 'Duplex')
            ->call('selectMaterial', 0, 0, $this->material->material_id)
            ->assertSet('parts.0.materials.0.rawmat_id', $this->material->material_id)
            ->assertSet('parts.0.materials.0.brand', 'IK')
            ->call('addMaterialRow', 0)
            ->assertCount('parts.0.materials', 2)
            ->call('save')
            ->assertHasNoErrors();

        $this->inquiry = IqpInquiry::where('product_draft_name', 'Box Test Material')->latest('id_inquiry')->firstOrFail();

        $part = $this->inquiry->parts()->with('materials')->firstOrFail();

        $this->assertSame('4/1', $part->cfg_color);
        $this->assertSame('Pantone 485 C', $part->cfg_ink);

        // Baris bahan kosong (belum pilih material) sengaja tidak ikut tersimpan.
        $this->assertCount(1, $part->materials);
        $this->assertSame($this->material->material_id, $part->materials->first()->rawmat_id);
        $this->assertTrue((bool) $part->materials->first()->is_primary);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Master\MatCategory;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

// Bahan Inquiry = slot #1 Paper / #2 Sheet-SF, pilih Jenis -> Gramatur dari
// mat_category (user 2026-09-29, commit 20b4d14); Sales tidak memilih mat_raw/brand.
class InquiryMaterialSmokeTest extends TestCase
{
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

        parent::tearDown();
    }

    public function test_form_renders_and_saves_material_and_color(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        // GET smoke: full initial render must not throw.
        $this->get('/inquiry/new?mode=NEW')->assertOk();

        $paperRoot = MatCategory::roots()->where('category_name', 'Paper')->value('id_category');
        $jenis = MatCategory::where('parent_id', $paperRoot)->where('level', 2)->where('activate', true)
            ->whereExists(fn ($q) => $q->from('mat_category as g')->whereColumn('g.parent_id', 'mat_category.id_category')->where('g.level', 3)->where('g.activate', true))
            ->orderBy('category_name')->firstOrFail();
        $gramatur = MatCategory::where('parent_id', $jenis->id_category)->where('level', 3)->where('activate', true)
            ->orderBy('category_name')->firstOrFail();

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
            ->call('setMaterialJenis', 0, 0, (string) $jenis->id_category)
            ->call('setMaterialGramatur', 0, 0, (string) $gramatur->id_category)
            ->assertSet('parts.0.materials.0.category_id', $gramatur->id_category)
            ->assertSet('parts.0.materials.0.rawmat_id', null)
            ->assertCount('parts.0.materials', 2)
            ->call('save')
            ->assertHasNoErrors();

        $this->inquiry = IqpInquiry::where('product_draft_name', 'Box Test Material')->latest('id_inquiry')->firstOrFail();

        $part = $this->inquiry->parts()->with('materials')->firstOrFail();

        $this->assertSame('4/1', $part->cfg_color);
        $this->assertSame('Pantone 485 C', $part->cfg_ink);

        // Slot Sheet/SF yang belum dipilih sengaja tidak ikut tersimpan.
        $this->assertCount(1, $part->materials);
        $saved = $part->materials->first();
        $this->assertSame($gramatur->id_category, (int) $saved->category_id);
        $this->assertNull($saved->rawmat_id);
        $this->assertSame(1, (int) $saved->layer_seq);
        $this->assertTrue((bool) $saved->is_primary);
    }
}

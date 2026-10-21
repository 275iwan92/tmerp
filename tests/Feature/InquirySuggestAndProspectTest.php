<?php

namespace Tests\Feature;

use App\Models\Master\MatCategory;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use App\Services\Sales\InquirySuggestionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InquiryDriver;
use Tests\TestCase;

/*
 * REMARK
 * Tujuan       : Menguji saran part Inquiry v2 (warna/ukuran dari artwork, bahan/finishing dari histori, rute suggest) dan Inquiry prospek lama yang tetap bisa disimpan lewat rute save v2.
 * Sumber hulu  : App\Services\Sales\InquirySuggestionService, routes/v2.php (v2.inquiry.suggest, v2.inquiry.save), App\Support\V2\InquiryPayload.
 * Input        : Gambar uji dibuat di tes; Inquiry uji dibuat lewat InquiryDriver.
 * Output       : Assert isi saran dan data tersimpan. Data uji dibersihkan sendiri di tearDown.
 * Dipakai oleh : php artisan test --filter=InquirySuggestAndProspectTest.
 * Status       : Aktif (T10 tahap 4, 2026-10-21).
 */
class InquirySuggestAndProspectTest extends TestCase
{
    /** @var list<int> */
    protected array $ids = [];

    protected array $files = [];

    protected function tearDown(): void
    {
        $sdi = DB::connection('tm2sdi');
        foreach ($this->ids as $id) {
            $partIds = $sdi->table('iqp_part')->where('inquiry_id', $id)->pluck('id_part');
            $sdi->table('iqp_part_material')->whereIn('part_id', $partIds)->delete();
            $sdi->table('iqp_part_finishing')->whereIn('part_id', $partIds)->delete();
            $sdi->table('iqp_part')->where('inquiry_id', $id)->delete();
            $sdi->table('iqp_tier')->where('inquiry_id', $id)->delete();
            $sdi->table('iqp_inquiry')->where('id_inquiry', $id)->delete();
        }
        array_map('unlink', array_filter($this->files, 'is_file'));

        parent::tearDown();
    }

    private function login(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
    }

    private function image(int $w, int $h, bool $colour, ?int $dpi = null): string
    {
        $img = imagecreatetruecolor($w, $h);
        for ($x = 0; $x < $w; $x++) {
            $c = $colour ? imagecolorallocate($img, ($x * 7) % 256, ($x * 13) % 256, ($x * 29) % 256) : imagecolorallocate($img, $x % 2 ? 0 : 255, $x % 2 ? 0 : 255, $x % 2 ? 0 : 255);
            imageline($img, $x, 0, $x, $h, $c);
        }
        $path = tempnam(sys_get_temp_dir(), 'inq').'.jpg';
        imagejpeg($img, $path, 100);
        if ($dpi) { // JFIF: units=1 (dpi) + density x/y
            $bytes = file_get_contents($path);
            file_put_contents($path, substr($bytes, 0, 13).chr(1).pack('nn', $dpi, $dpi).substr($bytes, 18));
        }

        return $this->files[] = $path;
    }

    public function test_artwork_gives_colour_notation_and_size_only_with_real_dpi(): void
    {
        $service = new InquirySuggestionService;

        $bw = collect($service->forPart('', $this->image(200, 200, false)))->keyBy('kind');
        $this->assertSame('1/0', $bw['color']['apply']['code']);
        $this->assertMatchesRegularExpression('/screen|layar/i', $bw['size']['text'], 'GD writes 96 dpi: the size is shown with the screen-default warning.');

        $col = collect($service->forPart('', $this->image(1000, 600, true, 300)))->keyBy('kind');
        $this->assertSame('4/4', $col['color']['apply']['code']);
        $this->assertEquals(84.7, $col['size']['apply']['w']);
        $this->assertEquals(50.8, $col['size']['apply']['h']);
        $this->assertDoesNotMatchRegularExpression('/screen|layar/i', $col['size']['text']);

        $screen = collect($service->forPart('', $this->image(720, 720, true, 72)))->keyBy('kind');
        $this->assertMatchesRegularExpression('/screen|layar/i', $screen['size']['text']);
    }

    public function test_history_suggests_material_and_finishing_of_the_same_product_type(): void
    {
        $this->login();
        $paperRoot = MatCategory::roots()->where('category_name', 'Paper')->value('id_category');
        $jenis = MatCategory::where('parent_id', $paperRoot)->where('level', 2)->where('activate', true)
            ->whereExists(fn ($q) => $q->from('mat_category as g')->whereColumn('g.parent_id', 'mat_category.id_category')->where('g.level', 3)->where('g.activate', true))->firstOrFail();
        $gram = MatCategory::where('parent_id', $jenis->id_category)->where('level', 3)->where('activate', true)->firstOrFail();
        $finishing = DB::connection('tm2mst')->table('ref_finishing_type')->where('activate', 1)->value('id_finishing_type');

        $inq = InquiryDriver::create('Box Test Suggest', ['parts' => [[
            'part_name' => 'Body', 'part_type' => 'DC', 'qty_per_sku' => 1, 'cfg_color' => '4/4',
            'materials' => [['chain' => 'P', 'jenis' => $jenis->id_category, 'gramatur' => $gram->id_category]],
            'finishings' => [['finishing_type_id' => $finishing, 'attribute' => '']],
        ]]]);
        $this->ids[] = $inq->id_inquiry;

        $res = $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail())->postJson(route('v2.inquiry.suggest'), ['type_code' => 'RTE_BOX'])->assertOk()->json('suggestions');
        $by = collect($res)->keyBy('kind');
        $this->assertArrayHasKey('material', $by->all());
        $this->assertArrayHasKey('finishing', $by->all());
        $this->assertNotSame('', $by['finishing']['action']);

        $up = $this->postJson(route('v2.inquiry.suggest'), ['type_code' => '', 'artwork' => UploadedFile::fake()->image('a.png', 40, 40)])->assertOk()->json('suggestions');
        $this->assertContains('color', array_column($up, 'kind'));
    }

    public function test_saved_artwork_is_removed_while_draft_and_locked_after_confirm(): void
    {
        $this->login();
        $inq = InquiryDriver::create('Box Test Remove Artwork');
        $this->ids[] = $inq->id_inquiry;
        $part = $inq->parts()->firstOrFail();
        InquiryDriver::writer()->storeArtwork($part, UploadedFile::fake()->image('art.png', 30, 30));
        $path = $part->refresh()->artwork_path;
        Storage::disk('public')->assertExists($path);

        $this->postJson(route('v2.inquiry.artwork.remove', $inq->id_inquiry), ['part_id' => $part->id_part])->assertOk();
        $this->assertNull($part->refresh()->artwork_path);
        $this->assertNull($part->artwork_original_filename);
        Storage::disk('public')->assertMissing($path);

        InquiryDriver::writer()->storeArtwork($part, UploadedFile::fake()->image('art2.png', 30, 30));
        $kept = $part->refresh()->artwork_path;
        InquiryDriver::confirm($inq);
        $this->postJson(route('v2.inquiry.artwork.remove', $inq->id_inquiry), ['part_id' => $part->id_part])->assertStatus(422);
        $this->assertSame($kept, $part->refresh()->artwork_path);
        Storage::disk('public')->delete($kept);
    }

    public function test_legacy_prospect_inquiry_opens_and_saves_with_prospect_name(): void
    {
        $this->login();
        $inq = InquiryDriver::create('Box Test Prospect', [], function (array $p) {
            $p['header'] = ['is_prospect' => true, 'prospect_name' => 'Prospek Lama', 'customer_id' => null] + $p['header'];

            return $p;
        });
        $this->ids[] = $inq->id_inquiry;
        $this->assertTrue((bool) $inq->is_prospect);

        $this->get('/v2/s/inquiry-new?id='.$inq->id_inquiry)->assertOk()->assertSee('Prospek Lama')->assertSee('Prospect Name');

        $part = $inq->parts()->firstOrFail();
        $post = [
            'id' => $inq->id_inquiry, 'action' => 'save', 'inquiry_date' => now()->toDateString(), 'source' => 'EMAIL', 'product_draft_name' => 'Box Test Prospect',
            'type_code' => 'RTE_BOX', 'prospect_name' => 'Prospek Lama Diubah',
            'tiers' => [['id' => $inq->tiers()->value('id_tier'), 'qty' => 1000, 'is_primary' => 1]],
            'parts' => [['id' => $part->id_part, 'part_name' => 'Body', 'part_type' => 'DC', 'qty_per_sku' => 1, 'cfg_color' => '4/4']],
        ];
        $this->postJson(route('v2.inquiry.save'), $post)->assertOk();

        $inq = IqpInquiry::findOrFail($inq->id_inquiry);
        $this->assertTrue((bool) $inq->is_prospect);
        $this->assertSame('Prospek Lama Diubah', $inq->prospect_name);
        $this->assertNull($inq->customer_id);

        $this->postJson(route('v2.inquiry.save'), ['prospect_name' => ''] + $post)->assertStatus(422)->assertJsonValidationErrors('prospect_name');
    }
}

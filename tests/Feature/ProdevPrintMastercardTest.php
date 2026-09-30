<?php

namespace Tests\Feature;

use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// Rebuild 2026-09-09 mendekati docs/mockups/mockup_mastercard.jpg (layar
// sebelumnya belum pernah dicocokkan ke mockup ini + ternyata tidak ada
// <style> lokal sama sekali utk table.items/.desc/.amt/.cap).
class ProdevPrintMastercardTest extends TestCase
{
    protected ?int $partId = null;

    protected ?int $productId = null;

    private ?string $origLang = null;

    // Teks yang dicek berbahasa Indonesia; akun johiwan bisa ber-lang 'en' --
    // paksa 'id' selama test (pola sama dgn InquiryHubStatBoxTest).
    protected function setUp(): void
    {
        parent::setUp();
        $this->origLang = SecUser::where('login', 'johiwan')->value('lang_code');
        SecUser::where('login', 'johiwan')->update(['lang_code' => 'id']);
    }

    protected function tearDown(): void
    {
        SecUser::where('login', 'johiwan')->update(['lang_code' => $this->origLang]);

        if ($this->partId) {
            ProdPart::whereKey($this->partId)->delete();
        }
        if ($this->productId) {
            ProdProduct::whereKey($this->productId)->delete();
        }

        parent::tearDown();
    }

    public function test_mastercard_print_renders_with_no_origin_material(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $product = ProdProduct::create([
            'product_code' => 'TEST-MC-PRINT', 'product_name' => 'Test Mastercard Print', 'activate' => true,
        ]);
        $this->productId = $product->id_product;

        $part = ProdPart::create([
            'product_id' => $product->id_product, 'part_code' => 'TEST-MC-PART', 'part_name' => 'Test Mastercard Part',
            'cm_length' => 24.8, 'cm_width' => 19, 'cm_height' => 3.4, 'flap_side' => 'TOP', 'activate' => true,
            'color_set_1' => ['BLACK', 'CYAN', 'MAGENTA', 'YELLOW'],
        ]);
        $this->partId = $part->id_part;

        $html = Livewire::test('pages::production.old_prodev_print', ['part' => $part->id_part])
            ->assertOk()
            ->assertSee('MASTER CARD / KARTU INDUK')
            ->assertSee('Test Mastercard Part')
            ->assertSee('Unit 1')
            ->assertSee('BLACK')
            ->assertSee('Mode #2 (manual/standalone)')
            ->assertSee('Belum ada artwork/preview tersimpan untuk part ini.')
            ->html();

        // Permintaan user 2026-09-09: A4 landscape + hitam-putih (theme
        // di-set langsung ke hex hitam/putih, bukan cuma class .bw, krn
        // inline style menang lawan class rule utk custom property sama).
        $this->assertStringContainsString('landscape', $html);
        $this->assertStringContainsString('--print-accent:#000000', $html);
        $this->assertStringContainsString('--print-ink:#000000', $html);
        $this->assertStringContainsString('size:A4 landscape', $html);
    }

    // Part demo id=65 ("Rigid Box Parfum #5", PDDUM1-5) -- bagian dari 33
    // prod_part dummy seeded migration 000012 (JANGAN dihapus, lihat memory
    // feedback_keep_dummy_data + project_prodev_inqprod_dummy_workload),
    // DIPERKAYA (bukan dibuat baru) via migration
    // 2026_09_09_000004_enrich_demo_mastercard_part_pddum1_5.php supaya user
    // bisa lihat contoh printout "beneran nyata" (permintaan user 2026-09-09)
    // -- termasuk Material dari Inquiry asal (Mode #1, inquiry 505) & artwork
    // ter-generate. TIDAK ada tearDown yang menghapus part ini.
    public function test_real_demo_part_65_renders_fully_populated(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $html = Livewire::test('pages::production.old_prodev_print', ['part' => 65])
            ->assertOk()
            ->assertSee('Rigid Box Parfum #5')
            ->assertSee('BAPAK BAMBANG')
            ->assertSee('PISAU RBP5 STANDAR')
            ->assertSee('DS000055')
            // Material: read-only reference dari iqp_part_material milik Inquiry
            // asal (Mode #1) -- BUKAN field ProdPart sendiri.
            ->assertSee('B KRAFT 275 GR MSE 75 X 102 CM')
            ->assertSee('275 gsm')
            ->assertSee('BLACK')
            ->assertSee('CYAN')
            ->assertSee('MAGENTA')
            ->assertSee('YELLOW')
            ->assertSee('CETAK')
            ->assertSee('Laminating Doff')
            ->assertSee('Hot Print')
            ->assertSee('Emboss')
            ->assertDontSee('Belum ada artwork/preview tersimpan untuk part ini.')
            ->html();

        $this->assertStringContainsString('prod_part/65/artwork/demo-mastercard.jpg', $html);
    }

    // Permintaan user 2026-09-10: "buatkan minim 5 dummy data dengan artwork"
    // -- part 65 (test di atas) + 4 tambahan di sini via migration
    // 2026_09_10_000002_enrich_4_more_demo_mastercard_parts.php, dipilih
    // dari 33 prod_part dummy PDDUM% yg SUDAH ADA (bukan bikin baru), 4
    // kategori produk berbeda supaya kelihatan variasi. Tidak ada tearDown
    // yg menghapus part2 ini (dummy data permanen, feedback_keep_dummy_data).
    public static function demoMastercardPartProvider(): array
    {
        return [
            'Folding Box Karton Makanan #1' => [61, 'Folding Box Karton Makanan #1', 'NIKI ECO CATERING', 'IVORY 350 GR 27 X 35'],
            'Corrugated Box Elektronik #2' => [62, 'Corrugated Box Elektronik #2', 'A-37', 'Paper Duplex 250gr 114 X 79 CM IKA DP'],
            'Paper Bag Belanja #8' => [68, 'Paper Bag Belanja #8', 'BAPAK TANTO EDY', 'DUPLEX 350 GR 54 X 53 MSExxx'],
            'Hang Tag Produk #9' => [69, 'Hang Tag Produk #9', 'PAK TANTO', 'IVORY 350 GR ESPRIME 79 X 109 CM'],
        ];
    }

    #[DataProvider('demoMastercardPartProvider')]
    public function test_additional_demo_parts_render_fully_populated_with_artwork(int $partId, string $partName, string $customerName, string $materialName): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $html = Livewire::test('pages::production.old_prodev_print', ['part' => $partId])
            ->assertOk()
            ->assertSee($partName)
            ->assertSee($customerName)
            ->assertSee($materialName)
            ->assertDontSee('Belum ada artwork/preview tersimpan untuk part ini.')
            ->html();

        $this->assertStringContainsString("prod_part/{$partId}/artwork/demo-mastercard.jpg", $html);
    }
}

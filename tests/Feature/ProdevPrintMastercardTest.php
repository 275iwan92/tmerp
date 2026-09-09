<?php

namespace Tests\Feature;

use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// Rebuild 2026-09-09 mendekati docs/mockups/mockup_mastercard.jpg (layar
// sebelumnya belum pernah dicocokkan ke mockup ini + ternyata tidak ada
// <style> lokal sama sekali utk table.items/.desc/.amt/.cap).
class ProdevPrintMastercardTest extends TestCase
{
    protected ?int $partId = null;

    protected ?int $productId = null;

    protected function tearDown(): void
    {
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

        $html = Livewire::test('pages::production.prodev-print', ['part' => $part->id_part])
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

        $html = Livewire::test('pages::production.prodev-print', ['part' => 65])
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
}

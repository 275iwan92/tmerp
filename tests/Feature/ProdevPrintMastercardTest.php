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

        Livewire::test('pages::production.prodev-print', ['part' => $part->id_part])
            ->assertOk()
            ->assertSee('MASTER CARD / KARTU INDUK')
            ->assertSee('Test Mastercard Part')
            ->assertSee('Unit 1')
            ->assertSee('BLACK')
            ->assertSee('Mode #2 (manual/standalone)')
            ->assertSee('Belum ada artwork/preview tersimpan untuk part ini.');
    }
}

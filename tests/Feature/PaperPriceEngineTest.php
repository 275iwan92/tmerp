<?php

/*
 * REMARK
 * Tujuan       : Menguji PaperPriceEngine (rumus harga rim ivory Jo 2026-10-17) dan layar Supplier Paper Price.
 * Sumber hulu  : app/Services/Pur/PaperPriceEngine.php, SupplierPaperPriceLookup.
 * Input        : Baris sup_paper_price uji (jenis kertas ZZTESTPAPER) dan bahan tanpa disimpan.
 * Output       : Lulus / gagal; baris uji dibersihkan di tearDown dan diperiksa ke database.
 * Dipakai oleh : php artisan test --filter=PaperPriceEngineTest
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Master\MatGrammage;
use App\Models\Master\MatRaw;
use App\Models\Master\SupPaperPrice;
use App\Models\Master\SupSupplier;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysConstant;
use App\Services\Pur\PaperPriceEngine;
use App\Support\V2\Grid\Master\SupplierPaperPriceLookup;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaperPriceEngineTest extends TestCase
{
    private const KIND = 'ZZTESTPAPER';

    private int $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        SupPaperPrice::query()->where('paper_kind', self::KIND)->delete();
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
        $this->supplier = (int) SupSupplier::query()->orderBy('id_supplier')->value('id_supplier');
    }

    protected function tearDown(): void
    {
        SupPaperPrice::query()->where('paper_kind', self::KIND)->delete();
        $this->assertSame(0, SupPaperPrice::query()->where('paper_kind', self::KIND)->count());
        parent::tearDown();
    }

    private function price(array $extra = []): SupPaperPrice
    {
        return SupPaperPrice::create($extra + ['supplier_id' => $this->supplier, 'paper_kind' => self::KIND, 'roll_price' => 13000, 'roll_price_unit' => 'KG',
            'cut_cost_per_kg' => 250, 'sheet_price_per_kg' => 12500, 'price_basis' => 'INCL', 'effective_date' => '2026-10-01', 'activate' => true]);
    }

    private function material(string $uom = 'RIM', float $gsm = 250, float $cmL = 79, float $cmW = 109): MatRaw
    {
        return new MatRaw(['material_name' => 'IVORY '.self::KIND.' 79 X 109 CM', 'uom_purchase' => $uom, 'gram_weight' => $gsm, 'cm_length' => $cmL, 'cm_width' => $cmW]);
    }

    private function divisor(): float
    {
        return 1 + SysConstant::number('PPN') / 100;
    }

    public function test_divisor_follows_the_ppn_constant_and_excl_price_is_not_divided(): void
    {
        $e = new PaperPriceEngine;
        $this->assertEqualsWithDelta($this->divisor(), $e->divisor('INCL'), 1e-9);
        $this->assertSame(1.0, $e->divisor('EXCL'));
    }

    public function test_ream_price_standard_size_and_cut_from_roll_follow_the_formula(): void
    {
        $e = new PaperPriceEngine;
        $p = $this->price();

        $this->assertEqualsWithDelta(12500 * 0.79 * 1.09 * 125 / $this->divisor(), $e->rimPrice($p, 0.79, 1.09, 250, false), 0.01);
        $this->assertEqualsWithDelta(13250 * 1.0 * 0.7 * 40 / $this->divisor(), $e->rimPrice($p, 1.0, 0.7, 80, true), 0.01);
        $this->assertEqualsWithDelta(1000 / 28, $e->reamsFromRoll(1000, 1.0, 0.7, 80), 1e-9);
        $this->assertNull($e->rimPrice($p, 0, 1, 80, false), 'Missing size gives no price.');
    }

    public function test_roll_price_per_ton_equals_the_same_price_per_kg(): void
    {
        $e = new PaperPriceEngine;
        $perKg = $this->price();
        $perTon = $this->price(['roll_price' => 13000000, 'roll_price_unit' => 'TON']);

        $this->assertEqualsWithDelta($e->rimPrice($perKg, 1, 0.7, 80, true), $e->rimPrice($perTon, 1, 0.7, 80, true), 0.0001);
    }

    public function test_price_for_per_purchase_unit_and_missing_data(): void
    {
        $e = new PaperPriceEngine;
        $this->price();
        $rim = $e->priceFor($this->material('RIM'), $this->supplier, Carbon::parse('2026-10-17'));

        $this->assertEqualsWithDelta(12500 * 0.79 * 1.09 * 125 / $this->divisor(), $rim, 0.01);
        $this->assertEqualsWithDelta($rim / 500, $e->priceFor($this->material('LBR'), $this->supplier, Carbon::parse('2026-10-17')), 0.001);
        $this->assertEqualsWithDelta(13000 / $this->divisor(), $e->priceFor($this->material('KG'), $this->supplier, Carbon::parse('2026-10-17')), 0.001);
        $this->assertNull($e->priceFor($this->material('PCS'), $this->supplier, Carbon::parse('2026-10-17')));
        $this->assertNull($e->priceFor($this->material('RIM', 0), $this->supplier, Carbon::parse('2026-10-17')), 'No grammage, no price.');
        $this->assertNull($e->priceFor($this->material('RIM'), null), 'No supplier, no price.');
    }

    public function test_the_row_in_force_on_the_document_date_is_used_and_a_matching_grammage_wins(): void
    {
        $e = new PaperPriceEngine;
        $this->price(['effective_date' => '2026-10-01', 'sheet_price_per_kg' => 12300]);
        $this->price(['effective_date' => '2026-10-17', 'sheet_price_per_kg' => 12500]);
        $this->price(['effective_date' => '2026-11-01', 'sheet_price_per_kg' => 99999]);
        $this->price(['effective_date' => '2026-10-20', 'sheet_price_per_kg' => 11111, 'activate' => false]);
        $m = $this->material();

        $this->assertEqualsWithDelta(12300, $e->row($m, $this->supplier, Carbon::parse('2026-10-10'))->sheet_price_per_kg, 0.001, 'Older document date uses the older price.');
        $this->assertEqualsWithDelta(12500, $e->row($m, $this->supplier, Carbon::parse('2026-10-25'))->sheet_price_per_kg, 0.001, 'Future and inactive rows are ignored.');

        $g = (int) MatGrammage::query()->where('grammage_value', 250)->value('id_grammage');
        if ($g) {
            $this->price(['grammage_id' => $g, 'effective_date' => '2026-09-01', 'sheet_price_per_kg' => 13500]);
            $this->assertEqualsWithDelta(13500, $e->row($m, $this->supplier, Carbon::parse('2026-10-25'))->sheet_price_per_kg, 0.001, 'The row of the same grammage wins over the empty one.');
        }

        $excl = $this->price(['price_basis' => 'EXCL', 'effective_date' => '2026-10-30', 'sheet_price_per_kg' => 12500]);
        $this->assertEqualsWithDelta(12500 * 0.79 * 1.09 * 125, $e->rimPrice($excl, 0.79, 1.09, 250, false), 0.01, 'Excl PPN is not divided.');
    }

    public function test_the_input_screen_saves_upper_case_kind_keeps_history_and_refuses_a_row_without_price(): void
    {
        $grid = new SupplierPaperPriceLookup;
        $row = ['id' => null, 'supplier_id' => $this->supplier, 'paper_kind' => strtolower(self::KIND), 'roll_price' => 13, 'roll_price_unit' => 'TON',
            'cut_cost_per_kg' => 250, 'sheet_price_per_kg' => 12500, 'price_basis' => 'INCL', 'effective_date' => '2026-10-17', 'activate' => true];
        $grid->save([$row]);
        $grid->save([array_merge($row, ['effective_date' => '2026-11-01', 'sheet_price_per_kg' => 12700])]);

        $this->assertSame(2, SupPaperPrice::query()->where('paper_kind', self::KIND)->count(), 'A new date adds a row; the old one stays as history.');

        $this->expectException(ValidationException::class);
        $grid->save([array_merge($row, ['roll_price' => null, 'sheet_price_per_kg' => null])]);
    }
}

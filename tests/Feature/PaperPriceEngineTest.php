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

use App\Models\Master\FinTaxRate;
use App\Models\Master\MatGrammage;
use App\Models\Master\MatRaw;
use App\Models\Master\SupPaperPrice;
use App\Models\Master\SupSingleFacePrice;
use App\Models\Master\SupSupplier;
use App\Models\Sys\SecUser;
use App\Services\Pur\PaperPriceEngine;
use App\Services\Pur\PaperSpec;
use App\Services\Pur\SingleFacePriceEngine;
use App\Support\Finance\TaxRate;
use App\Support\V2\Grid\Master\SupplierPaperPriceLookup;
use App\Support\V2\Grid\Master\TaxRateLookup;
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
        TaxRate::flush();
        SupPaperPrice::query()->where('paper_kind', self::KIND)->delete();
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
        $this->supplier = (int) SupSupplier::query()->orderBy('id_supplier')->value('id_supplier');
    }

    protected function tearDown(): void
    {
        SupPaperPrice::query()->where('paper_kind', self::KIND)->delete();
        SupSingleFacePrice::query()->where('created_by', self::KIND)->delete();
        $this->assertSame(0, SupPaperPrice::query()->where('paper_kind', self::KIND)->count() + SupSingleFacePrice::query()->where('created_by', self::KIND)->count());
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
        return 1 + TaxRate::ppn() / 100;
    }

    public function test_divisor_follows_the_ppn_rate_in_force_on_the_document_date_and_excl_price_is_not_divided(): void
    {
        $e = new PaperPriceEngine;
        $this->assertEqualsWithDelta($this->divisor(), $e->divisor('INCL'), 1e-9);
        $this->assertEqualsWithDelta(1.10, $e->divisor('INCL', Carbon::parse('2022-03-31')), 1e-9, 'Before 01-Apr-2022 PPN was 10%.');
        $this->assertEqualsWithDelta(1.11, $e->divisor('INCL', Carbon::parse('2022-04-01')), 1e-9, 'From 01-Apr-2022 PPN is 11%.');
        $this->assertSame(1.0, $e->divisor('EXCL'));
    }

    public function test_tax_rate_screen_adds_a_dated_rate_keeps_the_old_one_and_refuses_the_same_date(): void
    {
        $grid = new TaxRateLookup;
        $row = ['id' => null, 'tax_code' => 'PPN', 'rate' => 12, 'effective_date' => '2099-01-01', 'activate' => true];
        try {
            $grid->save([$row]);
            $this->assertEquals(12, TaxRate::ppn('2099-06-01'), 'The new rate applies from its date.');
            $this->assertEquals(11, TaxRate::ppn('2026-10-17'), 'An earlier document keeps its rate.');
            $this->assertEqualsWithDelta(1.12, (new PaperPriceEngine)->divisor('INCL', Carbon::parse('2099-06-01')), 1e-9);

            $this->expectException(ValidationException::class);
            $grid->save([$row]);
        } finally {
            FinTaxRate::query()->whereDate('effective_date', '2099-01-01')->delete();
            TaxRate::flush();
            $this->assertSame(0, FinTaxRate::query()->whereDate('effective_date', '2099-01-01')->count());
        }
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

    public function test_single_face_price_is_index_times_area_divided_by_the_ppn_divisor(): void
    {
        $e = new PaperPriceEngine;
        $sf = new SingleFacePriceEngine;
        $row = fn (array $x = []) => SupSingleFacePrice::create($x + ['supplier_id' => $this->supplier, 'substance_name' => 'SF M 125 / K 125', 'flute_type' => 'E/F', 'index_per_m2' => 2600,
            'price_basis' => 'INCL', 'effective_date' => '2026-10-01', 'activate' => true, 'created_by' => self::KIND]);
        $row();
        $row(['effective_date' => '2026-11-01', 'index_per_m2' => 2700]);
        $mat = fn (string $name, string $uom = 'LBR') => new MatRaw(['material_name' => $name, 'uom_purchase' => $uom]);
        $on = Carbon::parse('2026-10-17');

        $this->assertEqualsWithDelta(2600 * 0.865 * 0.56 / $this->divisor(), $e->priceFor($mat('SF EF M125/K125 865 X 560 MM'), $this->supplier, $on), 0.001, 'MM size, the older row.');
        $this->assertEqualsWithDelta(2600 * 0.72 * 0.52 / $this->divisor(), $e->priceFor($mat('DN SINGLE FACE E/F M125/K125 UK 72 x 52 CM'), $this->supplier, $on), 0.001, 'CM size.');
        $this->assertEqualsWithDelta(2700 * 0.72 * 0.52 / $this->divisor(), $e->priceFor($mat('SF EF M125/K125 72 x 52 CM'), $this->supplier, Carbon::parse('2026-11-05')), 0.001, 'The newer row from its date.');
        $this->assertEqualsWithDelta(500 * 2600 * 0.72 * 0.52 / $this->divisor(), $e->priceFor($mat('SF EF M125/K125 72 x 52 CM', 'RIM'), $this->supplier, $on), 0.01);
        $this->assertNull($e->priceFor($mat('SF EF M125/K127 72 x 52 CM'), $this->supplier, $on), 'Another substance has no row.');
        $this->assertNull($e->priceFor($mat('SF BF M125/K125 72 x 52 CM'), $this->supplier, $on), 'Another flute has no row.');
        $this->assertNull($e->priceFor($mat('SF M/K 72 x 52 CM'), $this->supplier, $on), 'No substance or flute in the name.');
        $this->assertNull($e->priceFor($mat('SF EF M125/K125'), $this->supplier, $on), 'No size in the name.');
        $this->assertNull($e->priceFor($mat('SF EF M125/K125 72 x 52 CM', 'KG'), $this->supplier, $on), 'Per kg is not a sheet price.');
        $this->assertSame('M125K125', PaperSpec::substanceKey('S FACE 125 M / 125 K'));
        $this->assertSame('EF', PaperSpec::fluteKey('E/F'));
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

<?php

/*
 * REMARK
 * Tujuan       : Menguji engine duplex dan lembar gelombang terhadap angka di Excel Jo (HITUNGAN DUPLEX.xlsx sheet INDEX; PERHITUNGAN BF, BCF, SINGLE WALL, DOUBLE WALL.xlsx sheet UJK, RCA), pemilihan baris menurut tanggal / gsm / grade, dan tiga layar konstanta.
 * Sumber hulu  : DuplexPriceEngine, CorrugatedSheetPriceEngine, PaperPriceEngine::priceFor, tiga layar lookup Master Data.
 * Input        : Baris konstanta uji (created_by ZZPAPER) dan bahan tanpa disimpan; tarif PPN dari fin_tax_rate.
 * Output       : Lulus / gagal; baris uji dihapus di tearDown dan diperiksa ke database.
 * Dipakai oleh : php artisan test --filter=PaperPurchaseEnginesTest
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Master\MatRaw;
use App\Models\Master\SupCorrugatedIndex;
use App\Models\Master\SupCorrugatedRule;
use App\Models\Master\SupDuplexPrice;
use App\Models\Master\SupSupplier;
use App\Models\Sys\SecUser;
use App\Services\Pur\CorrugatedSheetPriceEngine;
use App\Services\Pur\DuplexPriceEngine;
use App\Services\Pur\PaperPriceEngine;
use App\Support\Finance\TaxRate;
use App\Support\V2\Grid\Master\SupplierCorrugatedIndexLookup;
use App\Support\V2\Grid\Master\SupplierCorrugatedRuleLookup;
use App\Support\V2\Grid\Master\SupplierDuplexPriceLookup;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaperPurchaseEnginesTest extends TestCase
{
    private const BY = 'ZZPAPER';

    private int $supplier;

    private Carbon $on;

    protected function setUp(): void
    {
        parent::setUp();
        TaxRate::flush();
        $this->clean();
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
        $this->supplier = (int) SupSupplier::query()->orderBy('id_supplier')->value('id_supplier');
        $this->on = Carbon::parse('2026-10-17');
    }

    protected function tearDown(): void
    {
        $this->clean();
        $this->assertSame(0, SupDuplexPrice::query()->where('created_by', self::BY)->count() + SupCorrugatedRule::query()->where('created_by', self::BY)->count()
            + SupCorrugatedIndex::query()->where('created_by', self::BY)->count());
        parent::tearDown();
    }

    private function clean(): void
    {
        foreach ([SupDuplexPrice::class, SupCorrugatedRule::class, SupCorrugatedIndex::class] as $model) {
            $model::query()->where('created_by', self::BY)->delete();
        }
    }

    private function divisor(): float
    {
        return 1 + TaxRate::ppn($this->on) / 100;
    }

    private function mat(string $name, string $uom = 'RIM'): MatRaw
    {
        return new MatRaw(['material_name' => $name, 'uom_purchase' => $uom]);
    }

    private function duplex(array $x = []): SupDuplexPrice
    {
        return SupDuplexPrice::create($x + ['supplier_id' => $this->supplier, 'grade' => null, 'gsm_from' => 250, 'gsm_to' => 310, 'formula' => DuplexPriceEngine::REF_SHEET, 'index_value' => 1000,
            'apkey_pct' => 500, 'price_basis' => 'INCL', 'effective_date' => '2026-10-01', 'activate' => true, 'created_by' => self::BY]);
    }

    private function rule(array $x = []): SupCorrugatedRule
    {
        return SupCorrugatedRule::create($x + ['supplier_id' => $this->supplier, 'max_trim_width_cm' => 180, 'disc_300' => -10, 'disc_500' => -10, 'disc_1000' => -10, 'disc_2000' => -10,
            'effective_date' => '2026-10-01', 'activate' => true, 'created_by' => self::BY]);
    }

    private function index(string $substance, float $value, array $x = []): SupCorrugatedIndex
    {
        return SupCorrugatedIndex::create($x + ['supplier_id' => $this->supplier, 'substance_name' => $substance, 'index_per_m2' => $value, 'price_basis' => 'INCL',
            'effective_date' => '2026-10-01', 'activate' => true, 'created_by' => self::BY]);
    }

    public function test_duplex_reference_sheet_and_weight_formulas_equal_the_excel_values(): void
    {
        $e = new DuplexPriceEngine;
        $this->duplex(['gsm_from' => 400, 'gsm_to' => 400, 'index_value' => 353000, 'apkey_pct' => 480]);
        $this->duplex(['gsm_from' => 310, 'gsm_to' => 310, 'formula' => DuplexPriceEngine::WEIGHT, 'index_value' => 2173, 'apkey_pct' => 600]);

        // INDEX!H3 = 2.091.012,78 dan INDEX!J3 = H3 / 1,11; INDEX!H10 = 1.740.188,38.
        $this->assertEqualsWithDelta(2091012.7790036 / $this->divisor(), $e->priceFor($this->mat('DUPLEX 400 GR 89 X 119,4'), $this->supplier, $this->on), 0.01);
        $this->assertEqualsWithDelta(1740188.379 / $this->divisor(), $e->priceFor($this->mat('DUPLEX 310 GR 79 X 109'), $this->supplier, $this->on), 0.01);
        $this->assertEqualsWithDelta(1740188.379 / $this->divisor() / 500, $e->priceFor($this->mat('DUPLEX 310 GR 79 X 109', 'LBR'), $this->supplier, $this->on), 0.001);
        $this->assertNull($e->priceFor($this->mat('DUPLEX 310 GR 79 X 109', 'KG'), $this->supplier, $this->on));
    }

    public function test_duplex_excl_price_is_not_divided_and_master_columns_win_over_the_name(): void
    {
        $e = new DuplexPriceEngine;
        $this->duplex(['gsm_from' => 350, 'gsm_to' => 350, 'index_value' => 1000, 'apkey_pct' => 505, 'price_basis' => 'EXCL']);
        $m = new MatRaw(['material_name' => 'DUPLEX 999 GR 1 X 1', 'uom_purchase' => 'RIM', 'gram_weight' => 350, 'cm_length' => 79, 'cm_width' => 109]);

        $this->assertEqualsWithDelta(79 * 109 / (79 * 109) * 1000 * 5.05, $e->priceFor($m, $this->supplier, $this->on), 0.001);
    }

    public function test_duplex_picks_the_row_by_date_gsm_range_and_grade(): void
    {
        $e = new DuplexPriceEngine;
        $this->duplex(['grade' => 'IKDP', 'index_value' => 1000, 'apkey_pct' => 500, 'price_basis' => 'EXCL']);
        $this->duplex(['grade' => 'IDA', 'index_value' => 1000, 'apkey_pct' => 510, 'price_basis' => 'EXCL']);
        $this->duplex(['grade' => 'IKDP', 'index_value' => 1000, 'apkey_pct' => 600, 'price_basis' => 'EXCL', 'effective_date' => '2026-11-01']);
        $price = fn (string $name, ?Carbon $on = null) => $e->priceFor($this->mat($name), $this->supplier, $on ?? $this->on);

        $this->assertEqualsWithDelta(5000, $price('DUPLEX 250 GR IKDP 79 X 109'), 0.001, 'Grade in the name.');
        $this->assertEqualsWithDelta(5100, $price('DUPLEX 250 GR IDA 79 X 109'), 0.001);
        $this->assertEqualsWithDelta(6000, $price('DUPLEX 250 GR IKDP 79 X 109', Carbon::parse('2026-11-05')), 0.001, 'The newer row from its date.');
        $this->assertNull($price('DUPLEX 250 GR 79 X 109'), 'Two grades, none named, no open row: the officer chooses.');
        $this->assertNull($price('DUPLEX 350 GR IKDP 79 X 109'), 'Outside the gsm range.');

        $this->duplex(['grade' => null, 'index_value' => 1000, 'apkey_pct' => 400, 'price_basis' => 'EXCL']);
        $this->assertEqualsWithDelta(4000, $price('DUPLEX 250 GR 79 X 109'), 0.001, 'An open row is used when no grade is named.');
        $this->assertEqualsWithDelta(5000, $price('DUPLEX 250 GR IKDP 79 X 109'), 0.001, 'A named grade still wins.');
    }

    public function test_corrugated_quotes_equal_the_excel_values(): void
    {
        $e = new CorrugatedSheetPriceEngine;
        // UJK sheet: SW lebar 64 x panjang 78,5, 800 lembar, maks 180, diskon -10%, index SW 4410 -> B9 = 2475,22275.
        $this->rule(['max_trim_width_cm' => 180, 'disc_300' => -10]);
        $this->index('SW', 4410);
        $q = $e->quote($this->mat('SH SW K150/M125/K125 64 X 78,5 CM', 'LBR'), $this->supplier, 800, $this->on);
        $this->assertSame(2, $q['ups']);
        $this->assertEquals(130, $q['trim_width']);
        $this->assertEqualsWithDelta(314, $q['running_meter'], 0.001);
        $this->assertSame(300, $q['tier']);
        $this->assertEqualsWithDelta(2475.22275 / $this->divisor(), $q['price'], 0.001);

        // RCA sheet: lebar 95 x 57,5, 1500 lembar, maks 220, diskon 15% -> 2101,5028125.
        SupCorrugatedRule::query()->where('created_by', self::BY)->delete();
        $this->rule(['max_trim_width_cm' => 220, 'disc_300' => 15]);
        $this->assertEqualsWithDelta(2101.5028125 / $this->divisor(), $e->priceFor($this->mat('SH SW K150/M125/K125 95 X 57,5 CM', 'LBR'), $this->supplier, 1500, $this->on), 0.001);

        // RCA WK: 150 WL / 125 / 125 KL, lebar 67 x 246,4, 370 lembar, maks 220, diskon -25%, index 5285 -> 11123,163333.
        SupCorrugatedRule::query()->where('created_by', self::BY)->delete();
        $this->rule(['max_trim_width_cm' => 220, 'disc_300' => -25]);
        $this->index('150 WL / 125 / 125 KL', 5285);
        $this->assertEqualsWithDelta(11123.163333333 / $this->divisor(), $e->priceFor($this->mat('SH BF 150 WL/125/125 KL 67 X 246,4 CM', 'LBR'), $this->supplier, 370, $this->on), 0.001, 'The substance row wins over the generic SW row.');

        // UJK WK: lebar 34,4 x 113,3, 1800 lembar, maks 180, diskon -9%, index 5285 -> 2284,3857575.
        SupCorrugatedRule::query()->where('created_by', self::BY)->delete();
        $this->rule(['max_trim_width_cm' => 180, 'disc_300' => -9]);
        $this->assertEqualsWithDelta(2284.3857575 / $this->divisor(), $e->priceFor($this->mat('SH BF 150 WL/125/125 KL 34,4 X 113,3 CM', 'LBR'), $this->supplier, 1800, $this->on), 0.001);
    }

    public function test_corrugated_tiers_minimum_quantity_and_missing_data(): void
    {
        $e = new CorrugatedSheetPriceEngine;
        $this->rule(['max_trim_width_cm' => 180, 'disc_300' => -10, 'disc_500' => 0, 'disc_1000' => 10, 'disc_2000' => null]);
        $this->index('SW', 1000, ['price_basis' => 'EXCL']);
        $m = $this->mat('SH SW K150/M125/K125 64 X 100 CM', 'LBR');

        // up 2, panjang 100 cm: running meter = jumlah / 2.
        $this->assertSame(300, $e->quote($m, $this->supplier, 700, $this->on)['tier']);
        $this->assertSame(500, $e->quote($m, $this->supplier, 1100, $this->on)['tier']);
        $this->assertSame(1000, $e->quote($m, $this->supplier, 2500, $this->on)['tier']);
        $this->assertSame(1000, $e->quote($m, $this->supplier, 5000, $this->on)['tier'], 'A tier without a discount is skipped.');
        $this->assertEqualsWithDelta(130 * 100 / 10000 * 1000 / 2 * 0.9, $e->priceFor($m, $this->supplier, 2500, $this->on), 0.001, 'Excl price: not divided.');

        $below = $e->quote($m, $this->supplier, 500, $this->on);
        $this->assertNull($below['price']);
        $this->assertSame('minimum', $below['reason']);
        $this->assertSame('qty', $e->quote($m, $this->supplier, null, $this->on)['reason']);
        $this->assertSame('size', $e->quote($this->mat('SH SW K150/M125/K125'), $this->supplier, 800, $this->on)['reason']);
        $this->assertSame('width', $e->quote($this->mat('SH SW K150/M125/K125 200 X 100 CM'), $this->supplier, 800, $this->on)['reason']);
        $this->assertSame('rule', $e->quote($m, $this->supplier + 99999, 800, $this->on)['reason']);
        $this->assertNull($e->priceFor($this->mat('SH SW K150/M125/K125 64 X 100 CM', 'KG'), $this->supplier, 2500, $this->on));
        $this->assertEqualsWithDelta(500 * $e->priceFor($m, $this->supplier, 2500, $this->on), $e->priceFor($this->mat('SH SW K150/M125/K125 64 X 100 CM', 'RIM'), $this->supplier, 5, $this->on), 0.01, 'RIM = 500 sheets.');
    }

    public function test_paper_price_engine_sends_each_material_type_to_its_engine(): void
    {
        $p = new PaperPriceEngine;
        $this->duplex(['formula' => DuplexPriceEngine::REF_SHEET, 'price_basis' => 'EXCL']);
        $this->rule();
        $this->index('SW', 1000, ['price_basis' => 'EXCL']);

        $this->assertNotNull($p->priceFor($this->mat('DUPLEX 250 GR 79 X 109'), $this->supplier, $this->on));
        $this->assertNotNull($p->priceFor($this->mat('SH SW K150/M125/K125 64 X 100 CM', 'LBR'), $this->supplier, $this->on, 800));
        $this->assertNull($p->priceFor($this->mat('SH SW K150/M125/K125 64 X 100 CM', 'LBR'), $this->supplier, $this->on), 'A sheet without a quantity has no price.');
    }

    public function test_the_three_input_screens_save_dated_rows_and_refuse_duplicates(): void
    {
        $duplex = new SupplierDuplexPriceLookup;
        $row = ['id' => null, 'supplier_id' => $this->supplier, 'grade' => 'ikdp', 'gsm_from' => 250, 'gsm_to' => 310, 'formula' => DuplexPriceEngine::REF_SHEET, 'index_value' => 1000,
            'apkey_pct' => 500, 'price_basis' => 'EXCL', 'effective_date' => '2026-10-17', 'activate' => true];
        $duplex->save([$row]);
        $duplex->save([array_merge($row, ['effective_date' => '2026-11-01', 'apkey_pct' => 510])]);
        SupDuplexPrice::query()->where('supplier_id', $this->supplier)->where('grade', 'IKDP')->where('gsm_from', 250)->update(['created_by' => self::BY]);
        $this->assertSame(2, SupDuplexPrice::query()->where('created_by', self::BY)->count(), 'Grade saved in upper case; a new date adds a row.');
        $this->assertRefuses(fn () => $duplex->save([array_merge($row, ['gsm_to' => 100, 'effective_date' => '2026-12-01'])]), ValidationException::class);
        $this->assertRefuses(fn () => $duplex->save([$row]), ValidationException::class);

        $rule = new SupplierCorrugatedRuleLookup;
        $rr = ['id' => null, 'supplier_id' => $this->supplier, 'max_trim_width_cm' => 180, 'disc_300' => -12.5, 'disc_500' => -12.5, 'disc_1000' => -12.5, 'disc_2000' => -10, 'effective_date' => '2026-10-17', 'activate' => true];
        $rule->save([$rr]);
        SupCorrugatedRule::query()->where('supplier_id', $this->supplier)->whereDate('effective_date', '2026-10-17')->update(['created_by' => self::BY]);
        $this->assertRefuses(fn () => $rule->save([$rr]), ValidationException::class);

        $index = new SupplierCorrugatedIndexLookup;
        $ri = ['id' => null, 'supplier_id' => $this->supplier, 'substance_name' => 'sw', 'index_per_m2' => 4410, 'price_basis' => 'INCL', 'effective_date' => '2026-10-17', 'activate' => true];
        $index->save([$ri]);
        SupCorrugatedIndex::query()->where('supplier_id', $this->supplier)->where('substance_name', 'SW')->whereDate('effective_date', '2026-10-17')->update(['created_by' => self::BY]);
        $this->assertSame(1, SupCorrugatedIndex::query()->where('created_by', self::BY)->count(), 'Substance saved in upper case.');
        $this->assertRefuses(fn () => $index->save([$ri]), ValidationException::class);
    }

    private function assertRefuses(callable $fn, string $class): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->assertInstanceOf($class, $e);

            return;
        }
        $this->fail("Expected {$class}.");
    }
}

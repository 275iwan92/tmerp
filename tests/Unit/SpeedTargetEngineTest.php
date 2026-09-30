<?php

namespace Tests\Unit;

use App\Services\Production\SpeedTarget\MachineSpeedContext;
use App\Services\Production\SpeedTarget\SpeedHistory;
use App\Services\Production\SpeedTarget\SpeedSample;
use App\Services\Production\SpeedTarget\SpeedSettings;
use App\Services\Production\SpeedTarget\SpeedSuggestion;
use App\Services\Production\SpeedTarget\SpeedTargetEngine;
use PHPUnit\Framework\TestCase;

// Engine target kecepatan murni hitung — diuji tanpa database (data mesin disusun sebagai MachineSpeedContext).
class SpeedTargetEngineTest extends TestCase
{
    private function engine(array $settings = []): SpeedTargetEngine
    {
        return new SpeedTargetEngine(SpeedSettings::make($settings + ['SPEED_MIN_SAMPLE' => 3, 'SPEED_HIGH_SAMPLE' => 10]));
    }

    // $rates = qty per jam; tiap catatan 60 menit supaya rate = qty.
    private function samples(array $rates, string $process = 'PLONG', string $uom = 'LBR'): array
    {
        return array_map(fn ($r) => new SpeedSample($r, 60, $process, $uom, '2023-05-01'), $rates);
    }

    private function machine(int $id, array $o = []): MachineSpeedContext
    {
        return new MachineSpeedContext(
            machineId: $id, code: $o['code'] ?? "M-{$id}", type: $o['type'] ?? 'Die Cutting', drive: $o['drive'] ?? 'MANUAL',
            maxSpeed: $o['max'] ?? null, modelId: $o['model'] ?? null, modelCode: isset($o['model']) ? 'MODEL-'.$o['model'] : null,
            currentTarget: $o['current'] ?? null,
            capabilities: $o['caps'] ?? [['capability_id' => $id * 10, 'process_id' => 3, 'process_name' => 'PLONG', 'uom' => 'LEMBAR', 'current' => null, 'rank' => 1]],
            v2Samples: $o['v2'] ?? [], legacySamples: $o['legacy'] ?? [], legacyProcessSamples: $o['legacyProcess'] ?? [],
            processDefaultSpeed: $o['processDefault'] ?? null, legacyNames: $o['legacyNames'] ?? null,
        );
    }

    public function test_percentile_matches_mysql_percent_rank_method(): void
    {
        // MIN(x WHERE PERCENT_RANK() >= 0.75): index ceil(0.75 * (n-1)).
        $this->assertSame(700.0, SpeedHistory::percentile([100, 300, 500, 700, 900], 0.75));
        $this->assertSame(900.0, SpeedHistory::percentile([900, 100, 700, 300], 0.75));   // ceil(2.25) = index 3
        $this->assertSame(42.0, SpeedHistory::percentile([42], 0.75));
    }

    public function test_history_skips_setup_rows_odd_runs_and_other_units(): void
    {
        $h = new SpeedHistory(SpeedSettings::make(['SPEED_MIN_SAMPLE' => 3]));
        $samples = [
            ...$this->samples([600, 700, 800, 900]),
            new SpeedSample(1, 60, 'SETTING PLONG', 'LBR'),     // setup, bukan produksi
            new SpeedSample(5000, 2, 'PLONG', 'LBR'),            // run 2 menit = salah ketik
            new SpeedSample(99999, 60, 'PLONG', 'SET'),          // satuan lain
        ];

        $s = $h->suggest($samples, 'LEMBAR', 'LEGACY_MACHINE', ['high' => 'HIGH', 'low' => 'MEDIUM'], 'test');

        $this->assertSame(900.0, $s->speed);   // P75 dari 600..900 (4 catatan) = index ceil(2.25)=3
        $this->assertSame('LEMBAR', $s->uom);
        $this->assertSame(4, $s->sampleSize);
        $this->assertSame('MEDIUM', $s->confidence);
    }

    public function test_history_falls_back_to_dominant_unit_but_lowers_confidence(): void
    {
        $h = new SpeedHistory(SpeedSettings::make(['SPEED_MIN_SAMPLE' => 3, 'SPEED_HIGH_SAMPLE' => 3]));
        $s = $h->suggest($this->samples([100, 200, 300], 'LEM MESIN', 'PCS'), 'LEMBAR', 'LEGACY_MACHINE', ['high' => 'HIGH', 'low' => 'MEDIUM'], 'test');

        $this->assertSame('PCS', $s->uom);
        $this->assertSame('MEDIUM', $s->confidence);
        $this->assertStringContainsString('differs from process unit LEMBAR', $s->basis);
    }

    public function test_too_few_records_give_no_history_suggestion(): void
    {
        $h = new SpeedHistory(SpeedSettings::make(['SPEED_MIN_SAMPLE' => 20]));
        $this->assertNull($h->suggest($this->samples([500, 600]), 'LEMBAR', 'LEGACY_MACHINE', ['high' => 'HIGH', 'low' => 'MEDIUM'], 'test'));
    }

    public function test_source_priority_v2_beats_legacy_beats_spec(): void
    {
        $m = $this->machine(1, ['v2' => $this->samples([1000, 1000, 1000]), 'legacy' => $this->samples([700, 700, 700]), 'max' => 1500]);
        $this->assertSame('V2_ACTUAL', $this->engine()->run([$m])[1]['machine']->source);

        $m = $this->machine(1, ['legacy' => $this->samples([700, 700, 700]), 'max' => 1500]);
        $r = $this->engine()->run([$m])[1]['machine'];
        $this->assertSame('LEGACY_MACHINE', $r->source);
        $this->assertSame(700.0, $r->speed);
    }

    public function test_legacy_process_history_is_used_when_machine_has_none(): void
    {
        $m = $this->machine(1, ['caps' => [['capability_id' => 10, 'process_id' => 18, 'process_name' => 'Stitching', 'uom' => 'LEMBAR', 'current' => null, 'rank' => 2]],
            'legacyProcess' => ['STITCHING' => $this->samples([150, 160, 170], 'STITCHING')]]);

        $s = $this->engine()->run([$m])[1]['machine'];
        $this->assertSame('LEGACY_PROCESS', $s->source);
        $this->assertSame('LOW', $s->confidence);
        $this->assertSame(170.0, $s->speed);
    }

    public function test_same_model_twin_lends_its_history(): void
    {
        $known = $this->machine(1, ['model' => 7, 'legacy' => $this->samples([40, 40, 40])]);
        $twin = $this->machine(2, ['model' => 7]);

        $s = $this->engine()->run([$known, $twin])[2]['machine'];
        $this->assertSame('SAME_MODEL', $s->source);
        $this->assertSame(40.0, $s->speed);
    }

    public function test_peer_ratio_uses_real_plant_efficiency_of_same_type_and_drive(): void
    {
        $a = $this->machine(1, ['max' => 1500, 'legacy' => $this->samples([750, 750, 750])]);   // rasio 0.50
        $b = $this->machine(2, ['max' => 1000, 'legacy' => $this->samples([600, 600, 600])]);   // rasio 0.60
        $auto = $this->machine(3, ['max' => 7000, 'drive' => 'AUTO', 'legacy' => $this->samples([100, 100, 100])]);
        $new = $this->machine(4, ['max' => 1200]);

        $s = $this->engine()->run([$a, $b, $auto, $new])[4]['machine'];
        $this->assertSame('PEER_RATIO', $s->source);
        $this->assertSame(660.0, $s->speed);   // 1200 x median(0.50, 0.60) = 1200 x 0.55; mesin AUTO tidak ikut
    }

    public function test_model_spec_uses_drive_efficiency_constant(): void
    {
        $manual = $this->machine(1, ['type' => 'Pressing', 'max' => 1380]);
        $auto = $this->machine(2, ['type' => 'Water-Based Coating', 'drive' => 'AUTO', 'max' => 9000]);

        $r = $this->engine(['SPEED_EFF_MANUAL' => 0.5, 'SPEED_EFF_AUTO' => 0.6])->run([$manual, $auto]);
        $this->assertSame(['MODEL_SPEC', 690.0, 'LOW'], [$r[1]['machine']->source, $r[1]['machine']->speed, $r[1]['machine']->confidence]);
        $this->assertSame(5400.0, $r[2]['machine']->speed);
    }

    public function test_process_default_is_last_resort_and_nothing_means_null(): void
    {
        $this->assertSame('PROCESS_DEFAULT', $this->engine()->run([$this->machine(1, ['processDefault' => 250])])[1]['machine']->source);
        $this->assertNull($this->engine()->run([$this->machine(1)])[1]['machine']);
    }

    public function test_capability_suggestion_only_when_pair_history_differs_or_pair_has_own_value(): void
    {
        $caps = [
            ['capability_id' => 10, 'process_id' => 3, 'process_name' => 'PLONG', 'uom' => 'LEMBAR', 'current' => null, 'rank' => 1],
            ['capability_id' => 11, 'process_id' => 8, 'process_name' => 'KETREK', 'uom' => 'LEMBAR', 'current' => null, 'rank' => 2],
            ['capability_id' => 12, 'process_id' => 9, 'process_name' => 'EMBOS', 'uom' => 'LEMBAR', 'current' => 5000, 'rank' => 2],
        ];
        $legacy = [...$this->samples([700, 700, 700]), ...$this->samples([300, 300, 300], 'KETREK'), ...$this->samples([690, 690, 690], 'EMBOS')];

        $r = $this->engine()->run([$this->machine(1, ['caps' => $caps, 'legacy' => $legacy])])[1];

        $this->assertArrayNotHasKey(10, $r['capabilities']);            // sama dengan target mesin -> cukup mewarisi
        $this->assertSame(300.0, $r['capabilities'][11]->speed);        // pasangan jauh lebih lambat
        $this->assertSame(690.0, $r['capabilities'][12]->speed);        // pasangan punya angka sendiri -> dinilai ulang
    }

    public function test_should_propose_rules(): void
    {
        $e = $this->engine(['SPEED_CHANGE_MIN_PCT' => 5]);
        $mk = fn (float $v, string $c) => new SpeedSuggestion($v, 'LEMBAR', 'LEGACY_MACHINE', $c, 100, 'x');

        $this->assertTrue($e->shouldPropose(null, $mk(700, 'LOW')));        // kosong -> selalu diusulkan
        $this->assertFalse($e->shouldPropose(703, $mk(720, 'HIGH')));       // selisih 2,4% -> tidak mengganggu
        $this->assertTrue($e->shouldPropose(703, $mk(765, 'HIGH')));        // selisih 8,8%
        $this->assertFalse($e->shouldPropose(5600, $mk(4200, 'LOW')));      // tebakan Rendah tidak menggugat angka yang ada
    }

    public function test_excluded_process_words_come_from_setting(): void
    {
        $s = SpeedSettings::make(['SPEED_LEGACY_EXCLUDE' => 'SETTING, QC']);
        $this->assertTrue($s->isExcludedProcess('setting plong'));
        $this->assertTrue($s->isExcludedProcess('QC CETAK'));
        $this->assertFalse($s->isExcludedProcess('PLONG'));
    }
}

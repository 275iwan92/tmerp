<?php

namespace App\Services\Production\SpeedTarget;

// Mengubah sekumpulan catatan produksi menjadi satu angka target: saring run tak wajar & proses non-produksi,
// pilih satuan, ambil persentil. Dipakai semua sumber berbasis riwayat supaya aturannya satu.
final class SpeedHistory
{
    public function __construct(private readonly SpeedSettings $settings) {}

    /**
     * @param  list<SpeedSample>  $samples
     * @param  ?string  $wantUom  satuan output proses tujuan (LEMBAR/PCS); null = ambil satuan terbanyak
     * @param  array{high: string, low: string}  $confidence  keyakinan bila sampel >= SPEED_HIGH_SAMPLE / di bawahnya
     */
    public function suggest(array $samples, ?string $wantUom, string $source, array $confidence, string $label): ?SpeedSuggestion
    {
        $minMin = $this->settings->num('SPEED_RUN_MIN_MINUTES');
        $maxMin = $this->settings->num('SPEED_RUN_MAX_MINUTES');

        $byUom = [];
        foreach ($samples as $s) {
            if ($s->qty > 0 && $s->minutes >= $minMin && $s->minutes <= $maxMin && ! $this->settings->isExcludedProcess($s->process)) {
                $byUom[$s->uom ?? '?'][] = $s;
            }
        }
        if ($byUom === []) {
            return null;
        }

        $wantUom = SpeedSample::normalizeUom($wantUom);
        $uom = $wantUom !== null && count($byUom[$wantUom] ?? []) >= $this->settings->minSample()
            ? $wantUom
            : collect($byUom)->sortByDesc(fn ($rows) => count($rows))->keys()->first();
        $rows = $byUom[$uom];
        $n = count($rows);
        if ($n < $this->settings->minSample()) {
            return null;
        }

        $rates = array_map(fn (SpeedSample $s) => $s->ratePerHour(), $rows);
        $speed = round(self::percentile($rates, $this->settings->percentile()));
        $dates = array_filter(array_map(fn (SpeedSample $s) => $s->date, $rows));
        $period = $dates ? ' '.substr(min($dates), 0, 7).'..'.substr(max($dates), 0, 7) : '';

        $s = new SpeedSuggestion(
            $speed, $uom === '?' ? null : $uom, $source,
            $n >= $this->settings->highSample() ? $confidence['high'] : $confidence['low'],
            $n,
            sprintf('P%d of %s runs%s, %s; unit %s', round($this->settings->percentile() * 100), number_format($n), $period, $label, $uom),
        );

        return $wantUom !== null && $uom !== $wantUom
            ? $s->downgraded("history unit {$uom} differs from process unit {$wantUom}")
            : $s;
    }

    // Sama dengan MySQL MIN(x WHERE PERCENT_RANK() >= p) — metode yang dipakai PPIC baseline 2026-10-01.
    public static function percentile(array $values, float $p): float
    {
        sort($values);
        $n = count($values);

        return $n === 1 ? (float) $values[0] : (float) $values[(int) ceil($p * ($n - 1) - 1e-9)];
    }
}

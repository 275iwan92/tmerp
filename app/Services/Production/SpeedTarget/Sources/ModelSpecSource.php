<?php

namespace App\Services\Production\SpeedTarget\Sources;

use App\Services\Production\SpeedTarget\MachineSpeedContext;
use App\Services\Production\SpeedTarget\SpeedPeers;
use App\Services\Production\SpeedTarget\SpeedSettings;
use App\Services\Production\SpeedTarget\SpeedSuggestion;

// (5) Kecepatan maks (mesin, atau model katalog) x faktor efisiensi penggerak. Faktor DIKALIBRASI dari riwayat nyata
// (user 2026-10-01: "target produksi bisa dikoreksi dengan data legacy lama agar didapat estimasi mendekati realita"):
// median rasio aktual/maks semua mesin pabrik dengan penggerak sama yang punya riwayat. Konstanta SPEED_EFF_* hanya
// dipakai bila mesin pembanding < SPEED_EFF_CALIBRATE_MIN atau kalibrasi dimatikan (SPEED_EFF_CALIBRATE = 0).
final class ModelSpecSource implements SpeedSource
{
    public function __construct(private readonly SpeedSettings $settings) {}

    public function suggest(MachineSpeedContext $m, SpeedPeers $peers): ?SpeedSuggestion
    {
        if (! $m->maxSpeed) {
            return null;
        }
        $drive = $m->drive ?? 'MANUAL';
        $spec = sprintf('max %s/h%s', number_format($m->maxSpeed), $m->modelCode ? " ({$m->modelCode})" : '');

        $same = $this->settings->num('SPEED_EFF_CALIBRATE') > 0
            ? $peers->withHistory($m, fn (MachineSpeedContext $c) => ($c->drive ?? 'MANUAL') === $drive && $c->maxSpeed > 0)
            : [];
        if (count($same) >= max(1, (int) $this->settings->num('SPEED_EFF_CALIBRATE_MIN'))) {
            $ratios = array_map(fn ($p) => $p['suggestion']->speed / $p['context']->maxSpeed, $same);
            sort($ratios);
            $mid = intdiv(count($ratios), 2);
            $eff = count($ratios) % 2 ? $ratios[$mid] : ($ratios[$mid - 1] + $ratios[$mid]) / 2;
            $list = implode(', ', array_map(fn ($p) => sprintf('%s %.2f', $p['context']->code, $p['suggestion']->speed / $p['context']->maxSpeed), $same));

            return new SpeedSuggestion(round($m->maxSpeed * $eff), $m->primaryUom(), 'MODEL_SPEC', 'LOW', count($same),
                sprintf('%s x %s efficiency %.2f calibrated from real history of %d %s machines (%s)', $spec, $drive, $eff, count($same), $drive, $list));
        }

        $eff = $this->settings->efficiency($drive);

        return new SpeedSuggestion(round($m->maxSpeed * $eff), $m->primaryUom(), 'MODEL_SPEC', 'LOW', 0,
            sprintf('%s x %s efficiency %.2f (constant SPEED_EFF_%s; too few %s machines with history to calibrate)', $spec, $drive, $eff, $drive, $drive));
    }
}

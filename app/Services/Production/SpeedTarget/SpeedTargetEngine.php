<?php

namespace App\Services\Production\SpeedTarget;

use App\Services\Production\SpeedTarget\Sources\HistorySource;
use App\Services\Production\SpeedTarget\Sources\ModelSpecSource;
use App\Services\Production\SpeedTarget\Sources\PeerRatioSource;
use App\Services\Production\SpeedTarget\Sources\ProcessDefaultSource;
use App\Services\Production\SpeedTarget\Sources\SameModelSource;
use App\Services\Production\SpeedTarget\Sources\SpeedSource;

// ENGINE TARGET KECEPATAN — "engine suggests, officer confirms" (CLAUDE.md §4). Murni hitung: tidak membaca/menulis DB
// (SpeedDataRepository memuat, SpeedSuggestionStore menyimpan usulan PENDING). Urutan sumber = prioritas; sumber pertama
// yang menghasilkan angka menang. Dua lintasan: riwayat tiap mesin dulu, baru sumber yang meminjam riwayat mesin lain.
final class SpeedTargetEngine
{
    /** @var list<SpeedSource> */
    private array $sources;

    private SpeedHistory $history;

    public function __construct(private readonly SpeedSettings $settings)
    {
        $this->history = new SpeedHistory($settings);
        $this->sources = [
            new HistorySource($this->history, 'V2_ACTUAL'),
            new HistorySource($this->history, 'LEGACY_MACHINE'),
            new HistorySource($this->history, 'LEGACY_PROCESS'),
            new SameModelSource,
            new PeerRatioSource,
            new ModelSpecSource($settings),
            new ProcessDefaultSource,
        ];
    }

    public function settings(): SpeedSettings
    {
        return $this->settings;
    }

    /**
     * @param  list<MachineSpeedContext>  $contexts
     * @return array<int, array{machine: ?SpeedSuggestion, capabilities: array<int, SpeedSuggestion>}>
     */
    public function run(array $contexts): array
    {
        $byId = [];
        foreach ($contexts as $c) {
            $byId[$c->machineId] = $c;
        }

        $history = [];
        foreach ($byId as $id => $c) {
            $s = $this->sources[0]->suggest($c, new SpeedPeers) ?? $this->sources[1]->suggest($c, new SpeedPeers);
            if ($s) {
                $history[$id] = $s;
            }
        }
        $peers = new SpeedPeers($byId, $history);

        $out = [];
        foreach ($byId as $id => $c) {
            $machine = $this->suggestMachine($c, $peers);
            $out[$id] = ['machine' => $machine, 'capabilities' => $this->suggestCapabilities($c, $machine)];
        }

        return $out;
    }

    public function suggestMachine(MachineSpeedContext $m, SpeedPeers $peers): ?SpeedSuggestion
    {
        foreach ($this->sources as $source) {
            if ($s = $source->suggest($m, $peers)) {
                return $s;
            }
        }

        return null;
    }

    // Usulan per pasangan proses x mesin hanya dari riwayat PASANGAN itu (WO v2 / sistem lama dengan nama proses sama).
    // Pasangan tanpa angka sendiri yang hasilnya ≈ target mesin tidak diusulkan — ia sudah mewarisi target mesin lewat
    // MachineCapabilityService::resolveParams(). Pasangan yang SUDAH punya angka sendiri selalu dinilai ulang.
    /** @return array<int, SpeedSuggestion> */
    public function suggestCapabilities(MachineSpeedContext $m, ?SpeedSuggestion $machine): array
    {
        $reference = $machine?->speed ?? $m->currentTarget;
        $out = [];
        foreach ($m->capabilities as $cap) {
            $only = fn (array $samples) => array_values(array_filter($samples, fn (SpeedSample $s) => mb_strtoupper((string) $s->process) === mb_strtoupper($cap['process_name'])));
            $s = $this->history->suggest($only($m->v2Samples), $cap['uom'], 'V2_ACTUAL', ['high' => 'HIGH', 'low' => 'MEDIUM'], "v2 work orders on {$m->code}, process '{$cap['process_name']}'")
                ?? $this->history->suggest($only($m->legacySamples), $cap['uom'], 'LEGACY_MACHINE', ['high' => 'HIGH', 'low' => 'MEDIUM'], "old system as '{$m->legacyNames}', process '{$cap['process_name']}'");
            if (! $s) {
                continue;
            }
            if ($cap['current'] !== null || ! $reference || ! $this->isClose($s->speed, $reference)) {
                $out[$cap['capability_id']] = $s;
            }
        }

        return $out;
    }

    // PPIC hanya diganggu bila ada yang layak diputuskan: nilai kosong, atau selisih > SPEED_CHANGE_MIN_PCT dengan
    // keyakinan minimal Sedang (tebakan keyakinan Rendah tidak menggugat angka yang sudah diisi manusia).
    public function shouldPropose(?float $current, SpeedSuggestion $s): bool
    {
        if (! $current) {
            return true;
        }

        return $s->confidence !== 'LOW' && ! $this->isClose($s->speed, $current);
    }

    private function isClose(float $a, float $b): bool
    {
        return $b > 0 && abs($a - $b) / $b * 100 <= $this->settings->num('SPEED_CHANGE_MIN_PCT');
    }
}

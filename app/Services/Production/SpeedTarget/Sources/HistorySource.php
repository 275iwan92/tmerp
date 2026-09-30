<?php

namespace App\Services\Production\SpeedTarget\Sources;

use App\Services\Production\SpeedTarget\MachineSpeedContext;
use App\Services\Production\SpeedTarget\SpeedHistory;
use App\Services\Production\SpeedTarget\SpeedPeers;
use App\Services\Production\SpeedTarget\SpeedSuggestion;

// Sumber (1)-(3): angka dari catatan produksi nyata. Bedanya hanya kumpulan catatan & tingkat keyakinan.
//   V2_ACTUAL       realisasi operasi WO v2 di mesin ini (paling baru, menang)
//   LEGACY_MACHINE  tm2old.prd_output lewat prod_machine.legacy_machine_names
//   LEGACY_PROCESS  tm2old proses utama mesin, semua kru (mesin tak tercatat) — repository hanya mengisi proses yang
//                   dikerjakan SATU jenis mesin (PLONG dipakai pond & plotter: angkanya tak boleh dipinjam lintas jenis)
final class HistorySource implements SpeedSource
{
    public function __construct(private readonly SpeedHistory $history, private readonly string $source) {}

    public function suggest(MachineSpeedContext $m, SpeedPeers $peers): ?SpeedSuggestion
    {
        if ($this->source === 'LEGACY_PROCESS') {
            // Proses mesin ditelusuri urut preferensi; yang pertama punya riwayat cukup dipakai.
            foreach ($m->capabilities as $cap) {
                $samples = $m->legacyProcessSamples[mb_strtoupper($cap['process_name'])] ?? [];
                $s = $this->history->suggest($samples, $cap['uom'], 'LEGACY_PROCESS', ['high' => 'MEDIUM', 'low' => 'LOW'],
                    "old system process '{$cap['process_name']}', all crews (machine not recorded)");
                if ($s) {
                    return $s;
                }
            }

            return null;
        }

        [$samples, $label] = $this->source === 'V2_ACTUAL'
            ? [$m->v2Samples, "v2 work orders on {$m->code}"]
            : [$m->legacySamples, "old system as '{$m->legacyNames}'"];

        return $this->history->suggest($samples, $m->primaryUom(), $this->source, ['high' => 'HIGH', 'low' => 'MEDIUM'], $label);
    }
}

<?php

namespace App\Services\Production\SpeedTarget;

// Semua yang engine perlu tahu tentang satu mesin, sudah dimuat dari DB (SpeedDataRepository) — sumber-sumber
// usulan murni membaca objek ini sehingga bisa diuji tanpa database.
final class MachineSpeedContext
{
    /**
     * @param  list<array{capability_id: int, process_name: string, uom: ?string, current: ?float, rank: int}>  $capabilities  urut preferensi
     * @param  list<SpeedSample>  $v2Samples  realisasi WO v2 mesin ini
     * @param  list<SpeedSample>  $legacySamples  tm2old.prd_output dengan nama mesin lama milik mesin ini
     * @param  array<string, list<SpeedSample>>  $legacyProcessSamples  riwayat lama per nama proses (semua kru), hanya proses yang boleh dipakai
     */
    public function __construct(
        public readonly int $machineId,
        public readonly string $code,
        public readonly string $type,
        public readonly ?string $drive,
        public readonly ?float $maxSpeed,
        public readonly ?int $modelId,
        public readonly ?string $modelCode,
        public readonly ?float $currentTarget,
        public readonly array $capabilities = [],
        public readonly array $v2Samples = [],
        public readonly array $legacySamples = [],
        public readonly array $legacyProcessSamples = [],
        public readonly ?float $processDefaultSpeed = null,
        public readonly ?string $legacyNames = null,
    ) {}

    public function primaryProcess(): ?array
    {
        return $this->capabilities[0] ?? null;
    }

    public function primaryUom(): ?string
    {
        return $this->primaryProcess()['uom'] ?? null;
    }
}

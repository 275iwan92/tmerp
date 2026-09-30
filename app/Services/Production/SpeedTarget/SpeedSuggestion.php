<?php

namespace App\Services\Production\SpeedTarget;

// Satu usulan target kecepatan (qty per jam) beserta asal-usulnya. Nilai tidak pernah ditulis langsung ke master;
// SpeedSuggestionStore menyimpannya sebagai PENDING sampai PPIC menerima/mengubah.
final class SpeedSuggestion
{
    // Urutan = prioritas sumber (yang pertama menghasilkan angka menang).
    public const SOURCES = [
        'V2_ACTUAL' => 'Realisasi WO v2',
        'LEGACY_MACHINE' => 'Riwayat mesin (sistem lama)',
        'LEGACY_PROCESS' => 'Riwayat proses (sistem lama)',
        'SAME_MODEL' => 'Mesin model sama',
        'PEER_RATIO' => 'Rasio mesin sejenis',
        'MODEL_SPEC' => 'Spek model x efisiensi',
        'PROCESS_DEFAULT' => 'Default proses',
    ];

    public const CONFIDENCES = ['HIGH' => 'Tinggi', 'MEDIUM' => 'Sedang', 'LOW' => 'Rendah'];

    public function __construct(
        public readonly float $speed,
        public readonly ?string $uom,
        public readonly string $source,
        public readonly string $confidence,
        public readonly int $sampleSize,
        public readonly string $basis,
    ) {}

    // Angka dari produksi nyata mesin itu sendiri — hanya ini yang boleh jadi acuan mesin lain (sumber peer).
    public function isMachineHistory(): bool
    {
        return in_array($this->source, ['V2_ACTUAL', 'LEGACY_MACHINE'], true);
    }

    public function downgraded(string $reason): self
    {
        $next = ['HIGH' => 'MEDIUM', 'MEDIUM' => 'LOW', 'LOW' => 'LOW'][$this->confidence];

        return new self($this->speed, $this->uom, $this->source, $next, $this->sampleSize, $this->basis.'; '.$reason);
    }
}

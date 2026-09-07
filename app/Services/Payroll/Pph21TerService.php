<?php

namespace App\Services\Payroll;

use App\Models\Pyr\PyrPph21TerBracket;
use Illuminate\Support\Collection;

// PPh 21 Tarif Efektif Rata-rata (PP No. 58/2023 & PMK No. 168/2023) --
// bracket rate diambil dari tabel pyr_pph21_ter_bracket (bukan hardcode,
// diadopsi dari docs/payroll_timeattendance/tm2hrm_laravel_php83.zip yang
// aslinya hardcode match()). [DELTA-CONFIRM]: TER dirancang utk penghasilan
// BULANAN -- kalau dipakai utk pegawai payment_frequency=WEEKLY, bracket
// dicocokkan langsung ke bruto per-minggu TANPA konversi ke setara bulanan,
// yang secara sistematis MERINGANKAN pajak dibanding metode resmi (bruto
// mingguan jelas lebih kecil dari bulanan, kena bracket lebih rendah). Belum
// dikonfirmasi metodologi resminya ke akuntan/konsultan pajak -- pph21_active
// sebaiknya HANYA dinyalakan utk pegawai MONTHLY sampai ini diklarifikasi.
readonly class Pph21TerService
{
    public function getTerCategory(string $ptkpStatus): string
    {
        return match (strtoupper(trim($ptkpStatus))) {
            'TK/0', 'TK/1', 'K/0' => 'A',
            'TK/2', 'TK/3', 'K/1', 'K/2' => 'B',
            'K/3' => 'C',
            default => 'A',
        };
    }

    /**
     * @return array{category: string, rate: float, rate_percentage: float, tax_amount: float}
     */
    public function calculateTax(float $grossSalary, string $ptkpStatus, \DateTimeInterface $date, ?Collection $brackets = null): array
    {
        $category = $this->getTerCategory($ptkpStatus);
        $brackets ??= PyrPph21TerBracket::all();

        $rate = $this->findRate($brackets, $category, $grossSalary, $date);
        $taxAmount = round($grossSalary * $rate, 2);

        return [
            'category' => $category,
            'rate' => $rate,
            'rate_percentage' => round($rate * 100, 4),
            'tax_amount' => $taxAmount,
        ];
    }

    private function findRate(Collection $brackets, string $category, float $grossSalary, \DateTimeInterface $date): float
    {
        $applicable = $brackets->filter(fn (PyrPph21TerBracket $b) => $b->category === $category
            && $b->effective_from <= $date
            && (! $b->effective_to || $b->effective_to >= $date))
            ->sortBy(fn (PyrPph21TerBracket $b) => $b->bruto_upper_bound ?? PHP_FLOAT_MAX);

        foreach ($applicable as $bracket) {
            if ($bracket->bruto_upper_bound === null || $grossSalary <= (float) $bracket->bruto_upper_bound) {
                return (float) $bracket->rate;
            }
        }

        return 0.0;
    }
}

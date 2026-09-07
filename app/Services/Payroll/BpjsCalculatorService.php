<?php

namespace App\Services\Payroll;

use App\Models\Pyr\PyrBpjsRate;
use Illuminate\Support\Collection;

// BPJS Ketenagakerjaan (JHT/JP/JKK/JKM) & BPJS Kesehatan -- tarif & plafon
// dari tabel pyr_bpjs_rate (bukan hardcode, diadopsi dari
// docs/payroll_timeattendance/tm2hrm_laravel_php83.zip yang aslinya
// hardcode const/match()).
readonly class BpjsCalculatorService
{
    /**
     * @return array{employee: array{jht: float, jp: float, kes: float, total: float}, company: array{jht: float, jp: float, jkk: float, jkm: float, kes: float, total: float}, grand_total: float}
     */
    public function calculate(float $baseSalary, \DateTimeInterface $date, ?Collection $rates = null): array
    {
        $rates ??= PyrBpjsRate::all();

        $jhtEmployee = $this->componentAmount($rates, 'JHT_EMPLOYEE', $baseSalary, $date);
        $jhtCompany = $this->componentAmount($rates, 'JHT_COMPANY', $baseSalary, $date);
        $jpEmployee = $this->componentAmount($rates, 'JP_EMPLOYEE', $baseSalary, $date);
        $jpCompany = $this->componentAmount($rates, 'JP_COMPANY', $baseSalary, $date);
        $jkkCompany = $this->componentAmount($rates, 'JKK_COMPANY', $baseSalary, $date);
        $jkmCompany = $this->componentAmount($rates, 'JKM_COMPANY', $baseSalary, $date);
        $kesEmployee = $this->componentAmount($rates, 'KES_EMPLOYEE', $baseSalary, $date);
        $kesCompany = $this->componentAmount($rates, 'KES_COMPANY', $baseSalary, $date);

        $employeeTotal = $jhtEmployee + $jpEmployee + $kesEmployee;
        $companyTotal = $jhtCompany + $jpCompany + $jkkCompany + $jkmCompany + $kesCompany;

        return [
            'employee' => ['jht' => $jhtEmployee, 'jp' => $jpEmployee, 'kes' => $kesEmployee, 'total' => $employeeTotal],
            'company' => ['jht' => $jhtCompany, 'jp' => $jpCompany, 'jkk' => $jkkCompany, 'jkm' => $jkmCompany, 'kes' => $kesCompany, 'total' => $companyTotal],
            'grand_total' => $employeeTotal + $companyTotal,
        ];
    }

    private function componentAmount(Collection $rates, string $component, float $baseSalary, \DateTimeInterface $date): float
    {
        $rate = $rates->first(fn (PyrBpjsRate $r) => $r->component === $component
            && $r->effective_from <= $date
            && (! $r->effective_to || $r->effective_to >= $date));

        if (! $rate) {
            return 0.0;
        }

        $base = $rate->cap_amount !== null ? min($baseSalary, (float) $rate->cap_amount) : $baseSalary;

        return round($base * (float) $rate->rate, 2);
    }
}

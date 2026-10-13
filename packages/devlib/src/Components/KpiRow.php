<?php

/*
 * REMARK
 * Tujuan       : Baris pembungkus kartu KPI (x-dl::kpi) di atas laporan / dashboard: satu objek, jarak dan kolom diatur satu tempat.
 * Sumber hulu  : Daftar KPI dari kelas data layar (Desk::dashboardKpis, Ppic::*, ...).
 * Input        : slot berisi x-dl::kpi (atau kartu angka lain).
 * Output       : <div class="dl-grid__stats"> ... </div> (gaya grid dari devlib.css, sama dengan kartu angka di x-dl::grid).
 * Dipakai oleh : layar acc-*, mgmt-executive, maintenance-dashboard, production-live, production-scheduling, setup-data-origin.
 * Status       : Aktif.
 */

namespace Devlib\Components;

class KpiRow extends DevlibComponent
{
    public function tag(): string
    {
        return 'x-dl::kpi-row';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-grid__stats') }} {!! $dl() !!}>{{ $slot }}</div>
            BLADE;
    }
}

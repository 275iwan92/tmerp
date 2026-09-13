<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// 1 komponen dipakai utk 4 menu (QMS/CMMS/EHS/Aset & Modal) yang ADA di
// sidebar mockup (printflow_erp_complete_dashboard_mockup.html) tapi
// modul sumber datanya memang belum dibangun di ERP ini (keputusan
// 2026-09-13: struktur menu ditiru persis, TAPI angka tidak dikarang --
// lebih baik jujur "belum tersedia" daripada menampilkan KPI palsu di
// ERP perusahaan sungguhan). $section diisi lewat query string dari
// masing-masing route (lihat routes/web.php).
new #[Layout('layouts::report')] #[Title('Laporan — TELASIH MAKMUR ERP')] class extends Component
{
    public string $slug = '';

    public string $section = '';

    public string $reason = '';

    protected const MAP = [
        'qms' => [
            'section' => 'Quality Management System',
            'reason' => 'Modul QMS (Quality Management System) -- CAPA, audit finding, defect pareto -- belum dibangun di ERP ini. Data QC yang ADA saat ini baru sebatas QC Gate di Production Scheduling.',
        ],
        'cmms' => [
            'section' => 'CMMS Maintenance',
            'reason' => 'Modul CMMS (Computerized Maintenance Management System) -- preventive maintenance, MTBF/MTTR, spare-parts -- belum dibangun di ERP ini.',
        ],
        'ehs' => [
            'section' => 'Environment, Health & Safety',
            'reason' => 'Modul EHS (keselamatan kerja, insiden, izin lingkungan) belum dibangun di ERP ini.',
        ],
        'assets' => [
            'section' => 'Assets & Capital',
            'reason' => 'Modul Assets & Capital (aset tetap, depresiasi, capex) belum dibangun di ERP ini. Data aset yang ADA saat ini baru sebatas COA Fixed Asset di Akuntansi.',
        ],
    ];

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->section = self::MAP[$slug]['section'] ?? 'Belum Tersedia';
        $this->reason = self::MAP[$slug]['reason'] ?? 'Modul ini belum dibangun di ERP.';
    }
};
?>

<div class="p-4 sm:p-6" style="background:var(--content-bg)">
    <div class="mb-5">
        <h1 class="text-[22px] font-extrabold -tracking-wide" style="color:var(--sh-ink-strong)">{{ $section }}</h1>
    </div>

    <div class="rounded-2xl border p-8 text-center max-w-[560px] mx-auto" style="background:var(--sh-surface);border-color:var(--sh-border)">
        <div class="w-14 h-14 rounded-2xl mx-auto mb-4 grid place-items-center text-[22px]" style="background:color-mix(in srgb, var(--sh-warn) 16%, transparent); color:var(--sh-warn)">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
        <h2 class="font-bold text-[15px] mb-2" style="color:var(--sh-ink-strong)">Modul ini belum tersedia di ERP</h2>
        <p class="text-[12.5px] leading-relaxed" style="color:var(--sh-muted)">{{ $reason }}</p>
        <p class="text-[11.5px] mt-4" style="color:var(--sh-muted)">Menu ini sengaja tetap ditampilkan supaya struktur navigasi mengikuti rancangan yang diminta — begitu modul sumber datanya dibangun, laporan ini akan diisi data sungguhan, bukan angka contoh.</p>
    </div>
</div>

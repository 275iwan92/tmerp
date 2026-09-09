{{--
    Component library (docs/mockups/UIUX_components_part1.html, item #7
    "Masa Kerja") -- 2x x-form.date-picker (Awal/Akhir) + kalkulator durasi
    otomatis ("Durasi: X Tahun Y Bulan"). SENGAJA reuse x-form.date-picker
    (BUKAN native <input type=date> spt mockup asli) -- date-picker.blade.php
    sendiri sudah menegaskan native date input tidak dipakai lagi sejak
    keputusan user 2026-09-02.
--}}
@props(['label' => null, 'startName' => null, 'endName' => null, 'startLabel' => 'Mulai', 'endLabel' => 'Selesai'])
<div class="flex flex-col gap-1.5" x-data="tmDateRange()" x-init="compute()" @change.capture="compute()">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <div class="grid two gap-2">
        <x-form.date-picker :name="$startName" mode="date" :label="$startLabel" />
        <x-form.date-picker :name="$endName" mode="date" :label="$endLabel" />
    </div>
    <span class="text-[10.5px] font-semibold text-accent bg-[#eff6ff] border border-[#bfdbfe] rounded px-1.5 py-0.5 w-fit" x-text="durationLabel"></span>
</div>

{{--
    Accordion ringkas utk layar padat informasi (ProCost dsb). Pakai details/summary
    native + wire:ignore.self: status buka/tutup milik browser TIDAK di-reset saat
    Livewire morph, tapi isi di dalamnya tetap ikut ter-update (hindari bug
    x-show/x-if + state lokal -- memory feedback_alpine_xif_morph_stale_content_bug).
    Props: title, count (badge opsional), open (default tertutup), tone (slate|blue|green|amber).
--}}
@props(['title', 'count' => null, 'open' => false, 'tone' => 'slate'])
@php
    $tones = [
        'slate' => 'bg-[#f4f6fa] text-[#2c3e69]',
        'blue' => 'bg-[#eaf2ff] text-[#1d4ed8]',
        'green' => 'bg-[#e9f9ef] text-[#15803d]',
        'amber' => 'bg-[#fef6e7] text-[#b45309]',
    ];
@endphp
<details wire:ignore.self @if ($open) open @endif {{ $attributes->merge(['class' => 'group border border-[#d7dbe2] rounded-md bg-white overflow-hidden']) }}>
    <summary class="flex items-center gap-2 px-3 py-1.5 cursor-pointer select-none list-none {{ $tones[$tone] ?? $tones['slate'] }}">
        <span class="text-[10px] transition-transform group-open:rotate-90">▶</span>
        <span class="text-[11px] font-bold uppercase tracking-wide">{{ $title }}</span>
        @if ($count !== null)
            <span class="text-[10px] font-bold px-1.5 rounded-full bg-white/80">{{ $count }}</span>
        @endif
        {{ $summary ?? '' }}
    </summary>
    <div class="p-2">{{ $slot }}</div>
</details>

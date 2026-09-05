@props(['label'])
<div>
    <p class="text-[10px] text-muted uppercase font-semibold mb-0.5">{{ $label }}</p>
    <p class="text-[13px] text-[#1f2937] whitespace-pre-line">{{ trim((string) $slot) !== '' ? $slot : '—' }}</p>
</div>

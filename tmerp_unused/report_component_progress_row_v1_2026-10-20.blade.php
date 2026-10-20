@props(['label', 'value', 'pct', 'color' => 'var(--sh-accent)', 'note' => null])
<div class="mb-3.5 last:mb-0">
    <div class="flex items-center justify-between text-[12px] mb-1.5" style="color:var(--sh-ink)">
        <span>{{ $label }}</span>
        <b style="color:var(--sh-ink-strong)">{{ $value }} @if($note)<small class="font-normal" style="color:var(--sh-muted)">{{ $note }}</small>@endif</b>
    </div>
    <div class="h-[7px] rounded-full overflow-hidden" style="background:var(--sh-border)">
        <div class="h-full rounded-full" style="width:{{ min(100, max(0, (float) $pct)) }}%;background:{{ $color }}"></div>
    </div>
</div>

{{-- Kotak catatan bergaris kiri berwarna -- utk temuan/anomali NYATA, bukan hiasan. --}}
@props(['tone' => 'warn']) {{-- warn|danger|info --}}
@php
    $c = match ($tone) {
        'danger' => ['border' => 'var(--sh-danger)', 'bg' => 'color-mix(in srgb, var(--sh-danger) 8%, var(--sh-surface))'],
        'info' => ['border' => 'var(--sh-accent)', 'bg' => 'color-mix(in srgb, var(--sh-accent) 6%, var(--sh-surface))'],
        default => ['border' => 'var(--sh-warn)', 'bg' => 'color-mix(in srgb, var(--sh-warn) 8%, var(--sh-surface))'],
    };
@endphp
<div class="rounded-lg p-3 text-[12px]" style="border-left:3px solid {{ $c['border'] }};background:{{ $c['bg'] }};color:var(--sh-ink)">
    {{ $slot }}
</div>

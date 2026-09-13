@props(['tone' => 'info']) {{-- ok|warn|danger|info --}}
@php
    $c = match ($tone) {
        'ok' => ['bg' => 'color-mix(in srgb, var(--sh-ok) 16%, transparent)', 'fg' => 'var(--sh-ok)'],
        'warn' => ['bg' => 'color-mix(in srgb, var(--sh-warn) 16%, transparent)', 'fg' => 'var(--sh-warn)'],
        'danger' => ['bg' => 'color-mix(in srgb, var(--sh-danger) 16%, transparent)', 'fg' => 'var(--sh-danger)'],
        default => ['bg' => 'color-mix(in srgb, var(--sh-accent) 14%, transparent)', 'fg' => 'var(--sh-accent)'],
    };
@endphp
<span {{ $attributes->merge(['class' => 'inline-block px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap']) }}
      style="background:{{ $c['bg'] }};color:{{ $c['fg'] }}">{{ $slot }}</span>

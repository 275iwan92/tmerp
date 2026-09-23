<div {{ $attributes->merge(['class' => $classes()]) }}>
    @if ($title || isset($actions))
        <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
            @if ($title)<p class="text-[10px] font-bold text-accent uppercase">{{ $title }}</p>@endif
            @isset($actions)<div class="flex items-center gap-1">{{ $actions }}</div>@endisset
        </div>
    @endif
    @if ($description)
        <p class="text-[11px] text-muted mb-2.5">{{ $description }}</p>
    @endif
    @isset($intro)
        <p class="text-[11px] text-muted mb-2.5">{{ $intro }}</p>
    @endisset
    {{ $slot }}
</div>

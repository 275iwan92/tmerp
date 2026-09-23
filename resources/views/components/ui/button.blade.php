@php($content = $slot->isNotEmpty() ? $slot : $label)
@php($isFa = $icon && str_starts_with($icon, 'fa-'))
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes()]) }}>
        @if ($isFa)<i class="fa-solid {{ $icon }}"></i>@elseif ($icon){{ $icon }}@endif{{ $content }}@if ($iconRight)<i class="fa-solid {{ $iconRight }}"></i>@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes()]) }}>
        @if ($isFa)<i class="fa-solid {{ $icon }}"></i>@elseif ($icon){{ $icon }}@endif{{ $content }}@if ($iconRight)<i class="fa-solid {{ $iconRight }}"></i>@endif
    </button>
@endif

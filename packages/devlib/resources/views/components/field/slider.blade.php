<div class="dl-slider dl-slider--{{ $orientation }} {{ $range ? 'is-range' : '' }}" data-dl-slider data-format="{{ $format }}" data-kind="{{ $kind() }}" data-start="{{ $start() }}" style="--dl-slider-h:{{ $height }}">
    <input type="hidden" name="{{ $name }}" data-dl-slider-a>
    @if ($range)<input type="hidden" name="{{ $nameTo ?: $name.'_to' }}" data-dl-slider-b>@endif
    <div class="dl-slider__track">
        <div class="dl-slider__rail"><div class="dl-slider__fill"></div></div>
        <input id="{{ $uid }}" type="range" class="dl-slider__input" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $fromValue() }}" aria-label="{{ $label }}{{ $range ? ' '.\Devlib\Lang::text('(from)') : '' }}" @disabled($disabled || $readonly)>
        @if ($range)<input type="range" class="dl-slider__input" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $toValue() }}" aria-label="{{ $label }} {{ \Devlib\Lang::text('(to)') }}" @disabled($disabled || $readonly)>@endif
    </div>
    @if ($tickList())
        <div class="dl-slider__ticks">@foreach ($tickList() as [$pos, $lbl])<span style="--p:{{ $pos }}">{{ $lbl }}</span>@endforeach</div>
    @endif
    <span class="dl-slider__value" data-dl-slider-value></span>
</div>

<div class="dl-slider" data-dl-slider data-format="{{ $format }}">
    <input id="{{ $uid }}" type="range" name="{{ $name }}" class="dl-slider__input" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $value ?? $min }}" @disabled($disabled)>
    <span class="dl-slider__value" data-dl-slider-value></span>
</div>

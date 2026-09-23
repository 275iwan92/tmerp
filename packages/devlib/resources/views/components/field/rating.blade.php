<div class="dl-rating dl-rating--{{ $size }} {{ $readonly || $disabled ? 'is-readonly' : '' }}" data-dl-rating data-max="{{ $max }}" data-half="{{ $half ? 1 : 0 }}" role="slider" aria-valuemin="0" aria-valuemax="{{ $max }}" aria-valuenow="{{ (float) $value }}">
    <input type="hidden" name="{{ $name }}" value="{{ (float) $value }}" data-dl-value>
    @for ($i = 1; $i <= $max; $i++)
        <span class="dl-rating__star" data-value="{{ $i }}">★</span>
    @endfor
    <span class="dl-rating__text" data-dl-rating-text></span>
</div>

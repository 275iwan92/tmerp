<div class="dl-rating" data-dl-rating data-max="{{ $max }}">
    <input type="hidden" name="{{ $name }}" value="{{ (int) $value }}" data-dl-value>
    @for ($i = 1; $i <= $max; $i++)
        <span class="dl-rating__star {{ $i <= (int) $value ? 'is-active' : '' }}" data-value="{{ $i }}">★</span>
    @endfor
    <span class="dl-rating__text" data-dl-rating-text>{{ (int) $value }} / {{ $max }}</span>
</div>

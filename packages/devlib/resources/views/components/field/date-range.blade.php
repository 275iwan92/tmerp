<div class="dl-daterange" data-dl-daterange>
    <input id="{{ $uid }}" type="date" name="{{ $name }}" class="dl-input dl-input--boxed" value="{{ $value }}" @disabled($disabled) @readonly($readonly)>
    <input type="date" name="{{ $nameEnd ?? $name.'_end' }}" class="dl-input dl-input--boxed" value="{{ $valueEnd }}" @disabled($disabled) @readonly($readonly)>
</div>
@if ($duration)
    <span class="dl-field__hint" data-dl-duration></span>
@endif

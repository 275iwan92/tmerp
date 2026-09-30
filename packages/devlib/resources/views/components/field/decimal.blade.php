<div class="dl-field__box">
    <input id="{{ $uid }}" type="text" inputmode="decimal" name="{{ $name }}" class="dl-input {{ $unit ? 'has-unit' : '' }}" value="{{ \Devlib\Locale::input($value) === null ? $value : \Devlib\Locale::number($value, $decimals) }}" placeholder="{{ $placeholder ?? \Devlib\Locale::number(0, $decimals) }}" data-dl-decimal="{{ $decimals }}" data-dl-num @disabled($disabled)>
    @if ($unit)<span class="dl-field__unit">{{ $unit }}</span>@endif
</div>

<div class="dl-field__box">
    <input id="{{ $uid }}" type="text" inputmode="decimal" name="{{ $name }}" class="dl-input {{ $unit ? 'has-unit' : '' }}" value="{{ $value }}" placeholder="{{ $placeholder ?? number_format(0, $decimals) }}" data-dl-decimal="{{ $decimals }}" @disabled($disabled)>
    @if ($unit)<span class="dl-field__unit">{{ $unit }}</span>@endif
</div>

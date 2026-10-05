<div class="dl-field__box" data-dl-number data-min="{{ $min }}" data-max="{{ $max }}" data-step="{{ $step }}">
    <input id="{{ $uid }}" type="text" inputmode="numeric" name="{{ $name }}" class="dl-input {{ $readonly ? '' : 'has-spin' }}" value="{{ $value ?? $min }}" @disabled($disabled) @readonly($readonly)>
    @unless ($readonly)<div class="dl-spin"><button type="button" data-dl-spin="1">▲</button><button type="button" data-dl-spin="-1">▼</button></div>@endunless
</div>

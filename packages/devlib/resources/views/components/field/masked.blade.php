<div class="dl-field__box">
    <input id="{{ $uid }}" type="text" inputmode="numeric" name="{{ $name }}" class="dl-input" value="{{ $value }}" placeholder="{{ $placeholder }}"
        data-dl-mask="{{ $mask }}" data-invalid="{{ $invalidText ?? 'Harus '.substr_count($mask, '9').' digit' }}" maxlength="{{ strlen($mask) }}" @disabled($disabled)>
</div>

<div class="dl-field__box">
    <input id="{{ $uid }}" type="text" inputmode="numeric" name="{{ $name }}" class="dl-input" value="{{ $value }}" placeholder="{{ $placeholder }}"
        data-dl-mask="{{ $mask }}" data-invalid="{{ $invalidText ?? strtr(\Devlib\Lang::text('Must be :n digits'), [':n' => substr_count($mask, '9')]) }}" maxlength="{{ strlen($mask) }}" @disabled($disabled) @readonly($readonly)>
</div>

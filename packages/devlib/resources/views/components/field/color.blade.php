<div class="dl-color">
    <input id="{{ $uid }}" name="{{ $name }}" type="color" class="dl-color__btn" value="{{ $value ?? '#2563eb' }}" data-dl-color @disabled($disabled)>
    <span class="dl-color__code" data-dl-color-code>{{ strtoupper($value ?? '#2563eb') }}</span>
</div>

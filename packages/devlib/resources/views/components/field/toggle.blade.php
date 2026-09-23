<div class="dl-switchrow" data-dl-toggle data-on="{{ $onLabel }}" data-off="{{ $offLabel }}">
    <label class="dl-switch"><input id="{{ $uid }}" type="checkbox" name="{{ $name }}" value="1" @checked($value) @disabled($disabled)><span class="dl-switch__slider"></span></label>
    <span class="dl-switchrow__label" data-dl-toggle-label>{{ $value ? $onLabel : $offLabel }}</span>
</div>

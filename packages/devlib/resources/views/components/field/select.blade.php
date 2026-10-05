@php
    $opts = array_is_list($options) ? array_combine($options, $options) : $options;
    $empty = $placeholder ?? \Devlib\Lang::t('f_choose');
@endphp
<div class="dl-dd" data-dl-select data-empty="{{ $empty }}">
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-dl-value>
    <button type="button" id="{{ $uid }}" class="dl-dd__btn" data-dl-dd-toggle @disabled($disabled || $readonly)>
        <span data-dl-dd-text>{{ $opts[$value] ?? $empty }}</span><span>▼</span>
    </button>
    <div class="dl-dd__panel" data-dl-dd-panel>
        <div class="dl-dd__search"><input type="text" placeholder="{{ \Devlib\Lang::text($searchPlaceholder) }}" data-dl-dd-search></div>
        <div class="dl-dd__list" data-dl-dd-list>
            @foreach ($opts as $v => $l)
                <div class="dl-dd__item" data-value="{{ $v }}">{{ $l }}</div>
            @endforeach
        </div>
    </div>
</div>

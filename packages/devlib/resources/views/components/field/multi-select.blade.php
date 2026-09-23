@php
    $opts = array_is_list($options) ? array_combine($options, $options) : $options;
    $vals = array_map('strval', (array) $value);
@endphp
<div class="dl-dd" data-dl-multi data-empty="{{ $emptyText }}" data-noun="{{ $noun }}">
    <button type="button" id="{{ $uid }}" class="dl-dd__btn" data-dl-dd-toggle @disabled($disabled)><span data-dl-dd-text>{{ $emptyText }}</span><span>▼</span></button>
    <div class="dl-dd__panel" data-dl-dd-panel>
        <div class="dl-dd__search"><input type="text" placeholder="Cari..." data-dl-dd-search></div>
        <div class="dl-dd__list">
            @foreach ($opts as $v => $l)
                <label class="dl-dd__item dl-dd__item--split" data-search-item><span>{{ $l }}</span><input type="checkbox" name="{{ $name }}[]" value="{{ $v }}" data-label="{{ $v }}" @checked(in_array((string) $v, $vals, true))></label>
            @endforeach
        </div>
    </div>
</div>

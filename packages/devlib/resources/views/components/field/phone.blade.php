@php
    $list = $countries ?: [
        ['name' => 'Indonesia', 'dial' => '+62', 'flag' => '🇮🇩'],
        ['name' => 'United States', 'dial' => '+1', 'flag' => '🇺🇸'],
        ['name' => 'Singapore', 'dial' => '+65', 'flag' => '🇸🇬'],
        ['name' => 'Japan', 'dial' => '+81', 'flag' => '🇯🇵'],
    ];
    $cur = collect($list)->firstWhere('dial', $dial) ?? $list[0];
@endphp
<div class="dl-prefix" data-dl-prefix>
    <input type="hidden" name="{{ $name }}_dial" value="{{ $cur['dial'] }}" data-dl-prefix-value>
    <button type="button" class="dl-prefix__btn" data-dl-dd-toggle><span data-dl-prefix-a>{{ $cur['flag'] }}</span><span data-dl-prefix-b>{{ $cur['dial'] }}</span></button>
    <input id="{{ $uid }}" type="text" inputmode="tel" name="{{ $name }}" class="dl-input dl-input--boxed dl-input--prefixed" value="{{ $value }}" placeholder="{{ $placeholder ?? '812-3456-7890' }}" maxlength="15" data-dl-phone @disabled($disabled)>
    <div class="dl-dd__panel" data-dl-dd-panel>
        <div class="dl-dd__search"><input type="text" placeholder="Cari..." data-dl-dd-search></div>
        <div class="dl-dd__list" data-dl-dd-list>
            @foreach ($list as $c)
                <div class="dl-dd__item dl-dd__item--split" data-value="{{ $c['dial'] }}" data-a="{{ $c['flag'] }}" data-b="{{ $c['dial'] }}"><span>{{ $c['flag'] }} {{ $c['name'] }}</span><strong>{{ $c['dial'] }}</strong></div>
            @endforeach
        </div>
    </div>
</div>

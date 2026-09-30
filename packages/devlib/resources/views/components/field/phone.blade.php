@php
    $list = \Devlib\PhoneFormat::list($countries);
    $code = isset($list[$country]) ? $country : array_key_first($list);
    $cur = $list[$code];
@endphp
<div class="dl-prefix" data-dl-prefix data-dl-phonebox data-kind="{{ $kind }}" data-countries="{{ json_encode($list) }}">
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-dl-phone-value>
    <input type="hidden" name="{{ $name }}_country" value="{{ $code }}" data-dl-prefix-value>
    <button type="button" class="dl-prefix__btn" data-dl-dd-toggle @disabled($disabled || $readonly)><span data-dl-prefix-a>{{ $cur['flag'] }}</span><span data-dl-prefix-b>{{ $cur['dial'] }}</span></button>
    <input id="{{ $uid }}" type="tel" inputmode="tel" autocomplete="{{ $kind === 'landline' ? 'tel' : 'tel-national' }}" class="dl-input dl-input--boxed dl-input--prefixed" placeholder="{{ $placeholder }}" data-dl-phone @disabled($disabled) @readonly($readonly)>
    <div class="dl-dd__panel" data-dl-dd-panel>
        <div class="dl-dd__search"><input type="text" placeholder="{{ \Devlib\Lang::text('Search...') }}" data-dl-dd-search></div>
        <div class="dl-dd__list" data-dl-dd-list>
            @foreach ($list as $c => $x)
                <div class="dl-dd__item dl-dd__item--split" data-value="{{ $c }}" data-a="{{ $x['flag'] }}" data-b="{{ $x['dial'] }}"><span>{{ $x['flag'] }} {{ $x['name'] }}</span><strong>{{ $x['dial'] }}</strong></div>
            @endforeach
        </div>
    </div>
</div>
<div class="dl-phone__read" data-dl-phone-read></div>

@php
    $list = $currencies ?: [
        ['code' => 'USD', 'symbol' => '$'],
        ['code' => 'IDR', 'symbol' => 'Rp'],
        ['code' => 'EUR', 'symbol' => '€'],
        ['code' => 'JPY', 'symbol' => '¥'],
    ];
    $cur = collect($list)->firstWhere('code', $currency) ?? $list[0];
@endphp
<div class="dl-prefix" data-dl-prefix data-dl-money>
    <input type="hidden" name="{{ $name }}_currency" value="{{ $cur['code'] }}" data-dl-prefix-value>
    <button type="button" class="dl-prefix__btn" data-dl-dd-toggle><span data-dl-prefix-a>{{ $cur['symbol'] }}</span><span data-dl-prefix-b>{{ $cur['code'] }}</span></button>
    <input id="{{ $uid }}" type="text" inputmode="numeric" name="{{ $name }}" class="dl-input dl-input--boxed dl-input--prefixed" value="{{ $value }}" placeholder="{{ $placeholder ?? '0' }}" data-dl-money-input @disabled($disabled)>
    <div class="dl-dd__panel" data-dl-dd-panel>
        <div class="dl-dd__search"><input type="text" placeholder="Cari..." data-dl-dd-search></div>
        <div class="dl-dd__list" data-dl-dd-list>
            @foreach ($list as $c)
                <div class="dl-dd__item dl-dd__item--split" data-value="{{ $c['code'] }}" data-a="{{ $c['symbol'] }}" data-b="{{ $c['code'] }}"><span>{{ $c['code'] }}</span><strong>{{ $c['symbol'] }}</strong></div>
            @endforeach
        </div>
    </div>
</div>
<div class="dl-field__hint" data-dl-money-hint>Terbaca: -</div>

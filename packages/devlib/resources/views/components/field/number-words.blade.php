@php($cs = $currencies())
<div class="dl-words" data-dl-words data-currencies="{{ json_encode($cs) }}" data-decimals="{{ $decimals }}">
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-dl-words-value>
    @if ($currency)
        <div class="dl-prefix" data-dl-prefix>
            <input type="hidden" name="{{ $name }}_currency" value="{{ $currency }}" data-dl-prefix-value>
            <button type="button" class="dl-prefix__btn" @if ($picker) data-dl-dd-toggle @else disabled @endif><span data-dl-prefix-a>{{ $cs[$currency][0] ?? '' }}</span><span data-dl-prefix-b>{{ $currency }}</span></button>
            <input id="{{ $uid }}" type="text" inputmode="decimal" class="dl-input dl-input--boxed dl-input--prefixed dl-words__input" placeholder="{{ $placeholder ?? '0' }}" data-dl-words-input @disabled($disabled) @readonly($readonly)>
            @if ($picker)
                <div class="dl-dd__panel" data-dl-dd-panel>
                    <div class="dl-dd__search"><input type="text" placeholder="Cari..." data-dl-dd-search></div>
                    <div class="dl-dd__list" data-dl-dd-list>
                        @foreach ($cs as $code => $c)
                            <div class="dl-dd__item dl-dd__item--split" data-value="{{ $code }}" data-a="{{ $c[0] }}" data-b="{{ $code }}"><span>{{ $code }} · {{ $c[1] }}</span><strong>{{ $c[0] }}</strong></div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @else
        <div class="dl-field__box"><input id="{{ $uid }}" type="text" inputmode="decimal" class="dl-input dl-words__input" placeholder="{{ $placeholder ?? '0' }}" data-dl-words-input @disabled($disabled) @readonly($readonly)></div>
    @endif
    <div class="dl-words__text" data-dl-words-text></div>
</div>

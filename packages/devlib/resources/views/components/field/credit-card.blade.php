<div class="dl-cc" data-dl-cc>
    @if ($preview)
        <div class="dl-cc__card" data-dl-cc-card>
            <span class="dl-cc__chip"></span><i class="fa-solid fa-credit-card dl-cc__logo" data-dl-cc-logo></i>
            <span class="dl-cc__num" data-dl-cc-shown>•••• •••• •••• ••••</span>
            <span class="dl-cc__row"><span><small>{{ \Devlib\Lang::text('Holder') }}</small><b data-dl-cc-holder-shown>{{ \Devlib\Lang::t('cc_holder') }}</b></span><span><small>{{ \Devlib\Lang::text('Valid thru') }}</small><b data-dl-cc-exp-shown>MM/YY</b></span></span>
        </div>
    @endif
    <input type="hidden" name="{{ $name }}_brand" data-dl-cc-brand>
    <span class="dl-cc__lbl">{{ \Devlib\Lang::text('Card number') }}</span>
    <div class="dl-field__box"><i class="fa-regular fa-credit-card dl-cc__icon" data-dl-cc-icon></i><input id="{{ $uid }}" type="text" inputmode="numeric" autocomplete="cc-number" name="{{ $name }}" class="dl-input" placeholder="1234 5678 9012 3456" maxlength="23" data-dl-cc-num @disabled($disabled) @readonly($readonly)><span class="dl-cc__brandname" data-dl-cc-brandname></span></div>
    <div class="dl-cc__grid">
        <label><span class="dl-cc__lbl">{{ \Devlib\Lang::text('Valid thru (MM/YY)') }}</span><span class="dl-field__box"><input type="text" inputmode="numeric" autocomplete="cc-exp" name="{{ $name }}_exp" class="dl-input" placeholder="MM/YY" maxlength="5" data-dl-cc-exp @disabled($disabled) @readonly($readonly)></span></label>
        <label><span class="dl-cc__lbl">CVV</span><span class="dl-field__box"><input type="password" inputmode="numeric" autocomplete="cc-csc" name="{{ $name }}_cvv" class="dl-input" placeholder="•••" maxlength="3" data-dl-cc-cvv @disabled($disabled) @readonly($readonly)></span></label>
    </div>
    @if ($holder)
        <label><span class="dl-cc__lbl">{{ \Devlib\Lang::text('Name on card') }}</span><span class="dl-field__box"><input type="text" autocomplete="cc-name" name="{{ $name }}_holder" class="dl-input" placeholder="{{ \Devlib\Lang::text('As printed') }}" data-dl-cc-holder @disabled($disabled) @readonly($readonly)><button type="button" class="dl-field__trigger dl-case" data-dl-cc-case title="{{ \Devlib\Lang::text('Change case: lower / Title Case / UPPER') }}">AA</button></span></label>
    @endif
</div>

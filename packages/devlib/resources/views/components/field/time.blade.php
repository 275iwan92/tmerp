<div class="dl-field__box dl-pick" data-dl-time>
    <input id="{{ $uid }}" type="text" name="{{ $name }}" class="dl-input" value="{{ $value }}" placeholder="{{ $placeholder ?? '14:30' }}" data-dl-time-text maxlength="5" @disabled($disabled)>
    <button type="button" class="dl-field__trigger" data-dl-dd-toggle title="{{ \Devlib\Lang::text('Pick a time') }}">⏰</button>
    <div class="dl-pick__panel dl-pick__panel--time" data-dl-dd-panel>
        <div class="dl-pick__head">{{ \Devlib\Lang::t('s_pick_time') }}</div>
        <div class="dl-pick__time">
            <label>{{ \Devlib\Lang::t('s_hour') }} <select data-dl-time-h>@for ($h = 0; $h < 24; $h++)<option>{{ str_pad((string) $h, 2, '0', STR_PAD_LEFT) }}</option>@endfor</select></label>
            <b>:</b>
            <label>{{ \Devlib\Lang::t('s_minute') }} <select data-dl-time-m>@for ($m = 0; $m < 60; $m += 5)<option>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>@endfor</select></label>
        </div>
        <div class="dl-pick__foot"><button type="button" class="dl-pick__sec" data-dl-time-clear>Clear</button><button type="button" class="dl-pick__pri" data-dl-time-apply>Apply</button></div>
    </div>
</div>

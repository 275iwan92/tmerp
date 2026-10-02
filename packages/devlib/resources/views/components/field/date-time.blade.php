<div class="dl-field__box dl-pick" data-dl-datetime data-tz-label="{{ \Devlib\Locale::tzLabel() }}">
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-dl-value>
    <input id="{{ $uid }}" type="text" class="dl-input" placeholder="{{ $placeholder ?? '—' }}" data-dl-date-text readonly @disabled($disabled)>
    <button type="button" class="dl-field__trigger" data-dl-dd-toggle title="{{ \Devlib\Lang::text('Pick a date & time') }}" @disabled($disabled || $readonly)>📅</button>
    <div class="dl-pick__panel" data-dl-dd-panel>
        <div class="dl-pick__head">{{ \Devlib\Lang::text('Pick a date & time') }}</div>
        <div class="dl-pick__nav"><button type="button" data-dl-cal-prev>&lt;</button><span data-dl-cal-title></span><button type="button" data-dl-cal-next>&gt;</button></div>
        <div class="dl-cal dl-cal--names">@for ($i = 0; $i < 7; $i++)<div>{{ \Carbon\CarbonImmutable::create(2000, 1, 2 + $i)->locale(\Devlib\Locale::lang())->isoFormat('dd') }}</div>@endfor</div>
        <div class="dl-cal" data-dl-cal-days></div>
        <div class="dl-pick__time">
            <label>{{ \Devlib\Lang::t('s_hour') }} <select data-dl-time-h>@for ($h = 0; $h < 24; $h++)<option>{{ str_pad((string) $h, 2, '0', STR_PAD_LEFT) }}</option>@endfor</select></label>
            <b>:</b>
            <label>{{ \Devlib\Lang::t('s_minute') }} <select data-dl-time-m>@for ($m = 0; $m < 60; $m += $minuteStep)<option>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>@endfor</select></label>
        </div>
        <div class="dl-pick__preview" data-dl-dt-preview>{{ \Devlib\Lang::t('fd_selected') }}: -</div>
        <div class="dl-pick__foot"><button type="button" class="dl-pick__sec" data-dl-cal-clear>Clear</button><button type="button" class="dl-pick__pri" data-dl-dt-apply>Apply</button></div>
    </div>
</div>

<div class="dl-field__box dl-pick" data-dl-datetime data-tz-label="{{ \Devlib\Locale::tzLabel() }}">
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-dl-value>
    <input id="{{ $uid }}" type="text" class="dl-input" placeholder="{{ $placeholder ?? '1 Okt 1969, 14:30' }}" data-dl-date-text readonly @disabled($disabled)>
    <button type="button" class="dl-field__trigger" data-dl-dd-toggle title="Pilih tanggal & jam" @disabled($disabled || $readonly)>📅</button>
    <div class="dl-pick__panel" data-dl-dd-panel>
        <div class="dl-pick__head">Select Date &amp; Time</div>
        <div class="dl-pick__nav"><button type="button" data-dl-cal-prev>&lt;</button><span data-dl-cal-title></span><button type="button" data-dl-cal-next>&gt;</button></div>
        <div class="dl-cal dl-cal--names"><div>Mg</div><div>Sn</div><div>Sl</div><div>Rb</div><div>Km</div><div>Jm</div><div>Sb</div></div>
        <div class="dl-cal" data-dl-cal-days></div>
        <div class="dl-pick__time">
            <label>Jam <select data-dl-time-h>@for ($h = 0; $h < 24; $h++)<option>{{ str_pad((string) $h, 2, '0', STR_PAD_LEFT) }}</option>@endfor</select></label>
            <b>:</b>
            <label>Menit <select data-dl-time-m>@for ($m = 0; $m < 60; $m += $minuteStep)<option>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>@endfor</select></label>
        </div>
        <div class="dl-pick__preview" data-dl-dt-preview>Selected: -</div>
        <div class="dl-pick__foot"><button type="button" class="dl-pick__sec" data-dl-cal-clear>Clear</button><button type="button" class="dl-pick__pri" data-dl-dt-apply>Apply</button></div>
    </div>
</div>

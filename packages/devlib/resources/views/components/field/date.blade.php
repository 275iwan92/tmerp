<div class="dl-field__box dl-pick" data-dl-date>
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-dl-value>
    <input id="{{ $uid }}" type="text" class="dl-input" placeholder="{{ $placeholder ?? '1 Okt 1969' }}" data-dl-date-text readonly @disabled($disabled)>
    <button type="button" class="dl-field__trigger" data-dl-dd-toggle title="Pilih tanggal">📅</button>
    <div class="dl-pick__panel" data-dl-dd-panel>
        <div class="dl-pick__head">Pilih Tanggal</div>
        <div class="dl-pick__nav"><button type="button" data-dl-cal-prev>&lt;</button><span data-dl-cal-title></span><button type="button" data-dl-cal-next>&gt;</button></div>
        <div class="dl-cal dl-cal--names"><div>Mg</div><div>Sn</div><div>Sl</div><div>Rb</div><div>Km</div><div>Jm</div><div>Sb</div></div>
        <div class="dl-cal" data-dl-cal-days></div>
        <div class="dl-pick__foot"><button type="button" class="dl-pick__sec" data-dl-cal-clear>Clear</button><button type="button" class="dl-pick__pri" data-dl-cal-today>Hari ini</button></div>
    </div>
</div>

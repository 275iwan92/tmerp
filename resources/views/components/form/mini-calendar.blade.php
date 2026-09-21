{{--
    Component library (docs/mockups/mockup_UIUX_calendar.html) -- versi
    RINGKAS: month-view grid + daftar event tanggal terpilih. SENGAJA TANPA
    drag&drop / switch Day-Week-Month view spt mockup asli (itu scope layar
    kalender dedicated tersendiri kalau ada kebutuhan bisnis nyata, mis.
    kalender cuti HRM -- di sini murni referensi pola visual+interaksi
    "klik tanggal -> lihat event"). :events = ['YYYY-MM-DD' => [['title'=>...,
    'colorClass'=>'green'|'amber'|'red']]].
--}}
@props(['events' => []])
<div class="border border-[#e2e8f0] rounded-xl overflow-hidden" x-data="tmMiniCalendar({ events: @js($events) })">
    <div class="flex items-center justify-between px-3 py-2 bg-field-soft border-b border-field-border">
        <button type="button" @click="prevMonth()" class="w-6 h-6 rounded border border-field-border hover:bg-white cursor-pointer text-xs">&lt;</button>
        <span class="text-xs font-bold text-navy" x-text="monthLabel"></span>
        <button type="button" @click="nextMonth()" class="w-6 h-6 rounded border border-field-border hover:bg-white cursor-pointer text-xs">&gt;</button>
    </div>
    <div class="grid grid-cols-7 text-center text-[10px] font-bold text-muted uppercase border-b border-[#eef1f5]">
        <div class="py-1">{{ \App\Support\Localization\Translator::t('shared_calendar_day_sun', 'Min') }}</div><div class="py-1">{{ \App\Support\Localization\Translator::t('shared_calendar_day_mon', 'Sen') }}</div><div class="py-1">{{ \App\Support\Localization\Translator::t('shared_calendar_day_tue', 'Sel') }}</div><div class="py-1">{{ \App\Support\Localization\Translator::t('shared_calendar_day_wed', 'Rab') }}</div><div class="py-1">{{ \App\Support\Localization\Translator::t('shared_calendar_day_thu', 'Kam') }}</div><div class="py-1">{{ \App\Support\Localization\Translator::t('shared_calendar_day_fri', 'Jum') }}</div><div class="py-1">{{ \App\Support\Localization\Translator::t('shared_calendar_day_sat', 'Sab') }}</div>
    </div>
    <div class="grid grid-cols-7">
        <template x-for="(cell, idx) in days" :key="idx">
            <button type="button" @click="cell && selectDay(cell.dateStr)" :disabled="!cell"
                :class="!cell ? 'invisible' : (cell.dateStr === selDateStr ? 'bg-accent-soft ring-1 ring-inset ring-accent' : 'hover:bg-[#f9fafb]')"
                class="h-14 border-b border-r border-[#f1f5f9] flex flex-col items-center py-1 cursor-pointer">
                <span x-text="cell?.day" :class="cell && isToday(cell.dateStr) ? 'bg-accent text-white rounded-full w-5 h-5 flex items-center justify-center' : 'text-[#374151]'" class="text-[11px] font-semibold"></span>
                <span class="flex gap-0.5 mt-1">
                    <template x-for="(ev, i) in (cell?.events ?? []).slice(0, 3)" :key="i">
                        <span class="w-1.5 h-1.5 rounded-full"
                            :class="{ green: 'bg-[#16a34a]', amber: 'bg-[#b45309]', red: 'bg-[#c0392b]' }[ev.colorClass] || 'bg-[#9ca3af]'"></span>
                    </template>
                </span>
            </button>
        </template>
    </div>
    <div class="p-3">
        <div class="text-[10px] font-bold text-muted uppercase mb-1.5" x-text="selDateStr + ' — ' + selectedEvents.length + ' event'"></div>
        <template x-if="selectedEvents.length === 0">
            <div class="text-[11px] text-muted text-center py-3">{{ \App\Support\Localization\Translator::t('shared_calendar_no_events', 'Tidak ada event tanggal ini.') }}</div>
        </template>
        <div class="flex flex-col gap-1.5">
            <template x-for="(ev, i) in selectedEvents" :key="i">
                <div class="flex items-center gap-2 text-xs text-[#374151] bg-[#f9fafb] border border-[#eef1f5] rounded px-2 py-1.5">
                    <span class="w-2 h-2 rounded-full shrink-0" :class="{ green: 'bg-[#16a34a]', amber: 'bg-[#b45309]', red: 'bg-[#c0392b]' }[ev.colorClass] || 'bg-[#9ca3af]'"></span>
                    <span x-text="ev.title"></span>
                </div>
            </template>
        </div>
    </div>
</div>

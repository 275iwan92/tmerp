/* ============================================================================
 * Date/time picker kustom (docs/FORM_UI_STANDARD.md §3, diadaptasi dari mockup
 * docs/mockups/UIUX_components_part1.html) -- Alpine.data() murni, tanpa
 * library eksternal. Menggantikan native <input type="date"|"datetime-local">
 * di semua form.
 *
 * Kontrak: komponen x-form.date-picker menaruh wire:model (atau `name` polos
 * utk form non-Livewire) di sebuah <input type="hidden" x-ref="model">, BUKAN
 * di elemen ber-x-data ini sendiri -- pola yang sama dgn action-dropdown.blade.php
 * (Alpine cuma UX, DB/Livewire tetap sumber kebenaran). Saat user pilih
 * tanggal/jam, kita set $refs.model.value lalu dispatch event 'input'+'change'
 * supaya wire:model (apa pun path property-nya, termasuk .live/dot-notation)
 * ikut sinkron -- trik standar utk custom input ber-Livewire, sama semangat
 * dgn kenapa <select> polos wire:model dihindari (lihat memory: "Livewire
 * <select> morph bug").
 * ============================================================================ */
document.addEventListener('alpine:init', () => {
    // Nama bulan/hari ikut bahasa tampilan (<html lang>, diisi SetUserLocale) lewat Intl -- id = Januari / Jan / Minggu
    // persis seperti sebelumnya. Header kalender bahasa id tetap 2 huruf (Mg, Sn, ...) seperti DevLib dow2().
    const LANG = document.documentElement.lang || 'id';
    const intl = (n, date, opt) => Array.from({ length: n }, (_, i) => date(i).toLocaleString(LANG, opt));
    const MONTHS_LONG = intl(12, (i) => new Date(2000, i, 1), { month: 'long' });
    const MONTHS_SHORT = intl(12, (i) => new Date(2000, i, 1), { month: 'short' });
    const DAY_LABELS = LANG.startsWith('id') ? ['Mg', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb'] : intl(7, (i) => new Date(2000, 0, 2 + i), { weekday: 'short' });
    // Nama hari penuh utk ditampilkan di teks terpilih (permintaan user
    // 2026-09-04: "date & time picker yang ada hari nya", ref mockup
    // UIUX_components_part1.html) -- DAY_LABELS di atas cuma header grid
    // kalender (2 huruf), ini utk display value ("Jumat, 4 Sep 2026").
    const DAY_NAMES_LONG = intl(7, (i) => new Date(2000, 0, 2 + i), { weekday: 'long' });
    const pad = (n) => String(n).padStart(2, '0');

    Alpine.data('tmDatePicker', (opts = {}) => ({
        mode: opts.mode || 'date',
        min: opts.min || null,
        max: opts.max || null,
        open: false,
        display: '',
        monthNames: MONTHS_LONG,
        dayLabels: DAY_LABELS,
        viewYear: new Date().getFullYear(),
        viewMonth: new Date().getMonth(),
        selYear: null,
        selMonth: null,
        selDay: null,
        selHour: 0,
        selMinute: 0,
        days: [],
        yearRange: [],

        init(initialValue) {
            const now = new Date();
            this.yearRange = [];
            for (let y = now.getFullYear() + 10; y >= now.getFullYear() - 90; y--) this.yearRange.push(y);

            const parsed = initialValue ? this.parseIso(initialValue) : null;
            if (parsed) {
                this.selYear = parsed.y; this.selMonth = parsed.m; this.selDay = parsed.d;
                this.selHour = parsed.h; this.selMinute = parsed.i;
                this.viewYear = parsed.y; this.viewMonth = parsed.m;
            }
            this.display = this.formatDisplay(initialValue);
            this.buildDays();
        },

        parseIso(v) {
            const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
            if (!m) return null;
            return { y: +m[1], m: +m[2] - 1, d: +m[3], h: m[4] ? +m[4] : 0, i: m[5] ? +m[5] : 0 };
        },

        formatDisplay(v) {
            const p = v ? this.parseIso(v) : null;
            if (!p) return '';
            const dow = DAY_NAMES_LONG[new Date(p.y, p.m, p.d).getDay()];
            let out = `${dow}, ${p.d} ${MONTHS_SHORT[p.m]} ${p.y}`;
            if (this.mode === 'datetime') out += ` ${pad(p.h)}:${pad(p.i)}`;
            return out;
        },

        buildDays() {
            const firstDow = new Date(this.viewYear, this.viewMonth, 1).getDay();
            const total = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
            const arr = [];
            for (let i = 0; i < firstDow; i++) arr.push(null);
            for (let d = 1; d <= total; d++) arr.push(d);
            this.days = arr;
        },

        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.viewYear = this.selYear ?? this.viewYear;
                this.viewMonth = this.selMonth ?? this.viewMonth;
                this.buildDays();
            }
        },

        prevMonth() {
            this.viewMonth--;
            if (this.viewMonth < 0) { this.viewMonth = 11; this.viewYear--; }
            this.buildDays();
        },

        nextMonth() {
            this.viewMonth++;
            if (this.viewMonth > 11) { this.viewMonth = 0; this.viewYear++; }
            this.buildDays();
        },

        isSelectable(day) {
            const d = new Date(this.viewYear, this.viewMonth, day);
            if (this.min && d < new Date(this.min)) return false;
            if (this.max && d > new Date(this.max)) return false;
            return true;
        },

        selectDay(day) {
            if (!this.isSelectable(day)) return;
            this.selYear = this.viewYear; this.selMonth = this.viewMonth; this.selDay = day;
            this.apply();
            if (this.mode !== 'datetime') this.open = false;
        },

        apply() {
            if (this.selDay === null) return;
            const iso = this.mode === 'datetime'
                ? `${this.selYear}-${pad(this.selMonth + 1)}-${pad(this.selDay)} ${pad(this.selHour)}:${pad(this.selMinute)}:00`
                : `${this.selYear}-${pad(this.selMonth + 1)}-${pad(this.selDay)}`;
            this.setModel(iso);
        },

        today() {
            const now = new Date();
            this.selYear = now.getFullYear(); this.selMonth = now.getMonth(); this.selDay = now.getDate();
            this.viewYear = this.selYear; this.viewMonth = this.selMonth;
            if (this.mode === 'datetime') { this.selHour = now.getHours(); this.selMinute = now.getMinutes(); }
            this.buildDays();
            this.apply();
            this.open = false;
        },

        clearValue() {
            this.selYear = this.selMonth = this.selDay = null;
            this.setModel('');
            this.open = false;
        },

        setModel(iso) {
            this.$refs.model.value = iso;
            this.$refs.model.dispatchEvent(new Event('input', { bubbles: true }));
            this.$refs.model.dispatchEvent(new Event('change', { bubbles: true }));
            this.display = this.formatDisplay(iso);
        },
    }));
});

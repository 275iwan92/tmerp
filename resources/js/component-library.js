/* ============================================================================
 * Alpine.data() untuk komponen x-form.* baru (layar Settings > Library
 * Component for UI/UX, docs/mockups/UIUX_components_part1.html +
 * mockup_UIUX_calendar.html jadi acuan visual/interaksi). Sama pola dgn
 * date-picker.js: hidden <input x-ref="model"> yang di-dispatch event
 * input+change supaya wire:model (path apa pun) ikut sinkron, komponen ini
 * sendiri TIDAK tahu-menahu soal Livewire.
 * ============================================================================ */
document.addEventListener('alpine:init', () => {
    const setModel = (refEl, value) => {
        refEl.value = value;
        refEl.dispatchEvent(new Event('input', { bubbles: true }));
        refEl.dispatchEvent(new Event('change', { bubbles: true }));
    };

    // --- Integer stepper (spinner +/-) ---
    Alpine.data('tmNumberStepper', (opts = {}) => ({
        value: Number(opts.initial ?? 0),
        min: opts.min === null || opts.min === undefined ? null : Number(opts.min),
        max: opts.max === null || opts.max === undefined ? null : Number(opts.max),

        init() { setModel(this.$refs.model, this.value); },

        clamp(v) {
            if (this.min !== null && v < this.min) v = this.min;
            if (this.max !== null && v > this.max) v = this.max;
            return v;
        },

        step(delta) {
            this.value = this.clamp((Number(this.value) || 0) + delta);
            setModel(this.$refs.model, this.value);
        },

        onType(raw) {
            const n = parseInt(raw, 10);
            this.value = this.clamp(Number.isNaN(n) ? 0 : n);
            setModel(this.$refs.model, this.value);
        },
    }));

    // --- OTP (N kotak digit, auto-advance/backspace) ---
    Alpine.data('tmOtpInput', (opts = {}) => ({
        length: Number(opts.length ?? 6),
        boxes: [],

        init() {
            this.boxes = Array(this.length).fill('');
            this.$nextTick(() => this.sync());
        },

        onInput(i, e) {
            const v = e.target.value.replace(/[^0-9]/g, '').slice(-1);
            this.boxes[i] = v;
            const inputs = this.$refs.wrap.querySelectorAll('input');
            if (v && i < this.length - 1) inputs[i + 1].focus();
            this.sync();
        },

        onKeydown(i, e) {
            if (e.key === 'Backspace' && !this.boxes[i] && i > 0) {
                this.$refs.wrap.querySelectorAll('input')[i - 1].focus();
            }
        },

        sync() { setModel(this.$refs.model, this.boxes.join('')); },
    }));

    // --- Star rating ---
    Alpine.data('tmStarRating', (opts = {}) => ({
        max: Number(opts.max ?? 5),
        value: Number(opts.initial ?? 0),
        hover: 0,

        init() { setModel(this.$refs.model, this.value); },

        pick(n) { this.value = n; setModel(this.$refs.model, this.value); },
    }));

    // --- Masked digit-group input (NIK 6.6.4, dst) ---
    Alpine.data('tmMaskedInput', (opts = {}) => ({
        segments: opts.segments || [6, 6, 4],
        separator: opts.separator ?? '.',
        raw: String(opts.initial ?? '').replace(/\D/g, ''),
        display: '',

        get maxLen() { return this.segments.reduce((a, b) => a + b, 0); },

        init() { this.format(); setModel(this.$refs.model, this.raw); },

        format() {
            const digits = this.raw;
            let out = '';
            let pos = 0;
            this.segments.forEach((len, idx) => {
                const chunk = digits.substring(pos, pos + len);
                if (chunk) out += (idx > 0 && out ? this.separator : '') + chunk;
                pos += len;
            });
            this.display = out;
        },

        onInput(e) {
            this.raw = e.target.value.replace(/\D/g, '').slice(0, this.maxLen);
            this.format();
            e.target.value = this.display;
            setModel(this.$refs.model, this.raw);
        },
    }));

    // --- Searchable single-select (mis. Lokasi Domisili) ---
    Alpine.data('tmSearchableSelect', (opts = {}) => ({
        options: opts.options || [],
        query: '',
        open: false,
        selectedLabel: opts.initialLabel || '',

        get filtered() {
            const q = this.query.toLowerCase();
            return q ? this.options.filter((o) => o.toLowerCase().includes(q)) : this.options;
        },

        toggle() { this.open = !this.open; if (this.open) { this.query = ''; this.$nextTick(() => this.$refs.search?.focus()); } },

        pick(opt) {
            this.selectedLabel = opt;
            this.open = false;
            setModel(this.$refs.model, opt);
        },
    }));

    // --- Multi-select dengan tick mark (mis. Kemampuan Bahasa) ---
    Alpine.data('tmMultiSelect', (opts = {}) => ({
        options: opts.options || [], // [{value,label}]
        checked: opts.initial || [],
        open: false,

        get summary() {
            if (this.checked.length === 0) return opts.placeholder || 'Pilih...';
            if (this.checked.length === 1) return this.labelOf(this.checked[0]);
            return `${this.checked.length} terpilih (${this.checked.map((v) => this.labelOf(v)).join(', ')})`;
        },

        labelOf(v) { return this.options.find((o) => o.value === v)?.label ?? v; },
        isChecked(v) { return this.checked.includes(v); },

        toggleOption(v) {
            this.checked = this.isChecked(v) ? this.checked.filter((x) => x !== v) : [...this.checked, v];
            setModel(this.$refs.model, JSON.stringify(this.checked));
        },

        init() { setModel(this.$refs.model, JSON.stringify(this.checked)); },
    }));

    // --- Telepon: prefix negara (flag+dial) + digit mask 3-4-4 ---
    Alpine.data('tmPhoneInput', (opts = {}) => ({
        countries: opts.countries || [{ name: 'Indonesia', dial: '+62', flag: '🇮🇩' }],
        country: (opts.countries || [])[0] || { name: 'Indonesia', dial: '+62', flag: '🇮🇩' },
        countryOpen: false,
        countryQuery: '',
        raw: String(opts.initial ?? '').replace(/\D/g, ''),
        display: '',

        get filteredCountries() {
            const q = this.countryQuery.toLowerCase();
            return q ? this.countries.filter((c) => c.name.toLowerCase().includes(q)) : this.countries;
        },

        init() { this.formatPhone(); this.sync(); },

        formatPhone() {
            const d = this.raw;
            let out = '';
            if (d.length > 0) out += d.substring(0, 3);
            if (d.length >= 4) out += '-' + d.substring(3, 7);
            if (d.length >= 8) out += '-' + d.substring(7, 12);
            this.display = out;
        },

        onInput(e) {
            this.raw = e.target.value.replace(/\D/g, '').slice(0, 12);
            this.formatPhone();
            e.target.value = this.display;
            this.sync();
        },

        pickCountry(c) { this.country = c; this.countryOpen = false; this.sync(); },

        sync() { setModel(this.$refs.model, this.country.dial + this.raw); },
    }));

    // --- Nilai mata uang: prefix currency (symbol+code) + grouping ribuan ---
    Alpine.data('tmCurrencyInput', (opts = {}) => ({
        currencies: opts.currencies || [{ code: 'IDR', symbol: 'Rp' }],
        currency: (opts.currencies || [])[0] || { code: 'IDR', symbol: 'Rp' },
        currencyOpen: false,
        raw: String(opts.initial ?? '').replace(/\D/g, ''),
        display: '',

        init() { this.formatValue(); this.sync(); },

        formatValue() {
            this.display = this.raw ? BigInt(this.raw).toLocaleString('id-ID') : '';
        },

        onInput(e) {
            this.raw = e.target.value.replace(/\D/g, '');
            this.formatValue();
            e.target.value = this.display;
            this.sync();
        },

        pickCurrency(c) { this.currency = c; this.currencyOpen = false; this.sync(); },

        sync() { setModel(this.$refs.model, this.currency.code + ':' + this.raw); },

        get readableValue() {
            if (!this.raw) return '-';
            return `${this.currency.symbol} ${BigInt(this.raw).toLocaleString('id-ID')} (${this.currency.code})`;
        },
    }));

    // --- Drag & drop file dropzone (forward ke <input type=file> asli, wire:model native Livewire) ---
    Alpine.data('tmFileDropzone', () => ({
        dragOver: false,
        fileName: '',

        onDrop(e) {
            this.dragOver = false;
            const files = e.dataTransfer.files;
            if (!files || !files.length) return;
            this.$refs.input.files = files;
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            this.fileName = files[0].name;
        },

        onPicked(e) {
            const f = e.target.files?.[0];
            this.fileName = f ? f.name : '';
        },
    }));

    // --- Mini kalender bulan (docs/mockups/mockup_UIUX_calendar.html, versi
    // ringkas: month-view + daftar event tanggal terpilih, TANPA drag&drop
    // atau view Day/Week -- itu scope layar kalender dedicated tersendiri
    // kalau ada kebutuhan bisnis nyata, ini murni referensi pola). ---
    Alpine.data('tmMiniCalendar', (opts = {}) => ({
        events: opts.events || {}, // { 'YYYY-MM-DD': [{title, colorClass}] }
        today: new Date(),
        viewYear: new Date().getFullYear(),
        viewMonth: new Date().getMonth(),
        selDateStr: '',
        days: [],

        init() {
            this.selDateStr = this.toDateStr(this.today);
            this.buildDays();
        },

        toDateStr(d) { return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; },

        buildDays() {
            const firstDow = new Date(this.viewYear, this.viewMonth, 1).getDay();
            const total = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
            const arr = [];
            for (let i = 0; i < firstDow; i++) arr.push(null);
            for (let d = 1; d <= total; d++) {
                const dateStr = `${this.viewYear}-${String(this.viewMonth + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                arr.push({ day: d, dateStr, events: this.events[dateStr] || [] });
            }
            this.days = arr;
        },

        isToday(dateStr) { return dateStr === this.toDateStr(this.today); },

        prevMonth() { this.viewMonth--; if (this.viewMonth < 0) { this.viewMonth = 11; this.viewYear--; } this.buildDays(); },
        nextMonth() { this.viewMonth++; if (this.viewMonth > 11) { this.viewMonth = 0; this.viewYear++; } this.buildDays(); },
        selectDay(dateStr) { this.selDateStr = dateStr; },

        get selectedEvents() { return this.events[this.selDateStr] || []; },
        get monthLabel() {
            const names = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            return `${names[this.viewMonth]} ${this.viewYear}`;
        },
    }));

    // --- QR code generator (npm "qrcode", lokal di browser -- TIDAK kirim
    // data form ke API pihak ketiga mana pun, beda dari mockup asli yang
    // pakai CDN qrcodejs tapi sama-sama client-side). ---
    Alpine.data('tmQrDemo', () => ({
        open: false,

        async generate(text) {
            const QRCode = (await import('qrcode')).default;
            this.open = true;
            await this.$nextTick();
            await QRCode.toCanvas(this.$refs.canvas, text || '(kosong)', { width: 180, margin: 1, color: { dark: '#111827', light: '#ffffff' } });
        },

        download() {
            const a = document.createElement('a');
            a.href = this.$refs.canvas.toDataURL('image/png');
            a.download = 'demo-qrcode.png';
            a.click();
        },
    }));
});

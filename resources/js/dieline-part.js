/* =============================================================================
 * Alpine glue untuk section Part & Dieline (inquiry_new).
 * Bukan renderer baru — hanya menjembatani state Livewire <-> TMDieline
 * (resources/js/tmdieline.js) supaya redraw SVG murni client-side (real-time).
 *
 * Pemilihan Tipe Produk & Ukuran Pasaran adalah aksi Livewire sungguhan
 * (wire:change, lihat form.blade.php) — server yang mengisi default dims dari
 * prod_type_param/prod_type_size, lalu wire:key berubah supaya blok ini
 * di-remount dengan data awal baru. Field dimensi & flat W/H/Area pakai
 * wire:model(.blur) asli supaya perubahan benar-benar ikut ter-simpan; Alpine
 * hanya "menumpang" event input yang sama untuk redraw instan tanpa roundtrip.
 * ============================================================================= */
document.addEventListener('alpine:init', () => {
    Alpine.data('dielinePart', (types, initial) => ({
        types: types || {},
        typeCode: initial.typeCode || '',
        dims: { ...(initial.dims || {}) },
        flatManual: !!initial.flatManual,

        // Popup picker visual (BACKLOG/qc_feedback 2026-09-03 -- TAMBAHAN, bukan
        // pengganti <select> teks). Thumbnail per tipe = TMDieline.svg() dgn dims
        // default (preset ukuran pertama, atau default tiap param) -- preview ASLI
        // dieline tipe itu, bukan ikon generik. Dihitung sekali (cache), murni
        // client-side, tidak ada roundtrip Livewire tambahan.
        pickerOpen: false,
        _typeListCache: null,

        get typeList() {
            if (this._typeListCache) return this._typeListCache;

            this._typeListCache = Object.entries(this.types).map(([code, t]) => {
                const dims = this.defaultDimsFor(t);
                let svg = null;
                try { svg = t.spec ? window.TMDieline.svg(t.spec, dims) : null; } catch (e) { svg = null; }

                return { code, name: t.name, svg };
            });

            return this._typeListCache;
        },

        defaultDimsFor(t) {
            if (t.sizes && t.sizes.length) return t.sizes[0].values || {};

            const d = {};
            (t.params || []).forEach((p) => {
                if (p.type !== 'ENUM') d[p.key] = p.default;
            });

            return d;
        },

        // Pilih via popup -- reuse PERSIS handler wire:change yang sudah ada di
        // <select x-ref="typeSelect">, bukan bikin aksi Livewire baru. Semua field
        // dimensi TETAP full-editable sesudahnya, sama seperti pilih via dropdown.
        pickType(code) {
            const el = this.$refs.typeSelect;
            if (!el) return;
            el.value = code;
            el.dispatchEvent(new Event('change', { bubbles: true }));
            this.pickerOpen = false;
        },

        init() {
            // $refs anak (fw/fh/fa/preview) belum ter-register saat init() jalan
            // (Alpine baru proses node ini, belum jalan ke bawah) — tunda 1 tick.
            this.$nextTick(() => this.recalc());
        },

        get type() {
            return this.types[this.typeCode] || null;
        },

        get spec() {
            return this.type ? this.type.spec : null;
        },

        onDim(key, val) {
            this.dims[key] = val;
            this.recalc();
        },

        toggleManual(val) {
            this.flatManual = val;
            this.recalc();
        },

        onFlatManualInput() {
            if (!this.flatManual) return;
            const fw = this.$refs.fw ? this.$refs.fw.value : '';
            const fh = this.$refs.fh ? this.$refs.fh.value : '';
            this.setFlatField('fa', ((+fw || 0) * (+fh || 0)).toFixed(2));
            this.redraw();
        },

        recalc() {
            if (!this.flatManual) {
                const f = window.TMDieline.flat(this.spec, this.dims);
                this.setFlatField('fw', f.w || '');
                this.setFlatField('fh', f.h || '');
                this.setFlatField('fa', f.area != null ? f.area.toFixed(2) : '');
            }
            this.redraw();
        },

        // Set nilai lalu tembak event 'input' asli supaya listener wire:model
        // (punya Livewire sendiri, terpasang di elemen yang sama) ikut menangkap
        // perubahan — tanpa ini nilai hasil komputasi JS tidak akan pernah
        // ter-queue untuk dikirim ke server.
        setFlatField(ref, value) {
            const el = this.$refs[ref];
            if (!el || el.value === String(value)) return;
            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
        },

        redraw() {
            window.TMDieline.render(this.$refs.preview, this.spec, this.dims);
        },
    }));
});

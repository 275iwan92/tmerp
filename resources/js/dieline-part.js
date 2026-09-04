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

        // Terapkan saran ukuran dari analisis DPI artwork (form.blade.php ::
        // suggestSizeFromArtwork()) -- paksa Manual dulu (Flat W/H readonly saat
        // Auto), baru isi & hitung ulang Area + redraw.
        applyArtworkSize(w, h) {
            this.flatManual = true;
            this.setFlatField('fw', w);
            this.setFlatField('fh', h);
            this.setFlatField('fa', ((+w || 0) * (+h || 0)).toFixed(2));
            this.redraw();
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

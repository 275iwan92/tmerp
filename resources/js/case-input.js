// Case toggle (aa -> Aa -> AA) untuk SEMUA input teks string standar
// (x-form.input type=text, x-form.textarea). Keputusan user 2026-09-21:
// pola Component Library item #1 dijadikan acuan wajib, alasannya user sering
// input lewat HP/tablet di mana menekan Shift tiap kata itu merepotkan.
//
// Beda dari tmCaseToggleInput (component-library.js): di sana nilai dibawa
// hidden input krn elemen tampilannya textarea pengganti; DI SINI elemen asli
// yang dipegang komponen (input/textarea ber-wire:model, ber-Alpine handler,
// dst) TETAP jadi satu-satunya elemen -- transform dilakukan di tempat lalu
// event 'input' dibiarkan lanjut menggelembung. Wajib begitu: kalau nilai
// dipindah ke hidden input, semua wire:model.live/@focus/@click.outside yang
// sudah menempel di ratusan pemanggil akan menempel ke elemen yang salah.
document.addEventListener('alpine:init', () => {
    Alpine.data('tmCaseInput', (opts = {}) => ({
        modes: ['none', 'capitalize', 'uppercase'],
        labels: { none: 'aa', capitalize: 'Aa', uppercase: 'AA' },
        idx: Math.max(0, ['none', 'capitalize', 'uppercase'].indexOf(opts.mode ?? 'none')),

        transform(text, mode) {
            if (mode === 'uppercase') return text.toUpperCase();
            if (mode === 'capitalize') return text.replace(/\b\p{L}/gu, (c) => c.toUpperCase());
            return text;
        },

        // Handler ini jalan SEBELUM listener Livewire (Alpine bind langsung ke
        // elemen, Livewire mendelegasikan dari root) -- jadi yang dibaca
        // wire:model sudah nilai hasil transform, bukan ketikan mentah.
        onInput(e) {
            const mode = this.modes[this.idx];
            if (mode === 'none') return;
            const el = e.target;
            const next = this.transform(el.value, mode);
            if (next === el.value) return;
            const pos = el.selectionStart;
            el.value = next;
            // Panjang string tidak berubah oleh transform apa pun di atas,
            // jadi kursor bisa dikembalikan apa adanya.
            try { el.setSelectionRange(pos, pos); } catch { /* input type tertentu tak punya selection */ }
        },

        cycle() {
            this.idx = (this.idx + 1) % this.modes.length;
            const el = this.$refs.el;
            if (!el) return;
            const next = this.transform(el.value, this.modes[this.idx]);
            if (next !== el.value) {
                el.value = next;
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }
            el.focus();
        },
    }));
});

/* ============================================================================
 * Ctrl/Shift multi-select checkbox ala file manager (permintaan user
 * 2026-08-30) -- dipakai di grid dgn checkbox pilih-baris (co/list.blade.php,
 * master/raw-material/index.blade.php). Klik biasa (dgn/tanpa Ctrl, checkbox
 * memang sudah independen tanpa perlu modifier) = toggle 1 baris seperti
 * biasa. Shift+klik = pilih SEMUA baris di antara klik terakhir dan baris
 * ini (urutan DOM checkbox yang lagi dirender, BUKAN urutan PK) -- range
 * SELALU menambah pilihan (tidak toggle-off), konsisten pola file manager.
 *
 * Guard bisnis (baris mana yang BOLEH dihapus massal) TETAP di server
 * (HasCustomizableGrid::selectRange() cuma gabung array, query bulkDelete()
 * yang menyaring stat_confirm/doc_status) -- Alpine di sini murni UX,
 * bukan sumber kebenaran keamanan.
 * ============================================================================ */
document.addEventListener('alpine:init', () => {
    Alpine.data('rowMultiSelect', () => ({
        lastId: null,

        handleCheckClick(e, id) {
            if (e.shiftKey && this.lastId !== null && this.lastId !== id) {
                const boxes = Array.from(this.$el.querySelectorAll('.row-checkbox'));
                const ids = boxes.map((b) => parseInt(b.dataset.id, 10));
                const i1 = ids.indexOf(this.lastId);
                const i2 = ids.indexOf(id);

                if (i1 !== -1 && i2 !== -1) {
                    const lo = Math.min(i1, i2);
                    const hi = Math.max(i1, i2);
                    this.$wire.selectRange(ids.slice(lo, hi + 1));
                    this.lastId = id;

                    return;
                }
            }

            this.$wire.toggleRowSelection(id);
            this.lastId = id;
        },
    }));
});

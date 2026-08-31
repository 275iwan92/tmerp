/* =============================================================================
 * Standar alert/toast global (instruksi user 2026-08-31: satu bahasa visual
 * utk event simpan-data & data-duplikat, gantikan 3 mekanisme lama yg tak
 * seragam -- toast Alpine lokal shell, banner session()->flash() copy-paste
 * di 14 halaman, drawer sys_notifications). Komponen ini beda dari toast
 * lawas .shell (main-menu.blade.php toastMsg/toastShow, dipakai utk hal
 * remeh spt "item belum dibangun" -- SENGAJA tidak disatukan, beda level
 * kepentingan) & dari <x-alert.banner> (inline, bukan floating).
 *
 * Dipasang via <x-alert.toast/> di layouts/app.blade.php & layouts/shell.blade.php
 * -- otomatis ada di SETIAP halaman termasuk yg dimuat lewat <iframe> (grid/
 * form transaksi), karena tiap iframe = dokumen sendiri dgn app.js sendiri.
 *
 * Pemicu dari Livewire mana pun, tanpa import apa pun:
 *   $this->dispatch('app-alert', type: 'duplicate', title: '...', message: '...');
 * type: success | info | warning | danger | duplicate (varian warning yg TIDAK
 * auto-hilang -- butuh keputusan sadar user, lihat pushDuplicate default sticky).
 * ============================================================================= */
document.addEventListener('alpine:init', () => {
    Alpine.data('appAlerts', () => ({
        items: [],
        seq: 0,

        push(detail) {
            const d = Array.isArray(detail) ? (detail[0] ?? {}) : (detail ?? {});
            const type = d.type || 'info';
            const sticky = d.sticky ?? (type === 'duplicate');
            const duration = d.duration ?? (sticky ? 0 : (type === 'warning' || type === 'danger' || type === 'duplicate' ? 5200 : 4200));

            const id = ++this.seq;
            this.items.unshift({
                id,
                type: type === 'duplicate' ? 'warning' : type,
                isDuplicate: type === 'duplicate',
                title: d.title || '',
                message: d.message || '',
                duration,
            });

            if (duration > 0) {
                setTimeout(() => this.dismiss(id), duration);
            }
        },

        dismiss(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },

        init() {
            window.addEventListener('app-alert', (e) => this.push(e.detail));
        },
    }));
});

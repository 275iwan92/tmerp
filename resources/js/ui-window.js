// x-ui.window (App\View\Components\Ui\Window) -- jendela popup yang bisa
// digeser, diperkecil (dok bawah-kiri), diperbesar, ditutup. Permintaan user
// 2026-09-23 untuk semua popup dari menu profil.
//
// Buka/tutup TETAP flag Alpine milik shell pembungkus (mis. configWinOpen di
// appShell/reportShell) supaya item sys_user_menu action_type 'modal' cukup
// menulis `<flag> = true` seperti panel lain. `this[openKey]` membaca/menulis
// flag itu lewat rantai scope Alpine.
document.addEventListener('alpine:init', () => {
    Alpine.store('tmWin', { z: 200, dock: [] });

    Alpine.data('tmWindow', (opts) => ({
        openKey: opts.open,
        id: opts.id,
        w: opts.w ?? 720,
        h: opts.h ?? 500,
        x: null,
        y: null,
        z: 200,
        min: false,
        max: false,
        drag: null,

        init() {
            this.$watch(this.openKey, (on) => {
                if (on) {
                    this.min = false;
                    this.undock();
                    this.place();
                    this.focus();
                } else {
                    this.min = false;
                    this.max = false;
                    this.undock();
                }
            });
            // Ganti tema/bahasa butuh reload -- jendela yang memicunya dibuka lagi.
            try {
                if (sessionStorage.getItem('tmWinReopen') === this.id) {
                    sessionStorage.removeItem('tmWinReopen');
                    this.$nextTick(() => { this[this.openKey] = true; });
                }
            } catch (e) {}
        },

        get isOpen() {
            return !! this[this.openKey];
        },

        place() {
            if (this.x !== null) return;
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const w = Math.min(this.w, vw - 16);
            const h = Math.min(this.h, vh - 16);
            const cascade = (Alpine.store('tmWin').z - 200) % 5 * 24;
            this.x = Math.max(8, Math.round((vw - w) / 2) + cascade);
            this.y = Math.max(8, Math.round((vh - h) / 3) + cascade);
        },

        focus() {
            this.z = ++Alpine.store('tmWin').z;
        },

        close() {
            this[this.openKey] = false;
        },

        toggleMax() {
            if (this.min) this.toggleMin();
            this.max = ! this.max;
            this.focus();
        },

        toggleMin() {
            this.min = ! this.min;
            if (this.min) {
                Alpine.store('tmWin').dock.push(this.id);
            } else {
                this.undock();
                this.focus();
            }
        },

        undock() {
            const dock = Alpine.store('tmWin').dock;
            const i = dock.indexOf(this.id);
            if (i > -1) dock.splice(i, 1);
        },

        dragStart(e) {
            if (this.max || this.min || e.button !== 0 || e.target.closest('button')) return;
            this.focus();
            this.drag = { dx: e.clientX - this.x, dy: e.clientY - this.y };
            const move = (ev) => {
                const w = Math.min(this.w, window.innerWidth - 16);
                this.x = Math.min(Math.max(ev.clientX - this.drag.dx, 8 - w + 80), window.innerWidth - 80);
                this.y = Math.min(Math.max(ev.clientY - this.drag.dy, 0), window.innerHeight - 40);
            };
            const up = () => {
                this.drag = null;
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', up);
            };
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up);
        },

        get box() {
            if (this.min) {
                const i = Alpine.store('tmWin').dock.indexOf(this.id);
                return `z-index:${this.z};left:${12 + Math.max(0, i) * 252}px;bottom:12px;width:240px;`;
            }
            if (this.max) {
                return `z-index:${this.z};left:8px;top:8px;width:calc(100vw - 16px);height:calc(100vh - 16px);`;
            }
            return `z-index:${this.z};left:${this.x ?? 8}px;top:${this.y ?? 8}px;`
                + `width:min(${this.w}px, calc(100vw - 16px));height:min(${this.h}px, calc(100vh - 16px));`;
        },
    }));
});

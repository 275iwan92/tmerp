/* =============================================================================
 * Shell MDI (mega menu + sidebar + tab) untuk halaman menu utama pasca-login.
 * State navigasi (modul aktif, tab terbuka, command palette, AI preview) murni
 * client-side Alpine — diumpani sekali lewat @js() dari SysAppMenu (server).
 * Item dengan url nyata dibuka via <iframe> (rute Laravel asli, sesi/cookie
 * sama origin jadi otomatis ikut). Item tanpa rute (modul belum digarap)
 * dirender placeholder ala mockup supaya shell tetap terasa lengkap.
 *
 * Notifikasi & chat SENGAJA tidak ikut di sini — isinya di-render Livewire
 * (perlu roundtrip server utk tandai-baca/kirim pesan); Alpine di sana cuma
 * toggle buka/tutup drawer (lihat notifOpen/chatOpen di bawah).
 * ============================================================================= */
document.addEventListener('alpine:init', () => {
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    Alpine.data('appShell', (menuTree, flatItems, homeLabel) => ({
        menuTree: menuTree || [],
        flat: flatItems || [],
        homeLabel: homeLabel || 'Home',

        mini: false,
        nosidebar: false,
        notopbar: false,

        // Nilai `mini` sebelum sebuah halaman iframe memintanya dikecilkan.
        _miniRestore: null,

        // Default awal sidebar TERTUTUP di layar sempit (<768px, breakpoint sama
        // dgn @media di app.css) supaya topbar/avatar dropdown tidak kegencet saat
        // load pertama. Toggle ☰ ("nosidebar") tetap sama, ini cuma nilai awal.
        init() {
            if (window.matchMedia('(max-width: 767px)').matches) {
                this.nosidebar = true;
            }

            // Halaman di dalam <iframe> boleh minta sidebar dikecilkan supaya
            // layar kerjanya lega (pertama dipakai ProDev/Imposition, yang
            // butuh area gambar selebar mungkin). Nilai sidebar SEBELUMNYA
            // disimpan lalu dikembalikan begitu halaman itu ditinggalkan --
            // jadi preferensi user tidak ikut hilang gara-gara membuka satu
            // layar. Pesan dari origin lain diabaikan.
            window.addEventListener('message', (e) => {
                if (e.origin !== window.location.origin) return;
                if (e.data && e.data.tmerp === 'closeTab') {
                    const frame = [...document.querySelectorAll('iframe[data-tab-code]')].find((f) => f.contentWindow === e.source);
                    if (frame) this.closeTab(frame.dataset.tabCode);
                    return;
                }
                if (!e.data || e.data.tmerp !== 'sidebar') return;

                if (e.data.mini) {
                    if (this._miniRestore === null || this._miniRestore === undefined) {
                        this._miniRestore = this.mini;
                    }
                    this.mini = true;
                } else {
                    this.mini = this._miniRestore ?? false;
                    this._miniRestore = null;
                }
            });

            // Auto-buka panel dropdown-user via ?open=xxx (instruksi user
            // 2026-09-14: user-menu di Laporan Manajemen HARUS sama dgn di
            // sini -- shell Laporan tidak punya modal/drawer/tab MDI sendiri,
            // jadi item spt Chat/Notifikasi/Edit profil di sana mengarahkan
            // ke /menu?open=<flag> dulu; begitu mendarat di sini, panel yg
            // sesuai langsung terbuka -- bukan tombol mati. Nama flag boolean
            // (profileOpen dkk) HARUS sinkron dgn app/Livewire/Shell/UserMenu.php
            // & tabel sys_user_menu.action_target.
            const openParam = new URLSearchParams(window.location.search).get('open');
            if (openParam) {
                const booleanFlags = ['profileOpen', 'passwordOpen', 'notifOpen', 'chatOpen', 'aiOpen', 'portalOpen'];
                if (booleanFlags.includes(openParam)) {
                    this.$nextTick(() => { this[openParam] = true; });
                } else {
                    this.$nextTick(() => this.openTab(openParam));
                }
                window.history.replaceState({}, '', window.location.pathname);
            }
        },

        activeMod: 'HOME',
        openTabs: [],
        activeTabCode: null,

        ddOpen: false,
        profileOpen: false,
        passwordOpen: false,
        notifOpen: false,
        chatOpen: false,
        aiOpen: false,
        portalOpen: false,

        paletteOpen: false,
        paletteQuery: '',
        paletteSel: 0,

        toastMsg: '',
        toastShow: false,
        toast(msg) {
            this.toastMsg = msg;
            this.toastShow = true;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => { this.toastShow = false; }, 2200);
        },

        get currentModule() {
            return this.menuTree.find((m) => m.code === this.activeMod) || null;
        },

        get sidebarGroups() {
            return this.currentModule ? this.currentModule.children : [];
        },

        // Grup "Settings"/"Security" di footer sidebar (data-driven dari
        // sys_app_menu, code SETUP -- lihat SET_GRP_SETTINGS/SET_GRP_SECURITY,
        // migration 2026_09_07_000004) -- SETUP sengaja tidak muncul di
        // mega-menu topbar (difilter di main-menu.blade.php), jadi diambil
        // langsung dari menuTree, bukan lewat currentModule/selectModule.
        get setupGroups() {
            const setup = this.menuTree.find((m) => m.code === 'SETUP');
            return setup ? setup.children : [];
        },

        selectModule(code) {
            this.activeMod = code;
            if (code === 'HOME') this.showHome();
        },

        findItem(code) {
            return this.flat.find((x) => x.code === code);
        },

        openTab(code) {
            const it = this.findItem(code);
            if (! it) return;
            // module_status_system (globalrules.yaml): NOT_BUILT = is_clickable
            // false di DB (sys_app_menu_color) -- layar belum ada, jangan buka apa-apa.
            if (! it.isClickable) {
                this.toast(`${it.label} — ${it.statusLabel ?? 'belum tersedia'}.`);
                return;
            }
            if (! this.openTabs.some((t) => t.code === code)) {
                this.openTabs.push(it);
                this.activate(code);
                return;
            }
            this.activate(code);
            this.resetTabToMenu(it);
        },

        // Klik menu yang tab-nya sudah terbuka = kembali ke layar awal menu itu
        // (mis. grid ProDev), tapi form yang belum disimpan ditanya dulu
        // (dialog Yes/No/Cancel dari resources/js/dirty-guard.js di dalam iframe).
        // Grid+form satu URL (Stock In, Cash Bank, dst) dikenali dari tombol Save
        // yang sedang tampil -> tetap di-reload supaya kembali ke grid.
        async resetTabToMenu(it) {
            const frame = this.tabFrame(it.code);
            const win = frame?.contentWindow;
            if (! it.url || ! win) return;
            let here;
            try { here = win.location.pathname + win.location.search; } catch { return; }
            const target = new URL(it.url, window.location.origin);
            const home = frame.dataset.home || (target.pathname + target.search);
            const onForm = !! win.tmerpDirty?.onForm();
            if (here === home && ! onForm) return;
            if (! (await this.confirmTabLeave(it.code))) return;
            win.location.assign(target.href);
        },

        tabFrame(code) {
            return [...document.querySelectorAll('iframe[data-tab-code]')].find((f) => f.dataset.tabCode === code) || null;
        },

        tabWindow(code) {
            return this.tabFrame(code)?.contentWindow || null;
        },

        async confirmTabLeave(code) {
            try {
                const guard = this.tabWindow(code)?.tmerpDirty;
                if (! guard || ! guard.isActive()) return true;
                this.activate(code);
                return await guard.confirmLeave();
            } catch {
                return true;
            }
        },

        activate(code) {
            this.activeTabCode = code;
        },

        async closeTab(code) {
            if (! (await this.confirmTabLeave(code))) return;
            const idx = this.openTabs.findIndex((t) => t.code === code);
            if (idx === -1) return;
            const wasActive = this.activeTabCode === code;
            this.openTabs.splice(idx, 1);
            if (wasActive) {
                const last = this.openTabs.length ? this.openTabs[this.openTabs.length - 1] : null;
                last ? this.activate(last.code) : this.showHome();
            }
        },

        showHome() {
            this.activeTabCode = null;
        },

        placeholderHtml(it) {
            const today = new Date().toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
            const note = `<div class="am-note">Layar ini belum digarap di V2 — placeholder tampilan saja.</div>`;
            if (it.objectType === 'form') {
                return `<div class="app-mock"><div class="am-head"><h2>FORM — ${esc(it.label)}</h2><span class="date">${today}</span></div>${note}
                    <div class="am-form">
                        <div class="f"><label>Nomor</label><input value="(auto)" readonly></div>
                        <div class="f"><label>Tanggal</label><input value="${today}" readonly></div>
                        <div class="f full"><label>Keterangan</label><input placeholder="Isi keterangan…" disabled></div>
                        <div class="f"><label>Referensi</label><input placeholder="—" disabled></div>
                        <div class="f"><label>Status</label><input value="Draft" readonly></div>
                        <div class="am-actions"><button class="am-btn g" disabled>Simpan</button><button class="am-btn n" disabled>Batal</button></div>
                    </div></div>`;
            }
            return `<div class="app-mock"><div class="am-head"><h2>${it.objectType === 'report' ? 'REPORT' : 'DAFTAR'} — ${esc(it.label)}</h2><span class="date">${today}</span></div>${note}
                <div class="am-tools"><span class="am-search">🔎 Quick search</span>
                    <button class="am-btn g" disabled>＋ New</button><button class="am-btn n" disabled>⬇ Export</button></div>
                <table class="am-tbl"><thead><tr><th>Kolom A</th><th>Kolom B</th><th>Status</th></tr></thead>
                <tbody><tr><td colspan="3" style="text-align:center;color:var(--muted);padding:24px">(belum ada data — modul menyusul)</td></tr></tbody></table></div>`;
        },

        // ===== Command palette =====
        openPalette() {
            this.paletteOpen = true;
            this.paletteQuery = '';
            this.paletteSel = 0;
            this.$nextTick(() => this.$refs.paletteInput?.focus());
        },
        closePalette() {
            this.paletteOpen = false;
        },
        get paletteResults() {
            const q = this.paletteQuery.trim().toLowerCase();
            const toks = q.split(/\s+/).filter(Boolean);
            const src = q ? this.flat.filter((o) => toks.every((t) => (o.label + ' ' + o.mod).toLowerCase().includes(t))) : this.flat.slice(0, 7);
            return src;
        },
        paletteMove(delta) {
            const n = this.paletteResults.length;
            if (! n) return;
            this.paletteSel = (this.paletteSel + delta + n) % n;
        },
        paletteChoose() {
            const it = this.paletteResults[this.paletteSel];
            if (it) { this.openTab(it.code); this.closePalette(); }
        },

        // ===== AI preview (keyword match ke registry menu, bukan LLM sungguhan) =====
        ...aiPreview(),
        askAi(q) {
            q = (q || '').trim();
            if (! q) return;
            this.addAi('me', q);
            const toks = q.toLowerCase().split(/\s+/).filter((t) => t.length > 2);
            const hit = this.flat.find((o) => {
                const h = (o.label + ' ' + o.mod).toLowerCase();
                return toks.some((t) => h.includes(t));
            });
            if (hit) {
                this.addAi('bot', 'Baik, saya buka “' + hit.label + '” untuk Anda.');
                setTimeout(() => { this.openTab(hit.code); this.aiOpen = false; }, 500);
            } else {
                this.addAi('bot', 'Saya belum menemukan objek yang cocok. Sebut nama form/grid, mis. “quotation” atau “inquiry”.');
            }
        },
    }));

    // State shell Laporan Manajemen (layouts/report.blade.php): flag panel
    // dropdown user (App\Livewire\Shell\UserPanels) + modal halaman. Asisten
    // AI di sini mencari dashboard laporan, tidak membuka Menu Operasional.
    Alpine.data('reportShell', (navItems) => ({
        ddOpen: false,
        configOpen: false,
        notifOpen: false,
        chatOpen: false,
        aiOpen: false,
        profileOpen: false,
        passwordOpen: false,
        portalOpen: false,
        pageUrl: '',
        pageTitle: '',
        ...aiPreview(),
        askAi(q) {
            q = (q || '').trim();
            if (! q) return;
            this.addAi('me', q);
            const toks = q.toLowerCase().split(/\s+/).filter((t) => t.length > 2);
            const hit = (navItems || []).find((o) => toks.some((t) => o.label.toLowerCase().includes(t) || (o.keywords || '').includes(t)));
            if (hit) {
                this.addAi('bot', 'Baik, saya buka “' + hit.label + '”.');
                setTimeout(() => { window.location.href = hit.href; }, 500);
            } else {
                this.addAi('bot', 'Belum ada dashboard yang cocok. Coba sebut mis. “sales”, “purchasing”, “gudang”.');
            }
        },
    }));

    function aiPreview() {
        return {
            aiMessages: [],
            aiInput: '',
            addAi(role, text) {
                this.aiMessages.push({ role, text });
                this.$nextTick(() => {
                    const el = this.$refs.aiBody;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },
        };
    }
});

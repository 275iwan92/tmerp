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

        aiMessages: [],
        aiInput: '',

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

        selectModule(code) {
            this.activeMod = code;
            if (code === 'HOME') this.showHome();
        },

        // Label topbar dipersingkat -- nama modul asli (dari sys_app_menu.label,
        // dipakai juga di sidebar/command palette) tetap utuh, cuma tampilan
        // tab di topbar yang dipendekkan supaya baris modul muat tanpa scroll.
        topLabel(code, label) {
            const short = { HRM: 'HR', FINACC: 'FA' };
            return short[code] || label;
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
            }
            this.activate(code);
        },

        activate(code) {
            this.activeTabCode = code;
        },

        closeTab(code) {
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
        addAi(role, text) {
            this.aiMessages.push({ role, text });
            this.$nextTick(() => {
                const el = this.$refs.aiBody;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },
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
});

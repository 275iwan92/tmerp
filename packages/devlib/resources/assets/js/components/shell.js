/* DevLib · Shell */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, typeIcon, HOME, setDevMode, toast} = Devlib.h;

  // Kerangka aplikasi = kerangka kerja (tab MDI, konten, laci, modal, palet Ctrl+K, asisten AI) + navigasi.
  // Shell = navigasi Classic (mega menu + sidebar + top bar). Turunan menimpa initNav() & markActiveLeaf().
  class Shell extends Devlib.Component {
    static selector = ".dl-shell";
    init() {
      this.modules = JSON.parse(this.el.dataset.dlModules || '[]');
      this.items = [];
      this.modules.forEach(m => (m.children || []).forEach(c => (c.items ? c.items : [c]).forEach(it => this.items.push({...it, mod: m.label}))));
      this.tabbar = $(this.el, '[data-dl-tabbar]');
      this.content = $(this.el, '[data-dl-content]');
      this.homePane = $(this.el, '[data-dl-home]');
      this.activeTab = null;
      this.openTabs = new Map();
      this.chatCount = 2;
      this.notifCount = 3;
      // Dibaca sebelum initNav: navigasi Classic memanggil showHome() saat mulai, yang menyimpan daftar kosong.
      let saved = null; try { saved = JSON.parse(sessionStorage.getItem('dl-shell-tabs')); } catch (e) {}
      this.initFrame();
      this.initNav();
      this.refreshCounts();
      this.restoreTabs(saved);
    }

    // Tab terbuka bertahan saat halaman dimuat ulang (mis. menukar Menu Classic <-> Layer Menu). Per tab browser.
    saveTabs() { try { sessionStorage.setItem('dl-shell-tabs', JSON.stringify({open: [...this.openTabs.keys()], active: this.activeTab})); } catch (e) {} }

    restoreTabs(s) {
      if (!s?.open?.length) { if (this.el.dataset.dlOpen) this.openTab(this.el.dataset.dlOpen); return; }
      s.open.forEach(c => this.openTab(c));
      s.active && this.openTabs.has(s.active) ? this.activate(s.active) : this.showHome();
    }

    /* ---------------- kerangka kerja (dipakai semua turunan) ---------------- */
    initFrame() {
      this.palOv = $(this.el, '[data-dl-palette]');
      this.palIn = $(this.el, '[data-dl-pal-input]');
      this.palList = $(this.el, '[data-dl-pal-list]');
      this.pres = [];
      this.psel = 0;
      this.palOv.addEventListener('click', e => { if (e.target === this.palOv) this.closePalette(); });
      this.palIn.addEventListener('input', e => this.prender(e.target.value));
      this.palIn.addEventListener('keydown', e => {
        if (e.key === 'ArrowDown') { e.preventDefault(); this.psel = Math.min(this.psel + 1, this.pres.length - 1); this.phi(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); this.psel = Math.max(this.psel - 1, 0); this.phi(); }
        else if (e.key === 'Enter') { if (this.pres[this.psel]) { this.openTab(this.pres[this.psel].code); this.closePalette(); } }
        else if (e.key === 'Escape') this.closePalette();
      });
      this.palList.addEventListener('click', e => { const el = e.target.closest('[data-code]'); if (el) { this.openTab(el.dataset.code); this.closePalette(); } });
      document.addEventListener('keydown', e => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); this.palOv.classList.contains('is-open') ? this.closePalette() : this.openPalette(); }
        else if (e.key === 'Escape') this.closePalette();
      });

      $$(this.el, '[data-dl-mclose]').forEach(b => b.addEventListener('click', () => b.closest('.dl-mov').classList.remove('is-on')));
      $$(this.el, '.dl-mov').forEach(o => o.addEventListener('click', e => { if (e.target === o) o.classList.remove('is-on'); }));

      this.backdrop = $(this.el, '[data-dl-backdrop]');
      this.backdrop.addEventListener('click', this.closeDrawers);
      $$(this.el, '[data-dl-close]').forEach(b => b.addEventListener('click', this.closeDrawers));
      $(this.el, '[data-dl-markread]').addEventListener('click', () => { this.notifCount = 0; this.refreshCounts(); $$(this.el, '.dl-nz__dot').forEach(d => d.classList.add('is-read')); toast('Semua notifikasi ditandai terbaca.'); });

      this.chatEl = $(this.el, '[data-dl-drawer="chat"] [data-dl-chat]');
      if (this.chatEl?.dlChat) this.chatCount = this.chatEl.dlChat.unread;
      this.chatEl?.addEventListener('dl:chat-unread', e => { this.chatCount = e.detail.count; this.refreshCounts(); });

      this.aiBody = $(this.el, '[data-dl-ai-body]');
      this.aiIn = $(this.el, '[data-dl-ai-input]');
      $(this.el, '[data-dl-ai-send]').addEventListener('click', () => { this.askAi(this.aiIn.value); this.aiIn.value = ''; });
      this.aiIn.addEventListener('keydown', e => { if (e.key === 'Enter') { this.askAi(this.aiIn.value); this.aiIn.value = ''; } });
      this.aiBody.addEventListener('click', e => { const c = e.target.closest('[data-dl-ai]'); if (c) this.askAi(c.dataset.dlAi); });
    }

    // Aksi akun yang sama untuk menu avatar (Classic) maupun roda gigi (Layer Menu).
    command(a) {
      if (a === 'chat') this.openDrawer('chat');
      else if (a === 'notif' || a === 'ai') this.openDrawer(a);
      else if (a === 'edit' || a === 'pass') $(this.el, `[data-dl-modal="${a}"]`).classList.add('is-on');
      else if (a === 'palette') this.openPalette();
      else if (a === 'devmode') setDevMode(!document.body.classList.contains('dl-dev'));
      else if (a === 'help') toast('Bantuan & dukungan — segera (tiket ke tim dev / sys_support_tickets).');
      else if (a === 'menu-layer' || a === 'menu-classic') this.switchMenu(a.slice(5));
      else if (a === 'logout') $(this.el, 'form[data-dl-logout]')?.requestSubmit();
    }

    // Pilihan menu = cookie dl_menu (dibaca server lewat Shell::preferred()), lalu halaman dimuat ulang.
    switchMenu(mode) {
      document.cookie = `dl_menu=${mode}; path=/; max-age=31536000; samesite=lax`;
      location.reload();
    }

    /* ---------------- navigasi Classic: mega menu + sidebar + top bar ---------------- */
    initNav() {
      this.mega = $(this.el, '[data-dl-mega]');
      this.modTitle = $(this.el, '[data-dl-mod-title]');
      this.sideNav = $(this.el, '[data-dl-side-nav]');

      // Bilah modul: singkatan (short) + tooltip nama lengkap; bila tetap tidak muat -> panah geser + roda mouse.
      this.mega.innerHTML = [HOME, ...this.modules].map(m => `<button type="button" class="dl-mega__item" data-mod="${esc(m.code)}" title="${esc(m.label)}"><span class="mi"><i class="${esc(m.icon || '')}"></i></span>${esc(m.short || m.label)}</button>`).join('');
      this.megaWrap = document.createElement('div');
      this.megaWrap.className = 'dl-mega__wrap';this.mega.before(this.megaWrap);this.megaWrap.append(this.mega);
      this.aL = this.arrow(-1);
      this.aR = this.arrow(1);
      this.megaWrap.prepend(this.aL);this.megaWrap.append(this.aR);
      this.mega.addEventListener('scroll', this.megaEdge);window.addEventListener('resize', this.megaEdge);
      this.mega.addEventListener('wheel', e => { if (this.mega.scrollWidth > this.mega.clientWidth && Math.abs(e.deltaY) > Math.abs(e.deltaX)) { e.preventDefault(); this.mega.scrollLeft += e.deltaY; } }, {passive: false});
      setTimeout(this.megaEdge);
      $$(this.mega, '.dl-mega__item').forEach(b => b.addEventListener('click', () => this.selectModule(b.dataset.mod)));

      this.sideNav.addEventListener('click', e => { const el = e.target.closest('[data-code]'); if (el) this.openTab(el.dataset.code); });

      $$(this.el, '[data-dl-act]').forEach(b => b.addEventListener('click', e => {
        const a = b.dataset.dlAct;
        if (a === 'mini') this.el.classList.toggle('is-mini');
        else if (a === 'nosidebar') this.el.classList.toggle('is-nosidebar');
        else if (a === 'notopbar') this.el.classList.add('is-notopbar');
        else if (a === 'showtopbar') this.el.classList.remove('is-notopbar');
        else if (a === 'palette') this.openPalette();
        else if (a === 'usermenu') { e.stopPropagation(); this.userMenu.classList.toggle('is-open'); }
      }));

      this.userMenu = $(this.el, '[data-dl-usermenu]');
      document.addEventListener('click', () => this.userMenu.classList.remove('is-open'));
      this.userMenu.addEventListener('click', e => {
        e.stopPropagation();
        const fl = e.target.closest('[data-dl-lang]');
        if (fl) { $$(this.userMenu, '.dl-um-flag').forEach(x => x.classList.remove('is-active')); fl.classList.add('is-active'); toast('Bahasa: ' + (fl.dataset.dlLang === 'id' ? 'Indonesia' : 'English')); return; }
        const b = e.target.closest('[data-dl-um]'); if (!b) return;
        if (b.dataset.dlUm !== 'devmode') this.userMenu.classList.remove('is-open');
        this.command(b.dataset.dlUm);
      });

      this.hash = decodeURIComponent(location.hash.slice(1));
      this.selectModule(this.modules.some(m => m.code === this.hash) ? this.hash : 'HOME');
      window.addEventListener('hashchange', () => { const h = decodeURIComponent(location.hash.slice(1)); if (h === 'HOME' || this.modules.some(m => m.code === h)) this.selectModule(h); });
    }

    findItem(code) {
      return this.items.find(x => x.code === code);
    }

    arrow(d) { const b = document.createElement('button'); b.type = 'button'; b.className = `dl-mega__arrow is-${d < 0 ? 'l' : 'r'}`; b.innerHTML = `<i class="fa-solid fa-chevron-${d < 0 ? 'left' : 'right'}"></i>`; b.title = d < 0 ? 'Geser kiri' : 'Geser kanan'; b.addEventListener('click', () => this.mega.scrollBy({left: d * 200, behavior: 'smooth'})); return b; }
    megaEdge() { this.aL.hidden = this.mega.scrollLeft <= 2; this.aR.hidden = this.mega.scrollLeft + this.mega.clientWidth >= this.mega.scrollWidth - 2; }

    badge(it) {
      return it.badge ? `<span class="dl-leaf__badge ${it.badgeTone ? 'is-' + esc(it.badgeTone) : ''}">${esc(it.badge)}</span>` : '';
    }

    leaf(it) {
      return `<button type="button" class="dl-leaf" data-code="${esc(it.code)}" title="${esc(it.label)}"><span class="lic"><i class="${esc(it.icon || typeIcon(it.type))}"></i></span><span class="lbl">${esc(it.label)}</span>${this.badge(it)}</button>`;
    }

    selectModule(code) {
      $$(this.mega, '.dl-mega__item').forEach(b => b.classList.toggle('is-active', b.dataset.mod === code));
      if (code === 'HOME') {
        this.modTitle.innerHTML = `<span class="ic"><i class="${HOME.icon}"></i></span><span class="lbl">HOME</span>`;
        this.sideNav.innerHTML = `<div class="dl-side__hint">Modul <b>Home</b>. Pilih modul lain di atas untuk menampilkan submenunya di sini.</div>`;
        this.showHome(); return;
      }
      const m = this.modules.find(x => x.code === code);
      this.modTitle.innerHTML = `<span class="ic"><i class="${esc(m.icon || '')}"></i></span><span class="lbl">${esc(m.label.toUpperCase())}</span>`;
      this.sideNav.innerHTML = (m.children || []).map(c => c.items ? `<div class="dl-side__cap">${esc(c.label)}</div>` + c.items.map(this.leaf).join('') : this.leaf(c)).join('');
      this.markActiveLeaf();
    }

    markActiveLeaf() {
      return $$(this.sideNav, '.dl-leaf').forEach(l => l.classList.toggle('is-active', l.dataset.code === this.activeTab));
    }

    /* ---------------- tab MDI ---------------- */
    openTab(code) {
      const it = this.findItem(code); if (!it) return;
      if (this.openTabs.has(code)) { this.activate(code); return; }
      const tab = document.createElement('div'); tab.className = 'dl-tab'; tab.dataset.code = code;
      tab.innerHTML = `<span><i class="${esc(it.icon || typeIcon(it.type))}"></i></span><span class="tlbl">${esc(it.label)}</span><button type="button" class="tx" title="Tutup">×</button>`;
      tab.addEventListener('click', ev => { if (ev.target.classList.contains('tx')) { ev.stopPropagation(); this.closeTab(code); } else this.activate(code); });
      this.tabbar.appendChild(tab);
      const pane = document.createElement('div'); pane.className = 'dl-pane is-framed';
      pane.innerHTML = `<iframe src="${esc(it.url)}" title="${esc(it.label)}"></iframe>`;
      this.content.appendChild(pane);
      this.openTabs.set(code, {tab, pane});
      this.activate(code);
    }

    activate(code) {
      this.homePane.classList.remove('is-active'); this.activeTab = code;
      this.openTabs.forEach((v, c) => { const on = c === code; v.tab.classList.toggle('is-active', on); v.pane.classList.toggle('is-active', on); });
      this.markActiveLeaf();
      this.saveTabs();
    }

    closeTab(code) {
      const t = this.openTabs.get(code); if (!t) return;
      const was = t.tab.classList.contains('is-active');
      t.tab.remove(); t.pane.remove(); this.openTabs.delete(code);
      if (was) { const last = [...this.openTabs.keys()].pop(); last ? this.activate(last) : this.showHome(); }
      this.saveTabs();
    }

    showHome() {
      this.activeTab = null;
      this.openTabs.forEach(v => { v.tab.classList.remove('is-active'); v.pane.classList.remove('is-active'); });
      this.homePane.classList.add('is-active'); this.markActiveLeaf();
      this.saveTabs();
    }

    /* ---------------- palet Ctrl+K ---------------- */
    openPalette() { this.palOv.classList.add('is-open'); this.palIn.value = ''; this.prender(''); this.palIn.focus(); }

    closePalette() {
      return this.palOv.classList.remove('is-open');
    }

    prender(q) {
      q = q.trim(); const toks = q.toLowerCase().split(/\s+/).filter(Boolean);
      this.pres = q ? this.items.filter(o => { const h = (o.label + ' ' + o.mod).toLowerCase(); return toks.every(t => h.includes(t)); }) : this.items.slice(0, 7);
      this.palList.innerHTML = this.pres.length ? this.pres.map(o => `<div class="dl-pal__row" data-code="${esc(o.code)}"><span class="lic"><i class="${esc(o.icon || typeIcon(o.type))}"></i></span><div><div class="nm">${esc(o.label)}</div><div class="mm">${esc(o.mod)}</div></div></div>`).join('') : `<div class="dl-pal__empty">Tak ada yang cocok dengan “${esc(q)}”.</div>`;
      this.psel = 0; this.phi();
    }

    phi() {
      return $$(this.palList, '.dl-pal__row').forEach((r, i) => r.classList.toggle('is-sel', i === this.psel));
    }

    refreshCounts() {
      const c = $(this.el, '[data-dl-count="chat"]'), n = $(this.el, '[data-dl-count="notif"]'), dot = $(this.el, '[data-dl-avdot]');
      if (c) { c.textContent = this.chatCount; c.style.display = this.chatCount ? '' : 'none'; }
      if (n) { n.textContent = this.notifCount; n.style.display = this.notifCount ? '' : 'none'; }
      dot?.classList.toggle('is-hide', !(this.chatCount || this.notifCount));
    }

    /* ---------------- laci & asisten AI ---------------- */
    openDrawer(id) {
      $$(this.el, '.dl-drawer.is-on').forEach(d => d.classList.remove('is-on'));
      $(this.el, `[data-dl-drawer="${id}"]`).classList.add('is-on'); this.backdrop.classList.add('is-on');
    }

    closeDrawers() { $$(this.el, '.dl-drawer.is-on').forEach(d => d.classList.remove('is-on')); this.backdrop.classList.remove('is-on'); }
    addAi(role, txt) { const d = document.createElement('div'); d.className = 'dl-ai__msg dl-ai__msg--' + role; d.textContent = txt; this.aiBody.appendChild(d); this.aiBody.scrollTop = this.aiBody.scrollHeight; }

    askAi(q) {
      q = q.trim(); if (!q) return; this.addAi('me', q);
      const toks = q.toLowerCase().split(/\s+/).filter(t => t.length > 2);
      const hit = this.items.find(o => { const h = (o.label + ' ' + o.mod).toLowerCase(); return toks.some(t => h.includes(t)); });
      if (hit) { this.addAi('bot', 'Baik, saya buka “' + hit.label + '” untuk Anda.'); setTimeout(() => { this.openTab(hit.code); this.closeDrawers(); }, 650); }
      else this.addAi('bot', 'Saya belum menemukan objek yang cocok. Sebut nama komponen, mis. “login”, “grid”, atau “kanban”.');
    }
  }
  Devlib.define("Shell", Shell);
})(window.Devlib);

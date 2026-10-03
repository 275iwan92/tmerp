/* DevLib · Shell */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, typeIcon, HOME, setDevMode, toast, T, Tf, CASES, CASE_LABEL, applyCase} = Devlib.h;

  // Kerangka aplikasi = kerangka kerja (tab MDI, konten, laci, modal, palet Ctrl+K, asisten AI) + navigasi.
  // Shell = navigasi Classic (mega menu + sidebar + top bar). Turunan menimpa initNav() & markActiveLeaf().
  class Shell extends Devlib.Component {
    static selector = ".dl-shell";
    init() {
      this.modules = JSON.parse(this.el.dataset.dlModules || '[]');
      this.items = [];
      // Item bisa bersarang (grup di dalam grup = submenu); yang didaftar hanya daun (punya url).
      const leaves = n => n.items ? n.items.flatMap(leaves) : [n];
      this.modules.forEach(m => (m.children || []).flatMap(leaves).forEach(it => this.items.push({...it, mod: m.label})));
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
      // F1 (core.js) dari halaman ini atau dari layar di dalam tab -> Bantuan & dukungan.
      document.addEventListener('dl:help', () => this.command('help'));
      window.addEventListener('message', e => { if (e.origin === location.origin && e.data?.dl === 'help') this.command('help'); });
      // Tombol ber-data-dl-tab-url (mis. pedoman di halaman Bantuan) dibuka sebagai tab kerja.
      this.el.addEventListener('click', e => { const b = e.target.closest('[data-dl-tab-url]'); if (b) { e.preventDefault(); this.openUrlTab(b.dataset.dlTabCode || b.dataset.dlTabUrl, b.dataset.dlTabLabel || b.textContent.trim(), b.dataset.dlTabUrl, b.dataset.dlTabIcon); } });
      this.refreshCounts();
      this.restoreTabs(saved);
    }

    // Tab terbuka bertahan saat halaman dimuat ulang (mis. menukar Menu Classic <-> Layer Menu). Per tab browser.
    saveTabs() { try { sessionStorage.setItem('dl-shell-tabs', JSON.stringify({open: [...this.openTabs.keys()], active: this.activeTab})); } catch (e) {} }

    // data-dl-open-tab (dari ?open=, mis. pendaratan Direksi -> MGMT_EXEC) HARUS menang atas tab
    // yang dipulihkan dari sesi sebelumnya (tab lain browser tab yang sama) -- kalau tidak,
    // login berikutnya bisa "nyangkut" di tab lama alih-alih mendarat ke layar yang dimaksud.
    // ?open= dibuang dari address bar setelah dipakai supaya reload BERIKUTNYA (tanpa niat
    // "buka ini") tidak terus memaksa balik ke tab yang sama. Namanya BUKAN data-dl-open: itu milik
    // pustaka modal (core.js), yang menelan setiap klik di bawah elemen ber-atribut itu.
    restoreTabs(s) {
      const want = this.el.dataset.dlOpenTab;
      if (want) { const u = new URL(location.href); u.searchParams.delete('open'); history.replaceState(null, '', u.pathname + u.search + u.hash); }
      if (!s?.open?.length) { if (want) this.openTab(want); return; }
      s.open.forEach(c => this.openTab(c));
      if (want) this.openTab(want);
      else s.active && this.openTabs.has(s.active) ? this.activate(s.active) : this.showHome();
    }

    /* ---------------- kerangka kerja (dipakai semua turunan) ---------------- */
    initFrame() {
      this.palOv = $(this.el, '[data-dl-palette]');
      this.palIn = $(this.el, '[data-dl-pal-input]');
      this.palList = $(this.el, '[data-dl-pal-list]');
      this.pres = [];
      this.psel = 0;
      this.palOv.addEventListener('click', e => { if (e.target === this.palOv || e.target.closest('[data-dl-pal-close]')) this.closePalette(); });
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
      this.initAccount();
      $(this.el, '[data-dl-markread]').addEventListener('click', () => {
        const done = () => { this.notifCount = 0; this.refreshCounts(); $$(this.el, '.dl-nz__dot').forEach(d => d.classList.add('is-read')); toast(T('n_all_read')); };
        this.account?.read ? this.accountPost(this.account.read, {}).then(r => { if (r.ok) done(); }).catch(() => {}) : done();
      });

      this.chatEl = $(this.el, '[data-dl-drawer="chat"] [data-dl-chat]');
      if (this.chatEl?.dlChat) this.chatCount = this.chatEl.dlChat.unread;
      this.chatEl?.addEventListener('dl:chat-unread', e => { this.chatCount = e.detail.count; this.refreshCounts(); });

      this.aiBody = $(this.el, '[data-dl-ai-body]');
      this.aiIn = $(this.el, '[data-dl-ai-input]');
      $(this.el, '[data-dl-ai-send]').addEventListener('click', () => { this.askAi(this.aiIn.value); this.aiIn.value = ''; });
      this.aiIn.addEventListener('keydown', e => { if (e.key === 'Enter') { this.askAi(this.aiIn.value); this.aiIn.value = ''; } });
      this.aiBody.addEventListener('click', e => { const c = e.target.closest('[data-dl-ai]'); if (c) this.askAi(c.dataset.dlAi); });
      this.initAssistant();
    }

    /* ---------------- menu akun (prop account: notifikasi, profil, password dari / ke server) ---------------- */
    // Tanpa prop account (katalog DevLib) = pratinjau: isi contoh, Save hanya menutup dialog.
    initAccount() {
      try { this.account = JSON.parse($(this.el, '[data-dl-account]')?.dataset.dlAccount || 'null'); } catch (e) { this.account = null; }
      if (!this.account) return;
      const list = $(this.el, '.dl-notif__list');
      this.notifCount = $$(list, '.dl-nz__dot:not(.is-read)').length;
      list.addEventListener('click', e => {
        const n = e.target.closest('[data-dl-notif]'); if (!n) return;
        if (this.account.read && !$(n, '.dl-nz__dot').classList.contains('is-read')) {
          this.accountPost(this.account.read, {id: Number(n.dataset.dlNotif)}).then(r => {
            if (!r.ok) return;
            $(n, '.dl-nz__dot').classList.add('is-read'); this.notifCount = r.body.unread ?? Math.max(0, this.notifCount - 1); this.refreshCounts();
          }).catch(() => {});
        }
        if (n.dataset.url) { this.closeDrawers(); this.openUrlTab('N-' + n.dataset.dlNotif, n.dataset.title, n.dataset.url, 'fa-solid fa-bell'); }
      });
      $$(this.el, '[data-dl-account-save]').forEach(b => b.addEventListener('click', () => {
        const kind = b.dataset.dlAccountSave, box = b.closest('.dl-mov'), msg = $(box, '[data-dl-account-msg]');
        const data = Object.fromEntries($$(box, 'input[name]').map(i => [i.name, i.value]));
        b.disabled = true; msg.hidden = true;
        this.accountPost(this.account[kind], data).then(r => {
          if (!r.ok) { msg.textContent = r.body.message || ''; msg.hidden = false; return; }
          box.classList.remove('is-on'); toast(r.body.message || '');
          if (kind === 'password') $$(box, 'input[name]').forEach(i => { i.value = ''; });
        }).catch(() => { msg.textContent = T('s_save_failed'); msg.hidden = false; }).finally(() => { b.disabled = false; });
      }));
    }

    accountPost(url, body) {
      return fetch(url, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.account.csrf}, body: JSON.stringify(body)})
        .then(r => r.json().catch(() => ({})).then(body => ({ok: r.ok, body})));
    }

    /* ---------------- asisten (prop assistant: jawaban dari server) ---------------- */
    initAssistant() {
      const dr = $(this.el, '[data-dl-drawer="ai"]');
      try { this.ai = JSON.parse(dr.dataset.dlAssistant || 'null'); } catch (e) { this.ai = null; }
      const cb = $(dr, '[data-dl-ai-case]'); this.aiCase = 0;
      cb?.addEventListener('click', () => { this.aiCase = (this.aiCase + 1) % 3; cb.textContent = CASE_LABEL[CASES[this.aiCase]]; this.aiIn.value = applyCase(this.aiIn.value, CASES[this.aiCase]); this.aiIn.focus(); });
      this.aiIn.addEventListener('input', () => { if (!this.aiCase) return; const p = this.aiIn.selectionStart; this.aiIn.value = applyCase(this.aiIn.value, CASES[this.aiCase]); try { this.aiIn.setSelectionRange(p, p); } catch (e) {} });
      if (!this.ai?.api) return;
      (this.ai.history || []).forEach(m => { this.addAi('me', m.q); this.addReply(m.reply, m.id, m.feedback); });
      this.aiBody.addEventListener('click', e => {
        const open = e.target.closest('[data-dl-ai-open]'), topic = e.target.closest('[data-dl-ai-topic]'), page = e.target.closest('[data-dl-ai-page]'), fb = e.target.closest('[data-dl-ai-fb]');
        if (open) { const d = open.dataset; this.findItem(d.dlAiOpen) ? this.openTab(d.dlAiOpen) : this.openUrlTab(d.dlAiOpen, d.label, d.url, d.icon || undefined); this.closeDrawers(); }
        else if (topic) this.askAi(topic.dataset.label, topic.dataset.dlAiTopic);
        else if (page) { this.closeDrawers(); this.openPage?.(page.dataset.dlAiPage); }
        else if (fb) this.sendFeedback(fb);
      });
    }

    aiPost(url, body) {
      return fetch(url, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.ai.api.csrf}, body: JSON.stringify(body)})
        .then(r => r.ok ? r.json() : Promise.reject(r.status));
    }

    askServer(q, topic) {
      this.addAi('me', q);
      const wait = document.createElement('div'); wait.className = 'dl-ai__msg dl-ai__msg--bot dl-ai__wait'; wait.innerHTML = `<i class="fa-solid fa-ellipsis fa-fade"></i> ${esc(this.ai.thinking || '')}`;
      this.aiBody.appendChild(wait); this.aiBody.scrollTop = this.aiBody.scrollHeight;
      this.aiPost(this.ai.api.ask, {q, topic: topic || null})
        // reply.refresh (mis. 'board'): jawaban itu mengubah sesuatu di layar -> bagian itu memuat ulang isinya.
        .then(res => { wait.remove(); this.addReply(res.reply, res.id, null); if (res.reply?.refresh) document.dispatchEvent(new CustomEvent('dl:refresh', {detail: res.reply.refresh})); })
        .catch(() => { wait.remove(); this.addAi('bot', this.ai.failed || ''); });
    }

    // Satu jawaban = kartu (judul, angka besar, teks, langkah, rincian, catatan, tombol) + jempol naik / turun.
    addReply(r, id, feedback) {
      if (!r) return;
      const act = a => a.url ? `<button type="button" class="dl-btn dl-btn--sm dl-btn--primary" data-dl-ai-open="${esc(a.code || a.url)}" data-url="${esc(a.url)}" data-label="${esc(a.label)}" data-icon="${esc(a.icon || '')}"><i class="fa-solid fa-up-right-from-square"></i> ${esc(a.label)}</button>`
        : a.topic ? `<button type="button" class="dl-btn dl-btn--sm dl-btn--secondary" data-dl-ai-topic="${esc(a.topic)}" data-label="${esc(a.label)}"><i class="fa-solid fa-circle-question"></i> ${esc(a.label)}</button>`
        : `<button type="button" class="dl-btn dl-btn--sm dl-btn--secondary" data-dl-ai-page="${esc(a.page)}"><i class="fa-solid fa-ticket"></i> ${esc(a.label)}</button>`;
      const card = document.createElement('div'); card.className = 'dl-ai__card is-' + String(r.kind || '').toLowerCase();
      card.innerHTML = `<h5>${r.kind === 'DENIED' ? '<i class="fa-solid fa-lock"></i> ' : ''}${esc(r.title || '')}</h5>`
        + (r.big ? `<div class="dl-ai__big">${esc(r.big)}</div>` : '')
        + (r.text ? `<div>${esc(r.text)}</div>` : '')
        + (r.steps?.length ? `<ol>${r.steps.map(s => `<li>${esc(s)}</li>`).join('')}</ol>` : '')
        + (r.rows?.length ? `<div class="dl-ai__rows">${r.rows.map(x => `<div><span>${esc(x[0])}</span><b>${esc(x[1])}</b></div>`).join('')}</div>` : '')
        + (r.note ? `<div class="dl-ai__note">${esc(r.note)}</div>` : '')
        + (r.actions?.length ? `<div class="dl-ai__acts">${r.actions.map(act).join('')}</div>` : '');
      this.aiBody.appendChild(card);
      if (id) {
        const fb = document.createElement('div'); fb.className = 'dl-ai__fb'; fb.dataset.id = id;
        fb.innerHTML = [[1, 'thumbs-up'], [-1, 'thumbs-down']].map(([v, ic]) => `<button type="button" data-dl-ai-fb="${v}" class="${feedback === v ? 'is-on' : ''}" aria-label="${esc(T(v > 0 ? 'sh_ai_good' : 'sh_ai_bad'))}"><i class="fa-${feedback === v ? 'solid' : 'regular'} fa-${ic}"></i></button>`).join('');
        this.aiBody.appendChild(fb);
      }
      this.aiBody.scrollTop = this.aiBody.scrollHeight;
    }

    // Klik jempol yang sudah menyala = dicabut.
    sendFeedback(btn) {
      const box = btn.closest('.dl-ai__fb'), value = btn.classList.contains('is-on') ? 0 : Number(btn.dataset.dlAiFb);
      this.aiPost(this.ai.api.feedback, {id: Number(box.dataset.id), value}).then(() => $$(box, 'button').forEach(b => {
        const on = value !== 0 && Number(b.dataset.dlAiFb) === value; b.classList.toggle('is-on', on);
        b.firstElementChild.className = `fa-${on ? 'solid' : 'regular'} fa-thumbs-${Number(b.dataset.dlAiFb) > 0 ? 'up' : 'down'}`;
      })).catch(() => {});
    }

    // Aksi akun yang sama untuk menu avatar (Classic) maupun roda gigi (Layer Menu).
    command(a) {
      if (a === 'chat') this.openDrawer('chat');
      else if (a === 'notif' || a === 'ai') this.openDrawer(a);
      else if (a === 'edit' || a === 'pass') $(this.el, `[data-dl-modal="${a}"]`).classList.add('is-on');
      else if (a === 'palette') this.openPalette();
      else if (a === 'home') this.goHome();
      else if (a === 'devmode') setDevMode(!document.body.classList.contains('dl-dev'));
      else if (a === 'help') this.openHelp();
      else if (a === 'menu-layer' || a === 'menu-classic') this.switchMenu(a.slice(5));
      // Logout (user 2026-10-03): semua tab ditutup dan menu kembali ke lapis utama, login berikutnya mendarat di opening screen.
      else if (a === 'logout') { Devlib.resetWorkspace(); $(this.el, 'form[data-dl-logout]')?.requestSubmit(); }
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
        if (fl) { $$(this.userMenu, '.dl-um-flag').forEach(x => x.classList.remove('is-active')); fl.classList.add('is-active'); toast(T('language') + ': ' + (fl.dataset.dlLang === 'id' ? 'Indonesia' : 'English')); return; }
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

    // Bantuan = tab biasa (bukan item menu): didaftarkan sekali sebagai item HELP lalu dibuka seperti menu.
    openHelp() {
      const url = this.el.dataset.dlHelp;
      if (!url) { toast(T('sh_help_soon')); return; }
      this.openUrlTab('HELP', T('sh_help'), url, 'fa-solid fa-circle-question');
    }

    // Halaman profil bernama (mis. tiket bantuan): Shell Classic tidak punya halaman profil -> Bantuan. LayerShell menimpa.
    openPage(k) { this.command('help'); }

    // Halaman di luar menu (pedoman, bantuan) dibuka sebagai tab kerja biasa.
    openUrlTab(code, label, url, icon = 'fa-solid fa-book') {
      if (!this.findItem(code)) this.items.push({code, label, icon, url});
      this.openTab(code);
    }

    arrow(d) { const b = document.createElement('button'); b.type = 'button'; b.className = `dl-mega__arrow is-${d < 0 ? 'l' : 'r'}`; b.innerHTML = `<i class="fa-solid fa-chevron-${d < 0 ? 'left' : 'right'}"></i>`; b.title = T(d < 0 ? 'sh_scroll_left' : 'sh_scroll_right'); b.addEventListener('click', () => this.mega.scrollBy({left: d * 200, behavior: 'smooth'})); return b; }
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
        this.sideNav.innerHTML = `<div class="dl-side__hint">${esc(T('sh_home_hint'))}</div>`;
        this.showHome(); return;
      }
      const m = this.modules.find(x => x.code === code);
      this.modTitle.innerHTML = `<span class="ic"><i class="${esc(m.icon || '')}"></i></span><span class="lbl">${esc(m.label.toUpperCase())}</span>`;
      this.sideNav.innerHTML = (m.children || []).map(c => c.items ? `<div class="dl-side__cap">${esc(c.label)}</div>` + c.items.map(it => it.items ? `<div class="dl-side__cap">${esc(c.label)} › ${esc(it.label)}</div>` + it.items.map(this.leaf).join('') : this.leaf(it)).join('') : this.leaf(c)).join('');
      this.markActiveLeaf();
    }

    markActiveLeaf() {
      return $$(this.sideNav, '.dl-leaf').forEach(l => l.classList.toggle('is-active', l.dataset.code === this.activeTab));
    }

    /* ---------------- tab MDI ---------------- */
    openTab(code) {
      const it = this.findItem(code); if (!it) return;
      if (this.openTabs.has(code)) { this.activate(code); this.toGrid(code, it.url); return; }
      const tab = document.createElement('div'); tab.className = 'dl-tab'; tab.dataset.code = code;
      tab.innerHTML = `<span><i class="${esc(it.icon || typeIcon(it.type))}"></i></span><span class="tlbl">${esc(it.label)}</span><button type="button" class="tx" title="${esc(T('close'))}">×</button>`;
      tab.addEventListener('click', ev => { if (ev.target.classList.contains('tx')) { ev.stopPropagation(); this.closeTab(code); } else this.activate(code); });
      this.tabbar.appendChild(tab);
      const pane = document.createElement('div'); pane.className = 'dl-pane is-framed';
      pane.innerHTML = `<iframe src="${esc(it.url)}" title="${esc(it.label)}"></iframe>`;
      this.content.appendChild(pane);
      const rec = {tab, pane, grid: null};
      // Alamat grid sesungguhnya = alamat sesudah /item/{code} dialihkan; dipakai toGrid untuk tahu layar masih di grid atau sudah di form.
      pane.querySelector('iframe').addEventListener('load', () => { try { rec.grid = pane.querySelector('iframe').contentWindow.location.href; } catch (e) {} }, {once: true});
      this.openTabs.set(code, rec);
      this.activate(code);
    }

    // Klik menu pada layar yang sudah terbuka = kembali ke grid layar itu (alamat menu, tanpa ?id/?new...).
    // Form yang sedang diubah -> tanya Simpan / Buang / Batal (dialog dirty-guard DevLib di dalam layar itu);
    // Simpan: form dikirim, grid dibuka bila hasilnya tanpa pesan error; Batal: tetap di form.
    toGrid(code, url) {
      const rec = this.openTabs.get(code), fr = rec?.pane.querySelector('iframe'); let w;
      // Form v2 beralamat path (/v2/co/123), bukan ?id= -- bandingkan dengan alamat grid yang direkam saat tab dibuka.
      try { w = fr.contentWindow; if (!rec.grid || w.location.href === rec.grid) return; } catch (e) { return; }
      url = rec.grid;
      const go = () => { w.location.href = url; };
      const h = w.Devlib?.h, live = h ? [...h.dirtyForms].filter(f => w.document.contains(f)) : [];
      if (!live.length) { go(); return; }
      h.askDirty().then(v => {
        if (v === 'save') {
          fr.addEventListener('load', () => { try { if (!fr.contentWindow.document.querySelector('.dl-alert--danger.is-show')) fr.contentWindow.location.href = url; } catch (e) {} }, {once: true});
          h.submitForm(live[0]);
        } else if (v === 'discard') { live.forEach(f => { h.dirtyForms.delete(f); f.classList.remove('is-dirty'); }); go(); }
      });
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

    // Tab yang form-nya sudah diubah tetapi belum di-Save (dirty-guard DevLib di dalam layar tab itu).
    dirtyTabs() {
      return [...this.openTabs.values()].filter(t => {
        try { const w = t.pane.querySelector('iframe')?.contentWindow, h = w?.Devlib?.h; return !!h && [...h.dirtyForms].some(f => w.document.contains(f)); } catch (e) { return false; }
      });
    }

    // Logo / nama aplikasi (user 2026-10-02): semua tab ditutup, opening screen tampil. Ada isian belum di-Save -> tanya sekali dulu.
    goHome() {
      const closeAll = () => { this.openTabs.forEach(t => { t.tab.remove(); t.pane.remove(); }); this.openTabs.clear(); this.showHome(); };
      const n = this.dirtyTabs().length;
      if (!n) { closeAll(); return; }
      Devlib.confirm({title: T('sh_home_title'), message: Tf('sh_home_msg', {n}), ok: T('sh_home_ok')}).then(ok => { if (ok) closeAll(); });
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
      this.palList.innerHTML = this.pres.length ? this.pres.map(o => `<div class="dl-pal__row" data-code="${esc(o.code)}"><span class="lic"><i class="${esc(o.icon || typeIcon(o.type))}"></i></span><div><div class="nm">${esc(o.label)}</div><div class="mm">${esc(o.mod)}</div></div></div>`).join('') : `<div class="dl-pal__empty">${esc(Tf('sh_pal_none', {q}))}</div>`;
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

    askAi(q, topic) {
      q = q.trim(); if (!q) return;
      if (this.ai?.api) return this.askServer(q, topic);
      this.addAi('me', q);
      const toks = q.toLowerCase().split(/\s+/).filter(t => t.length > 2);
      const hit = this.items.find(o => { const h = (o.label + ' ' + o.mod).toLowerCase(); return toks.some(t => h.includes(t)); });
      if (hit) { this.addAi('bot', Tf('sh_ai_open', {label: hit.label})); setTimeout(() => { this.openTab(hit.code); this.closeDrawers(); }, 650); }
      else this.addAi('bot', T('sh_ai_none'));
    }
  }
  Devlib.define("Shell", Shell);
})(window.Devlib);

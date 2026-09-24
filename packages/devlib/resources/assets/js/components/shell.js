/* DevLib · Shell */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, typeIcon, HOME, setDevMode, toast} = Devlib.h;

  class Shell extends Devlib.Component {
    static selector = ".dl-shell";
    init() {
      const root = this.el;
      const modules = JSON.parse(root.dataset.dlModules || '[]');
      const items = [];
      modules.forEach(m => (m.children || []).forEach(c => (c.items ? c.items : [c]).forEach(it => items.push({...it, mod: m.label}))));
      const findItem = code => items.find(x => x.code === code);

      const mega = $(root, '[data-dl-mega]'), modTitle = $(root, '[data-dl-mod-title]'), sideNav = $(root, '[data-dl-side-nav]');
      const tabbar = $(root, '[data-dl-tabbar]'), content = $(root, '[data-dl-content]'), homePane = $(root, '[data-dl-home]');
      let activeTab = null;
      const openTabs = new Map();

      // Bilah modul: singkatan (short) + tooltip nama lengkap; bila tetap tidak muat -> panah geser + roda mouse.
      mega.innerHTML = [HOME, ...modules].map(m => `<button type="button" class="dl-mega__item" data-mod="${esc(m.code)}" title="${esc(m.label)}"><span class="mi"><i class="${esc(m.icon || '')}"></i></span>${esc(m.short || m.label)}</button>`).join('');
      const megaWrap = document.createElement('div'); megaWrap.className = 'dl-mega__wrap'; mega.before(megaWrap); megaWrap.append(mega);
      const arrow = d => { const b = document.createElement('button'); b.type = 'button'; b.className = `dl-mega__arrow is-${d < 0 ? 'l' : 'r'}`; b.innerHTML = `<i class="fa-solid fa-chevron-${d < 0 ? 'left' : 'right'}"></i>`; b.title = d < 0 ? 'Geser kiri' : 'Geser kanan'; b.addEventListener('click', () => mega.scrollBy({left: d * 200, behavior: 'smooth'})); return b; };
      const aL = arrow(-1), aR = arrow(1); megaWrap.prepend(aL); megaWrap.append(aR);
      const megaEdge = () => { aL.hidden = mega.scrollLeft <= 2; aR.hidden = mega.scrollLeft + mega.clientWidth >= mega.scrollWidth - 2; };
      mega.addEventListener('scroll', megaEdge); window.addEventListener('resize', megaEdge);
      mega.addEventListener('wheel', e => { if (mega.scrollWidth > mega.clientWidth && Math.abs(e.deltaY) > Math.abs(e.deltaX)) { e.preventDefault(); mega.scrollLeft += e.deltaY; } }, {passive: false});
      setTimeout(megaEdge);
      $$(mega, '.dl-mega__item').forEach(b => b.addEventListener('click', () => selectModule(b.dataset.mod)));

      const badge = it => it.badge ? `<span class="dl-leaf__badge ${it.badgeTone ? 'is-' + esc(it.badgeTone) : ''}">${esc(it.badge)}</span>` : '';
      const leaf = it => `<button type="button" class="dl-leaf" data-code="${esc(it.code)}" title="${esc(it.label)}"><span class="lic"><i class="${esc(it.icon || typeIcon(it.type))}"></i></span><span class="lbl">${esc(it.label)}</span>${badge(it)}</button>`;

      function selectModule(code) {
        $$(mega, '.dl-mega__item').forEach(b => b.classList.toggle('is-active', b.dataset.mod === code));
        if (code === 'HOME') {
          modTitle.innerHTML = `<span class="ic"><i class="${HOME.icon}"></i></span><span class="lbl">HOME</span>`;
          sideNav.innerHTML = `<div class="dl-side__hint">Modul <b>Home</b>. Pilih modul lain di atas untuk menampilkan submenunya di sini.</div>`;
          showHome(); return;
        }
        const m = modules.find(x => x.code === code);
        modTitle.innerHTML = `<span class="ic"><i class="${esc(m.icon || '')}"></i></span><span class="lbl">${esc(m.label.toUpperCase())}</span>`;
        sideNav.innerHTML = (m.children || []).map(c => c.items ? `<div class="dl-side__cap">${esc(c.label)}</div>` + c.items.map(leaf).join('') : leaf(c)).join('');
        markActiveLeaf();
      }
      const markActiveLeaf = () => $$(sideNav, '.dl-leaf').forEach(l => l.classList.toggle('is-active', l.dataset.code === activeTab));
      sideNav.addEventListener('click', e => { const el = e.target.closest('[data-code]'); if (el) openTab(el.dataset.code); });

      function openTab(code) {
        const it = findItem(code); if (!it) return;
        if (openTabs.has(code)) { activate(code); return; }
        const tab = document.createElement('div'); tab.className = 'dl-tab'; tab.dataset.code = code;
        tab.innerHTML = `<span><i class="${esc(it.icon || typeIcon(it.type))}"></i></span><span class="tlbl">${esc(it.label)}</span><button type="button" class="tx" title="Tutup">×</button>`;
        tab.addEventListener('click', ev => { if (ev.target.classList.contains('tx')) { ev.stopPropagation(); closeTab(code); } else activate(code); });
        tabbar.appendChild(tab);
        const pane = document.createElement('div'); pane.className = 'dl-pane is-framed';
        pane.innerHTML = `<iframe src="${esc(it.url)}" title="${esc(it.label)}"></iframe>`;
        content.appendChild(pane);
        openTabs.set(code, {tab, pane});
        activate(code);
      }
      function activate(code) {
        homePane.classList.remove('is-active'); activeTab = code;
        openTabs.forEach((v, c) => { const on = c === code; v.tab.classList.toggle('is-active', on); v.pane.classList.toggle('is-active', on); });
        markActiveLeaf();
      }
      function closeTab(code) {
        const t = openTabs.get(code); if (!t) return;
        const was = t.tab.classList.contains('is-active');
        t.tab.remove(); t.pane.remove(); openTabs.delete(code);
        if (was) { const last = [...openTabs.keys()].pop(); last ? activate(last) : showHome(); }
      }
      function showHome() {
        activeTab = null;
        openTabs.forEach(v => { v.tab.classList.remove('is-active'); v.pane.classList.remove('is-active'); });
        homePane.classList.add('is-active'); markActiveLeaf();
      }

      // Palet Ctrl+K
      const palOv = $(root, '[data-dl-palette]'), palIn = $(root, '[data-dl-pal-input]'), palList = $(root, '[data-dl-pal-list]');
      let pres = [], psel = 0;
      const openPalette = () => { palOv.classList.add('is-open'); palIn.value = ''; prender(''); palIn.focus(); };
      const closePalette = () => palOv.classList.remove('is-open');
      function prender(q) {
        q = q.trim(); const toks = q.toLowerCase().split(/\s+/).filter(Boolean);
        pres = q ? items.filter(o => { const h = (o.label + ' ' + o.mod).toLowerCase(); return toks.every(t => h.includes(t)); }) : items.slice(0, 7);
        palList.innerHTML = pres.length ? pres.map(o => `<div class="dl-pal__row" data-code="${esc(o.code)}"><span class="lic"><i class="${esc(o.icon || typeIcon(o.type))}"></i></span><div><div class="nm">${esc(o.label)}</div><div class="mm">${esc(o.mod)}</div></div></div>`).join('') : `<div class="dl-pal__empty">Tak ada yang cocok dengan “${esc(q)}”.</div>`;
        psel = 0; phi();
      }
      const phi = () => $$(palList, '.dl-pal__row').forEach((r, i) => r.classList.toggle('is-sel', i === psel));
      palOv.addEventListener('click', e => { if (e.target === palOv) closePalette(); });
      palIn.addEventListener('input', e => prender(e.target.value));
      palIn.addEventListener('keydown', e => {
        if (e.key === 'ArrowDown') { e.preventDefault(); psel = Math.min(psel + 1, pres.length - 1); phi(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); psel = Math.max(psel - 1, 0); phi(); }
        else if (e.key === 'Enter') { if (pres[psel]) { openTab(pres[psel].code); closePalette(); } }
        else if (e.key === 'Escape') closePalette();
      });
      palList.addEventListener('click', e => { const el = e.target.closest('[data-code]'); if (el) { openTab(el.dataset.code); closePalette(); } });
      document.addEventListener('keydown', e => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); palOv.classList.contains('is-open') ? closePalette() : openPalette(); }
        else if (e.key === 'Escape') closePalette();
      });

      // Tombol top bar / sidebar
      $$(root, '[data-dl-act]').forEach(b => b.addEventListener('click', e => {
        const a = b.dataset.dlAct;
        if (a === 'mini') root.classList.toggle('is-mini');
        else if (a === 'nosidebar') root.classList.toggle('is-nosidebar');
        else if (a === 'notopbar') root.classList.add('is-notopbar');
        else if (a === 'showtopbar') root.classList.remove('is-notopbar');
        else if (a === 'palette') openPalette();
        else if (a === 'usermenu') { e.stopPropagation(); userMenu.classList.toggle('is-open'); }
      }));

      // Menu avatar
      const userMenu = $(root, '[data-dl-usermenu]');
      document.addEventListener('click', () => userMenu.classList.remove('is-open'));
      userMenu.addEventListener('click', e => e.stopPropagation());
      let chatCount = 2, notifCount = 3;
      function refreshCounts() {
        const c = $(root, '[data-dl-count="chat"]'), n = $(root, '[data-dl-count="notif"]');
        c.textContent = chatCount; c.style.display = chatCount ? '' : 'none';
        n.textContent = notifCount; n.style.display = notifCount ? '' : 'none';
        $(root, '[data-dl-avdot]').classList.toggle('is-hide', !(chatCount || notifCount));
      }
      refreshCounts();
      userMenu.addEventListener('click', e => {
        const fl = e.target.closest('[data-dl-lang]');
        if (fl) { $$(userMenu, '.dl-um-flag').forEach(x => x.classList.remove('is-active')); fl.classList.add('is-active'); toast('Bahasa: ' + (fl.dataset.dlLang === 'id' ? 'Indonesia' : 'English')); return; }
        const b = e.target.closest('[data-dl-um]'); if (!b) return;
        const a = b.dataset.dlUm;
        if (a === 'devmode') { setDevMode(!document.body.classList.contains('dl-dev')); return; }
        userMenu.classList.remove('is-open');
        if (a === 'chat') openDrawer('chat');
        else if (a === 'notif') openDrawer('notif');
        else if (a === 'ai') openDrawer('ai');
        else if (a === 'edit' || a === 'pass') $(root, `[data-dl-modal="${a}"]`).classList.add('is-on');
        else if (a === 'help') toast('Bantuan & dukungan — segera (tiket ke tim dev / sys_support_tickets).');
      });

      // Modal
      $$(root, '[data-dl-mclose]').forEach(b => b.addEventListener('click', () => b.closest('.dl-mov').classList.remove('is-on')));
      $$(root, '.dl-mov').forEach(o => o.addEventListener('click', e => { if (e.target === o) o.classList.remove('is-on'); }));

      // Drawer
      const backdrop = $(root, '[data-dl-backdrop]');
      function openDrawer(id) {
        $$(root, '.dl-drawer.is-on').forEach(d => d.classList.remove('is-on'));
        $(root, `[data-dl-drawer="${id}"]`).classList.add('is-on'); backdrop.classList.add('is-on');
      }
      const closeDrawers = () => { $$(root, '.dl-drawer.is-on').forEach(d => d.classList.remove('is-on')); backdrop.classList.remove('is-on'); };
      backdrop.addEventListener('click', closeDrawers);
      $$(root, '[data-dl-close]').forEach(b => b.addEventListener('click', closeDrawers));
      $(root, '[data-dl-markread]').addEventListener('click', () => { notifCount = 0; refreshCounts(); $$(root, '.dl-nz__dot').forEach(d => d.classList.add('is-read')); toast('Semua notifikasi ditandai terbaca.'); });

      // Angka chat = total belum dibaca di x-dl::chat (berkurang saat percakapannya dibuka).
      const chatEl = $(root, '[data-dl-chat]');
      if (chatEl?.dlChat) { chatCount = chatEl.dlChat.unread; refreshCounts(); }
      root.addEventListener('dl:chat-unread', e => { chatCount = e.detail.count; refreshCounts(); });

      // Asisten AI (pratinjau: mencocokkan ke daftar menu)
      const aiBody = $(root, '[data-dl-ai-body]'), aiIn = $(root, '[data-dl-ai-input]');
      const addAi = (role, txt) => { const d = document.createElement('div'); d.className = 'dl-ai__msg dl-ai__msg--' + role; d.textContent = txt; aiBody.appendChild(d); aiBody.scrollTop = aiBody.scrollHeight; };
      function askAi(q) {
        q = q.trim(); if (!q) return; addAi('me', q);
        const toks = q.toLowerCase().split(/\s+/).filter(t => t.length > 2);
        const hit = items.find(o => { const h = (o.label + ' ' + o.mod).toLowerCase(); return toks.some(t => h.includes(t)); });
        if (hit) { addAi('bot', 'Baik, saya buka “' + hit.label + '” untuk Anda.'); setTimeout(() => { openTab(hit.code); closeDrawers(); }, 650); }
        else addAi('bot', 'Saya belum menemukan objek yang cocok. Sebut nama komponen, mis. “login”, “grid”, atau “kanban”.');
      }
      $(root, '[data-dl-ai-send]').addEventListener('click', () => { askAi(aiIn.value); aiIn.value = ''; });
      aiIn.addEventListener('keydown', e => { if (e.key === 'Enter') { askAi(aiIn.value); aiIn.value = ''; } });
      aiBody.addEventListener('click', e => { const c = e.target.closest('[data-dl-ai]'); if (c) askAi(c.dataset.dlAi); });

      // Deep link #KODE_MODUL membuka modul itu langsung (juga untuk potret otomatis).
      const hash = decodeURIComponent(location.hash.slice(1));
      selectModule(modules.some(m => m.code === hash) ? hash : 'HOME');
      window.addEventListener('hashchange', () => { const h = decodeURIComponent(location.hash.slice(1)); if (h === 'HOME' || modules.some(m => m.code === h)) selectModule(h); });
    }
  }
  Devlib.define("Shell", Shell);
})(window.Devlib);

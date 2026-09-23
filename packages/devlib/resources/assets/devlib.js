/* DevLib — perilaku komponen. Satu file untuk semua aplikasi pemakai.
 * Tiap komponen diinisialisasi dari elemen akarnya (data-dl="x-dl::…"),
 * tanpa framework, supaya jalan di halaman biasa maupun Livewire. */
(function () {
  'use strict';

  const $ = (root, sel) => root.querySelector(sel);
  const $$ = (root, sel) => Array.from(root.querySelectorAll(sel));
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  function toast(msg) {
    let t = document.querySelector('.dl-toast');
    if (!t) { t = document.createElement('div'); t.className = 'dl-toast'; document.body.appendChild(t); }
    t.textContent = msg; t.classList.add('is-show');
    clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('is-show'), 2200);
  }

  /* ============================ LOGIN ============================ */
  const LOGIN_T = {
    id: {heading:'Masuk',sub:'Gunakan akun terdaftar untuk mengakses aplikasi.',l_login:'Username atau email',l_pass:'Password',forgot:'Lupa password?',btn:'Masuk',or:'atau',no_acc:'Belum punya akun?',reg:'Daftar',err_empty:'Isi username/email dan password.',err_wrong:'Username atau password salah.'},
    en: {heading:'Sign in',sub:'Use your registered account to access the app.',l_login:'Username or email',l_pass:'Password',forgot:'Forgot password?',btn:'Sign in',or:'or',no_acc:"Don't have an account?",reg:'Register',err_empty:'Enter your username/email and password.',err_wrong:'Incorrect username or password.'}
  };

  function initLogin(root) {
    let lang = new URLSearchParams(location.search).get('lang') || 'id';
    const alertBox = $(root, '[data-dl-alert]');
    const timeEl = $(root, '[data-dl-clock-time]'), dateEl = $(root, '[data-dl-clock-date]');

    function tick() {
      if (!timeEl) return;
      const now = new Date(), loc = lang === 'id' ? 'id-ID' : 'en-US';
      timeEl.textContent = now.toLocaleTimeString(loc, {hour12: false});
      dateEl.textContent = now.toLocaleDateString(loc, {weekday:'long', day:'numeric', month:'long', year:'numeric'});
    }
    function showError(code) {
      if (!code) { alertBox.classList.remove('is-show'); return; }
      alertBox.textContent = LOGIN_T[lang][code === 'empty' ? 'err_empty' : 'err_wrong'];
      alertBox.classList.add('is-show');
    }
    function applyLang() {
      $$(root, '[data-i18n]').forEach(el => { const k = el.getAttribute('data-i18n'); if (LOGIN_T[lang][k]) el.textContent = LOGIN_T[lang][k]; });
      $$(root, '.dl-flag').forEach(f => f.classList.toggle('is-active', f.dataset.lang === lang));
      document.documentElement.lang = lang;
      if (alertBox.classList.contains('is-show')) showError(alertBox.dataset.code);
      tick();
    }
    $$(root, '.dl-flag').forEach(f => f.addEventListener('click', () => { lang = f.dataset.lang; applyLang(); }));
    setInterval(tick, 1000);

    const img = $(root, '.dl-login__img');
    if (img) {
      const heroes = JSON.parse(root.dataset.heroes || '[]');
      img.onload = () => img.classList.add('is-on');
      if (heroes.length) img.src = heroes[Math.floor(Math.random() * heroes.length)];
      const quotes = JSON.parse(root.dataset.quotes || '[]');
      if (quotes.length) { const q = quotes[Math.floor(Math.random() * quotes.length)]; $(root, '[data-dl-quote]').textContent = q.t; $(root, '[data-dl-author]').textContent = q.a; }
    }

    // Grip: lebarkan panel 15%–40% layar (arah tarik ikut posisi panel).
    const grip = $(root, '.dl-login__grip'), panel = $(root, '.dl-login__panel');
    if (grip) {
      const mirrored = root.classList.contains('dl-login--mirror');
      let dragging = false;
      grip.addEventListener('mousedown', e => { dragging = true; root.classList.add('is-resizing'); e.preventDefault(); });
      window.addEventListener('mousemove', e => {
        if (!dragging) return;
        const min = Math.max(window.innerWidth * 0.15, 240), max = window.innerWidth * 0.40;
        let w = mirrored ? e.clientX : window.innerWidth - e.clientX;
        panel.style.width = Math.max(min, Math.min(max, w)) + 'px';
      });
      window.addEventListener('mouseup', () => { dragging = false; root.classList.remove('is-resizing'); });
    }

    const pass = $(root, '#dl-pass'), user = $(root, '#dl-user');
    $(root, '[data-dl-eye]').addEventListener('click', function () {
      const show = pass.type === 'password'; pass.type = show ? 'text' : 'password';
      this.innerHTML = `<i class="fa-solid fa-eye${show ? '-slash' : ''}"></i>`;
    });

    root.querySelector('form').addEventListener('submit', e => {
      const u = user.value.trim(), p = pass.value.trim();
      $(root, '[data-dl-wrap-user]').classList.toggle('is-invalid', !u);
      $(root, '[data-dl-wrap-pass]').classList.toggle('is-invalid', !p);
      if (!u || !p) { e.preventDefault(); alertBox.dataset.code = 'empty'; showError('empty'); }
    });

    if (root.dataset.error) { alertBox.dataset.code = root.dataset.error; }
    applyLang();
    if (root.dataset.error) showError(root.dataset.error);
  }

  /* ============================ SHELL ============================ */
  const typeIcon = t => t === 'grid' ? 'fa-solid fa-table-list' : t === 'report' ? 'fa-solid fa-chart-column' : 'fa-solid fa-pen-to-square';
  const HOME = {code: 'HOME', label: 'Home', icon: 'fa-solid fa-house'};

  function initShell(root) {
    const modules = JSON.parse(root.dataset.dlModules || '[]');
    const items = [];
    modules.forEach(m => (m.children || []).forEach(c => (c.items ? c.items : [c]).forEach(it => items.push({...it, mod: m.label}))));
    const findItem = code => items.find(x => x.code === code);

    const mega = $(root, '[data-dl-mega]'), modTitle = $(root, '[data-dl-mod-title]'), sideNav = $(root, '[data-dl-side-nav]');
    const tabbar = $(root, '[data-dl-tabbar]'), content = $(root, '[data-dl-content]'), homePane = $(root, '[data-dl-home]');
    let activeTab = null;
    const openTabs = new Map();

    mega.innerHTML = [HOME, ...modules].map(m => `<button type="button" class="dl-mega__item" data-mod="${esc(m.code)}"><span class="mi"><i class="${esc(m.icon || '')}"></i></span>${esc(m.label)}</button>`).join('');
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
      if (a === 'chat') { openDrawer('chat'); chatCount = 0; refreshCounts(); }
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

    // Chat (data contoh)
    const convos = [
      {name:'Rina Wijaya', sub:'Sales', msgs:[{me:false,t:'Pak, PO dari PT Sarana sudah masuk?'},{me:true,t:'Sudah, saya cek dulu ya.'}]},
      {name:'Grup Gudang', sub:'3 anggota', msgs:[{me:false,t:'Stok Bahan A tinggal 200 kg.'},{me:false,t:'Perlu PR baru?'}]},
      {name:'Andi (Finance)', sub:'Finance', msgs:[{me:false,t:'Invoice #2600393 sudah dibayar.'},{me:true,t:'Noted, terima kasih.'}]}
    ];
    let curC = 0;
    const chatList = $(root, '[data-dl-chat-list]'), msgs = $(root, '[data-dl-chat-msgs]'), chatIn = $(root, '[data-dl-chat-input]');
    const renderChatList = () => chatList.innerHTML = convos.map((c, i) => `<div class="dl-cl ${i === curC ? 'is-active' : ''}" data-c="${i}"><b>${esc(c.name)}</b><span>${esc(c.sub)}</span></div>`).join('');
    const renderMsgs = () => { msgs.innerHTML = convos[curC].msgs.map(m => `<div class="dl-msg ${m.me ? 'dl-msg--me' : 'dl-msg--them'}">${esc(m.t)}</div>`).join(''); msgs.scrollTop = msgs.scrollHeight; };
    chatList.addEventListener('click', e => { const el = e.target.closest('[data-c]'); if (el) { curC = +el.dataset.c; renderChatList(); renderMsgs(); } });
    const sendChat = () => { if (!chatIn.value.trim()) return; convos[curC].msgs.push({me:true, t:chatIn.value.trim()}); chatIn.value = ''; renderMsgs(); };
    $(root, '[data-dl-chat-send]').addEventListener('click', sendChat);
    chatIn.addEventListener('keydown', e => { if (e.key === 'Enter') sendChat(); });
    renderChatList(); renderMsgs();

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

  /* ======================= MODE DEVELOPER ======================= */
  // Tooltip asal-usul tiap objek library (data-dl*). Elemen interaktif yang
  // TIDAK berada di dalam komponen library diberi garis merah (.dl-nolib).
  let tip, badge;
  function setDevMode(on) {
    try { localStorage.setItem('dl-dev', on ? '1' : '0'); } catch (e) {}
    document.body.classList.toggle('dl-dev', on);
    document.querySelectorAll('[data-dl-devswitch]').forEach(s => s.classList.toggle('is-on', on));
    $$(document, 'button,a,input,select,textarea,table').forEach(el => el.classList.toggle('dl-nolib', on && !el.closest('[data-dl]')));
    if (on) {
      const all = $$(document, 'button,a,input,select,textarea,table');
      const lib = all.filter(el => el.closest('[data-dl]')).length;
      badge.textContent = `Mode Developer · ${all.length ? Math.round(lib * 100 / all.length) : 100}% objek dari library`;
    }
    if (!on) tip.style.display = 'none';
    // Frame tab ikut diberi tahu supaya isinya juga menampilkan inspektur.
    document.querySelectorAll('iframe').forEach(f => { try { f.contentWindow.postMessage({dlDev: on}, '*'); } catch (e) {} });
  }
  function initDevMode() {
    tip = document.createElement('div'); tip.className = 'dl-dev-tip'; document.body.appendChild(tip);
    badge = document.createElement('div'); badge.className = 'dl-dev-badge'; document.body.appendChild(badge);
    document.addEventListener('mousemove', e => {
      if (!document.body.classList.contains('dl-dev')) return;
      const el = e.target.closest && e.target.closest('[data-dl]');
      if (!el) {
        if (e.target.classList && e.target.classList.contains('dl-nolib')) {
          tip.innerHTML = '<b>BUKAN dari library</b><br><span class="k">elemen:</span> &lt;' + esc(e.target.tagName.toLowerCase()) + '&gt;';
        } else { tip.style.display = 'none'; return; }
      } else {
        tip.innerHTML = `<b>${esc(el.dataset.dl)}</b><br><span class="k">turunan:</span> ${esc(el.dataset.dlClass || '—')}<br><span class="k">sumber data:</span> ${esc(el.dataset.dlSrc || '—')}`;
      }
      tip.style.display = 'block';
      const x = Math.min(e.clientX + 14, window.innerWidth - tip.offsetWidth - 8), y = Math.min(e.clientY + 16, window.innerHeight - tip.offsetHeight - 8);
      tip.style.left = x + 'px'; tip.style.top = y + 'px';
    });
    window.addEventListener('message', e => { if (e.data && typeof e.data.dlDev === 'boolean') setDevMode(e.data.dlDev); });
    let saved = false; try { saved = localStorage.getItem('dl-dev') === '1'; } catch (e) {}
    const allowed = !!document.querySelector('[data-dl-developer="1"]') || window.self !== window.top;
    setDevMode(saved && allowed);
  }

  window.Devlib = {toast, setDevMode};
  document.addEventListener('DOMContentLoaded', () => {
    initDevMode();
    document.querySelectorAll('.dl-login').forEach(initLogin);
    document.querySelectorAll('.dl-shell').forEach(initShell);
  });
})();

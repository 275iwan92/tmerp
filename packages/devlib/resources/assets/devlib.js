/* DevLib — perilaku komponen. Satu file untuk semua aplikasi pemakai.
 * Tiap komponen diinisialisasi dari elemen akarnya (data-dl="x-dl::…"),
 * tanpa framework, supaya jalan di halaman biasa maupun Livewire. */
(function () {
  'use strict';

  const $ = (root, sel) => root.querySelector(sel);
  const $$ = (root, sel) => Array.from(root.querySelectorAll(sel));
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  /* ========================= PRIMITIVE ========================= */
  let currentLang = new URLSearchParams(location.search).get('lang') || window.DL_LANG_ACTIVE || 'id';

  function initLangs(root) {
    $$(root, '.dl-flag').forEach(f => f.addEventListener('click', () => {
      currentLang = f.dataset.lang;
      document.querySelectorAll('[data-dl-langs] .dl-flag').forEach(x => x.classList.toggle('is-active', x.dataset.lang === currentLang));
      document.documentElement.lang = currentLang;
      document.dispatchEvent(new CustomEvent('dl:lang', {detail: currentLang}));
    }));
    $$(root, '.dl-flag').forEach(x => x.classList.toggle('is-active', x.dataset.lang === currentLang));
  }

  function initSplit(root) {
    const val = $(root, '[data-dl-split-value]');
    $(root, '[data-dl-split-toggle]').addEventListener('click', e => { e.stopPropagation(); root.classList.toggle('is-open'); });
    $$(root, '.dl-split__item').forEach(it => it.addEventListener('click', e => {
      e.stopPropagation();
      val.value = it.dataset.action;
      $(root, '[data-dl-split-label]').textContent = it.dataset.action;
      $(root, '[data-dl-split-icon]').textContent = it.dataset.icon;
      root.classList.remove('is-open');
    }));
  }

  function initDevice(root) {
    const view = $(root, '[data-dl-device-view]');
    $$(root, '.dl-device__btn').forEach(b => b.addEventListener('click', () => {
      $$(root, '.dl-device__btn').forEach(x => x.classList.toggle('is-active', x === b));
      view.className = 'dl-device__view is-' + b.dataset.mode;
    }));
  }

  // Tombol tambahan form (mockup: "Generate QR Code Data") -> QR berisi semua isian form.
  function initForm(form) {
    const fs = $(form, '[data-f-fs]'), viewMode = form.dataset.mode === 'view';
    let snapshot = null;
    const setView = on => { form.classList.toggle('is-view', on); if (fs) fs.disabled = on; const ed = $(form, '[data-f-edit]'); if (ed) ed.hidden = !on; };
    if (viewMode) setView(true);
    // Isian wajib + baris detail; error di tab tersembunyi -> tab dibuka & diberi tanda jumlah error.
    const validate = () => {
      $$(form, '.dl-tabs__err').forEach(x => x.remove());
      const bad = $$(form, '.dl-field').filter(f => !f.closest('.dl-lines') && $(f, '.dl-field__req') && !hasValue(f));
      bad.forEach(f => { f.classList.add('is-invalid'); const er = $(f, '[data-dl-field-error]'); if (er) er.textContent = 'Wajib diisi'; });
      const lineBad = $$(form, '[data-dl-lines]').reduce((n, l) => n + (l.dlLines ? l.dlLines.validate() : 0), 0);
      const panels = new Map();
      [...bad, ...$$(form, '.dl-lines td.is-invalid')].forEach(x => { const p = x.closest('.dl-tabs__panel'); if (p) panels.set(p, (panels.get(p) || 0) + 1); });
      panels.forEach((n, p) => { const tab = $(p.closest('[data-dl-tabs]'), `.dl-tabs__tab[data-tab="${p.dataset.panel}"]`); if (tab) tab.insertAdjacentHTML('beforeend', `<span class="dl-tabs__err">${n}</span>`); });
      const first = bad[0] || $(form, '.dl-lines td.is-invalid');
      if (first) { const p = first.closest('.dl-tabs__panel'); if (p && !p.classList.contains('is-active')) $(p.closest('[data-dl-tabs]'), `.dl-tabs__tab[data-tab="${p.dataset.panel}"]`)?.click(); setTimeout(() => { first.scrollIntoView({block: 'center'}); $(first, 'input:not([type=hidden]),textarea,button')?.focus(); }, 30); }
      return bad.length + lineBad;
    };
    form.addEventListener('input', e => { const f = e.target.closest('.dl-field.is-invalid'); if (f && hasValue(f)) { f.classList.remove('is-invalid'); const x = $(f, '[data-dl-field-error]'); if (x) x.textContent = ''; } }, true);
    form.addEventListener('submit', e => {
      const n = validate();
      if (n) { e.preventDefault(); dirtyForms.add(form); form.classList.add('is-dirty'); toast(`${n} isian wajib belum diisi.`, 'danger'); return; }
      if (form.getAttribute('action')) return;
      e.preventDefault();
      const data = {}; new FormData(form).forEach((v, k) => { if (k !== '_token') data[k] = v; });
      toast('Aksi: ' + (form.querySelector('[data-dl-split-value]')?.value || 'Simpan') + ' (pratinjau, tidak dikirim)', 'success');
      form.dispatchEvent(new CustomEvent('dl:form-submit', {bubbles: true, detail: data}));
      if (viewMode) { snapshot = null; setView(true); }
      const layer = form.closest('[data-dl-modal-id],[data-dl-drawer-id]'); if (layer) closeLayer(layer);
    });
    form.addEventListener('click', e => {
      if (e.target.closest('[data-f-edit]')) { if (fs) fs.disabled = false; snapshot = {}; new FormData(form).forEach((v, k) => { snapshot[k] = v; }); $$(form, 'input[type=checkbox][name]').forEach(c => { snapshot[c.name] = c.checked; }); setView(false); $(form, '.dl-form__body input:not([type=hidden]),.dl-form__body textarea')?.focus(); return; }
      if (e.target.closest('[data-f-cancel]') && snapshot) { fillForm(form, snapshot, false); snapshot = null; setView(true); toast('Perubahan dibatalkan.'); }
    });
    const extra = form.querySelector('[data-dl-form-extra]');
    if (!extra) return;
    extra.addEventListener('click', () => {
      const data = {};
      new FormData(form).forEach((v, k) => {
        if (k === '_token' || k === '_action' || v instanceof File) return;
        if (/password/i.test(k)) v = v ? '********' : '-';
        k = k.replace(/\[\]$/, '');
        data[k] = k in data ? [].concat(data[k], v) : v;
      });
      showQr(JSON.stringify(data, null, 2));
    });
  }

  function showQr(text) {
    let ov = document.querySelector('.dl-qr');
    if (!ov) {
      ov = document.createElement('div'); ov.className = 'dl-mov dl-qr';
      ov.innerHTML = '<div class="dl-modal" style="max-width:300px"><div class="dl-modal__head"><h3>Form QR Code Data</h3><button type="button" class="dl-modal__x" data-x>&times;</button></div><div class="dl-modal__body" style="display:flex;justify-content:center"><div data-qr></div></div><div class="dl-modal__foot"><button type="button" class="dl-mbtn dl-mbtn--c" data-x>Tutup</button><button type="button" class="dl-mbtn dl-mbtn--p" data-dl-qr-dl>Download PNG</button></div></div>';
      document.body.appendChild(ov);
      ov.addEventListener('click', e => { if (e.target === ov || e.target.closest('[data-x]')) ov.classList.remove('is-on'); });
      ov.querySelector('[data-dl-qr-dl]').addEventListener('click', () => { const img = ov.querySelector('[data-qr] img, [data-qr] canvas'); if (!img) return; const a = document.createElement('a'); a.href = img.src || img.toDataURL(); a.download = 'form-qrcode.png'; a.click(); });
    }
    const draw = () => { const box = ov.querySelector('[data-qr]'); box.innerHTML = ''; new window.QRCode(box, {text, width: 180, height: 180, colorDark: '#111827', colorLight: '#ffffff'}); ov.classList.add('is-on'); };
    if (window.QRCode) return draw();
    const s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js'; s.onload = draw; document.head.appendChild(s);
  }

  /* ============================ FIELD ============================ */
  const CASES = ['none', 'capitalize', 'uppercase'], CASE_LABEL = {none: 'aa', capitalize: 'Aa', uppercase: 'AA'};
  const applyCase = (t, m) => m === 'uppercase' ? t.toUpperCase() : m === 'capitalize' ? t.replace(/\b\w/g, c => c.toUpperCase()) : t;
  const idNum = n => Number(n).toLocaleString('id-ID');

  // Panel dropdown/popover milik field: buka/tutup; klik di luar menutup semua.
  function bindPanel(root, onOpen) {
    const toggle = $(root, '[data-dl-dd-toggle]'), panel = $(root, '[data-dl-dd-panel]');
    if (!toggle || !panel) return {};
    toggle.addEventListener('click', e => {
      e.stopPropagation();
      const open = !panel.classList.contains('is-open');
      document.querySelectorAll('[data-dl-dd-panel].is-open').forEach(p => p.classList.remove('is-open'));
      panel.classList.toggle('is-open', open);
      if (open && onOpen) onOpen();
    });
    panel.addEventListener('click', e => e.stopPropagation());
    const search = $(panel, '[data-dl-dd-search]');
    if (search) search.addEventListener('input', () => $$(panel, '.dl-dd__item').forEach(it => { it.style.display = it.textContent.toLowerCase().includes(search.value.toLowerCase()) ? '' : 'none'; }));
    return {panel, search, close: () => panel.classList.remove('is-open')};
  }

  function initField(f) {
    const input = $(f, '.dl-input');

    const caseBtn = $(f, '[data-dl-case]');
    if (caseBtn && input) {
      let idx = 0;
      caseBtn.addEventListener('click', () => { idx = (idx + 1) % 3; caseBtn.textContent = CASE_LABEL[CASES[idx]]; input.value = applyCase(input.value, CASES[idx]); });
      input.addEventListener('input', () => { const p = input.selectionStart; input.value = applyCase(input.value, CASES[idx]); try { input.setSelectionRange(p, p); } catch (e) {} });
    }
    $$(f, '[data-dl-autogrow]').forEach(t => { const g = () => { t.style.height = 'auto'; t.style.height = t.scrollHeight + 'px'; }; t.addEventListener('input', g); g(); });

    const eye = $(f, '[data-dl-eye]');
    if (eye) eye.addEventListener('click', () => { const show = input.type === 'password'; input.type = show ? 'text' : 'password'; eye.innerHTML = `<i class="fa-solid fa-eye${show ? '-slash' : ''}"></i>`; });

    const sel = $(f, '[data-dl-select]');
    if (sel) {
      const p = bindPanel(sel, () => { if (p.search) { p.search.value = ''; p.search.dispatchEvent(new Event('input')); p.search.focus(); } });
      $$(sel, '.dl-dd__item').forEach(it => it.addEventListener('click', () => { const h = $(sel, '[data-dl-value]'); h.value = it.dataset.value; $(sel, '[data-dl-dd-text]').textContent = it.textContent; p.close(); h.dispatchEvent(new Event('change', {bubbles: true})); }));
    }

    const multi = $(f, '[data-dl-multi]');
    if (multi) {
      bindPanel(multi);
      const upd = () => {
        const v = $$(multi, 'input:checked').map(c => c.dataset.label);
        $(multi, '[data-dl-dd-text]').textContent = !v.length ? multi.dataset.empty : v.length === 1 ? v[0] : `${v.length} ${multi.dataset.noun} (${v.join(', ')})`;
      };
      $$(multi, 'input').forEach(c => c.addEventListener('change', upd)); upd();
    }

    const color = $(f, '[data-dl-color]');
    if (color) color.addEventListener('input', () => { $(f, '[data-dl-color-code]').textContent = color.value.toUpperCase(); });

    const tg = $(f, '[data-dl-toggle]');
    if (tg) { const cb = $(tg, 'input'); cb.addEventListener('change', () => { $(tg, '[data-dl-toggle-label]').textContent = cb.checked ? tg.dataset.on : tg.dataset.off; }); }


    const dr = $(f, '[data-dl-daterange]');
    if (dr) {
      const [a, b] = $$(dr, 'input'), hint = $(f, '[data-dl-duration]');
      const u = () => {
        if (!hint || !a.value || !b.value) return;
        const d1 = new Date(a.value), d2 = new Date(b.value);
        if (d2 < d1) { hint.textContent = 'Durasi: Tanggal akhir tidak valid.'; return; }
        const m = (d2.getFullYear() - d1.getFullYear()) * 12 + (d2.getMonth() - d1.getMonth());
        hint.textContent = `Durasi: ${Math.floor(m / 12)} Tahun ${m % 12} Bulan`;
      };
      a.addEventListener('change', u); b.addEventListener('change', u); u();
    }

    const otp = $(f, '[data-dl-otp]');
    if (otp) {
      const boxes = $$(otp, '.dl-otp__box'), hidden = $(otp, '[data-dl-value]');
      boxes.forEach((bx, i) => bx.addEventListener('keyup', e => {
        bx.value = bx.value.replace(/\D/g, '');
        if (e.key >= '0' && e.key <= '9' && i < boxes.length - 1) boxes[i + 1].focus();
        else if (e.key === 'Backspace' && i > 0) boxes[i - 1].focus();
        hidden.value = boxes.map(x => x.value).join('');
      }));
    }

    const mask = $(f, '[data-dl-mask]');
    if (mask) {
      const pat = mask.dataset.dlMask, digits = (pat.match(/9/g) || []).length, err = $(f, '[data-dl-field-error]');
      mask.addEventListener('input', () => {
        const raw = mask.value.replace(/\D/g, '').slice(0, digits); let out = '', j = 0;
        for (const ch of pat) { if (j >= raw.length) break; out += ch === '9' ? raw[j++] : ch; }
        mask.value = out;
      });
      mask.addEventListener('blur', () => { const n = mask.value.replace(/\D/g, '').length; const bad = n > 0 && n < digits; f.classList.toggle('is-invalid', bad); err.textContent = bad ? mask.dataset.invalid : ''; });
    }

    const pre = $(f, '[data-dl-prefix]');
    if (pre) {
      const p = bindPanel(pre, () => { if (p.search) { p.search.value = ''; p.search.dispatchEvent(new Event('input')); } });
      const money = pre.hasAttribute('data-dl-money'), mi = $(pre, '[data-dl-money-input]'), hint = $(f, '[data-dl-money-hint]');
      const updMoney = () => {
        const raw = mi.value.replace(/\D/g, '');
        mi.value = raw ? BigInt(raw).toLocaleString('id-ID') : '';
        hint.textContent = raw ? `Terbaca: ${$(pre, '[data-dl-prefix-a]').textContent} ${BigInt(raw).toLocaleString('id-ID')} (${$(pre, '[data-dl-prefix-b]').textContent})` : 'Terbaca: -';
        const wt = $(f, '[data-dl-money-words]');
        if (wt) { const C = JSON.parse(wt.dataset.currencies), code = $(pre, '[data-dl-prefix-value]').value; wt.textContent = raw ? words(raw, C[code] || null) : ''; }
      };
      $$(pre, '.dl-dd__item').forEach(it => it.addEventListener('click', () => {
        $(pre, '[data-dl-prefix-value]').value = it.dataset.value;
        $(pre, '[data-dl-prefix-a]').textContent = it.dataset.a; $(pre, '[data-dl-prefix-b]').textContent = it.dataset.b;
        p.close(); if (money) updMoney();
      }));
      if (money) { mi.addEventListener('input', updMoney); updMoney(); }
    }

    const dec = $(f, '[data-dl-decimal]');
    if (dec) {
      const n = +dec.dataset.dlDecimal;
      dec.addEventListener('input', () => { dec.value = dec.value.replace(/[^0-9.,]/g, '').replace(',', '.'); });
      dec.addEventListener('blur', () => { if (dec.value !== '' && !isNaN(parseFloat(dec.value))) dec.value = parseFloat(dec.value).toFixed(n); });
    }

    const num = $(f, '[data-dl-number]');
    if (num) {
      const inp = $(num, 'input'), min = num.dataset.min === '' ? -Infinity : +num.dataset.min, max = num.dataset.max === '' ? Infinity : +num.dataset.max, step = +num.dataset.step || 1;
      const clamp = v => Math.min(max, Math.max(min, v));
      $$(num, '[data-dl-spin]').forEach(b => b.addEventListener('click', () => { inp.value = clamp((parseInt(inp.value, 10) || 0) + step * b.dataset.dlSpin); }));
      inp.addEventListener('input', () => { inp.value = inp.value.replace(/\D/g, ''); });
      inp.addEventListener('blur', () => { inp.value = clamp(parseInt(inp.value, 10) || 0); });
    }

    const dt = $(f, '[data-dl-date]');
    if (dt) {
      const hidden = $(dt, '[data-dl-value]'), text = $(dt, '[data-dl-date-text]'), days = $(dt, '[data-dl-cal-days]'), title = $(dt, '[data-dl-cal-title]');
      const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
      let view = hidden.value ? new Date(hidden.value) : new Date();
      const iso = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
      const setVal = (d, quiet) => { hidden.value = d ? iso(d) : ''; text.value = d ? `${d.getDate()} ${MON[d.getMonth()]} ${d.getFullYear()}` : ''; if (!quiet) hidden.dispatchEvent(new Event('change', {bubbles: true})); };
      const draw = () => {
        title.textContent = `${MON[view.getMonth()]} ${view.getFullYear()}`;
        const first = new Date(view.getFullYear(), view.getMonth(), 1).getDay(), total = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
        let h = '<div></div>'.repeat(first);
        for (let d = 1; d <= total; d++) { const v = iso(new Date(view.getFullYear(), view.getMonth(), d)); h += `<button type="button" data-d="${v}" class="${v === hidden.value ? 'is-sel' : ''}">${d}</button>`; }
        days.innerHTML = h;
      };
      const p = bindPanel(dt, draw);
      days.addEventListener('click', e => { const b = e.target.closest('[data-d]'); if (b) { setVal(new Date(b.dataset.d)); p.close(); } });
      $(dt, '[data-dl-cal-prev]').addEventListener('click', () => { view.setMonth(view.getMonth() - 1); draw(); });
      $(dt, '[data-dl-cal-next]').addEventListener('click', () => { view.setMonth(view.getMonth() + 1); draw(); });
      $(dt, '[data-dl-cal-clear]').addEventListener('click', () => { setVal(null); p.close(); });
      $(dt, '[data-dl-cal-today]').addEventListener('click', () => { setVal(new Date()); p.close(); });
      text.addEventListener('click', () => $(dt, '[data-dl-dd-toggle]').click());
      if (hidden.value) setVal(new Date(hidden.value), true);
    }

    const dtt = $(f, '[data-dl-datetime]');
    if (dtt) {
      const hidden = $(dtt, '[data-dl-value]'), text = $(dtt, '[data-dl-date-text]'), days = $(dtt, '[data-dl-cal-days]'), title = $(dtt, '[data-dl-cal-title]');
      const hh = $(dtt, '[data-dl-time-h]'), mm = $(dtt, '[data-dl-time-m]'), prev = $(dtt, '[data-dl-dt-preview]'), tz = dtt.dataset.tzLabel || 'WIB';
      const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
      const pad = n => String(n).padStart(2, '0');
      let [d0, t0] = (hidden.value || '').split(/[ T]/);
      let sel = d0 ? d0 : null;
      let view = sel ? new Date(sel) : new Date();
      if (t0) { hh.value = t0.slice(0, 2); const m = Math.round(+t0.slice(3, 5) / +mm.options[1].value) * +mm.options[1].value; mm.value = pad(m % 60); }
      const iso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
      const label = (ds, h, m) => { const d = new Date(ds); return `${d.getDate()} ${MON[d.getMonth()]} ${d.getFullYear()}, ${h}:${m} ${tz}`; };
      const preview = () => { prev.textContent = 'Selected: ' + (sel ? label(sel, hh.value, mm.value) : '-'); };
      const draw = () => {
        title.textContent = `${MON[view.getMonth()]} ${view.getFullYear()}`;
        const first = new Date(view.getFullYear(), view.getMonth(), 1).getDay(), total = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
        let h = '<div></div>'.repeat(first);
        for (let d = 1; d <= total; d++) { const v = iso(new Date(view.getFullYear(), view.getMonth(), d)); h += `<button type="button" data-d="${v}" class="${v === sel ? 'is-sel' : ''}">${d}</button>`; }
        days.innerHTML = h; preview();
      };
      const p = bindPanel(dtt, draw);
      days.addEventListener('click', e => { const b = e.target.closest('[data-d]'); if (b) { sel = b.dataset.d; draw(); } });
      [hh, mm].forEach(s => s.addEventListener('change', preview));
      $(dtt, '[data-dl-cal-prev]').addEventListener('click', () => { view.setMonth(view.getMonth() - 1); draw(); });
      $(dtt, '[data-dl-cal-next]').addEventListener('click', () => { view.setMonth(view.getMonth() + 1); draw(); });
      $(dtt, '[data-dl-cal-clear]').addEventListener('click', () => { sel = null; hidden.value = ''; text.value = ''; p.close(); });
      $(dtt, '[data-dl-dt-apply]').addEventListener('click', () => {
        if (!sel) { sel = iso(new Date()); }
        hidden.value = `${sel} ${hh.value}:${mm.value}`; text.value = label(sel, hh.value, mm.value); p.close();
        hidden.dispatchEvent(new Event('change', {bubbles: true}));
      });
      text.addEventListener('click', () => $(dtt, '[data-dl-dd-toggle]').click());
      if (sel) text.value = label(sel, hh.value, mm.value);
    }

    const tm = $(f, '[data-dl-time]');
    if (tm) {
      const text = $(tm, '[data-dl-time-text]'), h = $(tm, '[data-dl-time-h]'), m = $(tm, '[data-dl-time-m]');
      const p = bindPanel(tm, () => { const [hh, mm] = text.value.split(':'); if (hh) h.value = hh.padStart(2, '0'); if (mm) m.value = String(Math.round(+mm / 5) * 5 % 60).padStart(2, '0'); });
      $(tm, '[data-dl-time-apply]').addEventListener('click', () => { text.value = `${h.value}:${m.value}`; p.close(); });
      $(tm, '[data-dl-time-clear]').addEventListener('click', () => { text.value = ''; p.close(); });
      text.addEventListener('input', () => { const r = text.value.replace(/\D/g, '').slice(0, 4); text.value = r.length > 2 ? r.slice(0, 2) + ':' + r.slice(2) : r; });
    }
  }

  // Urutan pilihan dari daftar: random, atau sequence (lanjut tiap halaman dibuka, diingat per kunci).
  function pickIndex(len, order, key) {
    if (!len) return -1;
    if (order !== 'sequence') return Math.floor(Math.random() * len);
    let i = -1; try { i = parseInt(localStorage.getItem('dl-seq-' + key) ?? '-1', 10); } catch (e) {}
    i = (i + 1) % len;
    try { localStorage.setItem('dl-seq-' + key, i); } catch (e) {}
    return i;
  }

  function initClock(root) {
    const t = $(root, '[data-dl-clock-time]'), d = $(root, '[data-dl-clock-date]'), timeZone = root.dataset.tz || undefined;
    const tick = () => {
      const now = new Date(), loc = currentLang === 'id' ? 'id-ID' : 'en-US';
      if (t) t.textContent = now.toLocaleTimeString(loc, {hour12: false, timeZone});
      d.textContent = now.toLocaleDateString(loc, {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone});
    };
    tick(); setInterval(tick, 1000); document.addEventListener('dl:lang', tick);
  }

  function initGreeting(root) {
    const G = {id: ['Selamat pagi', 'Selamat siang', 'Selamat sore', 'Selamat malam'], en: ['Good morning', 'Good afternoon', 'Good evening', 'Good night']};
    const upd = () => {
      const h = +new Date().toLocaleString('en-US', {hour: 'numeric', hour12: false, timeZone: root.dataset.tz || undefined}) % 24;
      const i = h < 11 ? 0 : h < 15 ? 1 : h < 18 ? 2 : 3;
      root.textContent = G[currentLang][i] + (root.dataset.name ? ', ' + root.dataset.name : '') + ' 👋';
    };
    upd(); setInterval(upd, 60000); document.addEventListener('dl:lang', upd);
  }

  function initQuote(root) {
    const list = JSON.parse(root.dataset.quotes || '[]');
    if (!list.length) { root.style.display = 'none'; return; }
    let i = pickIndex(list.length, root.dataset.order, 'quote');
    const show = () => { $(root, '[data-dl-quote-text]').textContent = list[i].t; const a = $(root, '[data-dl-quote-author]'); a.textContent = list[i].a; a.style.display = list[i].a ? '' : 'none'; };
    show();
    const sec = +root.dataset.interval;
    if (sec > 0) setInterval(() => { i = (i + 1) % list.length; show(); }, sec * 1000);
  }

  function initAnnounce(root) {
    const lines = JSON.parse(root.dataset.lines || '[]'), txt = $(root, '[data-dl-announce-text]');
    let i = 0;
    if (lines.length > 1) setInterval(() => { i = (i + 1) % lines.length; txt.textContent = lines[i]; }, (+root.dataset.interval || 6) * 1000);
  }

  // Kode cuaca WMO (Open-Meteo) -> ikon + keterangan.
  const WMO = [[0, '☀️', 'Cerah', 'Clear'], [2, '⛅', 'Berawan sebagian', 'Partly cloudy'], [3, '☁️', 'Berawan', 'Cloudy'], [48, '🌫️', 'Berkabut', 'Fog'],
    [57, '🌦️', 'Gerimis', 'Drizzle'], [67, '🌧️', 'Hujan', 'Rain'], [77, '🌨️', 'Salju', 'Snow'], [82, '🌧️', 'Hujan deras', 'Showers'], [99, '⛈️', 'Badai petir', 'Thunderstorm']];
  function initWeather(root) {
    const url = `https://api.open-meteo.com/v1/forecast?latitude=${root.dataset.lat}&longitude=${root.dataset.lon}&current=temperature_2m,weather_code&timezone=auto`;
    let w = null;
    const paint = () => {
      if (!w) return;
      const m = WMO.find(x => w.code <= x[0]) || WMO[WMO.length - 1];
      $(root, '[data-dl-weather-icon]').textContent = m[1];
      $(root, '[data-dl-weather-temp]').textContent = Math.round(w.temp) + '°C';
      $(root, '[data-dl-weather-desc]').textContent = currentLang === 'id' ? m[2] : m[3];
    };
    fetch(url).then(r => r.json()).then(j => { w = {temp: j.current.temperature_2m, code: j.current.weather_code}; paint(); })
      .catch(() => { $(root, '[data-dl-weather-desc]').textContent = currentLang === 'id' ? 'cuaca tak tersedia' : 'weather unavailable'; });
    document.addEventListener('dl:lang', paint);
  }

  function initFrame(root) {
    const imgs = JSON.parse(root.dataset.images || '[]'), layers = $$(root, '.dl-frame__bg img');
    const portrait = () => window.matchMedia('(orientation: portrait)').matches;
    let i = pickIndex(imgs.length, root.dataset.order, 'bg-' + root.dataset.key), front = 0;
    const show = () => {
      if (i < 0) return;
      const src = portrait() && imgs[i].portrait ? imgs[i].portrait : imgs[i].src, next = layers[1 - front];
      next.onload = () => { next.classList.add('is-on'); layers[front].classList.remove('is-on'); front = 1 - front; };
      next.src = src;
    };
    show();
    const sec = +root.dataset.interval;
    if (sec > 0 && imgs.length > 1) setInterval(() => { i = root.dataset.order === 'random' ? Math.floor(Math.random() * imgs.length) : (i + 1) % imgs.length; show(); }, sec * 1000);
    window.matchMedia('(orientation: portrait)').addEventListener('change', show);

    // Grip: lebarkan box sisi kiri/kanan (15%–40% layar).
    const grip = $(root, '[data-dl-frame-grip]');
    if (grip) {
      const left = root.classList.contains('dl-frame--box-left');
      let drag = false;
      grip.addEventListener('mousedown', e => { drag = true; root.classList.add('is-resizing'); e.preventDefault(); });
      window.addEventListener('mousemove', e => {
        if (!drag) return;
        const min = Math.max(window.innerWidth * 0.15, 260), max = window.innerWidth * 0.40;
        root.style.setProperty('--dl-box-w', Math.max(min, Math.min(max, left ? e.clientX : window.innerWidth - e.clientX)) + 'px');
      });
      window.addEventListener('mouseup', () => { drag = false; root.classList.remove('is-resizing'); });
    }
  }

  function initUat(root) {
    $(root, '[data-dl-uat-toggle]').addEventListener('click', e => { e.stopPropagation(); root.classList.toggle('is-open'); });
    root.addEventListener('click', e => e.stopPropagation());
    document.addEventListener('click', () => root.classList.remove('is-open'));
  }

  /* ============================ LOGIN BOX ============================ */
  // Isinya komponen; di sini tinggal teks 2 bahasa dan validasi kosong.
  const LOGIN_T = {
    id: {heading:'Masuk',sub:'Gunakan akun terdaftar untuk mengakses aplikasi.',l_login:'Username atau email',l_email:'Email',l_pass:'Password',forgot:'Lupa password?',btn:'Masuk',or:'atau',no_acc:'Belum punya akun?',reg:'Daftar',err_empty:'Isi username/email dan password.',err_wrong:'Username atau password salah.'},
    en: {heading:'Sign in',sub:'Use your registered account to access the app.',l_login:'Username or email',l_email:'Email',l_pass:'Password',forgot:'Forgot password?',btn:'Sign in',or:'or',no_acc:"Don't have an account?",reg:'Register',err_empty:'Enter your username/email and password.',err_wrong:'Incorrect username or password.'}
  };

  function initLoginBox(root) {
    const alertBox = $(root, '[data-dl-alert]');
    let errCode = root.dataset.error || '';
    const showError = () => { if (!alertBox) return; alertBox.textContent = errCode ? LOGIN_T[currentLang][errCode === 'empty' ? 'err_empty' : 'err_wrong'] : ''; alertBox.classList.toggle('is-show', !!errCode); };
    const applyLang = () => { $$(root, '[data-i18n]').forEach(el => { const k = el.dataset.i18n; if (LOGIN_T[currentLang][k]) el.textContent = LOGIN_T[currentLang][k]; }); showError(); };
    document.addEventListener('dl:lang', applyLang);
    root.querySelector('form').addEventListener('submit', e => {
      let empty = false;
      $$(root, '.dl-field--lg').forEach(fd => { const v = $(fd, 'input').value.trim(); fd.classList.toggle('is-invalid', !v); if (!v) empty = true; });
      if (empty) { e.preventDefault(); errCode = 'empty'; showError(); }
    });
    applyLang();
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
    const allowed = !!document.querySelector('[data-dl-developer="1"],[data-dl-devtoggle]') || window.self !== window.top;
    setDevMode(document.body.dataset.dlDev === '1' || (saved && allowed));
  }

  function initCode(root) {
    const modal = root.querySelector('[data-dl-code-modal]');
    root.querySelector('[data-dl-code-open]').addEventListener('click', () => modal.classList.add('is-on'));
    root.querySelector('[data-dl-code-close]').addEventListener('click', () => modal.classList.remove('is-on'));
    modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('is-on'); });
    if (location.hash === '#kode') modal.classList.add('is-on');
    root.querySelectorAll('[data-tab]').forEach(t => t.addEventListener('click', () => {
      root.querySelectorAll('[data-tab]').forEach(x => x.classList.toggle('is-active', x === t));
      root.querySelectorAll('[data-pane]').forEach(p => p.classList.toggle('is-active', p.dataset.pane === t.dataset.tab));
    }));
  }

  /* ======================= SMALL COMPONENTS ======================= */
  // Lookup: cari di semua kolom, pilih baris -> nilai tersimpan + field lain terisi otomatis.
  function initLookup(root) {
    const rows = JSON.parse(root.dataset.rows || '[]'), fill = JSON.parse(root.dataset.fill || '{}');
    const p = bindPanel(root, () => { if (p.search) { p.search.value = ''; p.search.dispatchEvent(new Event('input')); p.search.focus(); } });
    $$(root, 'tr.dl-dd__item').forEach(tr => tr.addEventListener('click', () => {
      const r = rows[+tr.dataset.i];
      $(root, '[data-dl-value]').value = r[root.dataset.key];
      $(root, '[data-dl-dd-text]').textContent = r[root.dataset.label];
      p.close();
      $(root, '[data-dl-value]').dispatchEvent(new Event('change', {bubbles: true}));
      const scope = root.closest('form') || document;
      Object.entries(fill).forEach(([field, col]) => {
        const box = scope.querySelector(`[data-dl-field="${field}"]`);
        const input = box ? (box.querySelector('[data-dl-value]') || box.querySelector('input,textarea,select')) : scope.querySelector(`[name="${field}"]`);
        if (!input) return;
        input.value = r[col] ?? '';
        input.dispatchEvent(new Event('input', {bubbles: true})); input.dispatchEvent(new Event('change', {bubbles: true}));
        const txt = box && box.querySelector('[data-dl-dd-text]');
        if (txt) txt.textContent = r[col] ?? '';
        if (box) { box.classList.remove('is-filled'); void box.offsetWidth; box.classList.add('is-filled'); }
      });
    }));
    if (root.hasAttribute('data-create')) {
      const btn = $(root, '[data-dl-lookup-create]'), label = root.dataset.label;
      p.search.addEventListener('input', () => {
        const q = p.search.value.trim();
        btn.hidden = !q || rows.some(r => String(r[label]).toLowerCase() === q.toLowerCase());
        $(btn, '[data-dl-lookup-new]').textContent = q;
      });
      // Tambah baru dari dalam pilihan: baris baru langsung terpilih; aplikasi menyimpannya ke master lewat event dl:lookup-create.
      btn.addEventListener('click', () => {
        const q = p.search.value.trim(), r = {[root.dataset.key]: 'new:' + q, [label]: q};
        rows.push(r);
        const tr = document.createElement('tr'); tr.className = 'dl-dd__item'; tr.dataset.i = rows.length - 1;
        tr.innerHTML = $$(root, 'thead th').map((th, i) => `<td>${i === 0 ? '(baru)' : ''}</td>`).join('');
        tr.children[Math.min(1, tr.children.length - 1)].textContent = q;
        $(root, '[data-dl-dd-list]').appendChild(tr);
        $(root, '[data-dl-value]').value = r[root.dataset.key]; $(root, '[data-dl-dd-text]').textContent = q; btn.hidden = true; p.close();
        root.dispatchEvent(new CustomEvent('dl:lookup-create', {bubbles: true, detail: {text: q}}));
        toast('Ditambahkan: ' + q);
      });
    }
  }

  function initTabs(root) {
    $$(root, ':scope > .dl-tabs__bar .dl-tabs__tab').forEach(t => t.addEventListener('click', () => {
      $$(root, ':scope > .dl-tabs__bar .dl-tabs__tab').forEach(x => x.classList.toggle('is-active', x === t));
      $$(root, ':scope > .dl-tabs__panel').forEach(p => p.classList.toggle('is-active', p.dataset.panel === t.dataset.tab));
    }));
  }

  // Kotak statistik = filter: klik menyalakan, klik lagi melepas (satu aktif per kelompok).
  function initStat(btn) {
    btn.addEventListener('click', () => {
      const on = !btn.classList.contains('is-active');
      (btn.parentElement ? $$(btn.parentElement, '[data-dl-stat]') : [btn]).forEach(b => b.classList.remove('is-active'));
      btn.classList.toggle('is-active', on);
      btn.dispatchEvent(new CustomEvent('dl:stat', {bubbles: true, detail: {active: on}}));
    });
  }

  /* ================= SMALL COMPONENTS (lanjutan) ================= */
  const PRESET_T = {save: ['Simpan', 'Save'], cancel: ['Batal', 'Cancel'], close: ['Tutup', 'Close'], delete: ['Hapus', 'Delete'], apply: ['Terapkan', 'Apply'],
    print: ['Cetak', 'Print'], submit: ['Ajukan', 'Submit'], approve: ['Setujui', 'Approve'], reject: ['Tolak', 'Reject'], hold: ['Tahan', 'Hold']};
  // Label tombol preset ikut bahasa aktif (Back & Confirm & + New Record tetap).
  document.addEventListener('dl:lang', () => $$(document, '[data-dl-preset]').forEach(b => {
    const t = PRESET_T[b.dataset.dlPreset]; if (!t) return;
    const node = [...b.childNodes].reverse().find(n => n.nodeType === 3 && n.textContent.trim());
    if (node) node.textContent = t[currentLang === 'en' ? 1 : 0];
  }));

  function initSegment(root) {
    const hidden = $(root, '[data-dl-value]');
    $$(root, '.dl-segment__opt').forEach(b => b.addEventListener('click', () => {
      $$(root, '.dl-segment__opt').forEach(x => x.classList.toggle('is-active', x === b));
      hidden.value = b.dataset.value; hidden.dispatchEvent(new Event('change', {bubbles: true}));
    }));
  }

  function initTags(root) {
    const input = $(root, '.dl-tags__input'), box = $(root, '.dl-tags__box'), name = root.dataset.name;
    const add = v => {
      v = v.trim().replace(/,$/, ''); if (!v) return;
      if ($$(root, 'input[type=hidden]').some(h => h.value.toLowerCase() === v.toLowerCase())) { input.value = ''; return; }
      const chip = document.createElement('span'); chip.className = 'dl-tags__chip';
      chip.innerHTML = `${esc(v)}<input type="hidden" name="${esc(name)}[]" value="${esc(v)}"><button type="button" data-dl-tag-x>&times;</button>`;
      box.insertBefore(chip, input); input.value = '';
    };
    input.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); add(input.value); }
      else if (e.key === 'Backspace' && !input.value) { const last = [...box.querySelectorAll('.dl-tags__chip')].pop(); if (last) last.remove(); }
    });
    input.addEventListener('change', () => add(input.value));
    box.addEventListener('click', e => { if (e.target.closest('[data-dl-tag-x]')) e.target.closest('.dl-tags__chip').remove(); else input.focus(); });
  }

  function initKv(root) {
    const body = $(root, '[data-dl-kv-body]'), name = root.dataset.name;
    const renumber = () => $$(body, 'tr').forEach((tr, i) => { const [k, v] = $$(tr, 'input'); k.name = `${name}[${i}][key]`; v.name = `${name}[${i}][value]`; });
    $(root, '[data-dl-kv-add]').addEventListener('click', () => {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td><input type="text"></td><td><input type="text"></td><td><button type="button" data-dl-kv-x title="Hapus baris">&times;</button></td>';
      body.appendChild(tr); renumber(); $(tr, 'input').focus();
    });
    body.addEventListener('click', e => { if (e.target.closest('[data-dl-kv-x]')) { e.target.closest('tr').remove(); renumber(); } });
  }

  function initRich(root) {
    const area = $(root, '.dl-rich__area'), val = $(root, '[data-dl-rich-value]');
    const sync = () => { val.value = area.innerHTML; };
    $$(root, '[data-cmd]').forEach(b => b.addEventListener('mousedown', e => { e.preventDefault(); document.execCommand(b.dataset.cmd); sync(); }));
    area.addEventListener('input', sync);
  }

  function initUpload(up) {
    const file = $(up, 'input[type=file]'), list = $(up, '[data-dl-upload-list]'), preview = up.hasAttribute('data-preview');
    let files = [];
    const draw = () => {
      up.classList.toggle('has-file', files.length > 0);
      list.innerHTML = '';
      files.forEach((f, i) => {
        const row = document.createElement('div'); row.className = 'dl-upload__file';
        const isImg = preview && f.type.startsWith('image/');
        row.innerHTML = (isImg ? '<img class="dl-upload__thumb" alt="">' : '<span>📄</span>') + `<span class="dl-upload__name">${esc(f.name)}</span><small>${Math.max(1, Math.round(f.size / 1024))} KB</small><button type="button" data-i="${i}" title="Hapus">✕</button>`;
        if (isImg) { const img = $(row, 'img'); img.src = URL.createObjectURL(f); img.onload = () => URL.revokeObjectURL(img.src); }
        list.appendChild(row);
      });
      const dt = new DataTransfer(); files.forEach(f => dt.items.add(f)); file.files = dt.files;
    };
    const take = incoming => { files = file.multiple ? files.concat([...incoming]) : [...incoming].slice(0, 1); draw(); };
    up.addEventListener('click', e => { if (!e.target.closest('button') && !e.target.closest('.dl-upload__file')) file.click(); });
    file.addEventListener('click', e => e.stopPropagation());
    file.addEventListener('change', () => { if (file.files.length) take(file.files); });
    list.addEventListener('click', e => { const b = e.target.closest('button[data-i]'); if (b) { e.stopPropagation(); files.splice(+b.dataset.i, 1); draw(); } });
    ['dragover', 'dragenter'].forEach(ev => up.addEventListener(ev, e => { e.preventDefault(); up.classList.add('is-drag'); }));
    ['dragleave', 'drop'].forEach(ev => up.addEventListener(ev, e => { e.preventDefault(); up.classList.remove('is-drag'); }));
    up.addEventListener('drop', e => { if (e.dataTransfer.files.length) take(e.dataTransfer.files); });
  }

  function initCounter(el) {
    const input = el.closest('.dl-field').querySelector('.dl-input'), max = el.dataset.max;
    const upd = () => { el.textContent = input.value.length + (max ? ' / ' + max : '') + ' karakter'; el.classList.toggle('is-full', !!max && input.value.length >= +max); };
    input.addEventListener('input', upd); upd();
  }

  // Back: ke layar sebelumnya bila datang dari situs ini, selain itu ke alamat fallback.
  function initBack(a) {
    a.addEventListener('click', e => {
      if (document.referrer && new URL(document.referrer).origin === location.origin && history.length > 1) { e.preventDefault(); history.back(); }
    });
  }

  /* ======================= FEEDBACK & STATES ======================= */
  // Toast bertumpuk: Devlib.toast('Tersimpan', 'success', 'Judul opsional').
  function toast(msg, tone = 'info', title = null) {
    let host = document.querySelector('.dl-toasts');
    if (!host) { host = document.createElement('div'); host.className = 'dl-toasts'; host.setAttribute('aria-live', 'polite'); document.body.appendChild(host); }
    const icon = {success: 'fa-circle-check', danger: 'fa-circle-xmark', warn: 'fa-triangle-exclamation', info: 'fa-circle-info'}[tone] || 'fa-circle-info';
    const t = document.createElement('div'); t.className = 'dl-toast2 dl-toast2--' + tone;
    t.innerHTML = `<i class="fa-solid ${icon}"></i><div>${title ? `<b>${esc(title)}</b>` : ''}<span>${esc(msg)}</span></div><button type="button" aria-label="Tutup">&times;</button>`;
    const bye = () => { t.classList.add('is-out'); setTimeout(() => t.remove(), 250); };
    $(t, 'button').addEventListener('click', bye);
    host.appendChild(t); setTimeout(bye, tone === 'danger' ? 7000 : 3500);
  }

  // Modal konfirmasi library (pengganti window.confirm): Devlib.confirm({...}).then(ok => ...)
  function confirmBox({title = 'Konfirmasi', message = 'Lanjutkan?', tone = 'warn', ok = 'Ya, lanjutkan', cancel = null} = {}) {
    return new Promise(resolve => {
      const cancelLabel = cancel || (currentLang === 'en' ? 'Cancel' : 'Batal');
      const icon = {danger: 'fa-trash-can', warn: 'fa-triangle-exclamation', success: 'fa-circle-check', info: 'fa-circle-info'}[tone] || 'fa-circle-question';
      const ov = document.createElement('div'); ov.className = 'dl-mov is-on dl-confirm';
      ov.innerHTML = `<div class="dl-modal dl-modal--sm" role="alertdialog" aria-modal="true"><div class="dl-confirm__body"><span class="dl-confirm__icon dl-confirm__icon--${tone}"><i class="fa-solid ${icon}"></i></span><div><h3>${esc(title)}</h3><p>${esc(message)}</p></div></div>`
        + `<div class="dl-modal__foot"><button type="button" class="dl-btn dl-btn--secondary dl-btn--md" data-no>${esc(cancelLabel)}</button><button type="button" class="dl-btn dl-btn--${tone === 'danger' ? 'danger' : 'primary'} dl-btn--md" data-yes>${esc(ok)}</button></div></div>`;
      const done = v => { ov.remove(); document.removeEventListener('keydown', key); resolve(v); };
      const key = e => { if (e.key === 'Escape') done(false); else if (e.key === 'Enter') done(true); };
      ov.addEventListener('click', e => { if (e.target === ov || e.target.closest('[data-no]')) done(false); else if (e.target.closest('[data-yes]')) done(true); });
      document.addEventListener('keydown', key);
      document.body.appendChild(ov); $(ov, '[data-yes]').focus();
    });
  }

  // data-dl-confirm="pesan" (+ data-dl-confirm-tone) di tombol/tautan: tanya dulu lewat modal library.
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-dl-confirm]');
    if (!b || b.dataset.dlConfirmed) return;
    e.preventDefault(); e.stopImmediatePropagation();
    confirmBox({message: b.dataset.dlConfirm, tone: b.dataset.dlConfirmTone || (b.closest('.dl-iconbtn--danger,.dl-btn--danger') ? 'danger' : 'warn')}).then(ok => {
      if (!ok) return;
      b.dataset.dlConfirmed = '1'; b.click(); delete b.dataset.dlConfirmed;
    });
  }, true);

  // Modal & drawer library: data-dl-open="<id>" membuka, [data-dl-close-modal] / Esc / klik luar menutup.
  const layer = id => document.querySelector(`[data-dl-modal-id="${id}"],[data-dl-drawer-id="${id}"]`);
  const openLayer = id => { const l = layer(id); if (l) { l.classList.add('is-on'); const f = l.querySelector('input,textarea,select,button:not([data-dl-close-modal])'); if (f) setTimeout(() => f.focus(), 50); } };
  const closeLayer = l => l && l.classList.remove('is-on');
  document.addEventListener('click', e => {
    const o = e.target.closest('[data-dl-open]'); if (o) { e.preventDefault(); openLayer(o.dataset.dlOpen); return; }
    const c = e.target.closest('[data-dl-close-modal]'); if (c) { closeLayer(c.closest('[data-dl-modal-id],[data-dl-drawer-id]')); return; }
    if (e.target.matches('[data-dl-modal-id].dl-mov')) closeLayer(e.target);
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') $$(document, '[data-dl-modal-id].is-on,[data-dl-drawer-id].is-on').slice(-1).forEach(closeLayer); });

  // Form dikirim -> tombol submit terkunci + "Memproses…" (cegah klik ganda).
  document.addEventListener('submit', e => {
    if (e.defaultPrevented) return;
    const btn = e.submitter || e.target.querySelector('[type=submit]');
    if (!btn || btn.dataset.dlNoBusy !== undefined) return;
    setTimeout(() => { if (e.defaultPrevented) return; btn.disabled = true; btn.classList.add('is-busy'); btn.dataset.dlLabel = btn.innerHTML; btn.innerHTML = '<span class="dl-spinner__ring dl-spinner__ring--inline"></span> ' + (currentLang === 'en' ? 'Processing…' : 'Memproses…'); }, 0);
  });

  function initNotif(root) {
    const all = $(root, '[data-dl-notif-all]'), count = $(root, '[data-dl-notif-count]');
    const refresh = () => { const n = $$(root, '.dl-notif__item.is-unread').length; if (count) { count.textContent = n + ' belum dibaca'; count.hidden = !n; } if (all) all.hidden = !n; };
    root.addEventListener('click', e => { const it = e.target.closest('[data-dl-notif-item]'); if (it) { it.classList.remove('is-unread'); const d = $(it, '.dl-notif__dot'); if (d) d.remove(); refresh(); } });
    if (all) all.addEventListener('click', () => { $$(root, '.dl-notif__item.is-unread').forEach(it => { it.classList.remove('is-unread'); const d = $(it, '.dl-notif__dot'); if (d) d.remove(); }); refresh(); toast('Semua notifikasi ditandai dibaca.', 'success'); });
  }

  /* ======================= GLOBAL BEHAVIORS ======================= */
  const T = k => (window.DL_LANG && window.DL_LANG[k]) ? window.DL_LANG[k][currentLang === 'en' ? 1 : 0] : k;

  // Bahasa: pilihan diingat (cookie dibaca server + localStorage), semua [data-dl-t] ikut berganti.
  document.addEventListener('dl:lang', () => {
    try { localStorage.setItem('dl-lang', currentLang); } catch (e) {}
    document.cookie = 'dl_lang=' + currentLang + ';path=/;max-age=31536000;SameSite=Lax';
    $$(document, '[data-dl-t]').forEach(el => { el.textContent = T(el.dataset.dlT); });
  });

  // Dirty-guard: form ber-atribut data-dl-guard yang sudah diubah -> tanya sebelum ditinggal.
  const dirtyForms = new Set();
  const markDirty = e => { const f = e.target.closest && e.target.closest('form[data-dl-guard]'); if (f) { dirtyForms.add(f); f.classList.add('is-dirty'); } };
  document.addEventListener('input', markDirty, true);
  document.addEventListener('change', markDirty, true);
  document.addEventListener('submit', e => { dirtyForms.delete(e.target); e.target.classList.remove('is-dirty'); }, true);
  window.addEventListener('beforeunload', e => { if ([...dirtyForms].some(f => document.contains(f))) { e.preventDefault(); e.returnValue = ''; } });

  // Tiga pilihan: Simpan (kirim form), Buang (lanjut pergi), Batal (tetap di sini).
  function askDirty() {
    return new Promise(resolve => {
      const ov = document.createElement('div'); ov.className = 'dl-mov is-on dl-confirm';
      ov.innerHTML = `<div class="dl-modal dl-modal--sm" role="alertdialog" aria-modal="true"><div class="dl-confirm__body"><span class="dl-confirm__icon dl-confirm__icon--warn"><i class="fa-solid fa-floppy-disk"></i></span><div><h3>${esc(T('dirty_title'))}</h3><p>${esc(T('dirty_msg'))}</p></div></div>`
        + `<div class="dl-modal__foot"><button type="button" class="dl-btn dl-btn--ghost dl-btn--md" data-v="stay">${esc(T('cancel'))}</button><button type="button" class="dl-btn dl-btn--secondary dl-btn--md" data-v="discard">${esc(T('discard'))}</button><button type="button" class="dl-btn dl-btn--primary dl-btn--md" data-v="save">${esc(T('save'))}</button></div></div>`;
      const done = v => { ov.remove(); resolve(v); };
      ov.addEventListener('click', e => { const b = e.target.closest('[data-v]'); if (b) done(b.dataset.v); else if (e.target === ov) done('stay'); });
      document.body.appendChild(ov); $(ov, '[data-v=save]').focus();
    });
  }
  function submitForm(f) { const btn = f.querySelector('[type=submit]'); if (f.requestSubmit) f.requestSubmit(btn || undefined); else if (btn) btn.click(); }
  document.addEventListener('click', e => {
    const a = e.target.closest('[data-dl-back], [data-dl-leave], a[href]:not([href^="#"]):not([target=_blank]):not([href^="javascript"])');
    const live = [...dirtyForms].filter(f => document.contains(f));
    if (!a || !live.length || a.closest('form[data-dl-guard]') === live[0] && !a.hasAttribute('data-dl-back') && !a.hasAttribute('data-dl-leave')) return;
    e.preventDefault(); e.stopImmediatePropagation();
    askDirty().then(v => {
      if (v === 'save') submitForm(live[0]);
      else if (v === 'discard') { live.forEach(f => { dirtyForms.delete(f); f.classList.remove('is-dirty'); }); a.click(); }
    });
  }, true);

  // Pintasan keyboard: tombol ber-data-dl-key (preset Simpan = Ctrl+S, Baru = Alt+N), '?' = daftar pintasan.
  const keyName = e => [e.ctrlKey || e.metaKey ? 'ctrl' : '', e.altKey ? 'alt' : '', e.shiftKey ? 'shift' : '', e.key.toLowerCase()].filter(Boolean).join('+');
  const visible = el => el.offsetParent !== null && !el.disabled;
  const BUILTIN = [['ctrl+k', 'Cari menu / perintah'], ['esc', 'Tutup modal / drawer / popup'], ['ctrl+shift+d', 'Mode Developer (admin)'], ['?', 'Tampilkan daftar pintasan ini']];
  function showShortcuts() {
    const own = $$(document, '[data-dl-key]').filter(visible).map(b => [b.dataset.dlKey, b.textContent.trim() || b.title]);
    const rows = [...new Map([...own, ...BUILTIN].map(r => [r[0], r])).values()];
    const ov = document.createElement('div'); ov.className = 'dl-mov is-on';
    ov.innerHTML = `<div class="dl-modal dl-modal--sm"><div class="dl-modal__head"><h3><i class="fa-solid fa-keyboard"></i> ${esc(T('shortcuts'))}</h3><button type="button" class="dl-modal__x">&times;</button></div><div class="dl-modal__body"><table class="dl-keys">${rows.map(([k, d]) => `<tr><td>${k.split('+').map(p => `<kbd>${esc(p === 'ctrl' ? 'Ctrl' : p.length === 1 ? p.toUpperCase() : p[0].toUpperCase() + p.slice(1))}</kbd>`).join(' + ')}</td><td>${esc(d)}</td></tr>`).join('')}</table></div></div>`;
    ov.addEventListener('click', e => { if (e.target === ov || e.target.closest('.dl-modal__x')) ov.remove(); });
    document.body.appendChild(ov);
  }
  document.addEventListener('keydown', e => {
    const k = keyName(e), typing = /INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.target.isContentEditable;
    if (k === 'ctrl+shift+d' && document.querySelector('[data-dl-devtoggle],[data-dl-developer="1"]')) { e.preventDefault(); setDevMode(!document.body.classList.contains('dl-dev')); return; }
    if ((k === '?' || k === 'shift+?') && !typing) { e.preventDefault(); showShortcuts(); return; }
    const btn = $$(document, `[data-dl-key="${k}"]`).find(visible);
    if (btn) { e.preventDefault(); btn.click(); }
  });

  function initDevToggle(b) { b.addEventListener('click', () => setDevMode(!document.body.classList.contains('dl-dev'))); }

  /* ============================ BASIC ============================ */
  // Format lokal baku di browser (sama dengan Devlib\Locale di PHP): zona waktu sistem, Rupiah, angka.
  const TZ = () => document.documentElement.dataset.dlTz || 'Asia/Jakarta';
  const fmt = {
    number: (n, d = 0) => Number(n).toLocaleString('id-ID', {minimumFractionDigits: d, maximumFractionDigits: d}),
    money: (n, cur = 'IDR', d = 0) => ({IDR: 'Rp', USD: '$', EUR: '€', JPY: '¥'}[cur] || cur) + ' ' + fmt.number(n, d),
    date: (v = new Date(), style = 'short') => new Date(v).toLocaleDateString('id-ID', style === 'long' ? {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: TZ()} : style === 'numeric' ? {day: '2-digit', month: '2-digit', year: 'numeric', timeZone: TZ()} : {day: 'numeric', month: 'short', year: 'numeric', timeZone: TZ()}),
    time: (v = new Date()) => new Date(v).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit', hour12: false, timeZone: TZ()}) + ' WIB',
  };

  function initThemePicker(root) {
    const html = document.documentElement;
    const save = (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} };
    const mark = () => {
      $$(root, '[data-theme]').forEach(b => b.classList.toggle('is-active', b.dataset.theme === html.dataset.dlTheme));
      $$(root, '[data-density]').forEach(b => b.classList.toggle('is-active', b.dataset.density === (html.dataset.dlDensity || 'normal')));
      const sc = html.style.getPropertyValue('--dl-font-scale') || '1';
      $$(root, '[data-scale]').forEach(b => b.classList.toggle('is-active', b.dataset.scale === sc.trim()));
      document.dispatchEvent(new CustomEvent('dl:theme'));
    };
    $$(root, '[data-theme]').forEach(b => b.addEventListener('click', () => { html.dataset.dlTheme = b.dataset.theme; save('dl-theme', b.dataset.theme); mark(); }));
    $$(root, '[data-density]').forEach(b => b.addEventListener('click', () => { html.dataset.dlDensity = b.dataset.density; save('dl-density', b.dataset.density); mark(); }));
    $$(root, '[data-scale]').forEach(b => b.addEventListener('click', () => { html.style.setProperty('--dl-font-scale', b.dataset.scale); save('dl-font-scale', b.dataset.scale); mark(); }));
    mark();
  }

  // Nilai token terkini (ikut tema aktif) ditulis ke tabel dokumentasi.
  function initTokens(root) {
    const paint = () => $$(root, '[data-dl-token-value]').forEach(td => { td.textContent = getComputedStyle(document.documentElement).getPropertyValue(td.dataset.dlTokenValue).trim() || '—'; });
    paint(); document.addEventListener('dl:theme', paint);
  }

  // Contoh format lokal hidup (halaman Locale & Formats).
  function initFmtDemo(root) {
    const tick = () => $$(root, '[data-fmt]').forEach(el => { el.textContent = new Function('fmt', 'return ' + el.dataset.fmt)(fmt); });
    tick(); setInterval(tick, 1000);
  }

  /* ============================ GRID ============================ */
  // Mesin grid sisi-browser (DevLib, tanpa DB). Di tmerp, versi server (Livewire) memakai
  // markup & kelas yang sama; hanya sumber data & query-nya yang berpindah ke server.
  function initGrid(root) {
    const cfg = JSON.parse(root.dataset.config), rows = JSON.parse(root.dataset.rows || '[]'), cols = cfg.columns, hasExp = cfg.expand.length > 0 || !!cfg.detail;
    const saveKey = 'dl-grid-' + cfg.id;
    let saved = {}; try { saved = JSON.parse(localStorage.getItem(saveKey) || '{}'); } catch (e) {}
    const st = Object.assign({search: '', sort: null, dir: 1, stat: null, tab: 0, filters: {}, hidden: cols.filter(c => !c.visible).map(c => c.key),
      group: cfg.groupBy || '', view: 'table', page: 1, perPage: cfg.perPage, density: 'normal', lines: 'fixed', linesN: 1, collapsed: [], open: []}, saved, {page: 1, search: ''});
    const sel = new Set(); let lastIdx = null;
    const persist = () => { try { localStorage.setItem(saveKey, JSON.stringify({sort: st.sort, dir: st.dir, hidden: st.hidden, group: st.group, perPage: st.perPage, density: st.density, lines: st.lines, linesN: st.linesN, filters: st.filters, tab: st.tab})); } catch (e) {} };
    const q = s => $(root, s), qq = s => $$(root, s);
    const shown = () => cols.filter(c => !st.hidden.includes(c.key));
    // true = tidak ada induk yang menangani (grid berdiri sendiri -> tampilkan pratinjau).
    const emit = (name, detail) => root.dispatchEvent(new CustomEvent(name, {bubbles: true, cancelable: true, detail}));
    const val = (r, k) => r[k] ?? '';
    const match = (r, where) => !where || Object.entries(where).every(([k, v]) => Array.isArray(v) ? v.map(String).includes(String(val(r, k))) : String(val(r, k)) === String(v));
    const fmtCell = (c, v) => c.type === 'money' ? fmt.money(v) : c.type === 'number' ? fmt.number(v, String(v).includes('.') ? 2 : 0) : c.type === 'date' ? (v ? fmt.date(v) : '') : v;

    function filtered(opts = {}) {
      const term = st.search.trim().toLowerCase(), vis = shown();
      return rows.filter(r =>
        (opts.noStat || st.stat === null || match(r, cfg.stats[st.stat].where)) &&
        (opts.noTab || !cfg.tabs.length || match(r, cfg.tabs[st.tab].where)) &&
        Object.entries(st.filters).every(([k, v]) => v === '' || String(val(r, k)) === v) &&
        (!term || vis.some(c => String(fmtCell(c, val(r, c.key))).toLowerCase().includes(term) || (c.code && String(val(r, c.code)).toLowerCase().includes(term)))));
    }
    function sorted(list) {
      if (!st.sort) return list;
      const c = cols.find(x => x.key === st.sort), num = ['number', 'money'].includes(c && c.type);
      return [...list].sort((a, b) => { const x = val(a, st.sort), y = val(b, st.sort); return (num ? (+x - +y) : String(x).localeCompare(String(y), 'id', {numeric: true})) * st.dir; });
    }

    // ---- kotak statistik & tab (angka dihitung dari seluruh data, bukan hasil saringan) ----
    function drawStats() {
      const box = q('[data-g-stats]'); if (!box) return;
      const base = filtered({noStat: true});
      box.innerHTML = cfg.stats.map((s, i) => `<button type="button" class="dl-stat dl-stat--${esc(s.tone || 'accent')} is-filter ${st.stat === i ? 'is-active' : ''}" data-i="${i}"><span class="dl-stat__label">${esc(s.label)}</span><span class="dl-stat__value">${fmt.number(base.filter(r => match(r, s.where)).length)}</span></button>`).join('');
    }
    function drawTabs() {
      const bar = q('[data-g-tabs]'); if (!bar) return;
      const base = filtered({noTab: true, noStat: true});
      bar.innerHTML = cfg.tabs.map((t, i) => `<button type="button" class="dl-tabs__tab ${st.tab === i ? 'is-active' : ''}" data-i="${i}">${esc(t.label)} <span class="dl-grid__tabcount">${base.filter(r => match(r, t.where)).length}</span></button>`).join('');
    }

    // ---- kepala tabel ----
    function drawHead() {
      const vis = shown();
      q('[data-g-head]').innerHTML = '<tr><th class="dl-grid__chk"><input type="checkbox" data-g-all aria-label="Pilih semua di halaman"></th>' + (hasExp ? '<th class="dl-grid__exp"></th>' : '')
        + vis.map(c => {
          const on = st.sort === c.key, arrow = on ? (st.dir > 0 ? 'fa-arrow-up-short-wide' : 'fa-arrow-down-wide-short') : 'fa-sort';
          return `<th class="${['number', 'money'].includes(c.type) ? 'num' : ''} ${c.sortable ? 'is-sortable' : ''} ${on ? 'is-sorted' : ''}" data-key="${esc(c.key)}" ${c.width ? `style="width:${esc(c.width)}"` : ''}>${esc(c.label)}${c.sortable ? ` <i class="fa-solid ${arrow}"></i>` : ''}</th>`;
        }).join('') + (cfg.actions !== 'none' ? '<th class="dl-grid__act">Aksi</th>' : '') + '</tr>';
    }

    // ---- sel ----
    function cell(c, r) {
      const v = val(r, c.key);
      switch (c.type) {
        case 'identity': {
          const name = String(v || '?'), w = name.trim().split(/\s+/), ini = (w[0][0] + (w[1] ? w[1][0] : '')).toUpperCase();
          let h = 0; for (const ch of name) h = (h * 31 + ch.charCodeAt(0)) % 360;
          return `<td><span class="dl-avatar-id"><span class="dl-avatar-id__pic" style="--h:${h}">${esc(ini)}</span><span class="dl-avatar-id__txt"><b>${esc(name)}</b>${c.code ? `<small>${esc(val(r, c.code))}</small>` : ''}</span></span></td>`;
        }
        case 'badge': return `<td><span class="dl-badge dl-badge--pill dl-badge--${esc((c.tones || {})[v] || 'neutral')}">${esc(v)}</span></td>`;
        case 'money': case 'number': return `<td class="num">${esc(fmtCell(c, v))}</td>`;
        case 'image': return `<td class="dl-grid__imgcell">${v ? `<button type="button" class="dl-grid__thumb" data-g-img="${esc(v)}" title="Perbesar"><img src="${esc(v)}" alt="" loading="lazy"></button>` : '<span class="dl-grid__noimg"><i class="fa-regular fa-image"></i></span>'}</td>`;
        case 'progress': { const n = Math.max(0, Math.min(100, +v || 0)), t = n >= 100 ? 'success' : n >= 60 ? 'accent' : n >= 30 ? 'warn' : 'danger'; return `<td><span class="dl-grid__prog dl-chart--${t}"><span style="width:${n}%"></span></span><small class="dl-grid__progv">${n}%</small></td>`; }
        case 'steps': { const at = (c.steps || []).indexOf(v); return `<td><span class="dl-grid__steps" title="${esc(v)}">${(c.steps || []).map((s2, i) => `<i class="${i < at ? 'is-done' : i === at ? 'is-now' : ''}" title="${esc(s2)}"></i>`).join('')}</span><small class="dl-grid__stepv">${esc(v)}</small></td>`; }
        case 'toggle': return `<td><label class="dl-switch dl-switch--sm"><input type="checkbox" data-g-cell="${esc(c.key)}" ${v ? 'checked' : ''}><span class="dl-switch__slider"></span></label></td>`;
        case 'rating': return `<td class="dl-grid__stars" title="${esc(v)} / 5">${[1, 2, 3, 4, 5].map(i => `<i class="fa-solid fa-star ${i <= Math.round(+v || 0) ? 'is-on' : ''}"></i>`).join('')}</td>`;
        case 'spark': { const d = (Array.isArray(v) ? v : []).map(Number); if (d.length < 2) return '<td></td>'; const mn = Math.min(...d), mx = Math.max(...d); const pts = d.map((y, i) => `${(i / (d.length - 1) * 80).toFixed(1)},${(22 - (mx > mn ? (y - mn) / (mx - mn) : .5) * 20).toFixed(1)}`).join(' '); return `<td><svg class="dl-grid__spark ${d[d.length - 1] < d[0] ? 'is-down' : ''}" viewBox="0 0 80 24"><polyline points="${pts}"/></svg></td>`; }
        case 'button': return `<td><button type="button" class="dl-btn dl-btn--secondary dl-btn--sm" data-g-action="${esc(c.action || c.key)}">${esc(c.text || c.label)}</button></td>`;
        default: return `<td><div class="dl-grid__txt">${esc(fmtCell(c, v))}</div></td>`;
      }
    }
    function actionsCell(r) {
      if (cfg.actions === 'none') return '';
      const id = esc(r[cfg.rowKey]);
      if (cfg.actions === 'worklist') return `<td class="dl-grid__act"><button type="button" class="dl-iconbtn" data-g-act="open" data-id="${id}" title="Buka (klik 2x baris)"><i class="fa-solid fa-up-right-from-square"></i></button>${r.status === 'SUBMITTED' ? `<button type="button" class="dl-iconbtn dl-iconbtn--success" data-g-act="confirm" data-id="${id}" title="Confirm" data-dl-confirm="Confirm dokumen ini?"><i class="fa-solid fa-circle-check"></i></button>` : ''}</td>`;
      const on = !!r[cfg.activeKey];
      return `<td class="dl-grid__act"><button type="button" class="dl-iconbtn" data-g-act="edit" data-id="${id}" title="Edit (klik 2x baris)"><i class="fa-solid fa-pen"></i></button><button type="button" class="dl-iconbtn dl-iconbtn--${on ? 'danger' : 'success'}" data-g-act="toggle" data-id="${id}" title="${on ? 'Nonaktifkan' : 'Aktifkan'}" data-dl-confirm="${on ? 'Nonaktifkan' : 'Aktifkan'} data ini?"><i class="fa-solid ${on ? 'fa-ban' : 'fa-circle-check'}"></i></button></td>`;
    }
    function rowHtml(r, idx) {
      const id = String(r[cfg.rowKey]), open = st.open.includes(id), span = shown().length + 1 + (hasExp ? 1 : 0) + (cfg.actions !== 'none' ? 1 : 0);
      let h = `<tr class="dl-grid__row ${sel.has(id) ? 'is-selected' : ''} ${cfg.activeKey && r[cfg.activeKey] === false ? 'is-inactive' : ''}" data-id="${esc(id)}" data-idx="${idx}" title="Klik 2x untuk membuka">`
        + `<td class="dl-grid__chk"><input type="checkbox" ${sel.has(id) ? 'checked' : ''} aria-label="Pilih baris"></td>`
        + (hasExp ? `<td class="dl-grid__exp"><button type="button" class="dl-grid__chev ${open ? 'is-open' : ''}" data-g-exp aria-label="Buka detail"><i class="fa-solid fa-chevron-right"></i></button></td>` : '')
        + shown().map(c => cell(c, r)).join('') + actionsCell(r) + '</tr>';
      if (open && cfg.detail) { const D = cfg.detail, kids = r[D.key] || []; h += `<tr class="dl-grid__detail"><td colspan="${span}"><div class="dl-grid__nest"><b>${esc(D.title || 'Detail')} · ${kids.length} baris</b><table><thead><tr>${D.columns.map(c => `<th class="${['number', 'money'].includes(c.type) ? 'num' : ''}">${esc(c.label)}</th>`).join('')}</tr></thead><tbody>${kids.map(k => `<tr>${D.columns.map(c => `<td class="${['number', 'money'].includes(c.type) ? 'num' : ''}">${esc(fmtCell(c, k[c.key] ?? ''))}</td>`).join('')}</tr>`).join('') || `<tr><td colspan="${D.columns.length}">Tidak ada detail</td></tr>`}</tbody></table></div></td></tr>`; }
      else if (open) h += `<tr class="dl-grid__detail"><td colspan="${span}"><div class="dl-grid__detailbox">${cfg.expand.map(k => { const c = cols.find(x => x.key === k) || {key: k, label: k}; return `<div><small>${esc(c.label)}</small><b>${esc(fmtCell(c, val(r, k)) || '—')}</b></div>`; }).join('')}</div></td></tr>`;
      return h;
    }

    // ---- badan tabel (+ grup) & kaki (subtotal) ----
    let current = [];
    function drawBody() {
      const list = sorted(filtered()), total = list.length, pages = Math.max(1, Math.ceil(total / st.perPage));
      st.page = Math.min(st.page, pages);
      const page = list.slice((st.page - 1) * st.perPage, st.page * st.perPage);
      current = page;
      const span = shown().length + 1 + (hasExp ? 1 : 0) + (cfg.actions !== 'none' ? 1 : 0), sums = shown().filter(c => c.sum);
      let html = '';
      if (!page.length) html = `<tr><td colspan="${span}"><div class="dl-empty"><span class="dl-empty__icon"><i class="fa-solid fa-magnifying-glass"></i></span><b class="dl-empty__title">Tidak ada data yang cocok</b><p class="dl-empty__msg">Ubah kata kunci, lepas filter, atau pilih tab lain.</p></div></td></tr>`;
      else if (st.group) {
        const gcol = cols.find(c => c.key === st.group), buckets = new Map();
        page.forEach((r, i) => { const g = String(val(r, st.group)) || '(kosong)'; if (!buckets.has(g)) buckets.set(g, []); buckets.get(g).push([r, i]); });
        buckets.forEach((items, g) => {
          const shut = st.collapsed.includes(g);
          html += `<tr class="dl-grid__group"><td colspan="${span}"><button type="button" data-g-grp="${esc(g)}"><i class="fa-solid fa-chevron-${shut ? 'right' : 'down'}"></i> ${esc(gcol ? gcol.label : st.group)}: <b>${esc(g)}</b> <span>${items.length} baris</span>${sums.map(c => ` · ${esc(c.label)} <b>${esc(fmtCell(c, items.reduce((t, [r]) => t + (+val(r, c.key) || 0), 0)))}</b>`).join('')}</button></td></tr>`;
          if (!shut) items.forEach(([r, i]) => { html += rowHtml(r, i); });
        });
      } else page.forEach((r, i) => { html += rowHtml(r, i); });
      q('[data-g-body]').innerHTML = html;
      if (cfg.quickAdd.length) {
        const qa = cfg.quickAdd.map(k => cols.find(c => c.key === k) || {key: k, label: k});
        q('[data-g-body]').insertAdjacentHTML('afterbegin', `<tr class="dl-grid__quick"><td class="dl-grid__chk"><i class="fa-solid fa-plus"></i></td>${hasExp ? '<td></td>' : ''}<td colspan="${span - 1 - (hasExp ? 1 : 0)}"><div class="dl-grid__qa">${qa.map(c => `<input type="text" data-g-qa="${esc(c.key)}" placeholder="${esc(c.label)}${['number', 'money'].includes(c.type) ? ' (angka)' : ''}" aria-label="${esc(c.label)}">`).join('')}<button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-g-qa-add>Tambah</button><small>Enter = tambah</small></div></td></tr>`);
      }
      const cardsOn = cfg.cards && st.view === 'cards';
      q('[data-g-cards]').hidden = !cardsOn; q('.dl-grid__wrap').hidden = !!cardsOn;
      if (cardsOn) { const C = cfg.cards; q('[data-g-cards]').innerHTML = page.map(r => `<article class="dl-grid__card" data-id="${esc(r[cfg.rowKey])}"><div class="dl-grid__cardimg">${r[C.image] ? `<img src="${esc(r[C.image])}" alt="" loading="lazy">` : '<i class="fa-regular fa-image"></i>'}${C.badge && r[C.badge] ? `<span class="dl-badge dl-badge--pill dl-badge--${esc((C.tones || {})[r[C.badge]] || 'neutral')}">${esc(r[C.badge])}</span>` : ''}</div><div class="dl-grid__cardbody"><b>${esc(r[C.title])}</b><small>${esc(r[C.sub] ?? '')}</small>${(C.meta || []).map(k => { const c = cols.find(x => x.key === k) || {key: k}; return `<span><em>${esc(c.label || k)}</em>${esc(fmtCell(c, r[k] ?? ''))}</span>`; }).join('')}</div></article>`).join('') || '<div class="dl-empty"><b class="dl-empty__title">Tidak ada data yang cocok</b></div>'; }
      q('[data-g-foot]').innerHTML = sums.length && page.length ? '<tr><td class="dl-grid__chk"></td>' + (hasExp ? '<td></td>' : '') + shown().map((c, i) => c.sum ? `<td class="num"><b>${esc(fmtCell(c, list.reduce((t, r) => t + (+val(r, c.key) || 0), 0)))}</b></td>` : `<td>${i === 0 ? '<b>Total (semua hasil)</b>' : ''}</td>`).join('') + (cfg.actions !== 'none' ? '<td></td>' : '') + '</tr>' : '';
      drawPager(total, pages);
      const all = q('[data-g-all]'); if (all) { const ids = page.map(r => String(r[cfg.rowKey])); all.checked = ids.length > 0 && ids.every(i => sel.has(i)); all.indeterminate = !all.checked && ids.some(i => sel.has(i)); }
    }
    function drawPager(total, pages) {
      const from = total ? (st.page - 1) * st.perPage + 1 : 0, to = Math.min(total, st.page * st.perPage), start = Math.max(1, Math.min(st.page - 2, pages - 4));
      let nums = ''; for (let p = start; p <= Math.min(pages, start + 4); p++) nums += `<button type="button" class="${p === st.page ? 'is-active' : ''}" data-p="${p}">${p}</button>`;
      q('[data-g-pager]').innerHTML = `<div class="dl-pager"><div class="dl-pager__left">Go to <input type="number" min="1" max="${pages}" value="${st.page}" class="dl-pager__goto" data-g-goto> View ${[10, 25, 50, 100].map(n => `<button type="button" class="dl-pager__size ${n === st.perPage ? 'is-active' : ''}" data-pp="${n}">${n}</button>`).join('')}</div>`
        + `<div class="dl-pager__nav"><button type="button" data-p="1" ${st.page <= 1 ? 'disabled' : ''} title="Halaman pertama">⏮</button><button type="button" data-p="${st.page - 1}" ${st.page <= 1 ? 'disabled' : ''} title="Sebelumnya">◀</button>${nums}<button type="button" data-p="${st.page + 1}" ${st.page >= pages ? 'disabled' : ''} title="Berikutnya">▶</button><button type="button" data-p="${pages}" ${st.page >= pages ? 'disabled' : ''} title="Halaman terakhir">⏭</button></div>`
        + `<div class="dl-pager__info">[${fmt.number(from)} – ${fmt.number(to)} dari ${fmt.number(total)}]</div></div>`;
    }

    // ---- toolbar: filter, kolom, grup, kerapatan, chip ----
    function drawTools() {
      const ff = q('[data-g-filter-fields]');
      if (ff) ff.innerHTML = Object.entries(cfg.filters).map(([k, label]) => { const opts = [...new Set(rows.map(r => String(val(r, k))))].sort(); return `<label class="dl-pop2__field"><span>${esc(label)}</span><select data-g-filter="${esc(k)}"><option value="">Semua</option>${opts.map(o => `<option ${st.filters[k] === o ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select></label>`; }).join('');
      const n = Object.values(st.filters).filter(v => v !== '').length, badge = q('[data-g-filter-count]');
      if (badge) { badge.hidden = !n; badge.textContent = n; }
      q('[data-g-col-list]').innerHTML = cols.map(c => `<label class="dl-choice"><input type="checkbox" data-g-col="${esc(c.key)}" ${st.hidden.includes(c.key) ? '' : 'checked'}> ${esc(c.label)}</label>`).join('');
      const gl = q('[data-g-group-list]');
      if (gl) gl.innerHTML = [['', 'Tanpa grup'], ...Object.entries(cfg.groups)].map(([k, l]) => `<label class="dl-choice"><input type="radio" name="g-${esc(cfg.id)}" value="${esc(k)}" data-g-group ${st.group === k ? 'checked' : ''}> ${esc(l)}</label>`).join('');
      qq('[data-g-density] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b.dataset.value === st.density));
      qq('[data-g-lines] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b.dataset.value === st.lines));
      const ln = q('[data-g-lines-n]'); ln.value = st.linesN; ln.disabled = st.lines === 'auto';
      root.dataset.density = st.density; root.style.setProperty('--dl-grid-lines', st.lines === 'auto' ? 'unset' : st.linesN); root.classList.toggle('is-clamped', st.lines !== 'auto');
      const chips = [
        ...(st.stat !== null ? [['stat', 'Kotak: ' + cfg.stats[st.stat].label]] : []),
        ...Object.entries(st.filters).filter(([, v]) => v !== '').map(([k, v]) => ['f:' + k, `${cfg.filters[k]}: ${v}`]),
        ...(st.search ? [['search', 'Cari: “' + st.search + '”']] : []),
      ];
      const cbox = q('[data-g-chips]'); cbox.hidden = !chips.length;
      cbox.innerHTML = chips.map(([k, l]) => `<span class="dl-tags__chip">${esc(l)}<button type="button" data-g-chip="${esc(k)}" aria-label="Lepas">&times;</button></span>`).join('') + (chips.length > 1 ? '<button type="button" class="dl-btn dl-btn--link" data-g-chip="all">Lepas semua</button>' : '');
    }
    function drawBulk() {
      const b = q('[data-g-bulk]'); b.hidden = !sel.size; q('[data-g-bulk-count]').textContent = fmt.number(sel.size);
      const all = q('[data-g-select-all]'); const n = filtered().length; all.textContent = sel.size < n ? `Pilih semua ${fmt.number(n)} hasil` : 'Semua hasil terpilih'; all.disabled = sel.size >= n;
    }
    const redraw = () => { drawStats(); drawTabs(); drawTools(); drawHead(); drawBody(); drawBulk(); persist(); };

    // ---- interaksi ----
    const search = q('[data-g-search]'); let t;
    search.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => { st.search = search.value; st.page = 1; redraw(); }, 250); });
    root.addEventListener('click', e => {
      const x = e.target;
      const s = x.closest('.dl-grid__stats [data-i]'); if (s) { st.stat = st.stat === +s.dataset.i ? null : +s.dataset.i; st.page = 1; return redraw(); }
      const tb = x.closest('[data-g-tabs] [data-i]'); if (tb) { st.tab = +tb.dataset.i; st.page = 1; return redraw(); }
      const th = x.closest('th.is-sortable'); if (th) { const k = th.dataset.key; if (st.sort === k) { if (st.dir > 0) st.dir = -1; else { st.sort = null; st.dir = 1; } } else { st.sort = k; st.dir = 1; } return redraw(); }
      const pg = x.closest('[data-p]'); if (pg && !pg.disabled) { st.page = +pg.dataset.p; return drawBody(); }
      const pp = x.closest('[data-pp]'); if (pp) { st.perPage = +pp.dataset.pp; st.page = 1; return redraw(); }
      const tg = x.closest('[data-g-toggle]'); if (tg) { e.stopPropagation(); const pan = q(`[data-g-panel="${tg.dataset.gToggle}"]`), was = pan.classList.contains('is-open'); qq('.dl-pop2__panel').forEach(p => p.classList.remove('is-open')); pan.classList.toggle('is-open', !was); return; }
      const d = x.closest('[data-g-density] .dl-segment__opt'); if (d) { st.density = d.dataset.value; return redraw(); }
      const l = x.closest('[data-g-lines] .dl-segment__opt'); if (l) { st.lines = l.dataset.value; return redraw(); }
      if (x.closest('[data-g-density-reset]')) { st.density = 'normal'; st.lines = 'fixed'; st.linesN = 1; return redraw(); }
      if (x.closest('[data-g-filter-reset]')) { st.filters = {}; st.page = 1; return redraw(); }
      const ch = x.closest('[data-g-chip]'); if (ch) { const k = ch.dataset.gChip; if (k === 'all') { st.filters = {}; st.stat = null; st.search = ''; search.value = ''; } else if (k === 'stat') st.stat = null; else if (k === 'search') { st.search = ''; search.value = ''; } else st.filters[k.slice(2)] = ''; st.page = 1; return redraw(); }
      const gr = x.closest('[data-g-grp]'); if (gr) { const g = gr.dataset.gGrp; st.collapsed = st.collapsed.includes(g) ? st.collapsed.filter(v => v !== g) : [...st.collapsed, g]; return drawBody(); }
      const vw = x.closest('[data-g-view] .dl-segment__opt'); if (vw) { st.view = vw.dataset.value; qq('[data-g-view] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b === vw)); return drawBody(); }
      const im = x.closest('[data-g-img]'); if (im) { const ov = document.createElement('div'); ov.className = 'dl-mov is-on dl-grid__lightbox'; ov.innerHTML = `<img src="${esc(im.dataset.gImg)}" alt=""><button type="button" class="dl-modal__x">&times;</button>`; ov.addEventListener('click', () => ov.remove()); document.body.appendChild(ov); return; }
      const ga = x.closest('[data-g-action]'); if (ga) { const id = ga.closest('tr').dataset.id; if (emit('dl:grid-action', {id, action: ga.dataset.gAction})) toast(`${ga.textContent}: ${rows.find(v => String(v[cfg.rowKey]) === id)?.name || id} (pratinjau)`); return; }
      if (x.closest('[data-g-qa-add]')) return quickAdd();
      const ex = x.closest('[data-g-exp]'); if (ex) { const id = ex.closest('tr').dataset.id; st.open = st.open.includes(id) ? st.open.filter(v => v !== id) : [...st.open, id]; return drawBody(); }
      if (x.closest('[data-g-select-all]')) { filtered().forEach(r => sel.add(String(r[cfg.rowKey]))); drawBody(); return drawBulk(); }
      if (x.closest('[data-g-select-none]')) { sel.clear(); drawBody(); return drawBulk(); }
      const ba = x.closest('[data-g-bulk-action]'); if (ba) { toast(`${ba.textContent.trim()}: ${fmt.number(sel.size)} baris (pratinjau)`, 'success'); root.dispatchEvent(new CustomEvent('dl:grid-bulk', {bubbles: true, detail: {action: ba.dataset.gBulkAction, ids: [...sel]}})); return; }
      const act = x.closest('[data-g-act]'); if (act) { const r = rows.find(v => String(v[cfg.rowKey]) === act.dataset.id); if (act.dataset.gAct === 'toggle' && cfg.activeKey) { r[cfg.activeKey] = !r[cfg.activeKey]; toast((r[cfg.activeKey] ? 'Diaktifkan: ' : 'Dinonaktifkan: ') + (r.name || act.dataset.id), 'success'); redraw(); } else if (emit('dl:grid-open', {id: act.dataset.id, action: act.dataset.gAct})) toast(`${act.title.split(' (')[0]}: ${r.name || r[cfg.rowKey]} (pratinjau)`); return; }
      if (x.closest('[data-g-new]')) { if (emit('dl:grid-new', {})) toast('+ New Record (pratinjau)'); return; }
      if (x.closest('[data-g-export]')) return exportCsv();
      const cb = x.matches('.dl-grid__row input[type=checkbox]') ? x : null;
      if (cb) {
        const tr = cb.closest('tr'), idx = +tr.dataset.idx;
        if (e.shiftKey && lastIdx !== null) { const [a, b] = [Math.min(lastIdx, idx), Math.max(lastIdx, idx)]; current.slice(a, b + 1).forEach(r => cb.checked ? sel.add(String(r[cfg.rowKey])) : sel.delete(String(r[cfg.rowKey]))); }
        else cb.checked ? sel.add(tr.dataset.id) : sel.delete(tr.dataset.id);
        lastIdx = idx; drawBody(); drawBulk(); return;
      }
      if (x.matches('[data-g-all]')) { current.forEach(r => x.checked ? sel.add(String(r[cfg.rowKey])) : sel.delete(String(r[cfg.rowKey]))); drawBody(); drawBulk(); }
    });
    function quickAdd() {
      const inputs = qq('[data-g-qa]'), first = inputs[0];
      if (!first || !first.value.trim()) { first && first.focus(); toast(`${cols.find(c => c.key === cfg.quickAdd[0])?.label || 'Isian pertama'} wajib diisi.`, 'warn'); return; }
      const r = {[cfg.rowKey]: 'n' + Date.now()}; if (cfg.activeKey) r[cfg.activeKey] = true;
      inputs.forEach(i => { const c = cols.find(x => x.key === i.dataset.gQa) || {}; r[i.dataset.gQa] = ['number', 'money'].includes(c.type) ? +i.value.replace(/\./g, '').replace(',', '.') || 0 : i.value.trim(); });
      rows.unshift(r); redraw(); emit('dl:grid-add', {row: r}); toast('Baris ditambahkan.', 'success'); const f = q('[data-g-qa]'); if (f) f.focus();
    }
    root.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.matches('[data-g-qa]')) { e.preventDefault(); quickAdd(); } });
    root.addEventListener('change', e => {
      const tc = e.target.closest('[data-g-cell]'); if (tc) { const id = tc.closest('tr').dataset.id, r = rows.find(v => String(v[cfg.rowKey]) === id); r[tc.dataset.gCell] = tc.checked; emit('dl:grid-cell', {id, key: tc.dataset.gCell, value: tc.checked}); return; }
      const f = e.target.closest('[data-g-filter]'); if (f) { st.filters[f.dataset.gFilter] = f.value; st.page = 1; return redraw(); }
      const c = e.target.closest('[data-g-col]'); if (c) { const k = c.dataset.gCol; st.hidden = c.checked ? st.hidden.filter(v => v !== k) : [...st.hidden, k]; if (!shown().length) { st.hidden = st.hidden.filter(v => v !== k); } return redraw(); }
      const g = e.target.closest('[data-g-group]'); if (g) { st.group = g.value; st.collapsed = []; return redraw(); }
      if (e.target.matches('[data-g-lines-n]')) { st.linesN = Math.max(1, Math.min(5, +e.target.value || 1)); return redraw(); }
      if (e.target.matches('[data-g-goto]')) { st.page = Math.max(1, +e.target.value || 1); drawBody(); }
    });
    root.addEventListener('dblclick', e => { const tr = e.target.closest('.dl-grid__row, .dl-grid__card'); if (!tr || e.target.closest('button,input,label')) return; const r = rows.find(v => String(v[cfg.rowKey]) === tr.dataset.id); if (emit('dl:grid-open', {id: tr.dataset.id, action: 'open'})) toast('Buka: ' + (r.name || tr.dataset.id) + ' (klik 2x)'); });
    document.addEventListener('click', e => { if (!e.target.closest('[data-g-pop]')) qq('.dl-pop2__panel').forEach(p => p.classList.remove('is-open')); });

    // Export = kolom yang sedang tampil, semua baris hasil saringan (bukan hanya halaman ini).
    function exportCsv() {
      const vis = shown(), list = sorted(filtered()), cellTxt = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
      const csv = [vis.map(c => cellTxt(c.label)).join(';'), ...list.map(r => vis.map(c => cellTxt(c.type === 'identity' && c.code ? `${val(r, c.key)} (${val(r, c.code)})` : val(r, c.key))).join(';'))].join('\r\n');
      const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + csv], {type: 'text/csv'})); a.download = cfg.id + '.csv'; a.click();
      toast(`Export ${fmt.number(list.length)} baris × ${vis.length} kolom (kolom yang tampil).`, 'success');
    }

    root.dlGrid = {rows, cfg, redraw, setRows: list => { rows.splice(0, rows.length, ...list); st.page = 1; sel.clear(); redraw(); }};
    redraw();
  }


  /* ===================== SMALL COMPONENTS (lanjutan) ===================== */
  // Mask: 9 = digit, karakter lain disisipkan apa adanya.
  const applyMask = (pat, d) => { let out = '', j = 0; for (const ch of pat) { if (j >= d.length) break; out += ch === '9' ? d[j++] : ch; } return out; };
  const fieldErr = (el, msg) => { const f = el.closest('.dl-field'); if (!f) return; f.classList.toggle('is-invalid', !!msg); const e = $(f, '[data-dl-field-error]'); if (e) e.textContent = msg || ''; };

  // Accordion: satu kode untuk vertikal & horizontal; aturan buka/tutup dari grupnya.
  document.addEventListener('click', e => {
    const h = e.target.closest('.dl-accordion__head'); if (!h) return;
    const acc = h.parentElement, grp = acc.parentElement.closest('[data-dl-accgroup]'), horiz = grp && grp.classList.contains('dl-accgroup--horizontal');
    const open = !acc.classList.contains('is-open');
    if (!open && horiz) return;
    if (open && grp && grp.dataset.multiple !== '1') $$(grp, ':scope > .dl-accordion.is-open').forEach(a => { a.classList.remove('is-open'); $(a, '.dl-accordion__head').setAttribute('aria-expanded', 'false'); });
    acc.classList.toggle('is-open', open); h.setAttribute('aria-expanded', String(open));
  });
  function initAccGroup(g) {
    if (g.classList.contains('dl-accgroup--horizontal') && !$(g, ':scope > .dl-accordion.is-open')) { const a = $(g, ':scope > .dl-accordion'); if (a) a.classList.add('is-open'); }
  }

  // Slider: angka / tanggal, satu gagang / rentang, horizontal / vertikal.
  function initSlider(sl) {
    const [ia, ib] = $$(sl, '.dl-slider__input'), ha = $(sl, '[data-dl-slider-a]'), hb = $(sl, '[data-dl-slider-b]'), out = $(sl, '[data-dl-slider-value]');
    const min = +ia.min, max = +ia.max, date = sl.dataset.kind === 'date';
    const s0 = date ? Date.parse(sl.dataset.start + 'T00:00:00Z') : 0;
    const iso = n => new Date(s0 + n * 86400000).toISOString().slice(0, 10);
    const show = n => date ? fmt.date(iso(n) + 'T12:00:00+07:00') : sl.dataset.format.replace('{v}', idNum(n));
    const pct = v => max === min ? 0 : (v - min) / (max - min) * 100;
    const u = e => {
      if (ib && +ia.value > +ib.value) { if (e && e.target === ib) ib.value = ia.value; else ia.value = ib.value; }
      const a = +ia.value, b = ib ? +ib.value : a;
      sl.style.setProperty('--a', ib ? pct(a) : 0); sl.style.setProperty('--b', pct(b));
      ia.style.zIndex = ib && a >= max - (max - min) * 0.02 ? 3 : 1;
      ha.value = date ? iso(a) : a; if (hb) hb.value = date ? iso(b) : b;
      out.textContent = ib ? `${show(a)} – ${show(b)}` + (date ? ` · ${b - a + 1} hari` : '') : show(a);
    };
    [ia, ib].filter(Boolean).forEach(i => i.addEventListener('input', u)); u();
  }

  // Rating: bintang penuh / setengah, klik yang sama = kosongkan, readonly tanpa interaksi.
  function initRating(rt) {
    const stars = $$(rt, '.dl-rating__star'), hidden = $(rt, '[data-dl-value]'), max = +rt.dataset.max, half = rt.dataset.half === '1';
    const paint = v => { stars.forEach(s => { const i = +s.dataset.value; s.classList.toggle('is-active', i <= v); s.classList.toggle('is-half', i - 0.5 === v); }); };
    const text = v => { $(rt, '[data-dl-rating-text]').textContent = `${String(v).replace('.', ',')} / ${max}`; rt.setAttribute('aria-valuenow', v); };
    const at = (s, e) => { const i = +s.dataset.value; return half && e.offsetX < s.offsetWidth / 2 ? i - 0.5 : i; };
    paint(+hidden.value); text(+hidden.value);
    if (rt.classList.contains('is-readonly')) return;
    stars.forEach(s => {
      s.addEventListener('click', e => { const v = at(s, e); hidden.value = v === +hidden.value ? 0 : v; paint(+hidden.value); text(+hidden.value); hidden.dispatchEvent(new Event('change', {bubbles: true})); });
      s.addEventListener('mousemove', e => paint(at(s, e)));
    });
    rt.addEventListener('mouseleave', () => paint(+hidden.value));
  }

  // Tanda tangan: goresan disimpan per garis (untuk Undo), digambar ulang saat ukuran berubah.
  function initSignature(box) {
    const cv = $(box, 'canvas'), ctx = cv.getContext('2d'), hidden = $(box, '[data-dl-value]'), ph = $(box, '[data-dl-sign-ph]'), st = $(box, '[data-dl-sign-status]');
    const ro = box.classList.contains('is-readonly'); let strokes = [], cur = null, bg = null;
    const ink = () => getComputedStyle(box).getPropertyValue('--dl-ink-strong').trim() || '#111';
    const draw = () => {
      const r = cv.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
      cv.width = r.width * dpr; cv.height = r.height * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      if (bg) ctx.drawImage(bg, 0, 0, r.width, r.height);
      ctx.lineCap = ctx.lineJoin = 'round'; ctx.strokeStyle = ink();
      strokes.forEach(p => { ctx.lineWidth = 2.2; ctx.beginPath(); ctx.moveTo(p[0][0], p[0][1]); for (let i = 1; i < p.length - 1; i++) ctx.quadraticCurveTo(p[i][0], p[i][1], (p[i][0] + p[i + 1][0]) / 2, (p[i][1] + p[i + 1][1]) / 2); const l = p[p.length - 1]; ctx.lineTo(l[0], l[1] + (p.length === 1 ? 0.1 : 0)); ctx.stroke(); });
    };
    const sync = () => {
      const has = strokes.length > 0 || !!bg;
      ph.hidden = has; st.textContent = has ? 'Sudah ditandatangani' : 'Belum ditandatangani'; box.classList.toggle('is-signed', has);
      hidden.value = has ? cv.toDataURL('image/png') : ''; hidden.dispatchEvent(new Event('change', {bubbles: true}));
    };
    const pos = e => { const r = cv.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; };
    if (!ro) {
      cv.addEventListener('pointerdown', e => { cv.setPointerCapture(e.pointerId); cur = [pos(e)]; strokes.push(cur); draw(); });
      cv.addEventListener('pointermove', e => { if (!cur) return; cur.push(pos(e)); draw(); });
      const end = () => { if (cur) { cur = null; sync(); } };
      cv.addEventListener('pointerup', end); cv.addEventListener('pointercancel', end);
      $(box, '[data-dl-sign-undo]').addEventListener('click', () => { if (strokes.length) strokes.pop(); else bg = null; draw(); sync(); });
      $(box, '[data-dl-sign-clear]').addEventListener('click', () => { strokes = []; bg = null; draw(); sync(); });
    }
    $(box, '[data-dl-sign-png]').addEventListener('click', () => { if (!hidden.value) { toast('Belum ada tanda tangan.', 'warn'); return; } const a = document.createElement('a'); a.href = hidden.value; a.download = 'tanda-tangan.png'; a.click(); });
    if (hidden.value) { const im = new Image(); im.onload = () => { bg = im; draw(); sync(); }; im.src = hidden.value; }
    new ResizeObserver(draw).observe(cv); draw(); if (!hidden.value) sync();
  }

  // Kartu kredit: [kode, awalan, kelompok digit, panjang sah, digit CVV, ikon FA, nama].
  const CC = [
    ['amex', /^3[47]/, [4, 6, 5], [15], 4, 'fa-brands fa-cc-amex', 'American Express'],
    ['diners', /^3(0[0-5]|[689])/, [4, 6, 4], [14], 3, 'fa-brands fa-cc-diners-club', 'Diners Club'],
    ['visa', /^4/, [4, 4, 4, 4, 3], [13, 16, 19], 3, 'fa-brands fa-cc-visa', 'Visa'],
    ['mastercard', /^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/, [4, 4, 4, 4], [16], 3, 'fa-brands fa-cc-mastercard', 'Mastercard'],
    ['jcb', /^35/, [4, 4, 4, 4, 3], [16, 19], 3, 'fa-brands fa-cc-jcb', 'JCB'],
    ['discover', /^(6011|65|64[4-9])/, [4, 4, 4, 4, 3], [16, 19], 3, 'fa-brands fa-cc-discover', 'Discover'],
    ['unionpay', /^62/, [4, 4, 4, 4, 3], [16, 17, 18, 19], 3, 'fa-solid fa-credit-card', 'UnionPay'],
  ];
  const luhn = d => { let s = 0; for (let i = 0; i < d.length; i++) { let x = +d[d.length - 1 - i]; if (i % 2) { x *= 2; if (x > 9) x -= 9; } s += x; } return s % 10 === 0; };
  function initCreditCard(box) {
    const num = $(box, '[data-dl-cc-num]'), exp = $(box, '[data-dl-cc-exp]'), cvv = $(box, '[data-dl-cc-cvv]'), hol = $(box, '[data-dl-cc-holder]');
    const q = s => $(box, s), brandIn = q('[data-dl-cc-brand]');
    let brand = null, caseIdx = 2;
    const upd = () => {
      const d = num.value.replace(/\D/g, '');
      brand = CC.find(b => b[1].test(d)) || null;
      const groups = brand ? brand[2] : [4, 4, 4, 4, 3], maxLen = brand ? Math.max(...brand[3]) : 19, dd = d.slice(0, maxLen);
      let out = [], k = 0; for (const g of groups) { if (k >= dd.length) break; out.push(dd.slice(k, k + g)); k += g; }
      num.value = out.join(' ');
      brandIn.value = brand ? brand[0] : '';
      q('[data-dl-cc-icon]').className = (brand ? brand[5] : 'fa-regular fa-credit-card') + ' dl-cc__icon';
      q('[data-dl-cc-brandname]').textContent = brand ? brand[6] : '';
      cvv.maxLength = brand ? brand[4] : 3; cvv.placeholder = '•'.repeat(cvv.maxLength);
      const card = q('[data-dl-cc-card]');
      if (card) {
        card.dataset.brand = brand ? brand[0] : '';
        q('[data-dl-cc-logo]').className = (brand ? brand[5] : 'fa-solid fa-credit-card') + ' dl-cc__logo';
        const mask = groups.map((g, i) => { const part = dd.slice(groups.slice(0, i).reduce((a, b) => a + b, 0)).slice(0, g); return part.padEnd(g, '•'); });
        let used = 0; const shownGroups = mask.filter((_, i) => { const st = used; used += groups[i]; return st < Math.max(dd.length, (brand ? Math.min(...brand[3]) : 16)); });
        q('[data-dl-cc-shown]').textContent = shownGroups.join(' ');
        q('[data-dl-cc-exp-shown]').textContent = exp.value || 'MM/YY';
        if (hol) q('[data-dl-cc-holder-shown]').textContent = hol.value.toUpperCase() || 'NAMA PEMEGANG';
      }
    };
    const check = () => {
      const d = num.value.replace(/\D/g, ''), msgs = [];
      if (d) {
        if (!brand) msgs.push('Jenis kartu tidak dikenal');
        else if (!brand[3].includes(d.length)) msgs.push(`Nomor ${brand[6]} harus ${brand[3].join(' / ')} digit`);
        else if (!luhn(d)) msgs.push('Nomor kartu tidak valid (cek kembali angkanya)');
      }
      const m = exp.value.match(/^(\d{2})\/(\d{2})$/);
      if (exp.value && !m) msgs.push('Masa berlaku harus MM/YY');
      else if (m) {
        const now = new Date(new Date().toLocaleString('en-US', {timeZone: TZ()})), mm = +m[1], yy = 2000 + +m[2];
        if (mm < 1 || mm > 12) msgs.push('Bulan berlaku 01–12');
        else if (yy < now.getFullYear() || yy === now.getFullYear() && mm < now.getMonth() + 1) msgs.push('Kartu sudah kedaluwarsa');
      }
      if (cvv.value && cvv.value.length !== cvv.maxLength) msgs.push(`CVV harus ${cvv.maxLength} digit`);
      fieldErr(box, msgs.join(' · '));
    };
    num.addEventListener('input', upd);
    exp.addEventListener('input', e => {
      let d = exp.value.replace(/\D/g, '').slice(0, 4);
      if (d.length === 1 && d > '1') d = '0' + d;
      exp.value = d.length > 2 || (d.length === 2 && e.inputType !== 'deleteContentBackward') ? d.slice(0, 2) + '/' + d.slice(2) : d;
      upd();
    });
    cvv.addEventListener('input', () => { cvv.value = cvv.value.replace(/\D/g, '').slice(0, cvv.maxLength); });
    if (hol) {
      const cb = q('[data-dl-cc-case]');
      cb.addEventListener('click', () => { caseIdx = (caseIdx + 1) % 3; cb.textContent = CASE_LABEL[CASES[caseIdx]]; hol.value = applyCase(hol.value, CASES[caseIdx]); upd(); });
      hol.addEventListener('input', () => { const p = hol.selectionStart; hol.value = applyCase(hol.value, CASES[caseIdx]); try { hol.setSelectionRange(p, p); } catch (e) {} upd(); });
    }
    [num, exp, cvv].forEach(i => i.addEventListener('blur', check));
    upd();
  }

  // Telepon per negara: aturan dari Devlib\PhoneFormat (data-countries).
  function initPhone(box) {
    const C = JSON.parse(box.dataset.countries), kind = box.dataset.kind, inp = $(box, '[data-dl-phone]'), hid = $(box, '[data-dl-phone-value]'), cc = $(box, '[data-dl-prefix-value]');
    const f = box.closest('.dl-field'), read = f && $(f, '[data-dl-phone-read]');
    const rules = c => kind === 'any' ? [...C[c].mobile, ...C[c].landline] : C[c][kind];
    const pick = (c, d) => rules(c).find(r => new RegExp(r[0]).test(d));
    const clean = (c, v) => {
      let d = v.replace(/\D/g, ''); const dial = C[c].dial.slice(1);
      if (/^\s*(\+|00)/.test(v)) { d = d.replace(/^00/, ''); if (d.startsWith(dial)) d = d.slice(dial.length); }
      if (C[c].trunk && d.startsWith('0')) d = d.slice(1);
      return d;
    };
    const kindName = (c, r) => C[c].mobile.includes(r) ? 'seluler' : 'rumah/kantor';
    let last = { d: '', r: null };
    const upd = () => {
      const c = cc.value, d = clean(c, inp.value), r = pick(c, d), rule = r || rules(c)[0];
      const alts = rule[1].split('|'), len = m => (m.match(/9/g) || []).length, dd = d.slice(0, Math.max(...alts.map(len)));
      inp.value = applyMask(alts.find(m => len(m) >= dd.length) || alts[alts.length - 1], dd);
      hid.value = dd ? C[c].dial + dd : '';
      inp.placeholder = C[c].example[kind === 'landline' ? 'landline' : 'mobile'];
      if (read) read.textContent = dd ? `Terbaca: ${C[c].dial} ${inp.value}` + (C[c].trunk ? ` · lokal 0${inp.value}` : '') + ` · ${C[c].name}${r ? ', ' + kindName(c, r) : ''}` : '';
      last = { d: dd, r };
    };
    const check = () => {
      const c = cc.value, { d, r } = last, what = kind === 'landline' ? 'telepon rumah/kantor' : kind === 'mobile' ? 'HP' : 'telepon';
      fieldErr(box, !d ? '' : !r ? `Nomor ${what} ${C[c].name} tidak sesuai pola, contoh ${C[c].dial} ${inp.placeholder}` : d.length < r[2] ? `Terlalu pendek: minimal ${r[2]} digit setelah ${C[c].dial}` : '');
    };
    inp.addEventListener('input', upd); inp.addEventListener('blur', check);
    $$(box, '.dl-dd__item').forEach(it => it.addEventListener('click', () => { upd(); if (last.d) check(); }));
    if (hid.value) inp.value = hid.value;
    upd();
  }



  /* ============================ LINES (detail dokumen) ============================ */
  function initLines(el) {
    const cfg = JSON.parse(el.dataset.config), cols = cfg.columns, name = cfg.name, ro = cfg.readonly;
    let rows = JSON.parse(el.dataset.rows || '[]');
    const money = v => (+v || 0).toLocaleString('id-ID');
    const raw = v => String(v ?? '').replace(/\./g, '').replace(',', '.');
    const blank = () => Object.fromEntries(cols.map(c => [c.key, c.default ?? '']));
    const filled = r => cols.some(c => !c.formula && c.type !== 'display' && String(r[c.key] ?? '').trim() !== '');
    // Rumus aman: hanya nama kolom, angka, dan + - * / ( ).
    cols.forEach(c => {
      if (!c.formula) return;
      if (!/^[\w\s+\-*/().]+$/.test(c.formula)) throw new Error('formula tidak sah: ' + c.formula);
      const body = c.formula.replace(/[A-Za-z_]\w*/g, k => { if (!cols.some(x => x.key === k)) throw new Error('kolom tidak dikenal: ' + k); return `(+r[${JSON.stringify(k)}]||0)`; });
      c.fn = new Function('r', 'return ' + body);
    });
    const calc = r => cols.forEach(c => { if (c.fn) r[c.key] = Math.round(c.fn(r) * 100) / 100; });
    const num = c => ['number', 'money'].includes(c.type) || !!c.fn;
    const show = (c, v) => (v === '' || v == null) && !c.fn ? '' : c.type === 'money' || (c.fn && c.type !== 'number') ? money(v) : c.type === 'number' ? (v === '' ? '' : (+v).toLocaleString('id-ID')) : v;

    function cell(c, r, i) {
      const v = r[c.key] ?? '', req = c.required ? ' data-req' : '';
      if (c.fn || c.type === 'display' || ro) return `<td class="${num(c) ? 'num' : ''} dl-lines__ro" data-k="${esc(c.key)}">${esc(c.type === 'lookup' ? (r[c.key + '_label'] ?? v) : show(c, v))}</td>`;
      switch (c.type) {
        case 'select': return `<td${req}><select class="dl-lines__in" data-k="${esc(c.key)}"><option value=""></option>${Object.entries(c.options || {}).map(([k, l]) => `<option value="${esc(k)}" ${String(k) === String(v) ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></td>`;
        case 'date': return `<td${req}><input type="date" class="dl-lines__in" data-k="${esc(c.key)}" value="${esc(v)}"></td>`;
        case 'number': case 'money': return `<td class="num"${req}><input type="text" inputmode="decimal" class="dl-lines__in dl-lines__num" data-k="${esc(c.key)}" value="${esc(show(c, v))}"></td>`;
        case 'lookup': return `<td${req} class="dl-lines__lk"><input type="text" class="dl-lines__in" data-k="${esc(c.key)}" data-lk value="${esc(r[c.key + '_label'] ?? '')}" placeholder="Cari..." autocomplete="off"><i class="fa-solid fa-magnifying-glass"></i></td>`;
        default: return `<td${req} class="dl-lines__txt"><input type="text" class="dl-lines__in" data-k="${esc(c.key)}" value="${esc(v)}"><button type="button" class="dl-lines__case" data-l-case tabindex="-1" title="Ganti huruf: kecil / Kapital Tiap Kata / BESAR">aa</button></td>`;
      }
    }
    const head = () => { $(el, '[data-l-head]').innerHTML = `<tr><th class="dl-lines__no">#</th>${cols.map(c => `<th class="${num(c) ? 'num' : ''}" ${c.width ? `style="width:${esc(c.width)}"` : ''}>${esc(c.label)}${c.required ? ' <span class="dl-field__req">*</span>' : ''}</th>`).join('')}${ro ? '' : '<th class="dl-lines__act"></th>'}</tr>`; };
    function body() {
      $(el, '[data-l-body]').innerHTML = rows.map((r, i) => `<tr data-i="${i}"><td class="dl-lines__no">${i + 1}</td>${cols.map(c => cell(c, r, i)).join('')}${ro ? '' : `<td class="dl-lines__act"><button type="button" class="dl-iconbtn dl-iconbtn--plain" data-l-dup title="Duplikat baris"><i class="fa-solid fa-copy"></i></button><button type="button" class="dl-iconbtn dl-iconbtn--plain dl-iconbtn--danger" data-l-del title="Hapus baris"><i class="fa-solid fa-trash-can"></i></button></td>`}</tr>`).join('')
        || `<tr><td colspan="${cols.length + 2}" class="dl-lines__empty">Belum ada baris. Klik <b>${esc($(el, '[data-l-add]')?.textContent.trim() || '+ Tambah Baris')}</b>.</td></tr>`;
      refresh();
    }
    // Hitung ulang rumus, jumlah kolom, total dokumen, dan input tersembunyi (tanpa merender ulang input yang sedang diketik).
    function refresh() {
      rows.forEach((r, i) => { calc(r); cols.filter(c => c.fn).forEach(c => { const td = $(el, `tr[data-i="${i}"] td[data-k="${c.key}"]`); if (td) td.textContent = show(c, r[c.key]); }); });
      const sums = cols.filter(c => c.sum);
      $(el, '[data-l-foot]').innerHTML = sums.length && rows.length ? `<tr><td></td>${cols.map((c, i) => c.sum ? `<td class="num"><b>${esc(show(c, rows.reduce((t, r) => t + (+r[c.key] || 0), 0)))}</b></td>` : `<td>${i === 0 ? '<b>Jumlah</b>' : ''}</td>`).join('')}${ro ? '' : '<td></td>'}</tr>` : '';
      $(el, '[data-l-count]').textContent = `${rows.filter(filled).length} baris`;
      const t = total();
      if (cfg.totals && cfg.totals.of) Object.entries(t).forEach(([k, v]) => { const b = $(el, `[data-l-t="${k}"]`); if (b) b.textContent = 'Rp ' + money(v); });
      $(el, '[data-l-inputs]').innerHTML = rows.filter(filled).map((r, i) => Object.entries(r).map(([k, v]) => `<input type="hidden" name="${esc(name)}[${i}][${esc(k)}]" value="${esc(v)}">`).join('')).join('');
    }
    function total() {
      const of = cfg.totals && cfg.totals.of, sub = rows.reduce((t, r) => t + (+r[of] || 0), 0);
      const d = $(el, '[data-l-disc]'), disc = d ? +raw(d.value) || 0 : 0, dpp = sub - disc;
      const p = $(el, '[data-l-ppn]'), ppn = p && p.checked ? Math.round(dpp * (+cfg.totals.ppn || 0)) / 100 : 0;
      return {sub, dpp, ppn, grand: dpp + ppn};
    }
    const changed = () => el.dispatchEvent(new Event('change', {bubbles: true}));
    const rowOf = t => rows[+t.closest('tr').dataset.i];
    const colOf = k => cols.find(c => c.key === k);
    const add = (focus = true) => { rows.push(blank()); body(); changed(); if (focus) { const f = $(el, `tr[data-i="${rows.length - 1}"] .dl-lines__in`); if (f) f.focus(); } };

    el.addEventListener('input', e => {
      const t = e.target;
      if (t.matches('[data-l-disc]')) { t.value = t.value.replace(/[^\d]/g, '') ? (+t.value.replace(/[^\d]/g, '')).toLocaleString('id-ID') : ''; return refresh(); }
      if (!t.matches('.dl-lines__in') || t.dataset.lk !== undefined) return;
      const c = colOf(t.dataset.k), r = rowOf(t);
      if (c.type === 'text' && t.dataset.case) { const p = t.selectionStart; t.value = applyCase(t.value, t.dataset.case); try { t.setSelectionRange(p, p); } catch (x) {} }
      r[c.key] = num(c) ? raw(t.value) : t.value;
      t.closest('td').classList.remove('is-invalid');
      refresh();
    });
    el.addEventListener('change', e => { if (e.target.matches('select.dl-lines__in, input[type=date].dl-lines__in')) { rowOf(e.target)[e.target.dataset.k] = e.target.value; e.target.closest('td').classList.remove('is-invalid'); refresh(); } if (e.target.matches('[data-l-ppn]')) refresh(); });
    el.addEventListener('focusin', e => { const t = e.target; if (t.classList.contains('dl-lines__num')) { t.value = rowOf(t)[t.dataset.k] ?? ''; t.select(); } });
    el.addEventListener('focusout', e => { const t = e.target; if (t.classList.contains('dl-lines__num')) t.value = show(colOf(t.dataset.k), rowOf(t)[t.dataset.k] ?? ''); });
    el.addEventListener('keydown', e => {
      const t = e.target; if (e.key !== 'Enter' || !t.matches('.dl-lines__in') || t.dataset.lk !== undefined) return;
      e.preventDefault();
      const i = +t.closest('tr').dataset.i, next = $(el, `tr[data-i="${i + 1}"] [data-k="${t.dataset.k}"]`);
      if (next) next.focus(); else if (!ro) { add(false); $(el, `tr[data-i="${i + 1}"] [data-k="${t.dataset.k}"]`)?.focus(); }
    });
    el.addEventListener('click', e => {
      const x = e.target;
      if (x.closest('[data-l-add]')) return add();
      if (x.closest('[data-l-dup]')) { const i = +x.closest('tr').dataset.i; rows.splice(i + 1, 0, {...rows[i]}); body(); changed(); return; }
      const del = x.closest('[data-l-del]');
      if (del) {
        const i = +del.closest('tr').dataset.i, go = () => { rows.splice(i, 1); body(); changed(); };
        if (filled(rows[i])) confirmBox({message: `Hapus baris ${i + 1}?`, tone: 'danger'}).then(ok => ok && go()); else go();
        return;
      }
      const cb = x.closest('[data-l-case]');
      if (cb) { const inp = cb.previousElementSibling, idx = (CASES.indexOf(inp.dataset.case || 'none') + 1) % 3; inp.dataset.case = CASES[idx]; cb.textContent = CASE_LABEL[CASES[idx]]; inp.value = applyCase(inp.value, CASES[idx]); inp.dispatchEvent(new Event('input', {bubbles: true})); inp.focus(); }
    });

    // Lookup di dalam sel: ketik untuk mencari, pilih -> kolom lain di baris terisi (fill).
    let pop = null;
    const closePop = () => { if (pop) { pop.remove(); pop = null; } };
    function openPop(inp) {
      const c = colOf(inp.dataset.k), L = c.lookup, q = inp.value.trim().toLowerCase();
      const list = L.rows.filter(r => !q || Object.keys(L.columns).some(k => String(r[k] ?? '').toLowerCase().includes(q))).slice(0, 50);
      closePop();
      pop = document.createElement('div'); pop.className = 'dl-lines__pop';
      pop.innerHTML = `<table class="dl-lookup__table"><thead><tr>${Object.values(L.columns).map(h => `<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${list.map(r => `<tr class="dl-dd__item" data-key="${esc(r[L.key])}">${Object.keys(L.columns).map(k => `<td>${esc(r[k] ?? '')}</td>`).join('')}</tr>`).join('') || `<tr><td colspan="9" class="dl-lines__empty">Tidak ditemukan</td></tr>`}</tbody></table>`;
      const rc = inp.getBoundingClientRect();
      pop.style.cssText = `left:${rc.left + scrollX}px;top:${rc.bottom + scrollY + 2}px;min-width:${Math.max(rc.width, 320)}px`;
      pop.addEventListener('mousedown', e => {
        const tr = e.target.closest('tr[data-key]'); if (!tr) return;
        e.preventDefault();
        const src = L.rows.find(r => String(r[L.key]) === tr.dataset.key), row = rowOf(inp);
        row[c.key] = src[L.key]; row[c.key + '_label'] = src[L.label];
        Object.entries(L.fill || {}).forEach(([to, from]) => { row[to] = src[from] ?? ''; });
        const i = +inp.closest('tr').dataset.i; closePop(); body(); changed();
        const nextCol = cols[cols.indexOf(c) + 1]; $(el, `tr[data-i="${i}"] [data-k="${nextCol ? nextCol.key : c.key}"]`)?.focus();
      });
      document.body.appendChild(pop);
    }
    el.addEventListener('focusin', e => { if (e.target.dataset.lk !== undefined) openPop(e.target); });
    el.addEventListener('input', e => { if (e.target.dataset.lk !== undefined) openPop(e.target); });
    el.addEventListener('focusout', e => { if (e.target.dataset.lk !== undefined) { const r = rowOf(e.target), c = colOf(e.target.dataset.k); e.target.value = r[c.key + '_label'] ?? ''; setTimeout(closePop, 120); } });

    // Validasi: sel wajib pada baris yang terisi + jumlah baris minimal. Kembalian = jumlah masalah.
    function validate() {
      let bad = 0;
      $$(el, 'td.is-invalid').forEach(td => td.classList.remove('is-invalid'));
      rows.forEach((r, i) => { if (!filled(r)) return; cols.filter(c => c.required).forEach(c => { if (String(r[c.key] ?? '').trim() === '') { bad++; $(el, `tr[data-i="${i}"] [data-k="${c.key}"]`)?.closest('td').classList.add('is-invalid'); } }); });
      const n = rows.filter(filled).length, err = $(el, '[data-l-err]') || el.appendChild(Object.assign(document.createElement('div'), {className: 'dl-lines__err'}));
      err.dataset.lErr = ''; err.textContent = n < cfg.minRows ? `Minimal ${cfg.minRows} baris terisi.` : bad ? `${bad} sel wajib belum diisi.` : '';
      return bad + (n < cfg.minRows ? 1 : 0);
    }
    el.dlLines = {
      get rows() { return rows.filter(filled).map(r => ({...r})); },
      set(list) { rows = (list || []).map(r => ({...r})); while (rows.length < Math.max(1, cfg.minRows)) rows.push(blank()); body(); $$(el, '[data-l-err]').forEach(x => { x.textContent = ''; }); },
      validate, total,
    };
    head();
    if (!rows.length && !ro) rows.push(blank());
    body();
  }


  /* ============================ APPROVAL ============================ */
  function initApproval(el) {
    const docs = JSON.parse(el.dataset.docs || '[]'), cols = JSON.parse(el.dataset.cols || '{}'), me = el.dataset.me;
    let cur = docs[0] ? docs[0].id : null;
    const pending = () => docs.filter(d => !d.decided);
    const note = $(el, '[name=approval_note]'), noteField = note.closest('.dl-field');
    const rp = v => 'Rp ' + (+v || 0).toLocaleString('id-ID');
    const cell = (k, v) => k.endsWith(':money') ? (+v || 0).toLocaleString('id-ID') : esc(v ?? '');
    const icon = {SUBMITTED: 'fa-paper-plane', APPROVED: 'fa-check', REJECTED: 'fa-ban', HOLD: 'fa-pause'};

    function list() {
      const p = pending();
      $(el, '[data-a-count]').textContent = `${p.length} menunggu`;
      $(el, '[data-a-list]').innerHTML = p.map(d => `<button type="button" class="dl-appr__item ${d.id === cur ? 'is-active' : ''} ${d.held ? 'is-held' : ''}" data-a-id="${esc(d.id)}"><span class="dl-appr__row"><b>${esc(d.no)}</b><b>${rp(d.amount)}</b></span><span class="dl-appr__sub">${esc(d.party || '')} · ${esc(d.date ? fmt.date(d.date + 'T12:00:00+07:00') : '')}</span>${d.held ? '<span class="dl-badge dl-badge--pill dl-badge--neutral">Ditahan</span>' : ''}</button>`).join('')
        || '<div class="dl-empty"><span class="dl-empty__icon"><i class="fa-solid fa-circle-check"></i></span><b class="dl-empty__title">Semua sudah diputuskan</b><p class="dl-empty__msg">Tidak ada dokumen yang menunggu approval Anda.</p></div>';
    }
    function detail() {
      const d = docs.find(x => x.id === cur && !x.decided);
      $(el, '[data-a-detail]').classList.toggle('is-empty', !d);
      if (!d) { $(el, '[data-a-body]').innerHTML = ''; return; }
      const steps = d.steps || [], at = steps.findIndex(s => s.status === 'current');
      const lines = d.lines || [], keys = Object.keys(cols);
      $(el, '[data-a-body]').innerHTML = `
        <div class="dl-appr__title"><div><h3>${esc(d.no)} — ${esc(d.title || '')}</h3><p>Diajukan oleh: ${esc(d.submitted_by || '')}${d.party ? ' · ' + esc(d.party) : ''}</p></div>${at >= 0 ? `<span class="dl-badge dl-badge--pill dl-badge--warn">Step ${at + 1}/${steps.length} — ${esc(steps[at].role)}</span>` : ''}</div>
        <div class="dl-appr__summary">${Object.entries(d.summary || {}).map(([k, v]) => `<div><small>${esc(k)}</small><b>${esc(v)}</b></div>`).join('')}</div>
        ${lines.length ? `<table class="dl-appr__lines"><thead><tr>${keys.map(k => `<th class="${k.endsWith(':money') ? 'num' : ''}">${esc(cols[k])}</th>`).join('')}</tr></thead><tbody>${lines.map(r => `<tr>${keys.map(k => `<td class="${k.endsWith(':money') ? 'num' : ''}">${cell(k, r[k.replace(':money', '')])}</td>`).join('')}</tr>`).join('')}</tbody></table>` : ''}
        <div class="dl-appr__cols">
          <div><p class="dl-appr__cap">Alur Approval</p><ol class="dl-appr__steps">${steps.map(s => `<li class="is-${esc(s.status)}"><span class="dl-appr__dot">${s.status === 'done' ? '<i class="fa-solid fa-check"></i>' : s.status === 'rejected' ? '<i class="fa-solid fa-xmark"></i>' : ''}</span><b>${esc(s.role)}</b>${s.plt ? ' <span class="dl-badge dl-badge--pill dl-badge--violet">PLT</span>' : ''}<small>${esc(s.by || (s.status === 'current' ? 'Menunggu Anda' : 'Menunggu'))}${s.at ? ' · ' + esc(s.at) : ''}</small></li>`).join('')}</ol></div>
          <div><p class="dl-appr__cap">Riwayat Dokumen</p><ul class="dl-appr__hist">${(d.history || []).map(h => `<li><i class="fa-solid ${icon[h.action] || 'fa-circle'}"></i><div><b>${esc(h.action)}</b>${h.plt ? ' <span class="dl-badge dl-badge--pill dl-badge--violet">PLT</span>' : ''}<small>Oleh: ${esc(h.by)} · ${esc(h.at)}</small>${h.note ? `<q>${esc(h.note)}</q>` : ''}</div></li>`).join('')}</ul></div>
        </div>`;
      note.value = ''; fieldErr(note, '');
    }
    function decide(action) {
      const d = docs.find(x => x.id === cur && !x.decided); if (!d) return;
      const n = note.value.trim();
      if (action !== 'approve' && !n) { fieldErr(note, action === 'reject' ? 'Alasan penolakan wajib diisi.' : 'Alasan menahan wajib diisi.'); note.focus(); return; }
      const word = {approve: 'Setujui', reject: 'Tolak', hold: 'Tahan'}[action];
      confirmBox({message: `${word} ${d.no}?`, tone: action === 'reject' ? 'danger' : action === 'hold' ? 'warn' : 'success'}).then(ok => {
        if (!ok) return;
        const now = `${fmt.date()} ${fmt.time()}`, step = (d.steps || []).find(s => s.status === 'current');
        (d.history = d.history || []).push({action: {approve: 'APPROVED', reject: 'REJECTED', hold: 'HOLD'}[action], by: me, at: now, note: n});
        if (action === 'hold') { d.held = true; toast(`${d.no} ditahan.`, 'warn'); detail(); list(); }
        else {
          if (step) Object.assign(step, {status: action === 'approve' ? 'done' : 'rejected', by: me, at: now});
          d.decided = action;
          toast(`${d.no} ${action === 'approve' ? 'disetujui' : 'ditolak'}.`, action === 'approve' ? 'success' : 'danger');
          const next = pending()[0]; cur = next ? next.id : null; list(); detail();
        }
        el.dispatchEvent(new CustomEvent('dl:approval', {bubbles: true, detail: {id: d.id, action, note: n}}));
      });
    }
    el.addEventListener('click', e => {
      const it = e.target.closest('[data-a-id]'); if (it) { cur = isNaN(+it.dataset.aId) ? it.dataset.aId : +it.dataset.aId; list(); detail(); return; }
      const b = e.target.closest('[data-a-do]'); if (b) decide(b.dataset.aDo);
    });
    list(); detail();
  }

  /* ============================ MASTER ============================ */
  // Isi satu field library dari nilai (dipakai Master, bisa dipakai layar lain lewat Devlib.fill).
  function setField(form, name, v) {
    const els = $$(form, `[name="${CSS.escape(name)}"]`); if (!els.length) return;
    const el = els[0], s = v == null ? '' : String(v);
    if (el.type === 'radio') { els.forEach(r => { r.checked = r.value === s; }); return; }
    if (el.type === 'checkbox') { el.checked = v === true || s === '1' || s === 'true'; el.dispatchEvent(new Event('change', {bubbles: true})); return; }
    el.value = s;
    const sel = el.closest('[data-dl-select]');
    if (sel) { const it = $(sel, `.dl-dd__item[data-value="${CSS.escape(s)}"]`); $(sel, '[data-dl-dd-text]').textContent = it ? it.textContent : sel.dataset.empty; return; }
    const lk = el.closest('[data-dl-lookup]');
    if (lk) { const r = JSON.parse(lk.dataset.rows || '[]').find(x => String(x[lk.dataset.key]) === s); $(lk, '[data-dl-dd-text]').textContent = r ? r[lk.dataset.label] : 'Pilih...'; return; }
    const dt = el.closest('[data-dl-date]');
    if (dt) { $(dt, '[data-dl-date-text]').value = s ? fmt.date(s.slice(0, 10) + 'T12:00:00+07:00') : ''; return; }
    const pb = el.closest('[data-dl-phonebox]');
    if (pb) { const i = $(pb, '[data-dl-phone]'); i.value = s; i.dispatchEvent(new Event('input', {bubbles: true})); return; }
    el.dispatchEvent(new Event('input', {bubbles: true}));
  }
  // clear=true: field yang tidak ada di data dikosongkan (untuk data baru).
  function fillForm(form, data, clear = false) {
    const names = new Set($$(form, '[name]').filter(e => !e.closest('[data-l-inputs]')).map(e => e.name).filter(n => n && n !== '_token' && !n.includes('[') && !n.endsWith('_country') && !n.endsWith('_dial')));
    names.forEach(n => { if (n in data) setField(form, n, data[n]); else if (clear) setField(form, n, n.endsWith('_ppn') ? true : n.endsWith('_discount') ? '0' : ''); });
    $$(form, '[data-dl-lines]').forEach(l => { if (l.dlLines && (clear || l.dataset.name in data)) l.dlLines.set(data[l.dataset.name] || []); });
    $$(form, '.dl-field.is-invalid').forEach(f => { f.classList.remove('is-invalid'); const e = $(f, '[data-dl-field-error]'); if (e) e.textContent = ''; });
    dirtyForms.delete(form); form.classList.remove('is-dirty');
  }
  // Input pendamping (mata uang / kode negara) selalu terisi, jadi tidak dihitung sebagai nilai.
  const hasValue = f => $$(f, '[name]:not([data-dl-prefix-value])').some(i => (i.type === 'checkbox' || i.type === 'radio') ? i.checked : i.value.trim() !== '');

  function initMaster(m) {
    const grid = $(m, '[data-dl-grid]'), form = $(m, '[data-m-form]'), gridBox = $(m, '[data-m-grid]'), d = m.dataset;
    let cur = null;
    const show = onForm => { gridBox.hidden = onForm; form.hidden = !onForm; m.scrollIntoView({block: 'start'}); };
    const open = id => {
      const g = grid.dlGrid;
      cur = id == null ? null : g.rows.find(r => String(r[g.cfg.rowKey]) === String(id));
      fillForm(form, cur || {}, true);
      $(form, '[data-m-heading]').textContent = cur ? `Edit ${d.title} · ${cur[d.nameKey] ?? ''}` : `${d.title} baru`;
      $(form, '[data-m-sub]').textContent = cur ? (d.codeKey ? cur[d.codeKey] ?? '' : '') : 'Belum disimpan';
      const pipe = $(form, '[data-m-pipe]');
      if (pipe) { const steps = $$(pipe, '[data-step]'), at = cur ? steps.findIndex(s => s.dataset.step === String(cur[d.pipelineKey])) : 0; steps.forEach((s, i) => { s.classList.toggle('is-done', i < at); s.classList.toggle('is-active', i === at); }); }
      show(true);
      const first = $(form, '.dl-master__body input:not([type=hidden]):not([readonly]), .dl-master__body textarea'); if (first) first.focus({preventScroll: true});
    };
    m.addEventListener('dl:grid-open', e => { e.preventDefault(); open(e.detail.id); });
    m.addEventListener('dl:grid-new', e => { e.preventDefault(); open(null); });
    $$(form, '[data-m-back]').forEach(b => b.addEventListener('click', () => show(false)));
    form.addEventListener('submit', e => {
      e.preventDefault();
      const missing = $$(form, '.dl-field').filter(f => $(f, '.dl-field__req') && !hasValue(f));
      missing.forEach(f => { f.classList.add('is-invalid'); const er = $(f, '[data-dl-field-error]'); if (er) er.textContent = 'Wajib diisi'; });
      const lines = $$(form, '[data-dl-lines]'), lineBad = lines.reduce((n, l) => n + l.dlLines.validate(), 0);
      if (missing.length || lineBad) { dirtyForms.add(form); toast(`${missing.length + lineBad} isian wajib belum diisi.`, 'danger'); (missing[0] || $(form, '.dl-lines td.is-invalid, .dl-lines__err:not(:empty)'))?.scrollIntoView({block: 'center'}); return; }
      const data = {};
      new FormData(form).forEach((v, k) => { if (k !== '_token' && !k.includes('[')) data[k] = v; });
      lines.forEach(l => { data[l.dataset.name] = l.dlLines.rows; if (l.dataset.totalKey) data[l.dataset.totalKey] = l.dlLines.total().grand; if (l.dataset.countKey) data[l.dataset.countKey] = l.dlLines.rows.length; });
      $$(form, 'input[type=checkbox][name]').forEach(c => { data[c.name] = c.checked; });
      const g = grid.dlGrid, isNew = !cur;
      if (isNew) {
        cur = Object.assign({[g.cfg.rowKey]: Math.max(0, ...g.rows.map(r => +r[g.cfg.rowKey] || 0)) + 1}, g.cfg.activeKey ? {[g.cfg.activeKey]: true} : {}, data);
        g.rows.unshift(cur);
      } else Object.assign(cur, data);
      g.redraw();
      toast(`${d.title} ${cur[d.nameKey] ?? ''} ${isNew ? 'ditambahkan' : 'disimpan'}.`, 'success');
      m.dispatchEvent(new CustomEvent('dl:master-save', {bubbles: true, detail: {record: cur, isNew}}));
      show(false);
    });
  }


  /* ============================ WIZARD ============================ */
  // Teks tampil sebuah field library (untuk ringkasan Review).
  function fieldText(f) {
    const q = s => $(f, s);
    if (q('[data-dl-select], [data-dl-lookup]')) return q('[data-dl-dd-text]').textContent.trim();
    if (q('[data-dl-date]')) return q('[data-dl-date-text]').value;
    if (q('[data-dl-phonebox]')) return q('[data-dl-phone]').value ? q('[data-dl-prefix-b]').textContent + ' ' + q('[data-dl-phone]').value : '';
    if (q('[data-dl-toggle]')) return q('[data-dl-toggle-label]').textContent;
    if (q('[data-dl-sign]')) return q('[data-dl-value]').value ? '✓ Sudah ditandatangani' : '';
    const radio = q('input[type=radio]:checked'); if (radio) return radio.closest('label').textContent.trim();
    const checks = $$(f, 'input[type=checkbox]:checked'); if (checks.length) return checks.map(c => c.closest('label')?.textContent.trim() || '✓').join(', ');
    const i = q('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea, select');
    return i ? (i.type === 'password' ? (i.value ? '••••••' : '') : i.value) : '';
  }
  function initWizard(w) {
    const steps = $$(w, '[data-w-step]'), bar = $(w, '[data-w-steps]');
    let at = 0, reached = 0;
    bar.innerHTML = steps.map((s, i) => `<li data-w-go="${i}"><span class="dl-wizard__num">${s.dataset.icon ? `<i class="fa-solid ${esc(s.dataset.icon)}"></i>` : i + 1}</span><span class="dl-wizard__lbl"><small>Langkah ${i + 1}</small>${esc(s.dataset.title)}</span></li>`).join('');
    const invalid = s => $$(s, '.dl-field').filter(f => $(f, '.dl-field__req') && !hasValue(f));
    function summary() {
      const box = $(w, '[data-w-summary]'); if (!box) return;
      box.innerHTML = steps.filter(s => !s.hasAttribute('data-w-review')).map((s, i) => `<div class="dl-wizard__sum"><div class="dl-wizard__sumhead"><b>${esc(s.dataset.title)}</b><button type="button" class="dl-btn dl-btn--link dl-btn--sm" data-w-go="${i}">Ubah</button></div><dl>${$$(s, '.dl-field').map(f => `<dt>${esc($(f, '.dl-field__label')?.textContent || '')}</dt><dd>${esc(fieldText(f)) || '<span class="dl-wizard__none">—</span>'}</dd>`).join('')}</dl></div>`).join('');
    }
    function go(i) {
      at = Math.max(0, Math.min(i, steps.length - 1)); reached = Math.max(reached, at);
      steps.forEach((s, k) => { s.hidden = k !== at; });
      $$(bar, 'li').forEach((li, k) => { li.className = k < at ? 'is-done' : k === at ? 'is-active' : k <= reached ? 'is-reached' : ''; });
      const last = at === steps.length - 1;
      $(w, '[data-w-prev]').disabled = at === 0;
      $(w, '[data-w-next]').hidden = last; $(w, '[data-w-finish]').hidden = !last;
      $(w, '[data-w-pos]').textContent = `${at + 1} / ${steps.length}`;
      if (steps[at].hasAttribute('data-w-review')) summary();
      w.dispatchEvent(new CustomEvent('dl:wizard-step', {bubbles: true, detail: {index: at, title: steps[at].dataset.title}}));
    }
    function check(i) {
      const bad = invalid(steps[i]);
      bad.forEach(f => { f.classList.add('is-invalid'); const e = $(f, '[data-dl-field-error]'); if (e) e.textContent = 'Wajib diisi'; });
      if (bad.length) { toast(`${bad.length} isian wajib di langkah "${steps[i].dataset.title}" belum diisi.`, 'danger'); $(bad[0], 'input:not([type=hidden]), textarea, button')?.focus(); }
      return !bad.length;
    }
    const clearOk = e => { const f = e.target.closest('.dl-field.is-invalid'); if (f && hasValue(f)) { f.classList.remove('is-invalid'); const x = $(f, '[data-dl-field-error]'); if (x) x.textContent = ''; } };
    w.addEventListener('input', clearOk, true); w.addEventListener('change', clearOk, true);
    w.addEventListener('click', e => {
      if (e.target.closest('[data-w-next]')) { if (check(at)) go(at + 1); return; }
      if (e.target.closest('[data-w-prev]')) return go(at - 1);
      const g = e.target.closest('[data-w-go]');
      if (g) { const i = +g.dataset.wGo; if (i <= reached || (i === at + 1 && check(at))) go(i); }
    });
    w.addEventListener('submit', e => {
      for (let i = 0; i < steps.length; i++) if (!check(i)) { e.preventDefault(); go(i); return; }
      if (!w.getAttribute('action')) {
        e.preventDefault();
        const data = {}; new FormData(w).forEach((v, k) => { if (k !== '_token') data[k] = v; });
        toast('Selesai: data terkumpul (pratinjau, tidak dikirim).', 'success');
        w.dispatchEvent(new CustomEvent('dl:wizard-finish', {bubbles: true, detail: data}));
      }
    });
    go(0);
  }


  /* ============================ KANBAN ============================ */
  function initKanban(el) {
    const cols = JSON.parse(el.dataset.columns || '[]'), cards = JSON.parse(el.dataset.cards || '[]'), rules = JSON.parse(el.dataset.transitions || '{}'), me = el.dataset.me;
    const members0 = JSON.parse(el.dataset.members || '[]');
    const board = $(el, '[data-k-board]'), canAddCol = el.dataset.addColumn === '1', canAddCard = el.dataset.addCard === '1';
    let q = '', prio = '', drag = null, seq = Date.now();
    const ini = n => String(n || '?').trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
    const due = c => c.due ? new Date(c.due.replace(' ', 'T') + ':00+07:00') : null;
    const dueTxt = c => { const d = due(c); return d ? `${fmt.date(d)}, ${fmt.time(d).replace(' WIB', '')}` : ''; };
    const allowed = (from, to) => from === to || !rules[from] || rules[from].includes(to);
    const emit = (type, card, from, to) => el.dispatchEvent(new CustomEvent('dl:kanban', {bubbles: true, detail: {type, card, from, to}}));
    const colOf = k => cols.find(c => c.key === k);
    const visible = c => (!prio || c.priority === prio) && (!q || [c.title, c.desc, ...(c.tags || []).map(t => t.label || t)].join(' ').toLowerCase().includes(q));

    function cardHtml(c) {
      const ck = c.checklist || [], done = ck.filter(i => i.done).length, d = due(c), late = d && d < new Date() && !colOf(c.col)?.done;
      return `<div class="dl-kcard" draggable="true" data-k-card="${esc(c.id)}" tabindex="0" title="Klik 2x untuk membuka detail">
        ${(c.tags || []).length ? `<div class="dl-kcard__tags">${c.tags.map(t => `<span class="dl-badge dl-badge--${esc(t.tone || 'accent')}">${esc(t.label || t)}</span>`).join('')}</div>` : ''}
        <div class="dl-kcard__title">${esc(c.title)}</div>
        ${c.desc ? `<div class="dl-kcard__desc">${esc(c.desc)}</div>` : ''}
        <div class="dl-kcard__foot">
          ${c.priority ? `<span class="dl-kcard__prio is-${esc(c.priority.toLowerCase())}">${esc(c.priority)}</span>` : ''}
          ${d ? `<span class="dl-kcard__due ${late ? 'is-late' : ''}"><i class="fa-regular fa-clock"></i> ${esc(dueTxt(c))}</span>` : ''}
          ${ck.length ? `<span class="dl-kcard__ck ${done === ck.length ? 'is-done' : ''}"><i class="fa-regular fa-square-check"></i> ${done}/${ck.length}</span>` : ''}
          ${(c.comments || []).length ? `<span class="dl-kcard__ck"><i class="fa-regular fa-comment"></i> ${c.comments.length}</span>` : ''}
          <span class="dl-kcard__spacer"></span>
          ${(c.members || (c.assignee ? [c.assignee] : [])).map(m => `<span class="dl-kcard__av" title="${esc(m)}">${esc(ini(m))}</span>`).join('')}
          <button type="button" class="dl-kcard__menu" data-k-move title="Pindah ke... (tanpa drag)"><i class="fa-solid fa-ellipsis"></i></button>
        </div></div>`;
    }
    function render() {
      board.innerHTML = cols.map(col => {
        const list = cards.filter(c => c.col === col.key), shown = list.filter(visible), over = col.limit && list.length > col.limit;
        return `<section class="dl-kcol dl-kcol--${esc(col.tone || 'accent')} ${over ? 'is-over' : ''}" data-k-col="${esc(col.key)}">
          <header class="dl-kcol__head"><span class="dl-kcol__title" contenteditable="true" spellcheck="false" data-k-rename title="Klik untuk ganti nama">${esc(col.label)}</span>
            <span class="dl-kcol__count" title="${col.limit ? 'Batas WIP ' + col.limit : ''}">${list.length}${col.limit ? ' / ' + col.limit : ''}</span></header>
          <div class="dl-kcol__body" data-k-drop>${shown.map(cardHtml).join('') || '<div class="dl-kcol__empty">Tidak ada kartu</div>'}</div>
          ${canAddCard ? `<footer class="dl-kcol__foot"><button type="button" class="dl-kcol__add" data-k-add><i class="fa-solid fa-plus"></i> Tambah kartu</button>
            <div class="dl-kcol__composer" hidden><textarea rows="2" placeholder="Judul kartu..." data-k-new-title></textarea><input type="datetime-local" data-k-new-due title="Tenggat">
            <div class="dl-row"><button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-k-new-save>Simpan</button><button type="button" class="dl-btn dl-btn--ghost dl-btn--sm" data-k-new-cancel>Batal</button></div></div></footer>` : ''}
        </section>`;
      }).join('') + (canAddCol ? '<button type="button" class="dl-kanban__addcol" data-k-addcol><i class="fa-solid fa-plus"></i> Tambah kolom</button>' : '');
    }
    function move(card, to, index) {
      const from = card.col;
      if (!allowed(from, to)) { toast(`Tidak boleh pindah dari "${colOf(from).label}" ke "${colOf(to).label}".`, 'danger'); render(); return false; }
      const tgt = colOf(to);
      if (from !== to && tgt.limit && cards.filter(c => c.col === to).length >= tgt.limit) toast(`Kolom "${tgt.label}" melewati batas WIP (${tgt.limit}).`, 'warn');
      cards.splice(cards.indexOf(card), 1);
      card.col = to;
      const inCol = cards.filter(c => c.col === to);
      const before = index == null || index >= inCol.length ? null : inCol[index];
      cards.splice(before ? cards.indexOf(before) : cards.length, 0, card);
      if (from !== to) (card.activity = card.activity || []).unshift({by: me, at: `${fmt.date()} ${fmt.time()}`, text: `memindahkan dari ${colOf(from).label} ke ${tgt.label}`});
      render(); emit('move', card, from, to);
      return true;
    }
    const cardById = id => cards.find(c => String(c.id) === String(id));

    // Drag & drop: posisi sisip = kartu pertama yang titik tengahnya di bawah kursor.
    el.addEventListener('dragstart', e => { const c = e.target.closest('[data-k-card]'); if (!c) return; drag = c.dataset.kCard; c.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', drag); } catch (x) {} });
    el.addEventListener('dragend', () => { drag = null; $$(el, '.is-drag,.is-target').forEach(x => x.classList.remove('is-drag', 'is-target')); $$(el, '.dl-kcard__ph').forEach(x => x.remove()); });
    el.addEventListener('dragover', e => {
      const body = e.target.closest('[data-k-drop]'); if (!body || drag == null) return;
      e.preventDefault();
      $$(el, '.is-target').forEach(x => x.classList.remove('is-target')); body.closest('.dl-kcol').classList.add('is-target');
      const after = $$(body, '[data-k-card]:not(.is-drag)').find(c => { const r = c.getBoundingClientRect(); return e.clientY < r.top + r.height / 2; });
      let ph = $(el, '.dl-kcard__ph'); if (!ph) { ph = document.createElement('div'); ph.className = 'dl-kcard__ph'; }
      if (after) body.insertBefore(ph, after); else body.appendChild(ph);
    });
    el.addEventListener('drop', e => {
      const body = e.target.closest('[data-k-drop]'); if (!body || drag == null) return;
      e.preventDefault();
      const card = cardById(drag), ph = $(body, '.dl-kcard__ph');
      const index = ph ? $$(body, '[data-k-card]:not(.is-drag), .dl-kcard__ph').indexOf(ph) : null;
      move(card, body.closest('[data-k-col]').dataset.kCol, index);
    });

    el.addEventListener('click', e => {
      const x = e.target;
      const mv = x.closest('[data-k-move]');
      if (mv) {
        e.stopPropagation();
        const card = cardById(mv.closest('[data-k-card]').dataset.kCard);
        $$(document, '.dl-kanban__menu').forEach(m => m.remove());
        const m = document.createElement('div'); m.className = 'dl-kanban__menu';
        m.innerHTML = `<div class="dl-kanban__menutitle">Pindah ke</div>` + cols.map(c => `<button type="button" data-k-to="${esc(c.key)}" ${c.key === card.col || !allowed(card.col, c.key) ? 'disabled' : ''}>${esc(c.label)}</button>`).join('');
        const r = mv.getBoundingClientRect(); m.style.cssText = `left:${Math.min(r.left + scrollX, innerWidth - 200)}px;top:${r.bottom + scrollY + 4}px`;
        m.addEventListener('click', ev => { const b = ev.target.closest('[data-k-to]'); if (b) { move(card, b.dataset.kTo); m.remove(); } });
        document.body.appendChild(m); setTimeout(() => document.addEventListener('click', () => m.remove(), {once: true}));
        return;
      }
      const add = x.closest('[data-k-add]'); if (add) { add.hidden = true; const cp = add.nextElementSibling; cp.hidden = false; $(cp, 'textarea').focus(); return; }
      if (x.closest('[data-k-new-cancel]')) { const f = x.closest('.dl-kcol__foot'); $(f, '.dl-kcol__composer').hidden = true; $(f, '[data-k-add]').hidden = false; return; }
      if (x.closest('[data-k-new-save]')) {
        const f = x.closest('.dl-kcol__foot'), t = $(f, '[data-k-new-title]').value.trim(), d = $(f, '[data-k-new-due]').value;
        if (!t) { $(f, '[data-k-new-title]').focus(); return; }
        const card = {id: 'n' + (++seq), col: x.closest('[data-k-col]').dataset.kCol, title: t, due: d ? d.replace('T', ' ') : '', assignee: me, tags: [], checklist: [], comments: [], activity: [{by: me, at: `${fmt.date()} ${fmt.time()}`, text: 'membuat kartu ini'}]};
        cards.push(card); render(); emit('create', card, null, card.col);
        const cf = $(el, `[data-k-col="${card.col}"] [data-k-add]`); cf.click(); return;
      }
      if (x.closest('[data-k-addcol]')) { const key = 'c' + (++seq); cols.push({key, label: 'Kolom baru', tone: 'neutral'}); render(); const t = $(el, `[data-k-col="${key}"] [data-k-rename]`); t.focus(); document.getSelection().selectAllChildren(t); emit('column', null, null, key); }
    });
    el.addEventListener('dblclick', e => { const c = e.target.closest('[data-k-card]'); if (c && !e.target.closest('[data-k-move]')) openCard(cardById(c.dataset.kCard)); });
    el.addEventListener('keydown', e => {
      if (e.target.matches('[data-k-rename]') && e.key === 'Enter') { e.preventDefault(); e.target.blur(); }
      if (e.target.matches('[data-k-new-title]') && e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); e.target.closest('.dl-kcol__foot').querySelector('[data-k-new-save]').click(); }
      if (e.target.matches('[data-k-card]') && e.key === 'Enter') openCard(cardById(e.target.dataset.kCard));
    });
    el.addEventListener('focusout', e => { if (e.target.matches('[data-k-rename]')) { const c = colOf(e.target.closest('[data-k-col]').dataset.kCol), v = e.target.textContent.trim(); if (v && v !== c.label) { c.label = v; emit('column', null, null, c.key); } else e.target.textContent = c.label; } });
    $(el, '[data-k-search]').addEventListener('input', e => { q = e.target.value.trim().toLowerCase(); render(); });
    $$(el, '[data-k-prio] .dl-segment__opt').forEach(b => b.addEventListener('click', () => { prio = b.dataset.value; $$(el, '[data-k-prio] .dl-segment__opt').forEach(x => x.classList.toggle('is-active', x === b)); render(); }));

    // Detail kartu (popup) -- tata letak mockup_UIUX_kanban: bilah aksi + popover, deskripsi klik-untuk-edit,
    // checklist bernama, komentar & aktivitas di kanan.
    const TONES = ['accent', 'success', 'warn', 'violet', 'info', 'danger'];
    const toneOf = l => TONES[[...l].reduce((a, ch) => a + ch.charCodeAt(0), 0) % TONES.length];
    const allLabels = () => [...new Map(cards.flatMap(c => (c.tags || []).map(t => typeof t === 'string' ? {label: t, tone: toneOf(t)} : t)).map(t => [t.label, t])).values()];
    const log = (card, text) => (card.activity = card.activity || []).unshift({by: me, at: `${fmt.date()} ${fmt.time()}`, text});
    function openCard(card) {
      const ov = document.createElement('div'); ov.className = 'dl-mov is-on dl-kmodal';
      let pop = null, editDesc = false, addItem = false, labelQ = '';
      const members = [...new Set([me, ...members0, ...cards.flatMap(c => c.members || (c.assignee ? [c.assignee] : []))])];
      card.members = card.members || (card.assignee ? [card.assignee] : []);
      const popHtml = {
        labels: () => `<input class="dl-kmodal__in" placeholder="Cari / buat label..." data-m-labelq value="${esc(labelQ)}">
          <div class="dl-kpop__list">${allLabels().filter(t => !labelQ || t.label.toLowerCase().includes(labelQ.toLowerCase())).map(t => `<label class="dl-kpop__row"><input type="checkbox" data-m-label="${esc(t.label)}" ${(card.tags || []).some(x => (x.label || x) === t.label) ? 'checked' : ''}><span class="dl-badge dl-badge--${esc(t.tone)}">${esc(t.label)}</span></label>`).join('')}</div>
          ${labelQ && !allLabels().some(t => t.label.toLowerCase() === labelQ.toLowerCase()) ? `<button type="button" class="dl-btn dl-btn--secondary dl-btn--sm" data-m-labelnew>+ Buat label "${esc(labelQ)}"</button>` : ''}`,
        dates: () => `<span class="dl-kpop__lbl">Tenggat (tanggal & jam, WIB)</span><input type="datetime-local" class="dl-kmodal__in" data-m-due value="${esc((card.due || '').replace(' ', 'T'))}">
          <div class="dl-row"><button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-m-duesave>Simpan</button><button type="button" class="dl-btn dl-btn--ghost dl-btn--sm" data-m-dueclear>Hapus tenggat</button></div>`,
        checklist: () => `<span class="dl-kpop__lbl">Judul checklist</span><input class="dl-kmodal__in" data-m-cktitle value="Checklist">
          <button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-m-ckcreate>Tambah</button>`,
        members: () => `<div class="dl-kpop__list">${members.map(m => `<label class="dl-kpop__row"><input type="checkbox" data-m-member="${esc(m)}" ${card.members.includes(m) ? 'checked' : ''}><span class="dl-kcard__av">${esc(ini(m))}</span>${esc(m)}</label>`).join('')}</div>`,
        priority: () => `<div class="dl-kpop__list">${['High', 'Medium', 'Low', ''].map(p => `<button type="button" class="dl-kpop__opt ${p === (card.priority || '') ? 'is-active' : ''}" data-m-prio="${p}">${p ? `<span class="dl-kcard__prio is-${p.toLowerCase()}">${p}</span>` : 'Tanpa prioritas'}</button>`).join('')}</div>`,
        move: () => `<div class="dl-kpop__list">${cols.map(c => `<button type="button" class="dl-kpop__opt ${c.key === card.col ? 'is-active' : ''}" data-m-to="${esc(c.key)}" ${c.key === card.col || !allowed(card.col, c.key) ? 'disabled' : ''}>${esc(c.label)}</button>`).join('')}</div>`,
      };
      const TITLES = {labels: 'Labels', dates: 'Dates', checklist: 'Checklist', members: 'Members', priority: 'Prioritas', move: 'Pindah ke'};
      const actionBtn = (k, icon) => `<div class="dl-kmodal__ag"><button type="button" class="dl-kmodal__abtn ${pop === k ? 'is-on' : ''}" data-m-pop="${k}"><i class="fa-solid ${icon}"></i> ${TITLES[k]}</button>${pop === k ? `<div class="dl-kpop"><div class="dl-kpop__head">${TITLES[k]}<button type="button" data-m-popclose>&times;</button></div>${popHtml[k]()}</div>` : ''}</div>`;
      const draw = () => {
        const ck = card.checklist || [], done = ck.filter(i => i.done).length, pct = ck.length ? Math.round(done / ck.length * 100) : 0, d = due(card);
        const hasCk = card.checklistTitle != null || ck.length;
        ov.innerHTML = `<div class="dl-modal dl-kmodal__box" role="dialog" aria-modal="true">
          <div class="dl-kmodal__head"><i class="fa-regular fa-square-check dl-kmodal__hicon"></i><div class="dl-kmodal__titlewrap"><input class="dl-kmodal__title" value="${esc(card.title)}" data-m-title aria-label="Judul kartu"><small>di kolom <b>${esc(colOf(card.col).label)}</b></small></div><button type="button" class="dl-modal__x" data-m-close aria-label="Tutup">&times;</button></div>
          <div class="dl-kmodal__body">
            <div class="dl-kmodal__main">
              <div class="dl-kmodal__actions">${actionBtn('labels', 'fa-tag')}${actionBtn('dates', 'fa-clock')}${actionBtn('checklist', 'fa-square-check')}${actionBtn('members', 'fa-user')}${actionBtn('priority', 'fa-flag')}${actionBtn('move', 'fa-arrow-right-arrow-left')}</div>
              ${(card.tags || []).length || card.members.length || d || card.priority ? `<div class="dl-kmodal__meta">
                ${card.members.length ? `<div><span>Members</span><div class="dl-row">${card.members.map(m => `<span class="dl-kcard__av" title="${esc(m)}">${esc(ini(m))}</span>`).join('')}</div></div>` : ''}
                ${(card.tags || []).length ? `<div><span>Labels</span><div class="dl-row">${card.tags.map(t => `<span class="dl-badge dl-badge--${esc(t.tone || 'accent')}">${esc(t.label || t)}</span>`).join('')}</div></div>` : ''}
                ${d ? `<div><span>Tenggat</span><b class="${d < new Date() ? 'dl-kcard__due is-late' : ''}">${esc(dueTxt(card))}</b></div>` : ''}
                ${card.priority ? `<div><span>Prioritas</span><span class="dl-kcard__prio is-${esc(card.priority.toLowerCase())}">${esc(card.priority)}</span></div>` : ''}
              </div>` : ''}
              <p class="dl-kmodal__cap"><i class="fa-solid fa-align-left"></i> Deskripsi</p>
              ${editDesc ? `<textarea rows="5" class="dl-kmodal__desc" data-m-desc placeholder="Tambahkan deskripsi yang lebih rinci...">${esc(card.desc || '')}</textarea>
                <div class="dl-row"><button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-m-descsave>Simpan</button><button type="button" class="dl-btn dl-btn--ghost dl-btn--sm" data-m-desccancel>Batal</button></div>`
                : `<div class="dl-kmodal__descview ${card.desc ? '' : 'is-empty'}" data-m-descedit tabindex="0">${esc(card.desc || 'Tambahkan deskripsi yang lebih rinci...')}</div>`}
              ${hasCk ? `<div class="dl-kmodal__ckhead"><p class="dl-kmodal__cap"><i class="fa-regular fa-square-check"></i> ${esc(card.checklistTitle || 'Checklist')}</p><button type="button" class="dl-btn dl-btn--danger-soft dl-btn--sm" data-m-ckdelall>Delete</button></div>
                <div class="dl-kmodal__pct"><span>${pct}%</span><div class="dl-progress__track"><div class="dl-progress__bar ${pct === 100 ? 'is-done' : ''}" style="width:${pct}%"></div></div></div>
                <ul class="dl-kmodal__ck">${ck.map((i, k) => `<li><label class="dl-choice"><input type="checkbox" data-m-ck="${k}" ${i.done ? 'checked' : ''}> <span class="${i.done ? 'is-done' : ''}">${esc(i.text)}</span></label><button type="button" data-m-ckdel="${k}" title="Hapus item"><i class="fa-solid fa-xmark"></i></button></li>`).join('')}</ul>
                ${addItem ? `<textarea rows="2" class="dl-kmodal__in" placeholder="Tambah item" data-m-cknew></textarea><div class="dl-row"><button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-m-ckadd>Tambah</button><button type="button" class="dl-btn dl-btn--ghost dl-btn--sm" data-m-cknewcancel>&times;</button></div>`
                  : '<button type="button" class="dl-kmodal__additem" data-m-ckopen>Tambah item</button>'}` : ''}
            </div>
            <aside class="dl-kmodal__side">
              <p class="dl-kmodal__cap"><i class="fa-regular fa-comments"></i> Komentar & Aktivitas</p>
              <div class="dl-kmodal__cmt"><span class="dl-kcard__av">${esc(ini(me))}</span><textarea rows="1" class="dl-kmodal__in" placeholder="Tulis komentar..." data-m-cmt></textarea></div>
              <ul class="dl-kmodal__act">${[...(card.comments || []).map(c => ({...c, kind: 'c'})), ...(card.activity || [])].map(a => `<li><span class="dl-kcard__av">${esc(ini(a.by))}</span><div><b>${esc(a.by)}</b> ${a.kind === 'c' ? '' : esc(a.text)}${a.kind === 'c' ? `<q>${esc(a.text)}</q>` : ''}<small>${esc(a.at)}</small></div></li>`).join('') || '<li class="dl-kcol__empty">Belum ada aktivitas</li>'}</ul>
            </aside>
          </div>
          <div class="dl-modal__foot"><button type="button" class="dl-btn dl-btn--danger dl-btn--md" data-m-del><i class="fa-solid fa-trash-can"></i> Hapus kartu</button><span class="dl-kcard__spacer"></span><button type="button" class="dl-btn dl-btn--primary dl-btn--md" data-m-close>Tutup</button></div>
        </div>`;
      };
      const keepTitle = () => { const t = $(ov, '[data-m-title]'); if (t && t.value.trim()) card.title = t.value.trim(); };
      const redraw = (focusSel) => { keepTitle(); draw(); if (focusSel) { const f = $(ov, focusSel); if (f) { f.focus(); if (f.setSelectionRange && f.value) f.setSelectionRange(f.value.length, f.value.length); } } };
      const close = () => { keepTitle(); ov.remove(); render(); emit('update', card, card.col, card.col); };
      draw();
      ov.addEventListener('click', e => {
        const x = e.target;
        if (x === ov || x.closest('[data-m-close]')) return close();
        const pb = x.closest('[data-m-pop]'); if (pb) { pop = pop === pb.dataset.mPop ? null : pb.dataset.mPop; labelQ = ''; return redraw(pop === 'labels' ? '[data-m-labelq]' : null); }
        if (x.closest('[data-m-popclose]') || (pop && !x.closest('.dl-kpop'))) { pop = null; redraw(); if (!x.closest('[data-m-descedit],[data-m-ckopen]')) return; }
        if (x.closest('[data-m-labelnew]')) { (card.tags = card.tags || []).push({label: labelQ, tone: toneOf(labelQ)}); log(card, `menambah label ${labelQ}`); labelQ = ''; return redraw('[data-m-labelq]'); }
        if (x.closest('[data-m-duesave]')) { card.due = $(ov, '[data-m-due]').value.replace('T', ' '); log(card, card.due ? `mengatur tenggat ${dueTxt(card)}` : 'menghapus tenggat'); pop = null; return redraw(); }
        if (x.closest('[data-m-dueclear]')) { card.due = ''; log(card, 'menghapus tenggat'); pop = null; return redraw(); }
        if (x.closest('[data-m-ckcreate]')) { card.checklistTitle = $(ov, '[data-m-cktitle]').value.trim() || 'Checklist'; card.checklist = card.checklist || []; pop = null; addItem = true; log(card, `menambah checklist ${card.checklistTitle}`); return redraw('[data-m-cknew]'); }
        const pr = x.closest('[data-m-prio]'); if (pr) { card.priority = pr.dataset.mPrio; pop = null; return redraw(); }
        const to = x.closest('[data-m-to]'); if (to) { pop = null; keepTitle(); if (move(card, to.dataset.mTo)) draw(); return; }
        if (x.closest('[data-m-descedit]')) { editDesc = true; return redraw('[data-m-desc]'); }
        if (x.closest('[data-m-descsave]')) { card.desc = $(ov, '[data-m-desc]').value.trim(); editDesc = false; return redraw(); }
        if (x.closest('[data-m-desccancel]')) { editDesc = false; return redraw(); }
        if (x.closest('[data-m-ckopen]')) { addItem = true; return redraw('[data-m-cknew]'); }
        if (x.closest('[data-m-cknewcancel]')) { addItem = false; return redraw(); }
        if (x.closest('[data-m-ckadd]')) { const i = $(ov, '[data-m-cknew]'); if (i.value.trim()) { (card.checklist = card.checklist || []).push({text: i.value.trim(), done: false}); } return redraw('[data-m-cknew]'); }
        const del = x.closest('[data-m-ckdel]'); if (del) { card.checklist.splice(+del.dataset.mCkdel, 1); return redraw(); }
        if (x.closest('[data-m-ckdelall]')) return confirmBox({message: `Hapus checklist "${card.checklistTitle || 'Checklist'}" beserta semua itemnya?`, tone: 'danger'}).then(ok => { if (ok) { card.checklist = []; card.checklistTitle = null; addItem = false; redraw(); } });
        if (x.closest('[data-m-del]')) confirmBox({message: `Hapus kartu "${card.title}"?`, tone: 'danger'}).then(ok => { if (!ok) return; cards.splice(cards.indexOf(card), 1); ov.remove(); render(); emit('delete', card, card.col, null); });
      });
      ov.addEventListener('input', e => { if (e.target.matches('[data-m-labelq]')) { labelQ = e.target.value; redraw('[data-m-labelq]'); } });
      ov.addEventListener('change', e => {
        const t = e.target;
        if (t.matches('[data-m-ck]')) { card.checklist[+t.dataset.mCk].done = t.checked; redraw(); }
        if (t.matches('[data-m-label]')) { const l = t.dataset.mLabel; card.tags = card.tags || []; if (t.checked) card.tags.push(allLabels().find(x => x.label === l)); else card.tags = card.tags.filter(x => (x.label || x) !== l); redraw(); }
        if (t.matches('[data-m-member]')) { const m = t.dataset.mMember; card.members = t.checked ? [...card.members, m] : card.members.filter(x => x !== m); card.assignee = card.members[0] || ''; log(card, `${t.checked ? 'menambah' : 'melepas'} anggota ${m}`); redraw(); }
      });
      ov.addEventListener('keydown', e => {
        const t = e.target;
        if (e.key === 'Escape') { if (pop) { pop = null; redraw(); } else close(); return; }
        if (e.key !== 'Enter' || e.shiftKey) return;
        if (t.matches('[data-m-title]')) { e.preventDefault(); t.blur(); }
        else if (t.matches('[data-m-cknew]')) { e.preventDefault(); $(ov, '[data-m-ckadd]').click(); }
        else if (t.matches('[data-m-cmt]') && t.value.trim()) { e.preventDefault(); (card.comments = card.comments || []).unshift({by: me, at: `${fmt.date()} ${fmt.time()}`, text: t.value.trim()}); redraw('[data-m-cmt]'); }
        else if (t.matches('[data-m-labelq]') && $(ov, '[data-m-labelnew]')) { e.preventDefault(); $(ov, '[data-m-labelnew]').click(); }
        else if (t.matches('[data-m-descedit]')) { editDesc = true; redraw('[data-m-desc]'); }
      });
      document.body.appendChild(ov); $(ov, '[data-m-title]').focus();
    }
    el.dlKanban = {cards, cols, render, move: (id, to, i) => move(cardById(id), to, i)};
    render();
  }


  /* ============================ TERBILANG ============================ */
  // Sama persis dengan Devlib\Terbilang (PHP). cur = [simbol, nama ID, nama EN, sub ID, sub EN, desimal].
  const W_ID = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
  const W_EN = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
  const W_EN_T = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
  const W_SC_ID = [[1e15, 'kuadriliun'], [1e12, 'triliun'], [1e9, 'miliar'], [1e6, 'juta'], [1e3, 'ribu']];
  const W_SC_EN = [[1e15, 'quadrillion'], [1e12, 'trillion'], [1e9, 'billion'], [1e6, 'million'], [1e3, 'thousand']];
  const wScale = (n, sc, say) => { for (const [d, w] of sc) if (n >= d) return `${say(Math.floor(n / d))} ${w} ${say(n % d)}`.trim(); return ''; };
  const wId = n => n < 12 ? W_ID[n] : n < 20 ? W_ID[n - 10] + ' belas' : n < 100 ? `${W_ID[Math.floor(n / 10)]} puluh ${W_ID[n % 10]}`.trim()
    : n < 200 ? `seratus ${wId(n - 100)}`.trim() : n < 1000 ? `${W_ID[Math.floor(n / 100)]} ratus ${wId(n % 100)}`.trim()
    : n < 2000 ? `seribu ${wId(n - 1000)}`.trim() : wScale(n, W_SC_ID, wId);
  const wEn = n => n < 20 ? W_EN[n] : n < 100 ? W_EN_T[Math.floor(n / 10)] + (n % 10 ? '-' + W_EN[n % 10] : '')
    : n < 1000 ? `${W_EN[Math.floor(n / 100)]} hundred ${wEn(n % 100)}`.trim() : wScale(n, W_SC_EN, wEn);
  function words(value, cur = null, lang = currentLang) {
    const s = String(value ?? '').trim(); if (s === '' || s === '-') return '';
    const en = lang === 'en', say = en ? wEn : wId, neg = s.startsWith('-');
    let [int, frac = ''] = s.replace(/^[-+]/, '').split('.');
    int = int.replace(/\D/g, '').replace(/^0+/, ''); frac = frac.replace(/\D/g, '');
    let out = int === '' ? (en ? 'zero' : 'nol') : say(+int);
    if (cur) {
      out += ' ' + (en ? cur[2] : cur[1]);
      const sub = cur[5] > 0 ? +frac.padEnd(cur[5], '0').slice(0, cur[5]) : 0;
      if (sub > 0) out += (en ? ' and ' : ' dan ') + say(sub) + ' ' + (en ? cur[4] : cur[3]);
    } else if (frac.replace(/0+$/, '')) out += (en ? ' point ' : ' koma ') + [...frac.replace(/0+$/, '')].map(d => d === '0' ? (en ? 'zero' : 'nol') : say(+d)).join(' ');
    out = (neg ? 'minus ' : '') + out;
    return out.charAt(0).toUpperCase() + out.slice(1);
  }

  function initNumberWords(box) {
    const C = JSON.parse(box.dataset.currencies), inp = $(box, '[data-dl-words-input]'), hid = $(box, '[data-dl-words-value]'), out = $(box, '[data-dl-words-text]'), cc = $(box, '[data-dl-prefix-value]');
    const places = () => cc ? C[cc.value][5] : +box.dataset.decimals;
    // Ketik gaya Indonesia: titik = ribuan (otomatis), koma = desimal.
    const upd = () => {
      const p = places(), neg = inp.value.trim().startsWith('-');
      let [a, b] = inp.value.replace(/[^\d,]/g, '').split(',');
      a = (a || '').replace(/^0+(?=\d)/, '').slice(0, 15);
      const hasComma = p > 0 && inp.value.includes(',');
      b = p > 0 ? (b || '').slice(0, p) : '';
      inp.value = (neg ? '-' : '') + (a ? (+a).toLocaleString('id-ID') : (hasComma ? '0' : '')) + (hasComma ? ',' + b : '');
      hid.value = a || b ? (neg ? '-' : '') + (a || '0') + (b ? '.' + b : '') : '';
      out.textContent = hid.value ? words(hid.value, cc ? C[cc.value] : null) : '';
      out.classList.toggle('is-empty', !hid.value);
    };
    inp.addEventListener('input', upd);
    $$(box, '.dl-dd__item').forEach(it => it.addEventListener('click', () => { upd(); hid.dispatchEvent(new Event('change', {bubbles: true})); }));
    if (hid.value) inp.value = String(hid.value).replace('.', ',');
    upd();
  }


  /* ============================ GANTT ============================ */
  function initGantt(el) {
    const cfg = JSON.parse(el.dataset.config), rows = cfg.rows, tasks = cfg.tasks, cats = cfg.categories, PX = cfg.px, S = cfg.start, E = cfg.end, SNAP = cfg.snap / 60;
    const board = $(el, '[data-g2-board]'), changed = new Set();
    let hi = null, fRes = '', fProc = '', pop = null;
    const h = t => { const [a, b] = String(t).split(':'); return +a + (+b || 0) / 60; };
    const hm = x => `${String(Math.floor(x + 1e-9)).padStart(2, '0')}:${String(Math.round((x % 1) * 60) % 60).padStart(2, '0')}`;
    tasks.forEach(t => { t.s = h(t.start); t.e = t.end ? h(t.end) : t.s + (+t.duration || 1); });
    const live = () => el.dataset.mode === 'live';
    const catOf = t => cats[t.process] || {label: t.process || '', tone: 'accent'};
    const rowTasks = k => tasks.filter(t => t.row === k).sort((a, b) => a.s - b.s);
    // Celah idle = jeda >= 1 jam di antara dua task pada resource yang sama.
    const gaps = k => { const out = [], list = rowTasks(k); for (let i = 1; i < list.length; i++) { const a = Math.max(...list.slice(0, i).map(t => t.e)), b = list[i].s; if (b - a >= 1 - 1e-9) out.push([a, b]); } return out; };
    const bigIdle = k => gaps(k).some(([a, b]) => b - a >= cfg.idle);
    const conflict = t => tasks.some(o => o !== t && o.row === t.row && o.s < t.e - 1e-9 && o.e > t.s + 1e-9);
    const W = (E - S) * PX;

    function stats() {
      const risk = tasks.filter(t => t.risk).length, idle = rows.filter(r => bigIdle(r.key)).length;
      $(el, '[data-g2-stats]').innerHTML = [
        ['all', 'Total Task Aktif', tasks.length, 'accent', 'klik untuk reset sorotan'],
        ['none', 'Belum Terjadwal', cfg.unscheduled, 'danger', '—'],
        ['risk', 'Berisiko Telat', risk, 'warn', 'klik untuk sorot di board'],
        ['idle', `Resource Kosong >${cfg.idle} jam`, idle, 'info', 'klik untuk sorot di board'],
      ].map(([k, l, v, tone, sub]) => `<button type="button" class="dl-stat dl-stat--${tone} is-filter ${hi === k ? 'is-active' : ''}" data-g2-hi="${k}"><span class="dl-stat__label">${esc(l)}</span><span class="dl-stat__value">${v}</span><span class="dl-stat__sub">${esc(sub)}</span></button>`).join('');
    }
    function legend() {
      $(el, '[data-g2-legend]').innerHTML = Object.entries(cats).map(([k, c]) => `<span><i class="dl-gantt__sw dl-gantt--${esc(c.tone)}"></i>${esc(c.label)}</span>`).join('')
        + '<span class="dl-gantt__lsep"></span><span><i class="dl-gantt__sw dl-gantt--breakdown"></i>Breakdown</span><span><i class="dl-gantt__sw is-qchold"></i>QC Hold</span><span><i class="dl-gantt__sw is-hot"></i>🔥 Hot Job</span><span class="dl-gantt__lsep"></span><span>▬ garis dalam blok = progress</span>';
    }
    function block(t) {
      const c = catOf(t), cls = t.state === 'breakdown' ? 'breakdown' : c.tone;
      const dim = hi && hi !== 'all' && !(hi === 'risk' ? t.risk : hi === 'idle' ? bigIdle(t.row) : false);
      return `<div class="dl-gantt__blk dl-gantt--${esc(cls)} ${t.state === 'hot' ? 'is-hot' : ''} ${t.state === 'qchold' ? 'is-qchold' : ''} ${conflict(t) ? 'is-conflict' : ''} ${dim ? 'is-dim' : ''} ${hi && !dim && hi !== 'all' ? 'is-match' : ''} ${changed.has(t.id) ? 'is-changed' : ''}"
        style="left:${(t.s - S) * PX}px;width:${Math.max(0.25, t.e - t.s) * PX}px" data-g2-task="${esc(t.id)}" title="${esc(t.label)} · ${hm(t.s)}–${hm(t.e)}">
        <b>${t.state === 'hot' ? '🔥 ' : t.state === 'breakdown' ? '⚠ ' : t.state === 'qchold' ? '🔍 ' : ''}${esc(t.label)}${t.state !== 'breakdown' && c.label ? ' · ' + esc(c.label) : ''}</b>
        <small>${hm(t.s)}–${hm(t.e)}</small><span class="dl-gantt__prog"><span style="width:${+t.progress || 0}%"></span></span>
        ${t.state === 'qchold' || live() ? '' : '<span class="dl-gantt__rz" data-g2-rz></span>'}</div>`;
    }
    function render() {
      const vis = rows.filter(r => (!fRes || r.key === fRes) && (!fProc || rowTasks(r.key).some(t => t.process === fProc)));
      let html = `<div class="dl-gantt__row dl-gantt__head"><div class="dl-gantt__res">${esc(cfg.resourceLabel)}</div><div class="dl-gantt__lane" style="width:${W}px">${Array.from({length: E - S + 1}, (_, i) => `<span style="left:${i * PX}px">${String(S + i).padStart(2, '0')}:00</span>`).join('')}</div></div>`;
      let sec = null;
      vis.forEach(r => {
        if (r.section && r.section !== sec) { sec = r.section; html += `<div class="dl-gantt__sec">${esc(sec)}</div>`; }
        const list = rowTasks(r.key).filter(t => !fProc || t.process === fProc);
        html += `<div class="dl-gantt__row" data-g2-row="${esc(r.key)}"><div class="dl-gantt__res"><b>${esc(r.label)}</b>${r.sub ? `<small><i class="fa-solid fa-user"></i> ${esc(r.sub)}</small>` : ''}</div>
          <div class="dl-gantt__lane" style="width:${W}px;background-size:${PX}px 100%">${fProc ? '' : gaps(r.key).map(([a, b]) => `<div class="dl-gantt__idle" style="left:${(a - S) * PX}px;width:${(b - a) * PX}px">idle</div>`).join('')}${list.map(block).join('')}</div></div>`;
      });
      if (!vis.length) html += '<div class="dl-empty"><b class="dl-empty__title">Tidak ada resource yang cocok dengan filter</b></div>';
      const now = new Date(new Date().toLocaleString('en-US', {timeZone: TZ()})), nh = now.getHours() + now.getMinutes() / 60;
      if (nh >= S && nh <= E) html += `<div class="dl-gantt__now" style="left:calc(var(--dl-gantt-res) + ${(nh - S) * PX}px)" title="Sekarang ${fmt.time()}"></div>`;
      board.innerHTML = html;
      stats();
      const chips = [...(fRes ? [['res', 'Resource: ' + rows.find(r => r.key === fRes).label]] : []), ...(fProc ? [['proc', 'Proses: ' + catOf({process: fProc}).label]] : [])];
      const cb = $(el, '[data-g2-chips]'); cb.hidden = !chips.length;
      cb.innerHTML = chips.map(([k, l]) => `<span class="dl-tags__chip">${esc(l)}<button type="button" data-g2-chip="${k}">&times;</button></span>`).join('') + (chips.length ? '<button type="button" class="dl-btn dl-btn--link" data-g2-chip="all">Reset semua filter</button>' : '');
      const sb = $(el, '[data-g2-submit]'); if (sb) { sb.disabled = !changed.size || live(); sb.dataset.count = changed.size; }
      $(el, '[data-g2-modehelp]').textContent = live() ? 'Mode Live: jadwal hanya bisa dilihat.' : `Mode Simulasi: ${changed.size} perubahan belum dikirim ke jadwal Live sampai Submit. QC Hold terkunci sampai QA memutuskan.`;
    }
    const setSel = (name, v) => { const f = $(el, `[name="${name}"]`); if (f) setField(el, name, v); };
    const task = id => tasks.find(t => String(t.id) === String(id));
    const touch = (t, type) => { changed.add(t.id); el.dispatchEvent(new CustomEvent('dl:gantt', {bubbles: true, detail: {type, task: {...t, start: hm(t.s), end: hm(t.e)}}})); if (conflict(t)) toast(`${t.label} bertabrakan dengan task lain di resource yang sama.`, 'warn'); };

    // Drag & resize (pointer events, snap). Klik tanpa gerak = popover.
    let op = null;
    board.addEventListener('pointerdown', e => {
      const b = e.target.closest('[data-g2-task]'); if (!b || e.button !== 0) return;
      const t = task(b.dataset.g2Task);
      closePop();
      if (live()) return;
      if (t.state === 'qchold') { op = {t, b, x0: e.clientX, locked: true}; return; }
      op = {t, b, x0: e.clientX, s0: t.s, e0: t.e, rz: !!e.target.closest('[data-g2-rz]'), moved: false};
      b.setPointerCapture(e.pointerId);
    });
    board.addEventListener('pointermove', e => {
      if (!op || op.locked) return;
      const dh = (e.clientX - op.x0) / PX; if (Math.abs(e.clientX - op.x0) > 3) op.moved = true; if (!op.moved) return;
      const sn = v => Math.round(v / SNAP) * SNAP;
      if (op.rz) op.t.e = Math.min(E, Math.max(op.t.s + SNAP, op.s0 + sn(op.e0 - op.s0 + dh)));
      else { const d = op.e0 - op.s0; op.t.s = Math.min(E - d, Math.max(S, sn(op.s0 + dh))); op.t.e = op.t.s + d; }
      op.b.style.left = (op.t.s - S) * PX + 'px'; op.b.style.width = (op.t.e - op.t.s) * PX + 'px';
      $(op.b, 'small').textContent = `${hm(op.t.s)}–${hm(op.t.e)}`;
    });
    board.addEventListener('pointerup', e => {
      if (!op) { const b = e.target.closest('[data-g2-task]'); if (b && live()) showPop(task(b.dataset.g2Task), b); return; }
      const o = op; op = null;
      if (o.locked) { if (Math.abs(e.clientX - o.x0) > 3) toast('QC Hold terkunci sampai QA memutuskan.', 'warn'); else showPop(o.t, o.b); return; }
      if (!o.moved) return showPop(o.t, o.b);
      if (o.t.s !== o.s0 || o.t.e !== o.e0) touch(o.t, o.rz ? 'resize' : 'move');
      render();
    });
    function closePop() { if (pop) { pop.remove(); pop = null; } }
    function showPop(t, b) {
      closePop();
      pop = document.createElement('div'); pop.className = 'dl-gantt__pop';
      pop.innerHTML = `<button type="button" class="dl-gantt__popx" aria-label="Tutup">&times;</button><h3>${esc(t.label)}</h3><dl><dt>Resource</dt><dd>${esc(rows.find(r => r.key === t.row)?.label || '')}</dd><dt>Proses</dt><dd>${esc(catOf(t).label)}</dd><dt>Waktu</dt><dd>${hm(t.s)}–${hm(t.e)}</dd><dt>Progress</dt><dd>${+t.progress || 0}%</dd><dt>Status</dt><dd>${esc(t.status || {hot: 'Hot Job', qchold: 'QC Hold', breakdown: 'Breakdown'}[t.state] || 'Terjadwal')}</dd></dl>`;
      const r = b.getBoundingClientRect(); pop.style.cssText = `left:${Math.min(r.left + scrollX, innerWidth - 260)}px;top:${r.bottom + scrollY + 6}px`;
      pop.querySelector('.dl-gantt__popx').addEventListener('click', closePop);
      document.body.appendChild(pop);
    }
    board.addEventListener('dblclick', e => {
      const b = e.target.closest('[data-g2-task]'); if (!b || live()) return;
      closePop();
      const t = task(b.dataset.g2Task);
      if (t.state === 'qchold') { toast('QC Hold terkunci sampai QA memutuskan.', 'warn'); return; }
      const ov = document.createElement('div'); ov.className = 'dl-mov is-on';
      ov.innerHTML = `<div class="dl-modal dl-modal--sm" role="dialog" aria-modal="true"><div class="dl-modal__head"><h3>Ubah Task</h3><button type="button" class="dl-modal__x" data-x>&times;</button></div>
        <div class="dl-modal__body dl-gantt__edit"><label><span>Kode</span><input value="${esc(t.label)}" disabled></label>
        <label><span>Resource</span><select data-e-row>${rows.map(r => `<option value="${esc(r.key)}" ${r.key === t.row ? 'selected' : ''}>${esc(r.label)}${r.section ? ' (' + esc(r.section.split(' ')[0].toLowerCase()) + ')' : ''}</option>`).join('')}</select></label>
        <label><span>Proses</span><select data-e-proc>${Object.entries(cats).map(([k, c]) => `<option value="${esc(k)}" ${k === t.process ? 'selected' : ''}>${esc(c.label)}</option>`).join('')}</select></label></div>
        <div class="dl-modal__foot"><button type="button" class="dl-btn dl-btn--ghost dl-btn--md" data-x>Batal</button><button type="button" class="dl-btn dl-btn--primary dl-btn--md" data-e-save>Simpan</button></div></div>`;
      ov.addEventListener('click', ev => {
        if (ev.target === ov || ev.target.closest('[data-x]')) return ov.remove();
        if (ev.target.closest('[data-e-save]')) { const nr = $(ov, '[data-e-row]').value, np = $(ov, '[data-e-proc]').value; if (nr !== t.row || np !== t.process) { t.row = nr; t.process = np; touch(t, 'reassign'); toast(`${t.label} dipindah ke ${rows.find(r => r.key === nr).label}.`, 'success'); } ov.remove(); render(); }
      });
      document.body.appendChild(ov); $(ov, '[data-e-row]').focus();
    });
    el.addEventListener('click', e => {
      const s = e.target.closest('[data-g2-hi]'); if (s) { const k = s.dataset.g2Hi; hi = k === 'all' || k === 'none' || hi === k ? null : k; return render(); }
      const c = e.target.closest('[data-g2-chip]'); if (c) { if (c.dataset.g2Chip !== 'proc') { fRes = ''; setSel('gantt_resource', ''); } if (c.dataset.g2Chip !== 'res') { fProc = ''; setSel('gantt_process', ''); } return render(); }
      const m = e.target.closest('[data-g2-mode] .dl-segment__opt'); if (m) { el.dataset.mode = m.dataset.value; $$(el, '[data-g2-mode] .dl-segment__opt').forEach(x => x.classList.toggle('is-active', x === m)); closePop(); return render(); }
      if (e.target.closest('[data-g2-submit]')) confirmBox({message: `Kirim ${changed.size} perubahan simulasi ke Master Schedule?`, tone: 'warn'}).then(ok => { if (!ok) return; el.dispatchEvent(new CustomEvent('dl:gantt-submit', {bubbles: true, detail: {tasks: tasks.filter(t => changed.has(t.id)).map(t => ({...t, start: hm(t.s), end: hm(t.e)}))}})); toast(`${changed.size} perubahan dikirim ke Master Schedule.`, 'success'); changed.clear(); render(); });
    });
    el.addEventListener('change', e => {
      if (e.target.name === 'gantt_resource') { fRes = e.target.value; render(); }
      if (e.target.name === 'gantt_process') { fProc = e.target.value; render(); }
    });
    document.addEventListener('pointerdown', e => { if (pop && !e.target.closest('.dl-gantt__pop,[data-g2-task]')) closePop(); });
    legend(); render();
    el.dlGantt = {tasks, rows, render, changed};
  }


  /* ============================ CALENDAR ============================ */
  function initCalendar(el) {
    const events = JSON.parse(el.dataset.events || '[]'), ws = +el.dataset.weekStart, main = $(el, '[data-c-main]');
    const MON = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const DAY = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'], DAYL = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const TONES = ['accent', 'success', 'warn', 'danger', 'violet', 'neutral'];
    const pad = n => String(n).padStart(2, '0');
    const nowW = () => new Date(new Date().toLocaleString('en-US', {timeZone: TZ()}));
    const parse = s => { const [d, t = '00:00'] = String(s).split(/[ T]/); const [y, m, dd] = d.split('-').map(Number), [hh, mi] = t.split(':').map(Number); return new Date(y, m - 1, dd, hh || 0, mi || 0); };
    const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const str = d => `${ymd(d)} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    const same = (a, b) => ymd(a) === ymd(b);
    const addD = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
    const hhmm = d => `${pad(d.getHours())}.${pad(d.getMinutes())}`;
    let seq = Date.now(), view = el.dataset.view || 'week', cur = el.dataset.date ? parse(el.dataset.date) : nowW(), sel = new Date(cur), q = '', drag = null;
    events.forEach(e => { e.s = parse(e.start); e.e = e.end ? parse(e.end) : e.allDay ? new Date(e.s) : new Date(e.s.getTime() + 3600000); e.tone = e.tone || 'accent'; });
    const onDay = d0 => { const d = new Date(d0.getFullYear(), d0.getMonth(), d0.getDate()); return events.filter(e => { const s0 = new Date(e.s.getFullYear(), e.s.getMonth(), e.s.getDate()), e0 = new Date(e.e.getFullYear(), e.e.getMonth(), e.e.getDate()); return d >= s0 && d <= e0; }).sort((a, b) => (b.allDay - a.allDay) || a.s - b.s); };
    const when = e => e.allDay ? 'Sepanjang hari' : `${hhmm(e.s)} – ${hhmm(e.e)} WIB`;
    const match = e => !q || (e.title + ' ' + (e.desc || '')).toLowerCase().includes(q);
    const emit = (type, e) => el.dispatchEvent(new CustomEvent('dl:calendar', {bubbles: true, detail: {type, event: {id: e.id, title: e.title, start: e.allDay ? ymd(e.s) : str(e.s), end: e.allDay ? ymd(e.e) : str(e.e), allDay: !!e.allDay, tone: e.tone, desc: e.desc || ''}}}));
    const weekStartOf = d => addD(new Date(d.getFullYear(), d.getMonth(), d.getDate()), -((d.getDay() - ws + 7) % 7));
    const chip = e => `<div class="dl-cal2__ev dl-cal2--${esc(e.tone)} ${match(e) ? '' : 'is-dim'}" draggable="true" data-c-ev="${esc(e.id)}" title="${esc(e.title)} · ${esc(when(e))}">${e.allDay ? '' : `<small>${hhmm(e.s)}</small> `}${esc(e.title)}</div>`;

    function mini() {
      const box = $(el, '[data-c-mini]'); if (!box) return;
      const first = new Date(sel.getFullYear(), sel.getMonth(), 1), start = weekStartOf(first), today = nowW();
      box.innerHTML = `<div class="dl-cal2__minihead"><b>${MON[sel.getMonth()]} ${sel.getFullYear()}</b><span><button type="button" data-c-mini="-1" aria-label="Bulan sebelumnya">‹</button><button type="button" data-c-mini="1" aria-label="Bulan berikutnya">›</button></span></div>
        <div class="dl-cal2__minigrid">${[0, 1, 2, 3, 4, 5, 6].map(i => `<span class="is-dow">${DAY[(i + ws) % 7][0]}</span>`).join('')}${Array.from({length: 42}, (_, i) => { const d = addD(start, i); return `<button type="button" data-c-go="${ymd(d)}" class="${d.getMonth() !== sel.getMonth() ? 'is-out' : ''} ${same(d, today) ? 'is-today' : ''} ${same(d, cur) ? 'is-sel' : ''} ${onDay(d).length ? 'has-ev' : ''}">${d.getDate()}</button>`; }).join('')}</div>`;
    }
    function title() {
      const t = $(el, '[data-c-title]');
      if (view === 'day') t.textContent = `${DAYL[cur.getDay()]}, ${cur.getDate()} ${MON[cur.getMonth()]} ${cur.getFullYear()}`;
      else if (view === 'week') { const a = weekStartOf(cur), b = addD(a, 6); t.textContent = a.getMonth() === b.getMonth() ? `${a.getDate()} – ${b.getDate()} ${MON[b.getMonth()]} ${b.getFullYear()}` : `${a.getDate()} ${MON[a.getMonth()].slice(0, 3)} – ${b.getDate()} ${MON[b.getMonth()].slice(0, 3)} ${b.getFullYear()}`; }
      else t.textContent = `${MON[cur.getMonth()]} ${cur.getFullYear()}`;
      $$(el, '[data-c-views] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b.dataset.value === view));
    }
    function monthView() {
      const first = new Date(cur.getFullYear(), cur.getMonth(), 1), start = weekStartOf(first), today = nowW();
      main.innerHTML = `<div class="dl-cal2__month"><div class="dl-cal2__dows">${[0, 1, 2, 3, 4, 5, 6].map(i => `<span>${DAY[(i + ws) % 7]}</span>`).join('')}</div><div class="dl-cal2__mgrid">${Array.from({length: 42}, (_, i) => {
        const d = addD(start, i), list = onDay(d);
        return `<div class="dl-cal2__cell ${d.getMonth() !== cur.getMonth() ? 'is-out' : ''} ${same(d, today) ? 'is-today' : ''} ${same(d, sel) ? 'is-sel' : ''}" data-c-day="${ymd(d)}" data-c-drop="${ymd(d)}"><span class="dl-cal2__num">${d.getDate()}</span>${list.slice(0, 3).map(chip).join('')}${list.length > 3 ? `<button type="button" class="dl-cal2__more" data-c-go="${ymd(d)}" data-c-toview="day">+${list.length - 3} lagi</button>` : ''}</div>`;
      }).join('')}</div></div>`;
    }
    const HPX = 44;
    function timeView(days) {
      const today = nowW(), n = nowW();
      const lanes = d => { // acara bertumpuk dibagi kolom berdampingan
        // Kelompok = acara yang saling bertumpuk (berantai); lebar dibagi hanya di dalam kelompoknya.
        const list = onDay(d).filter(e => !e.allDay && same(e.s, d));
        let group = [], cols = [], end = null;
        const flush = () => { group.forEach(e => { e._n = cols.length; }); group = []; cols = []; end = null; };
        list.forEach(e => {
          if (end && e.s >= end) flush();
          let c = cols.findIndex(col => col[col.length - 1].e <= e.s); if (c < 0) { cols.push([e]); c = cols.length - 1; } else cols[c].push(e);
          e._c = c; group.push(e); end = !end || e.e > end ? e.e : end;
        });
        flush();
        return list;
      };
      main.innerHTML = `<div class="dl-cal2__time" style="--dl-cal-cols:${days.length}">
        <div class="dl-cal2__thead"><span class="dl-cal2__tz">WIB</span>${days.map(d => `<button type="button" class="dl-cal2__dh ${same(d, today) ? 'is-today' : ''}" data-c-go="${ymd(d)}" data-c-toview="day"><small>${DAY[d.getDay()]}</small><b>${d.getDate()}</b></button>`).join('')}</div>
        <div class="dl-cal2__allday"><span class="dl-cal2__tz">Sepanjang<br>hari</span>${days.map(d => `<div data-c-drop="${ymd(d)}" data-c-allday data-c-day="${ymd(d)}">${onDay(d).filter(e => e.allDay || !same(e.s, e.e)).map(chip).join('')}</div>`).join('')}</div>
        <div class="dl-cal2__scroll" data-c-scroll><div class="dl-cal2__grid" style="height:${24 * HPX}px">
          <div class="dl-cal2__hours">${Array.from({length: 24}, (_, h) => `<span style="top:${h * HPX}px">${h ? pad(h) + '.00' : ''}</span>`).join('')}</div>
          ${days.map(d => `<div class="dl-cal2__col ${same(d, today) ? 'is-today' : ''}" data-c-col="${ymd(d)}" data-c-drop="${ymd(d)}">${lanes(d).map(e => { const top = (e.s.getHours() + e.s.getMinutes() / 60) * HPX, hgt = Math.max(HPX / 2, (e.e - e.s) / 3600000 * HPX); return `<div class="dl-cal2__blk dl-cal2--${esc(e.tone)} ${match(e) ? '' : 'is-dim'}" draggable="true" data-c-ev="${esc(e.id)}" style="top:${top}px;height:${hgt - 2}px;left:calc(${e._c} * 100% / ${e._n});width:calc(100% / ${e._n} - 4px)"><b>${esc(e.title)}</b><small>${esc(when(e))}</small></div>`; }).join('')}
            ${same(d, n) ? `<div class="dl-cal2__now" style="top:${(n.getHours() + n.getMinutes() / 60) * HPX}px"></div>` : ''}</div>`).join('')}
        </div></div></div>`;
      const sc = $(main, '[data-c-scroll]'); sc.scrollTop = +el.dataset.scroll * HPX;
    }
    function side() {
      const tl = $(el, '[data-c-today-list]'), dl = $(el, '[data-c-day-list]'); if (!tl) return;
      const t = nowW(), item = e => `<div class="dl-cal2__item dl-cal2--${esc(e.tone)}" data-c-ev="${esc(e.id)}"><b>${esc(e.title)}</b><small><i class="fa-regular fa-clock"></i> ${esc(when(e))}</small>${e.desc ? `<p>${esc(e.desc)}</p>` : ''}</div>`;
      tl.innerHTML = `<p class="dl-cal2__cap"><span class="dl-cal2__dot"></span>Acara hari ini</p>${onDay(t).map(item).join('') || '<p class="dl-cal2__none">Tidak ada acara hari ini.</p>'}`;
      const list = onDay(sel);
      dl.innerHTML = `<h3 class="dl-cal2__selh">${DAYL[sel.getDay()]}, ${sel.getDate()} ${MON[sel.getMonth()]}</h3><small class="dl-cal2__selc">${list.length} acara</small>${list.map(item).join('') || '<p class="dl-cal2__none">Klik 2x slot kosong untuk membuat acara.</p>'}`;
      const f = $(el, '[data-c-found]'); if (f) f.innerHTML = q ? `<p class="dl-cal2__cap">${events.filter(match).length} ditemukan</p>` + events.filter(match).slice(0, 8).map(e => `<button type="button" class="dl-cal2__hit" data-c-go="${ymd(e.s)}"><b>${esc(e.title)}</b><small>${e.s.getDate()} ${MON[e.s.getMonth()].slice(0, 3)} · ${esc(when(e))}</small></button>`).join('') : '';
    }
    function render() {
      title(); mini(); side();
      if (view === 'month') monthView();
      else if (view === 'week') { const a = weekStartOf(cur); timeView(Array.from({length: 7}, (_, i) => addD(a, i))); }
      else timeView([new Date(cur.getFullYear(), cur.getMonth(), cur.getDate())]);
    }
    const byId = id => events.find(e => String(e.id) === String(id));

    function editor(e, isNew) {
      const ov = document.createElement('div'); ov.className = 'dl-mov is-on';
      const v = d => e.allDay ? ymd(d) : `${ymd(d)}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
      const draw = () => {
        ov.innerHTML = `<div class="dl-modal dl-modal--sm" role="dialog" aria-modal="true"><div class="dl-modal__head"><h3>${isNew ? 'Acara baru' : 'Ubah acara'}</h3><button type="button" class="dl-modal__x" data-x>&times;</button></div>
        <div class="dl-modal__body dl-gantt__edit">
          <label><span>Judul *</span><input data-e-title value="${esc(e.title || '')}" placeholder="Judul acara"></label>
          <label class="dl-choice"><input type="checkbox" data-e-allday ${e.allDay ? 'checked' : ''}> Sepanjang hari</label>
          <label><span>Mulai</span><input type="${e.allDay ? 'date' : 'datetime-local'}" data-e-start value="${v(e.s)}"></label>
          <label><span>Selesai</span><input type="${e.allDay ? 'date' : 'datetime-local'}" data-e-end value="${v(e.e)}"></label>
          <div><span class="dl-kpop__lbl">Warna</span><div class="dl-cal2__tones">${TONES.map(t => `<button type="button" class="dl-cal2--${t} ${t === e.tone ? 'is-on' : ''}" data-e-tone="${t}" aria-label="${t}"></button>`).join('')}</div></div>
          <label><span>Deskripsi</span><textarea rows="3" data-e-desc>${esc(e.desc || '')}</textarea></label>
          <p class="dl-lines__err" data-e-err></p>
        </div>
        <div class="dl-modal__foot">${isNew ? '' : '<button type="button" class="dl-btn dl-btn--danger dl-btn--md" data-e-del><i class="fa-solid fa-trash-can"></i> Hapus</button><span class="dl-kcard__spacer"></span>'}<button type="button" class="dl-btn dl-btn--ghost dl-btn--md" data-x>Batal</button><button type="button" class="dl-btn dl-btn--primary dl-btn--md" data-e-save><i class="fa-solid fa-floppy-disk"></i> Simpan</button></div></div>`;
      };
      const read = () => { e.title = $(ov, '[data-e-title]').value.trim(); e.desc = $(ov, '[data-e-desc]').value.trim(); e.allDay = $(ov, '[data-e-allday]').checked; const a = $(ov, '[data-e-start]').value, b = $(ov, '[data-e-end]').value; if (a) e.s = parse(a); if (b) e.e = parse(b); };
      draw();
      ov.addEventListener('click', ev => {
        const x = ev.target;
        if (x === ov || x.closest('[data-x]')) return ov.remove();
        const t = x.closest('[data-e-tone]'); if (t) { read(); e.tone = t.dataset.eTone; return draw(); }
        if (x.closest('[data-e-save]')) {
          read(); const err = !e.title ? 'Judul wajib diisi.' : e.e < e.s ? 'Waktu selesai tidak boleh sebelum mulai.' : '';
          if (err) { $(ov, '[data-e-err]').textContent = err; return; }
          if (isNew) events.push(e);
          ov.remove(); sel = new Date(e.s); render(); emit(isNew ? 'create' : 'update', e); toast(`Acara "${e.title}" ${isNew ? 'dibuat' : 'disimpan'}.`, 'success');
        }
        if (x.closest('[data-e-del]')) confirmBox({message: `Hapus acara "${e.title}"?`, tone: 'danger'}).then(ok => { if (!ok) return; events.splice(events.indexOf(e), 1); ov.remove(); render(); emit('delete', e); });
      });
      ov.addEventListener('change', ev => { if (ev.target.matches('[data-e-allday]')) { read(); draw(); } });
      document.body.appendChild(ov); $(ov, '[data-e-title]').focus();
    }
    const blank = (d, h = 9, allDay = false) => { const s = new Date(d.getFullYear(), d.getMonth(), d.getDate(), allDay ? 0 : h, 0); return {id: 'n' + (++seq), title: '', s, e: allDay ? new Date(s) : new Date(s.getTime() + 3600000), allDay, tone: 'accent', desc: ''}; };

    el.addEventListener('click', e => {
      const x = e.target;
      if (x.closest('[data-c-today]')) { cur = nowW(); sel = new Date(cur); return render(); }
      const nav = x.closest('[data-c-prev],[data-c-next]');
      if (nav) { const k = nav.hasAttribute('data-c-next') ? 1 : -1; if (view === 'month') cur = new Date(cur.getFullYear(), cur.getMonth() + k, 1); else cur = addD(cur, k * (view === 'week' ? 7 : 1)); return render(); }
      const v = x.closest('[data-c-views] .dl-segment__opt'); if (v) { view = v.dataset.value; return render(); }
      const m = x.closest('[data-c-mini]'); if (m && m.tagName === 'BUTTON') { sel = new Date(sel.getFullYear(), sel.getMonth() + +m.dataset.cMini, 1); return mini(); }
      const go = x.closest('[data-c-go]'); if (go) { cur = parse(go.dataset.cGo); sel = new Date(cur); if (go.dataset.cToview) view = go.dataset.cToview; return render(); }
      if (x.closest('[data-c-create]')) return editor(blank(sel, nowW().getHours() + 1), true);
      const ev = x.closest('[data-c-ev]'); if (ev) { sel = new Date(byId(ev.dataset.cEv).s); mini(); side(); return; }
      const day = x.closest('[data-c-day],[data-c-col]'); if (day) { sel = parse(day.dataset.cDay || day.dataset.cCol); mini(); side(); $$(el, '.dl-cal2__cell.is-sel').forEach(c => c.classList.remove('is-sel')); if (day.classList.contains('dl-cal2__cell')) day.classList.add('is-sel'); }
    });
    el.addEventListener('dblclick', e => {
      const ev = e.target.closest('[data-c-ev]'); if (ev) return editor(byId(ev.dataset.cEv), false);
      const col = e.target.closest('[data-c-col]');
      if (col) { const r = col.getBoundingClientRect(), hr = Math.floor((e.clientY - r.top) / HPX); return editor(blank(parse(col.dataset.cCol), Math.min(23, Math.max(0, hr))), true); }
      const cell = e.target.closest('[data-c-day]'); if (cell) editor(blank(parse(cell.dataset.cDay), 9, cell.hasAttribute('data-c-allday')), true);
    });
    // Drag: ke sel hari (bulan / sepanjang hari) = pindah tanggal; ke kolom jam = pindah tanggal + jam (snap 30 menit).
    el.addEventListener('dragstart', e => { const ev = e.target.closest('[data-c-ev]'); if (!ev) return; drag = byId(ev.dataset.cEv); ev.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', drag.id); } catch (x) {} });
    el.addEventListener('dragend', () => { drag = null; $$(el, '.is-drag,.is-over').forEach(x => x.classList.remove('is-drag', 'is-over')); });
    el.addEventListener('dragover', e => { const t = e.target.closest('[data-c-drop]'); if (!t || !drag) return; e.preventDefault(); $$(el, '.is-over').forEach(x => x.classList.remove('is-over')); t.classList.add('is-over'); });
    el.addEventListener('drop', e => {
      const t = e.target.closest('[data-c-drop]'); if (!t || !drag) return;
      e.preventDefault();
      const d = parse(t.dataset.cDrop), dur = drag.e - drag.s;
      if (t.hasAttribute('data-c-col')) { const r = t.getBoundingClientRect(), mins = Math.max(0, Math.min(23.5 * 60, Math.round((e.clientY - r.top) / HPX * 2) * 30)); d.setHours(0, mins); drag.allDay = false; drag.s = d; drag.e = new Date(d.getTime() + (dur || 3600000)); }
      else { const s = new Date(d); if (!drag.allDay) s.setHours(drag.s.getHours(), drag.s.getMinutes()); drag.s = s; drag.e = new Date(s.getTime() + dur); if (t.hasAttribute('data-c-allday')) drag.allDay = true; }
      const moved = drag; drag = null; sel = new Date(moved.s); render(); emit('move', moved); toast(`"${moved.title}" dipindah ke ${moved.s.getDate()} ${MON[moved.s.getMonth()]}${moved.allDay ? '' : ', ' + hhmm(moved.s)}.`, 'success');
    });
    const sIn = $(el, '[data-c-search]'); if (sIn) sIn.addEventListener('input', () => { q = sIn.value.trim().toLowerCase(); render(); });
    el.dlCalendar = {events, render, go: (d, v) => { cur = parse(d); sel = new Date(cur); if (v) view = v; render(); }};
    render();
    setInterval(() => { const nl = $(main, '.dl-cal2__now'), n = nowW(); if (nl) nl.style.top = (n.getHours() + n.getMinutes() / 60) * HPX + 'px'; }, 60000);
  }


  /* ============================ PRINT OUT ============================ */
  // Menyusun dokumen ke lembar A4/A5: isi baris sampai lembar penuh (diukur, bukan ditebak), header tabel diulang,
  // total + catatan + tanda tangan hanya di lembar terakhir.
  function initPrint(el) {
    const cfg = JSON.parse(el.dataset.config), cols = cfg.columns, rows = cfg.rows, T = cfg.totals || {}, pages = $(el, '[data-p-pages]');
    const tpl = n => $(el, `template[data-p-${n}]`).innerHTML;
    const num = v => (+v || 0).toLocaleString('id-ID', {maximumFractionDigits: 2});
    const vis = () => cols.filter(c => el.dataset.prices === '1' || !c.price);
    const isNum = c => c.type === 'money' || c.type === 'number';
    const fmtN = (c, v) => { if (v === '' || v == null) return ''; const n = +v; return (c.signed && n > 0 && c.plus ? '+' : '') + (c.signed && n < 0 ? '−' + num(-n) : num(n)); };
    const tone = (c, v) => c.signed ? (+v > 0 ? 'is-pos' : +v < 0 ? 'is-neg' : 'is-zero') : '';
    const cell = (c, r) => {
      const v = r[c.key];
      if (c.type === 'badge') return `<td class="dl-print__c">${v ? `<span class="dl-print__badge is-${esc((c.tones || {})[v] || 'neutral')}">${esc(v)}</span>` : ''}</td>`;
      const txt = isNum(c) ? fmtN(c, v) : esc(v ?? '');
      return `<td class="${isNum(c) ? 'num' : ''} ${tone(c, v)} ${c.bold ? 'is-b' : ''}">${txt}${c.sub && r[c.sub] ? `<small>${esc(r[c.sub])}</small>` : ''}</td>`;
    };
    // Rumus aman untuk kolom berjalan (hanya nama kolom + angka + - * / ( )).
    const compile = expr => { if (!/^[\w\s+\-*/().]+$/.test(expr)) throw new Error('rumus tidak sah: ' + expr); return new Function('r', 'return ' + expr.replace(/[A-Za-z_]\w*/g, k => `(+r[${JSON.stringify(k)}]||0)`)); };
    const data = rows.map(r => ({...r}));
    cols.filter(c => c.running).forEach(c => { const f = compile(c.running); let b = cfg.opening ?? 0; data.forEach(r => { if (c.prev) r[c.prev] = b; b = Math.round((b + f(r)) * 100) / 100; r[c.key] = b; }); });
    const sumOf = (list, k) => list.reduce((t, r) => t + (+r[k] || 0), 0);
    // Baris tabel (html) sesuai kelompok terpilih: judul kelompok, baris, subtotal; lalu total akhir.
    function items() {
      const v = vis(), sums = v.filter(c => c.sum), g = el.dataset.group, out = [];
      const rowHtml = (r, i) => { const rt = cols.map(c => c.rowTones && c.rowTones[r[c.key]]).find(Boolean); return `<tr class="${rt ? 'is-row-' + rt : ''}"><td class="dl-print__no">${i + 1}</td>${v.map(c => cell(c, r)).join('')}</tr>`; };
      const subRow = (label, list, cls) => sums.length ? `<tr class="${cls}"><td></td>${v.map((c, i) => c.sum ? `<td class="num ${tone(c, sumOf(list, c.key))}">${fmtN(c, sumOf(list, c.key))}</td>` : `<td>${i === 0 ? esc(label) : ''}</td>`).join('')}</tr>` : '';
      let n = 0;
      if (g) {
        const order = [...new Set(data.map(r => r[g]))];
        order.forEach(k => { const list = data.filter(r => r[g] === k); out.push(`<tr class="dl-print__grp"><td colspan="${v.length + 1}">${esc(k)} · ${list.length} baris</td></tr>`); list.forEach(r => out.push(rowHtml(r, n++))); out.push(subRow('Subtotal ' + k, list, 'dl-print__sub')); });
      } else data.forEach(r => out.push(rowHtml(r, n++)));
      out.push(subRow('Total', data, 'dl-print__grand'));
      return out.filter(Boolean);
    }
    function summary() {
      const S = cfg.summary || []; if (!S.length) return '';
      return `<div class="dl-print__kpis" style="--n:${S.length}">${S.map(s => { const val = s.sum ? sumOf(data, s.sum) : s.last ? (+data[data.length - 1]?.[s.last] || 0) : s.value; const c = {signed: s.signed, plus: s.plus}; return `<div><small>${esc(s.label)}</small><b class="${tone(c, val)}">${typeof val === 'number' ? fmtN(c, val) : esc(val)}</b></div>`; }).join('')}</div>`;
    }
    function thead() {
      const v = vis(), grouped = v.some(c => c.group);
      if (!grouped) return `<tr><th class="dl-print__no">No</th>${v.map(c => `<th class="${c.type === 'text' ? '' : 'num'}" ${c.width ? `style="width:${esc(c.width)}"` : ''}>${esc(c.label)}</th>`).join('')}</tr>`;
      let top = '<tr><th rowspan="2" class="dl-print__no">No</th>', bottom = '<tr>';
      v.forEach((c, i) => {
        if (!c.group) { top += `<th rowspan="2">${esc(c.label)}</th>`; return; }
        if (i && v[i - 1].group === c.group) { bottom += `<th>${esc(c.label)}</th>`; return; }
        top += `<th colspan="${v.filter(x => x.group === c.group).length}">${esc(c.group)}</th>`; bottom += `<th>${esc(c.label)}</th>`;
      });
      return top + '</tr>' + bottom + '</tr>';
    }
    function totals() {
      if (!T.of || el.dataset.prices !== '1') return '';
      const sub = sumOf(data, T.of), disc = +T.discount || 0, dpp = sub - disc;
      const ppnOn = T.ppn && (!cfg.hasPpnKop || el.dataset.ppn === '1'), dec = 10 ** (cfg.currency ? cfg.currency[5] : 2), ppn = ppnOn ? Math.round(dpp * T.ppn / 100 * dec) / dec : 0, grand = dpp + ppn;
      const extra = (T.extra || []).reduce((t, [, v]) => t + (+v || 0), 0);
      const line = (l, v, cls = '') => `<div class="${cls}"><span>${esc(l)}</span><b>${v < 0 ? `(${num(-v)})` : num(v)}</b></div>`;
      const final = T.net ? grand + extra : grand;
      return `<div class="dl-print__totals">${line('Subtotal', sub)}${disc ? line('Diskon', -disc) + line('DPP', dpp, 'is-strong') : ''}${ppnOn ? line(`PPN ${T.ppn}%`, ppn, 'is-muted') : ''}${line(T.net ? 'Total Tagihan' : 'Total', grand, 'is-strong')}${T.net ? (T.extra || []).map(([l, v]) => line(l, v, 'is-violet')).join('') + line(T.net, final, 'is-net') : ''}</div>
        <p class="dl-print__words"><span>Terbilang:</span> <i># ${esc(words(String(Math.round(final * 100) / 100), cfg.currency))} #</i></p>`;
    }
    function sheet() {
      const s = document.createElement('section'); s.className = 'dl-print__sheet';
      s.innerHTML = `<div class="dl-print__inner">${tpl('head')}<div data-p-body></div>${tpl('foot')}</div>`;
      const ppn = el.dataset.ppn === '1';
      $$(s, '[data-p-kop]').forEach(k => { k.hidden = (k.dataset.pKop === '1') !== (ppn && cfg.hasPpnKop); });
      pages.appendChild(s); return s;
    }
    const fits = s => { const inner = $(s, '.dl-print__inner'); return inner.scrollHeight <= inner.clientHeight + 1; };
    function build() {
      pages.innerHTML = '';
      el.dataset.paperNow = el.dataset.paper;
      let s = sheet(), body = $(s, '[data-p-body]');
      body.insertAdjacentHTML('beforeend', tpl('parties') + tpl('criteria') + summary());
      const table = () => { body.insertAdjacentHTML('beforeend', `<table class="dl-print__table"><thead>${thead()}</thead><tbody></tbody></table>`); return $(body, 'tbody:last-of-type'); };
      let tb = table();
      items().forEach(html => {
        tb.insertAdjacentHTML('beforeend', html);
        if (!fits(s) && tb.rows.length > 1) { const last = tb.lastElementChild; last.remove(); s = sheet(); body = $(s, '[data-p-body]'); tb = table(); tb.appendChild(last); }
      });
      // Penutup (total, terbilang, catatan, tanda tangan) harus utuh di satu lembar; bila tidak muat, baris terakhir ikut pindah.
      const tail = totals() + tpl('notes') + tpl('sign');
      body.insertAdjacentHTML('beforeend', `<div class="dl-print__tail">${tail}</div>`);
      if (!fits(s)) {
        $(body, '.dl-print__tail').remove();
        const n = Math.min(2, tb.rows.length - 1), carry = n > 0 ? [...tb.rows].slice(-n) : [];
        s = sheet(); body = $(s, '[data-p-body]');
        if (carry.length) { const nt = table(); carry.forEach(tr => nt.appendChild(tr)); }
        body.insertAdjacentHTML('beforeend', `<div class="dl-print__tail">${tail}</div>`);
      }
      const all = $$(pages, '.dl-print__sheet');
      all.forEach((sh, i) => $$(sh, '[data-p-pg]').forEach(x => { x.textContent = `${i + 1}/${all.length}`; }));
      let st = document.getElementById('dl-print-page'); if (!st) { st = document.createElement('style'); st.id = 'dl-print-page'; document.head.appendChild(st); }
      st.textContent = `@page{size:${{a5: 'A5 landscape', a4l: 'A4 landscape'}[el.dataset.paper] || 'A4 portrait'};margin:0}`;
      const seg = (sel, v) => $$(el, `${sel} .dl-segment__opt`).forEach(b => b.classList.toggle('is-active', b.dataset.value === v));
      seg('[data-p-paper]', el.dataset.paper); seg('[data-p-prices]', el.dataset.prices); seg('[data-p-ppn]', el.dataset.ppn); seg('[data-p-group]', el.dataset.group || '');
      el.dispatchEvent(new CustomEvent('dl:print-built', {bubbles: true, detail: {pages: all.length}}));
    }
    el.addEventListener('click', e => {
      const o = e.target.closest('.dl-segment__opt');
      if (o) { const k = o.closest('[data-p-paper]') ? 'paper' : o.closest('[data-p-prices]') ? 'prices' : o.closest('[data-p-ppn]') ? 'ppn' : o.closest('[data-p-group]') ? 'group' : null; if (k) { el.dataset[k] = o.dataset.value; build(); } return; }
      if (e.target.closest('[data-p-bw]')) { el.classList.toggle('is-bw'); e.target.closest('[data-p-bw]').classList.toggle('is-on'); return; }
      if (e.target.closest('[data-p-print]')) window.print();
    });
    build();
    el.dlPrint = {build};
  }


  /* ============================ IMPORT ============================ */
  // CSV sederhana tapi benar: tanda kutip, "" di dalam kutip, baris baru di dalam kutip, BOM, deteksi pemisah.
  function parseCsv(text) {
    text = text.replace(/^\ufeff/, '');
    const first = text.split(/\r?\n/)[0] || '', delim = [';', '\t', ','].map(d => [d, first.split(d).length]).sort((a, b) => b[1] - a[1])[0][0];
    const rows = []; let row = [], cur = '', q = false;
    for (let i = 0; i < text.length; i++) {
      const ch = text[i];
      if (q) { if (ch === '"' && text[i + 1] === '"') { cur += '"'; i++; } else if (ch === '"') q = false; else cur += ch; continue; }
      if (ch === '"') q = true; else if (ch === delim) { row.push(cur); cur = ''; } else if (ch === '\n' || ch === '\r') { if (ch === '\r' && text[i + 1] === '\n') i++; row.push(cur); rows.push(row); row = []; cur = ''; } else cur += ch;
    }
    if (cur !== '' || row.length) { row.push(cur); rows.push(row); }
    return {delim, rows: rows.filter(r => r.some(c => c.trim() !== ''))};
  }
  const csvCell = v => /[",;\n\t]/.test(String(v ?? '')) ? `"${String(v).replace(/"/g, '""')}"` : String(v ?? '');
  const downloadCsv = (name, lines) => { const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['\ufeff' + lines.map(r => r.map(csvCell).join(';')).join('\r\n')], {type: 'text/csv'})); a.download = name; a.click(); };
  // Angka gaya Indonesia (1.234,5) maupun biasa (1234.5).
  const parseNum = v => { let s = String(v).trim().replace(/\s|Rp/gi, ''); if (s === '') return NaN; if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.'); else if (/^-?\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, ''); return /^-?\d+(\.\d+)?$/.test(s) ? +s : NaN; };
  const parseDate = v => {
    const s = String(v).trim(); let m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/), y, mo, d;
    if (m) [, y, mo, d] = m.map(Number); else if ((m = s.match(/^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$/))) [, d, mo, y] = m.map(Number); else return null;
    const t = new Date(y, mo - 1, d);  // tanggal mustahil (31-13, 30 Feb) ditolak
    return t.getFullYear() === y && t.getMonth() === mo - 1 && t.getDate() === d ? `${y}-${String(mo).padStart(2, '0')}-${String(d).padStart(2, '0')}` : null;
  };
  function initImport(el) {
    const cfg = JSON.parse(el.dataset.config), cols = cfg.columns;
    let file = null, map = {}, result = [];
    const norm = s => String(s).toLowerCase().replace(/[^a-z0-9]/g, '');
    const step = n => { $$(el, '[data-i-step]').forEach(s => { s.hidden = s.dataset.iStep !== String(n); }); $$(el, '[data-i-steps] li').forEach((li, i) => li.className = i + 1 < n ? 'is-done' : i + 1 === n ? 'is-active' : ''); };
    function load(f) {
      const rd = new FileReader();
      rd.onload = () => {
        const {delim, rows} = parseCsv(String(rd.result));
        if (rows.length < 2) { toast('File kosong atau hanya berisi judul kolom.', 'danger'); return; }
        file = {name: f.name, head: rows[0].map(h => h.trim()), body: rows.slice(1), delim};
        map = {}; cols.forEach(c => { const names = [c.key, c.label, ...c.aliases].map(norm); const i = file.head.findIndex(h => names.includes(norm(h))); map[c.key] = i; });
        drawMap(); step(2);
      };
      rd.readAsText(f, 'utf-8');
    }
    function drawMap() {
      $(el, '[data-i-fileinfo]').textContent = `${file.name} · ${file.body.length} baris data · ${file.head.length} kolom · pemisah "${file.delim === '\t' ? 'tab' : file.delim}"`;
      $(el, '[data-i-map]').innerHTML = cols.map(c => `<label class="dl-import__mrow ${map[c.key] < 0 && c.required ? 'is-bad' : ''}"><span><b>${esc(c.label)}</b>${c.required ? ' <span class="dl-field__req">*</span>' : ''}<small>${esc({text: 'teks', number: 'angka', date: 'tanggal', lookup: 'pilihan master'}[c.type] || c.type)}</small></span><i class="fa-solid fa-arrow-left"></i>
        <select data-i-col="${esc(c.key)}"><option value="-1">— tidak diimpor —</option>${file.head.map((h, i) => `<option value="${i}" ${map[c.key] === i ? 'selected' : ''}>${esc(h)}</option>`).join('')}</select>
        <small class="dl-import__sample">${map[c.key] >= 0 ? 'contoh: ' + esc(file.body[0][map[c.key]] ?? '') : ''}</small></label>`).join('');
    }
    function validate() {
      result = file.body.map((r, n) => {
        const out = {}, err = {};
        cols.forEach(c => {
          const raw = map[c.key] >= 0 ? String(r[map[c.key]] ?? '').trim() : '';
          if (raw === '') { if (c.required) err[c.key] = 'wajib diisi'; out[c.key] = ''; return; }
          if (c.type === 'number') { const v = parseNum(raw); if (isNaN(v)) err[c.key] = 'bukan angka'; else out[c.key] = v; }
          else if (c.type === 'date') { const v = parseDate(raw); if (!v) err[c.key] = 'format tanggal (DD/MM/YYYY atau YYYY-MM-DD)'; else out[c.key] = v; }
          else if (c.type === 'lookup') { const hit = c.lookup.rows.find(([k, l]) => norm(k) === norm(raw) || norm(l) === norm(raw)); if (!hit) err[c.key] = 'tidak ada di master'; else out[c.key] = hit[0]; }
          else out[c.key] = raw;
        });
        return {line: n + 2, raw: r, out, err, ok: !Object.keys(err).length};
      });
    }
    function drawPreview() {
      const good = result.filter(r => r.ok).length, bad = result.length - good, only = $(el, '[data-i-onlybad]').checked;
      $(el, '[data-i-stats]').innerHTML = `<span class="dl-badge dl-badge--pill dl-badge--neutral">${result.length} baris</span><span class="dl-badge dl-badge--pill dl-badge--success">${good} valid</span><span class="dl-badge dl-badge--pill dl-badge--danger">${bad} bermasalah</span>`;
      const list = result.filter(r => !only || !r.ok).slice(0, 200);
      $(el, '[data-i-table]').innerHTML = `<thead><tr><th>Baris</th>${cols.map(c => `<th>${esc(c.label)}</th>`).join('')}<th>Status</th></tr></thead><tbody>${list.map(r => `<tr class="${r.ok ? '' : 'is-bad'}"><td class="num">${r.line}</td>${cols.map(c => { const e = r.err[c.key], v = map[c.key] >= 0 ? r.raw[map[c.key]] ?? '' : ''; return `<td class="${e ? 'is-err' : ''}" ${e ? `title="${esc(e)}"` : ''}>${esc(v)}${e ? `<small>${esc(e)}</small>` : ''}</td>`; }).join('')}<td>${r.ok ? '<span class="dl-badge dl-badge--success">OK</span>' : `<span class="dl-badge dl-badge--danger">${Object.keys(r.err).length} error</span>`}</td></tr>`).join('')}</tbody>`;
      const go = $(el, '[data-i-go]'); go.disabled = !good; go.lastChild.textContent = good ? `Import ${good} baris valid` : 'Tidak ada baris valid';
      $(el, '[data-i-failed]').disabled = !bad;
    }
    el.addEventListener('change', e => {
      if (e.target.matches('[data-i-file]') && e.target.files[0]) load(e.target.files[0]);
      if (e.target.matches('[data-i-col]')) { map[e.target.dataset.iCol] = +e.target.value; drawMap(); }
      if (e.target.matches('[data-i-onlybad]')) drawPreview();
    });
    const drop = $(el, '[data-i-drop]');
    ['dragover', 'dragenter'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach(t => drop.addEventListener(t, () => drop.classList.remove('is-over')));
    drop.addEventListener('drop', e => { e.preventDefault(); const f = e.dataTransfer.files[0]; if (f) load(f); });
    el.addEventListener('click', e => {
      const x = e.target;
      if (x.closest('[data-i-template]')) downloadCsv(cfg.template + '.csv', [cols.map(c => c.label), cols.map(c => c.example ?? '')]);
      const b = x.closest('[data-i-back]'); if (b) step(+b.dataset.iBack);
      if (x.closest('[data-i-preview]')) {
        const miss = cols.filter(c => c.required && map[c.key] < 0);
        if (miss.length) { toast(`Kolom wajib belum dicocokkan: ${miss.map(c => c.label).join(', ')}.`, 'danger'); return; }
        validate(); drawPreview(); step(3);
      }
      if (x.closest('[data-i-failed]')) downloadCsv(cfg.template + '-gagal.csv', [[...file.head, 'Alasan'], ...result.filter(r => !r.ok).map(r => [...r.raw, Object.entries(r.err).map(([k, v]) => `${cols.find(c => c.key === k).label}: ${v}`).join('; ')])]);
      if (x.closest('[data-i-go]')) {
        const good = result.filter(r => r.ok).map(r => r.out), failed = result.length - good.length;
        confirmBox({message: `Import ${good.length} baris valid${failed ? ` (${failed} baris bermasalah dilewati)` : ''}?`, tone: 'warn'}).then(ok => {
          if (!ok) return;
          el.dispatchEvent(new CustomEvent('dl:import', {bubbles: true, detail: {rows: good, failed}}));
          toast(`${good.length} baris berhasil diimpor${failed ? `, ${failed} dilewati` : ''}.`, 'success');
          step(1); $(el, '[data-i-file]').value = '';
        });
      }
    });
    step(1);
    el.dlImport = {load: text => { const f = new File([text], 'data.csv', {type: 'text/csv'}); load(f); }};
  }


  /* ============================ ORG CHART ============================ */
  function initOrgChart(el) {
    const nodes = JSON.parse(el.dataset.nodes || '[]'), edit = el.dataset.editable === '1', tree = $(el, '[data-o-tree]'), canvas = $(el, '[data-o-canvas]');
    const undo = [], collapsed = new Set();
    let zoom = 1, q = '', drag = null;
    const ppl = n => (n.people || []).map(p => typeof p === 'string' ? {name: p} : p);
    const kids = id => nodes.filter(n => String(n.parent ?? '') === String(id ?? ''));
    const byId = id => nodes.find(n => String(n.id) === String(id));
    const isUnder = (id, anc) => { for (let n = byId(id); n && n.parent != null; n = byId(n.parent)) if (String(n.parent) === String(anc)) return true; return false; };
    const hit = n => q && (n.title + ' ' + (n.dept || '') + ' ' + ppl(n).map(p => p.name).join(' ')).toLowerCase().includes(q);
    const ini = s => String(s).trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
    const hue = s => [...String(s)].reduce((h, c) => (h * 31 + c.charCodeAt(0)) % 360, 0);
    const av = p => p.photo ? `<img src="${esc(p.photo)}" alt="">` : esc(ini(p.name));
    function card(n) {
      const P = ppl(n), c = kids(n.id);
      return `<div class="dl-org__card ${P.length ? '' : 'is-vacant'} ${hit(n) ? 'is-hit' : ''}" data-o-node="${esc(n.id)}" ${edit ? 'data-o-drop' : ''}>
        ${edit ? `<span class="dl-org__grip" draggable="true" data-o-grip title="Tarik ke atasan baru"><i class="fa-solid fa-grip-vertical"></i></span>` : ''}
        <div class="dl-org__who">${P.length ? `<span class="dl-org__avs">${P.slice(0, 3).map(p => `<span class="dl-org__av" style="--h:${hue(p.name)}" title="${esc(p.name)}">${av(p)}</span>`).join('')}</span>` : '<span class="dl-org__av is-empty"><i class="fa-solid fa-user-plus"></i></span>'}
          <span class="dl-org__txt"><b>${P.length ? esc(P.map(p => p.name).slice(0, 2).join(', ')) + (P.length > 2 ? ` +${P.length - 2}` : '') : 'Lowong'}</b><small>${esc(n.title)}</small>${n.dept ? `<em>${esc(n.dept)}</em>` : ''}</span></div>
        ${c.length ? `<button type="button" class="dl-org__fold" data-o-fold title="${collapsed.has(String(n.id)) ? 'Buka' : 'Lipat'} cabang">${collapsed.has(String(n.id)) ? `+${c.length}` : '−'}</button>` : ''}
      </div>`;
    }
    const branch = n => { const c = kids(n.id), open = !collapsed.has(String(n.id)); return `<li>${card(n)}${c.length && open ? `<ul>${c.map(branch).join('')}</ul>` : ''}</li>`; };
    function render() {
      tree.innerHTML = `<ul>${kids(null).map(branch).join('')}</ul>`;
      tree.style.transform = `scale(${zoom})`; $(el, '[data-o-zoomval]').textContent = Math.round(zoom * 100) + '%';
      const u = $(el, '[data-o-undo]'); if (u) u.disabled = !undo.length;
    }
    function move(id, to) {
      const n = byId(id);
      if (!n || String(n.id) === String(to) || String(n.parent) === String(to)) return;
      if (isUnder(to, id)) { toast('Tidak bisa dipindah ke bawahannya sendiri.', 'danger'); return; }
      undo.push([n.id, n.parent]); const from = n.parent; n.parent = to; collapsed.delete(String(to));
      render(); toast(`${n.title} kini di bawah ${byId(to).title}.`, 'success');
      el.dispatchEvent(new CustomEvent('dl:orgchart', {bubbles: true, detail: {id: n.id, from, to}}));
    }
    el.addEventListener('dragstart', e => { const g = e.target.closest('[data-o-grip]'); if (!g) return; drag = g.closest('[data-o-node]').dataset.oNode; g.closest('.dl-org__card').classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', drag); e.dataTransfer.setDragImage(g.closest('.dl-org__card'), 20, 20); } catch (x) {} });
    el.addEventListener('dragend', () => { drag = null; $$(el, '.is-drag,.is-target,.is-deny').forEach(x => x.classList.remove('is-drag', 'is-target', 'is-deny')); });
    el.addEventListener('dragover', e => { const t = e.target.closest('[data-o-drop]'); if (!t || drag == null) return; e.preventDefault(); $$(el, '.is-target,.is-deny').forEach(x => x.classList.remove('is-target', 'is-deny')); const bad = t.dataset.oNode === drag || isUnder(t.dataset.oNode, drag); t.classList.add(bad ? 'is-deny' : 'is-target'); e.dataTransfer.dropEffect = bad ? 'none' : 'move'; });
    el.addEventListener('drop', e => { const t = e.target.closest('[data-o-drop]'); if (!t || drag == null) return; e.preventDefault(); const d = drag; drag = null; move(d, t.dataset.oNode); });
    el.addEventListener('click', e => {
      const x = e.target;
      const f = x.closest('[data-o-fold]'); if (f) { const id = f.closest('[data-o-node]').dataset.oNode; collapsed.has(id) ? collapsed.delete(id) : collapsed.add(id); return render(); }
      if (x.closest('[data-o-undo]')) { const u = undo.pop(); if (u) { byId(u[0]).parent = u[1]; render(); toast('Perubahan terakhir dibatalkan.'); } return; }
      if (x.closest('[data-o-save]')) { el.dispatchEvent(new CustomEvent('dl:orgchart-save', {bubbles: true, detail: {nodes: nodes.map(n => ({id: n.id, parent: n.parent ?? null}))}})); toast(`Struktur disimpan (${undo.length} perubahan).`, 'success'); undo.length = 0; return render(); }
      const z = x.closest('[data-o-zoom]');
      if (z) { const k = +z.dataset.oZoom; if (k) zoom = Math.min(1.6, Math.max(0.4, Math.round((zoom + k * 0.1) * 10) / 10)); else { tree.style.transform = 'scale(1)'; zoom = Math.max(0.4, Math.min(1, canvas.clientWidth / tree.scrollWidth)); } return render(); }
      if (x.closest('[data-o-html]')) {
        const css = [...document.querySelectorAll('link[rel=stylesheet]')].map(l => `<link rel="stylesheet" href="${l.href}">`).join('');
        const html = `<!doctype html><html lang="id"><head><meta charset="utf-8"><title>${esc(el.dataset.title)}</title>${css}<style>body{margin:24px;font-family:sans-serif}.dl-org__grip,.dl-org__fold{display:none}</style></head><body><h1>${esc(el.dataset.title)}</h1><div class="dl-org"><div class="dl-org__tree">${tree.innerHTML}</div></div></body></html>`;
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([html], {type: 'text/html'})); a.download = 'struktur-organisasi.html'; a.click();
      }
    });
    $(el, '[data-o-search]').addEventListener('input', e => {
      q = e.target.value.trim().toLowerCase();
      if (q) nodes.filter(hit).forEach(n => { for (let p = byId(n.parent); p; p = byId(p.parent)) collapsed.delete(String(p.id)); });
      render();
      const first = $(tree, '.is-hit'); if (first) first.scrollIntoView({block: 'nearest', inline: 'center', behavior: 'smooth'});
    });
    el.dlOrg = {nodes, move, render};
    render();
    canvas.scrollLeft = (canvas.scrollWidth - canvas.clientWidth) / 2;  // mulai dari puncak (tengah)
  }


  /* ============================ DIELINE ============================ */
  // Hanya "lem" UI; semua geometri dihitung TMDieline (salinan utuh dari tmerp).
  function initDieline(el) {
    const T = JSON.parse(el.dataset.types || '{}'), name = el.dataset.name;
    let code = el.dataset.type, dims = JSON.parse(el.dataset.dims || '{}'), zoom = 1, manual = false;
    const q = s => $(el, s), fw = q('[data-d-fw]'), fh = q('[data-d-fh]'), fa = q('[data-d-fa]');
    const numStr = v => v === '' || v == null || isNaN(v) ? '' : (+v).toLocaleString('id-ID', {maximumFractionDigits: 2});
    function fields() {
      const t = T[code]; if (!t) return;
      dims = Object.fromEntries(Object.entries(t.params).map(([k, p]) => [k, dims[k] ?? p.default ?? '']));
      q('[data-d-sizes]').innerHTML = (t.sizes || []).map((s, i) => `<button type="button" class="dl-dieline__size" data-d-size="${i}">${esc(s.label)}</button>`).join('') || '<span class="dl-dieline__none">Belum ada ukuran pasaran untuk tipe ini.</span>';
      q('[data-d-dims]').innerHTML = Object.entries(t.params).filter(([, p]) => p.type === 'NUM' || typeof p.default === 'number').map(([k, p]) => `<label class="dl-dieline__dim"><span>${esc(p.label)}</span><span class="dl-field__box"><input class="dl-input" type="text" inputmode="decimal" name="${esc(name)}[dims][${esc(k)}]" data-d-dim="${esc(k)}" value="${esc(dims[k] ?? '')}"><em>${esc(p.unit || 'mm')}</em></span></label>`).join('');
      markSize();
    }
    const markSize = () => { const t = T[code]; $$(el, '[data-d-size]').forEach(b => { const s = t.sizes[+b.dataset.dSize]; b.classList.toggle('is-on', Object.entries(s.dims).every(([k, v]) => +dims[k] === +v)); }); };
    function redraw() {
      const t = T[code], spec = t ? t.spec : null;
      if (!manual) { const f = window.TMDieline.flat(spec, dims); fw.value = f.w || ''; fh.value = f.h || ''; }
      const a = (parseFloat(String(fw.value).replace(',', '.')) || 0) * (parseFloat(String(fh.value).replace(',', '.')) || 0);
      fa.value = a ? a.toFixed(2) : '';
      window.TMDieline.render(q('[data-d-preview]'), spec, dims);
      q('[data-d-preview]').style.transform = `scale(${zoom})`; q('[data-d-zoomval]').textContent = Math.round(zoom * 100) + '%';
      q('[data-d-info]').innerHTML = t ? `<b>${esc(t.label)}</b>${t.category ? ` · ${esc(t.category)}` : ''} · Bentang <b>${numStr(fw.value)} × ${numStr(fh.value)} mm</b> · Luas <b>${numStr(a / 100)} cm²</b>${manual ? ' <span class="dl-badge dl-badge--warn">manual</span>' : ''}` : '';
      markSize();
      el.dispatchEvent(new CustomEvent('dl:dieline', {bubbles: true, detail: {type: code, dims: {...dims}, flat: {w: +fw.value || 0, h: +fh.value || 0, area: a}, manual}}));
    }
    el.addEventListener('input', e => {
      const d = e.target.dataset.dDim;
      if (d !== undefined) { dims[d] = e.target.value === '' ? '' : parseFloat(e.target.value.replace(',', '.')); return redraw(); }
      if (e.target.matches('[data-d-fw],[data-d-fh]') && manual) redraw();
    });
    el.addEventListener('change', e => {
      if (e.target.name === name + '[type]') { code = e.target.value; dims = {}; fields(); redraw(); }
      if (e.target.matches('[data-d-manual]')) { manual = e.target.checked; fw.readOnly = fh.readOnly = !manual; redraw(); }
    });
    el.addEventListener('click', e => {
      const s = e.target.closest('[data-d-size]');
      if (s) { Object.assign(dims, T[code].sizes[+s.dataset.dSize].dims); $$(el, '[data-d-dim]').forEach(i => { i.value = dims[i.dataset.dDim] ?? ''; }); return redraw(); }
      const z = e.target.closest('[data-d-zoom]'); if (z) { zoom = Math.min(3, Math.max(0.4, Math.round((zoom + +z.dataset.dZoom * 0.2) * 10) / 10)); return redraw(); }
      if (e.target.closest('[data-d-svg]')) {
        const svg = window.TMDieline.svg(T[code].spec, dims); if (!svg) return toast('Isi dimensi dulu.', 'warn');
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([svg.replace('<svg ', '<svg style="background:#1b1f2a" ')], {type: 'image/svg+xml'})); a.download = `dieline-${code.toLowerCase()}.svg`; a.click();
      }
    });
    el.dlDieline = {get dims() { return {...dims}; }, setType: c => { code = c; dims = {}; fields(); redraw(); }, redraw};
    if (!window.TMDieline) { q('[data-d-preview]').textContent = 'TMDieline tidak termuat.'; return; }
    fields(); redraw();
  }


  /* ===================== DIELINE v2 / v3 (salinan v1 + ukuran) ===================== */
  // v1 (initDieline) SENGAJA tidak diubah. level 2 = garis ukuran + istilah internasional + mm/cm;
  // level 3 = v2 + judul spesifikasi + panjang cutting/creasing/perforasi + jendela 3D statis.
  // Hanya "lem" UI; semua geometri dihitung TMDieline (salinan utuh dari tmerp).
  function initDielineX(el) {
    const T = JSON.parse(el.dataset.types || '{}'), name = el.dataset.name, TERMS = JSON.parse(el.dataset.terms || '{}'), uid = 'dld' + Math.random().toString(36).slice(2, 8), v3 = el.dataset.level === '3';
    let code = el.dataset.type, dims = JSON.parse(el.dataset.dims || '{}'), zoom = 1, manual = false, unit = el.dataset.unit || 'mm';
    const term = (k, p) => TERMS[k] ? `${TERMS[k][0]} (${TERMS[k][1]})` : p.label;
    const q = s => $(el, s), fw = q('[data-d-fw]'), fh = q('[data-d-fh]'), fa = q('[data-d-fa]');
    const numStr = v => v === '' || v == null || isNaN(v) ? '' : (+v).toLocaleString('id-ID', {maximumFractionDigits: 2});
    function fields() {
      const t = T[code]; if (!t) return;
      dims = Object.fromEntries(Object.entries(t.params).map(([k, p]) => [k, dims[k] ?? p.default ?? '']));
      q('[data-d-sizes]').innerHTML = (t.sizes || []).map((s, i) => `<button type="button" class="dl-dieline__size" data-d-size="${i}">${esc(s.label)}</button>`).join('') || '<span class="dl-dieline__none">Belum ada ukuran pasaran untuk tipe ini.</span>';
      q('[data-d-dims]').innerHTML = Object.entries(t.params).filter(([, p]) => p.type === 'NUM' || typeof p.default === 'number').map(([k, p]) => `<label class="dl-dieline__dim" title="${esc(p.label)}"><span>${esc(term(k, p))}</span><span class="dl-field__box"><input class="dl-input" type="text" inputmode="decimal" name="${esc(name)}[dims][${esc(k)}]" data-d-dim="${esc(k)}" value="${esc(dims[k] ?? '')}"><em>${esc(p.unit || 'mm')}</em></span></label>`).join('');
      markSize();
    }
    const markSize = () => { const t = T[code]; $$(el, '[data-d-size]').forEach(b => { const s = t.sizes[+b.dataset.dSize]; b.classList.toggle('is-on', Object.entries(s.dims).every(([k, v]) => +dims[k] === +v)); }); };
    function redraw() {
      const t = T[code], spec = t ? t.spec : null;
      if (!manual) { const f = window.TMDieline.flat(spec, dims); fw.value = f.w || ''; fh.value = f.h || ''; }
      const a = (parseFloat(String(fw.value).replace(',', '.')) || 0) * (parseFloat(String(fh.value).replace(',', '.')) || 0);
      fa.value = a ? a.toFixed(2) : '';
      window.TMDieline.render(q('[data-d-preview]'), spec, dims);
      annotate();
      q('[data-d-preview]').style.transform = `scale(${zoom})`; q('[data-d-zoomval]').textContent = Math.round(zoom * 100) + '%';
      q('[data-d-info]').innerHTML = t ? `<b>${esc(t.label)}</b>${t.category ? ` · ${esc(t.category)}` : ''} · Bentang <b>${numStr(fw.value)} × ${numStr(fh.value)} mm</b> · Luas <b>${numStr(a / 100)} cm²</b>${manual ? ' <span class="dl-badge dl-badge--warn">manual</span>' : ''}` : '';
      markSize();
      el.dispatchEvent(new CustomEvent('dl:dieline', {bubbles: true, detail: {type: code, dims: {...dims}, flat: {w: +fw.value || 0, h: +fh.value || 0, area: a}, manual}}));
    }

    // Lapisan garis ukuran (seperti gambar teknik): TMDieline TIDAK diubah; ukuran dibaca dari hasil render.
    // Batas panel = garis lipat (CREASE) tegak/mendatar + tepi luar bentuk (tanpa bleed). Tiap ruas dicocokkan
    // dengan parameter tipe -> kode internasional (L, W, H, GF, TF, DF, BF...).
    // Panjang total garis per jenis (untuk meter lari pisau): cutting, creasing, perforasi/lubang.
    function lengths(svg) {
      const S = window.TMDieline.STROKE, sum = {cut: 0, crease: 0, hole: 0};
      $$(svg, 'line,rect,path,circle,polyline,polygon').forEach(n => {
        if (n.closest('.dl-dim') || !n.getTotalLength) return;
        const st = (n.getAttribute('stroke') || '').toLowerCase(), L = n.getTotalLength();
        if (st === S.CUT.toLowerCase()) sum.cut += L; else if (st === S.CREASE.toLowerCase()) sum.crease += L; else if (st === S.HOLE.toLowerCase()) sum.hole += L;
      });
      const f = v => unit === 'cm' ? (v / 10).toLocaleString('id-ID', {maximumFractionDigits: 1}) : Math.round(v).toLocaleString('id-ID');
      q('[data-d-lens]').innerHTML = `<div class="is-cut"><b>${f(sum.cut)} ${unit}</b><span>Cutting line</span></div><div class="is-crease"><b>${f(sum.crease)} ${unit}</b><span>Creasing line</span></div><div class="is-hole"><b>${f(sum.hole)} ${unit}</b><span>Perforation / hole</span></div>`;
    }
    // Ilustrasi 3D statis isometrik dari ukuran: kotak (L x W x H), silinder (diameter), atau lembaran datar.
    function preview3d() {
      const box = q('[data-d-3d]'), t = T[code]; if (!box || !t) return;
      const L = +dims.product_length || 0, Wd = +dims.product_width || 0, H = +dims.product_height || +dims.lid_height || 0, D = +dims.diameter || 0;
      const c = Math.cos(Math.PI / 6), s = 0.5, iso = (x, y, z) => [(x - y) * c, (x + y) * s - z];
      let body = '';
      if (D && H) {
        const r = D / 2, top = iso(0, 0, H), bot = iso(0, 0, 0), rx = r * 1.22, ry = r * 0.7;
        body = `<path d="M${bot[0] - rx},${top[1]} L${bot[0] - rx},${bot[1]} A${rx},${ry} 0 0 0 ${bot[0] + rx},${bot[1]} L${bot[0] + rx},${top[1]} Z" class="dl-3d__side"/><ellipse cx="${top[0]}" cy="${top[1]}" rx="${rx}" ry="${ry}" class="dl-3d__top"/>`;
      } else if (L && Wd && H) {
        const P = (x, y, z) => iso(x, y, z).join(','), poly = (pts, cls) => `<polygon points="${pts.join(' ')}" class="${cls}"/>`;
        body = poly([P(0, Wd, 0), P(L, Wd, 0), P(L, Wd, H), P(0, Wd, H)], 'dl-3d__front') + poly([P(L, 0, 0), P(L, Wd, 0), P(L, Wd, H), P(L, 0, H)], 'dl-3d__side') + poly([P(0, 0, H), P(L, 0, H), P(L, Wd, H), P(0, Wd, H)], 'dl-3d__top')
          + `<line x1="${iso(L, Wd, 0)[0]}" y1="${iso(L, Wd, 0)[1]}" x2="${iso(L, Wd, H)[0]}" y2="${iso(L, Wd, H)[1]}" class="dl-3d__edge"/>`;
      } else {
        const fwv = +fw.value || 100, fhv = +fh.value || 70, P = (x, y) => iso(x, y, 0).join(',');
        body = `<polygon points="${[P(0, 0), P(fwv, 0), P(fwv, fhv), P(0, fhv)].join(' ')}" class="dl-3d__top"/>`;
      }
      box.innerHTML = `<svg viewBox="0 0 10 10"><g>${body}</g></svg><span>3D</span>`;
      const g = $(box, 'g'), bb = g.getBBox(), m = Math.max(bb.width, bb.height) * 0.1;
      $(box, 'svg').setAttribute('viewBox', `${bb.x - m} ${bb.y - m} ${bb.width + 2 * m} ${bb.height + 2 * m}`);
    }
    function annotate() {
      const t0 = T[code], dimTxt = ['product_length', 'product_width', 'product_height'].map(k => dims[k]).filter(v => v !== '' && v != null);
      if (v3) q('[data-d-title]').textContent = t0 ? `${t0.label.toUpperCase()}${dimTxt.length ? ' · ' + dimTxt.join(' × ') + ' MM' : ''}${dims.diameter ? ' · Ø ' + dims.diameter + ' MM' : ''}` : '';
      if (v3) preview3d();
      $$(el, '[data-d-unit] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b.dataset.value === unit));
      const svg = q('[data-d-preview] svg'), termsBox = q('[data-d-terms]');
      if (svg && v3) lengths(svg);
      if (!svg || !q('[data-d-dims-on]').checked) { if (termsBox) termsBox.innerHTML = ''; return; }
      const S = window.TMDieline.STROKE, crease = S.CREASE.toLowerCase(), bleed = S.BLEED.toLowerCase();
      let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
      const xs = [], ys = [];
      $$(svg, 'line,rect,path,circle,polyline,polygon').forEach(n => {
        const st = (n.getAttribute('stroke') || '').toLowerCase();
        if (st === bleed) return;
        const b = n.getBBox(); x0 = Math.min(x0, b.x); y0 = Math.min(y0, b.y); x1 = Math.max(x1, b.x + b.width); y1 = Math.max(y1, b.y + b.height);
        if (st === crease && n.tagName === 'line') {
          const a = +n.getAttribute('x1'), c = +n.getAttribute('x2'), d = +n.getAttribute('y1'), f = +n.getAttribute('y2');
          if (Math.abs(a - c) < 0.01 && !n.getAttribute('stroke-dasharray')) xs.push(a); else if (Math.abs(d - f) < 0.01) ys.push(d);
        }
      });
      if (!isFinite(x0)) return;
      const uniq = arr => [...arr].sort((a, b) => a - b).filter((v, i, a) => !i || v - a[i - 1] > 0.5);
      const cutX = uniq([x0, ...xs.filter(v => v > x0 + 0.5 && v < x1 - 0.5), x1]), cutY = uniq([y0, ...ys.filter(v => v > y0 + 0.5 && v < y1 - 0.5), y1]);
      const Wd = x1 - x0, Hd = y1 - y0, fs = Math.max(6, Math.min(16, Math.max(Wd, Hd) / 42)), gap = fs * 2.3;
      const params = Object.keys(T[code].params);
      // Bila beberapa parameter sama nilainya, posisi ruas menentukan (ruas bawah = Bottom Tuck, atas = Top Tuck).
      const codeOf = (len, prefer = []) => { const ok = k => TERMS[k] && k !== 'overflow_margin' && Math.abs((+dims[k] || -1) - len) < 0.06; const k = prefer.find(ok) || params.find(ok); return k ? TERMS[k][1] : ''; };
      const val = len => unit === 'cm' ? (Math.round(len / 10 * 100) / 100).toLocaleString('id-ID') : (Math.round(len * 10) / 10).toLocaleString('id-ID');
      const used = new Set();
      const hdim = (a, b, y, total) => { const c = total ? '' : codeOf(b - a); if (c) used.add(c); const t = (c ? c + ' ' : '') + val(b - a) + (total ? ' ' + unit : ''), tw = t.length * fs * 0.56, small = tw > b - a - 4;
        return `<line x1="${a}" y1="${y}" x2="${b}" y2="${y}" class="dl-dim__l" marker-start="url(#${uid}a)" marker-end="url(#${uid}a)"/><text x="${(a + b) / 2}" y="${y - fs * 0.45}" class="dl-dim__t ${c ? 'is-code' : ''}" font-size="${small ? fs * 0.72 : fs}" text-anchor="middle">${esc(t)}</text>`; };
      const vdim = (a, b, x, total, prefer) => { const c = total ? '' : codeOf(b - a, prefer); if (c) used.add(c); const t = (c ? c + ' ' : '') + val(b - a) + (total ? ' ' + unit : ''), tw = t.length * fs * 0.56, small = tw > b - a - 4, m = (a + b) / 2;
        return `<line x1="${x}" y1="${a}" x2="${x}" y2="${b}" class="dl-dim__l" marker-start="url(#${uid}a)" marker-end="url(#${uid}a)"/><text x="${x - fs * 0.45}" y="${m}" class="dl-dim__t ${c ? 'is-code' : ''}" font-size="${small ? fs * 0.72 : fs}" text-anchor="middle" transform="rotate(-90 ${x - fs * 0.45} ${m})">${esc(t)}</text>`; };
      const ext = [];
      cutX.forEach(x => ext.push(`<line x1="${x}" y1="${y0 - gap - fs * 0.6}" x2="${x}" y2="${y0 - 2}" class="dl-dim__e"/>`));
      cutY.forEach(y => ext.push(`<line x1="${x0 - gap - fs * 0.6}" y1="${y}" x2="${x0 - 2}" y2="${y}" class="dl-dim__e"/>`));
      ext.push(`<line x1="${x0}" y1="${y1 + 2}" x2="${x0}" y2="${y1 + gap + fs * 0.6}" class="dl-dim__e"/><line x1="${x1}" y1="${y1 + 2}" x2="${x1}" y2="${y1 + gap + fs * 0.6}" class="dl-dim__e"/>`);
      ext.push(`<line x1="${x1 + 2}" y1="${y0}" x2="${x1 + gap + fs * 0.6}" y2="${y0}" class="dl-dim__e"/><line x1="${x1 + 2}" y1="${y1}" x2="${x1 + gap + fs * 0.6}" y2="${y1}" class="dl-dim__e"/>`);
      let g = `<defs><marker id="${uid}a" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="${fs * 0.55}" markerHeight="${fs * 0.55}" markerUnits="userSpaceOnUse" orient="auto-start-reverse"><path d="M0,1 L10,5 L0,9 z" class="dl-dim__a"/></marker></defs><g class="dl-dim">${ext.join('')}`;
      if (cutX.length > 2) for (let i = 0; i < cutX.length - 1; i++) g += hdim(cutX[i], cutX[i + 1], y0 - gap);
      if (cutY.length > 2) for (let i = 0; i < cutY.length - 1; i++) g += vdim(cutY[i], cutY[i + 1], x0 - gap, false, i === 0 ? ['top_tuck_flap', 'lid_height'] : i === cutY.length - 2 ? ['bottom_tuck_flap'] : ['product_height']);
      g += hdim(x0, x1, y1 + gap, true) + vdim(y0, y1, x1 + gap + fs * 1.2, true) + '</g>';
      svg.insertAdjacentHTML('beforeend', g);
      const pad = gap + fs * 2.2;
      svg.setAttribute('viewBox', `${x0 - pad} ${y0 - pad} ${Wd + pad * 2 + fs} ${Hd + pad * 2}`);
      if (termsBox) termsBox.innerHTML = `<b>Satuan: ${unit}</b>` + Object.entries(TERMS).filter(([k, t]) => used.has(t[1]) || k === 'overflow_margin' && dims[k]).map(([k, t]) => `<span><i>${esc(t[1])}</i>${esc(t[0])}</span>`).join('') + '<span class="is-note">Total = tepi potong (tanpa bleed); Bentang di bawah = termasuk bleed. Ukuran tanpa kode = panel turunan (mis. sirip kunci).</span>';
    }
    el.addEventListener('input', e => {
      const d = e.target.dataset.dDim;
      if (d !== undefined) { dims[d] = e.target.value === '' ? '' : parseFloat(e.target.value.replace(',', '.')); return redraw(); }
      if (e.target.matches('[data-d-fw],[data-d-fh]') && manual) redraw();
    });
    el.addEventListener('change', e => {
      if (e.target.name === name + '[type]') { code = e.target.value; dims = {}; fields(); redraw(); }
      if (e.target.matches('[data-d-manual]')) { manual = e.target.checked; fw.readOnly = fh.readOnly = !manual; redraw(); }
      if (e.target.matches('[data-d-dims-on]')) redraw();
    });
    el.addEventListener('click', e => {
      const s = e.target.closest('[data-d-size]');
      if (s) { Object.assign(dims, T[code].sizes[+s.dataset.dSize].dims); $$(el, '[data-d-dim]').forEach(i => { i.value = dims[i.dataset.dDim] ?? ''; }); return redraw(); }
      const u = e.target.closest('[data-d-unit] .dl-segment__opt'); if (u) { unit = u.dataset.value; return redraw(); }
      const z = e.target.closest('[data-d-zoom]'); if (z) { zoom = Math.min(3, Math.max(0.4, Math.round((zoom + +z.dataset.dZoom * 0.2) * 10) / 10)); return redraw(); }
      if (e.target.closest('[data-d-svg]')) {
        const svgEl = q('[data-d-preview] svg'); if (!svgEl) return toast('Isi dimensi dulu.', 'warn');
        const out = svgEl.cloneNode(true); out.setAttribute('style', 'background:#1b1f2a'); out.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([out.outerHTML], {type: 'image/svg+xml'})); a.download = `dieline-${code.toLowerCase()}.svg`; a.click();
      }
    });
    el.dlDielineX = {get dims() { return {...dims}; }, setType: c => { code = c; dims = {}; fields(); redraw(); }, redraw};
    if (!window.TMDieline) { q('[data-d-preview]').textContent = 'TMDieline tidak termuat.'; return; }
    fields(); redraw();
  }


  /* ============================ IMPOSITION ============================ */
  // Penampil hasil ImpositionEngine: TIDAK menghitung UP sendiri. Sel digambar dengan dieline asli (TMDieline).
  function initImposition(el) {
    const jobs = JSON.parse(el.dataset.jobs || '[]'), planos = JSON.parse(el.dataset.planos || '[]'), G = +el.dataset.gripper, M = +el.dataset.margin;
    let job = jobs[0], plano = planos[0], zoom = 1, sel = null, calc = 'pcs';
    const xf = {};  // transform tampilan per sel: {rot180, mx, my}
    const paper = $(el, '[data-i2-paper]');
    const n0 = v => (+v || 0).toLocaleString('id-ID', {maximumFractionDigits: 2});
    const res = () => job.results[plano.code];
    // dieline 1 UP (viewBox dipotong ke bentang flat supaya pas 1:1 dengan sel)
    function unitSvg(j) {
      const s = window.TMDieline && window.TMDieline.svg(j.spec, j.dims); if (!s) return '';
      const P = +j.dims.overflow_margin || 0;
      return s.replace(/viewBox="[^"]*"/, `viewBox="${-P} ${-P} ${j.flat.w} ${j.flat.h}" preserveAspectRatio="none"`).replace(/width="100%" height="100%"/, `width="${j.flat.w}" height="${j.flat.h}"`);
    }
    function draw() {
      const r = res(), W = plano.w, H = plano.h, unit = unitSvg(job), nums = $(el, '[data-i2-numbers]').checked;
      const cells = r.cells.map(c => {
        const t = xf[c.n] || {}, inner = c.rot ? `translate(${c.x + c.w},${c.y}) rotate(90)` : `translate(${c.x},${c.y})`;
        const fw = job.flat.w, fh = job.flat.h, extra = [t.rot ? `rotate(180 ${fw / 2} ${fh / 2})` : '', t.mx ? `translate(${fw} 0) scale(-1 1)` : '', t.my ? `translate(0 ${fh}) scale(1 -1)` : ''].join(' ');
        return `<g class="dl-impo__cell ${sel === c.n ? 'is-sel' : ''}" data-i2-cell="${c.n}"><rect x="${c.x}" y="${c.y}" width="${c.w}" height="${c.h}" class="dl-impo__cellbg"/><g transform="${inner}"><g transform="${extra}">${unit}</g></g>${nums ? `<text x="${c.x + c.w / 2}" y="${c.y + c.h / 2}" class="dl-impo__num">${c.n}${c.rot ? '↻' : ''}</text>` : ''}</g>`;
      }).join('');
      const tick = [];
      for (let x = 0; x <= W; x += 50) tick.push(`<line x1="${x}" y1="0" x2="${x}" y2="${x % 100 ? -6 : -12}" class="dl-impo__tk"/>${x % 100 === 0 ? `<text x="${x}" y="-16" class="dl-impo__tt">${x / 10}</text>` : ''}`);
      for (let y = 0; y <= H; y += 50) tick.push(`<line x1="0" y1="${y}" x2="${y % 100 ? -6 : -12}" y2="${y}" class="dl-impo__tk"/>${y % 100 === 0 ? `<text x="-16" y="${y + 4}" class="dl-impo__tt" text-anchor="end">${y / 10}</text>` : ''}`);
      paper.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="-44 -34 ${W + 60} ${H + 50}" style="width:${zoom * 100}%">
        <defs><pattern id="dl-impo-hatch" width="8" height="8" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><line x1="0" y1="0" x2="0" y2="8" class="dl-impo__hatch"/></pattern></defs>
        ${tick.join('')}<rect x="0" y="0" width="${W}" height="${H}" class="dl-impo__sheet"/>
        <rect x="0" y="0" width="${W}" height="${G}" fill="url(#dl-impo-hatch)" class="dl-impo__grip"/><text x="${W / 2}" y="${G - 2}" class="dl-impo__gt">GRIPPER ${G} mm</text>
        <rect x="${M}" y="${G + M}" width="${W - 2 * M}" height="${H - G - 2 * M}" class="dl-impo__area"/>
        ${cells}</svg>`;
      $(el, '[data-i2-zoomval]').textContent = Math.round(zoom * 100) + '%';
      $(el, '[data-i2-sel]').textContent = sel ? `UP #${sel} terpilih` : 'Klik objek untuk memilih';
    }
    function panels() {
      $(el, '[data-i2-jobs]').innerHTML = jobs.map(j => `<button type="button" class="dl-impo__job ${j === job ? 'is-on' : ''}" data-i2-job="${esc(j.code)}"><b>${esc(j.label)}</b><small>${esc(j.code)} · bentang ${n0(j.flat.w / 10)} × ${n0(j.flat.h / 10)} cm</small></button>`).join('');
      $(el, '[data-i2-planos]').innerHTML = planos.map(p => `<button type="button" class="dl-segment__opt ${p === plano ? 'is-active' : ''}" data-i2-plano="${esc(p.code)}">${esc(p.code)}<small>${job.results[p.code].up} up</small></button>`).join('');
      const best = planos.reduce((a, p) => job.results[p.code].efficiency > job.results[a.code].efficiency ? p : a, planos[0]);
      $(el, '[data-i2-kv]').innerHTML = `<dt>Plano</dt><dd>${n0(plano.w / 10)} × ${n0(plano.h / 10)} cm</dd><dt>Bentang (flat)</dt><dd>${n0(job.flat.w / 10)} × ${n0(job.flat.h / 10)} cm</dd><dt>Area blank</dt><dd>${n0(job.flat.w * job.flat.h / 100)} cm²</dd><dt>Gap / Gripper / Margin</dt><dd>${el.dataset.gap || 3} / ${G} / ${M} mm</dd><dt>Plano paling efisien</dt><dd><b>${esc(best.code)}</b> (${n0(job.results[best.code].efficiency)}%)</dd>`;
      const r = res();
      $(el, '[data-i2-hero]').innerHTML = `<div><small>UP / plano</small><b>${r.up}</b></div><div><small>Metode</small><b>${esc(r.method)}</b></div><div><small>Efisiensi</small><b class="is-pos">${n0(r.efficiency)}%</b></div><div><small>Waste</small><b class="is-neg">${n0(r.waste)}%</b></div>`;
      $(el, '[data-i2-bar]').innerHTML = `<span style="width:${r.efficiency}%">Terpakai ${n0(r.efficiency)}%</span><em>terbuang ${n0(r.waste)}%</em>`;
      calcOut();
    }
    function calcOut() {
      const r = res(), q = +String($(el, '[data-i2-qty]').value).replace(/\D/g, '') || 0, sp = parseFloat(String($(el, '[data-i2-spoil]').value).replace(',', '.')) || 0;
      $(el, '[data-i2-inlabel]').textContent = calc === 'pcs' ? 'Qty order (pcs)' : 'Jumlah plano (lembar)';
      let html;
      if (calc === 'pcs') { const net = r.up ? Math.ceil(q / r.up) : 0, extra = Math.ceil(net * sp / 100); html = `<div class="dl-impo__big"><small>Plano dibutuhkan</small><b>${n0(net + extra)}</b><span>lembar</span></div><p>${n0(net)} bersih + ${n0(extra)} inschiet (${n0(sp)}%) · sisa hasil ${n0((net) * r.up - q)} pcs</p>`; }
      else { const net = Math.floor(q / (1 + sp / 100)); html = `<div class="dl-impo__big"><small>Hasil produk</small><b>${n0(net * r.up)}</b><span>pcs</span></div><p>${n0(q)} plano − inschiet ${n0(sp)}% = ${n0(net)} plano efektif × ${r.up} up</p>`; }
      $(el, '[data-i2-result]').innerHTML = html;
    }
    el.addEventListener('click', e => {
      const x = e.target;
      const j = x.closest('[data-i2-job]'); if (j) { job = jobs.find(v => v.code === j.dataset.i2Job); sel = null; Object.keys(xf).forEach(k => delete xf[k]); panels(); return draw(); }
      const p = x.closest('[data-i2-plano]'); if (p) { plano = planos.find(v => v.code === p.dataset.i2Plano); sel = null; panels(); return draw(); }
      const c = x.closest('[data-i2-cell]'); if (c) { sel = sel === +c.dataset.i2Cell ? null : +c.dataset.i2Cell; return draw(); }
      const z = x.closest('[data-i2-zoom]'); if (z) { zoom = Math.min(3, Math.max(0.4, Math.round((zoom + +z.dataset.i2Zoom * 0.2) * 10) / 10)); return draw(); }
      const tf = x.closest('[data-i2-rot],[data-i2-mx],[data-i2-my]');
      if (tf) { const k = tf.hasAttribute('data-i2-rot') ? 'rot' : tf.hasAttribute('data-i2-mx') ? 'mx' : 'my'; const targets = sel ? [sel] : res().cells.map(c => c.n); targets.forEach(n => { xf[n] = xf[n] || {}; xf[n][k] = !xf[n][k]; }); toast(`${{rot: 'Putar 180°', mx: 'Mirror X', my: 'Mirror Y'}[k]} → ${sel ? 'UP #' + sel : 'semua UP'} (tampilan)`); return draw(); }
      const cm = x.closest('[data-i2-calc] .dl-segment__opt'); if (cm) { calc = cm.dataset.value; $$(el, '[data-i2-calc] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b === cm)); return calcOut(); }
      if (x.closest('[data-i2-svg]')) { const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([$(paper, 'svg').outerHTML], {type: 'image/svg+xml'})); a.download = `imposition-${job.code}-${plano.code}.svg`; a.click(); }
    });
    el.addEventListener('input', e => { if (e.target.matches('[data-i2-qty],[data-i2-spoil]')) calcOut(); });
    el.addEventListener('change', e => { if (e.target.matches('[data-i2-numbers]')) draw(); });
    el.dlImposition = {select: (j, p) => { job = jobs.find(v => v.code === j) || job; plano = planos.find(v => v.code === p) || plano; panels(); draw(); }};
    panels(); draw();
  }


  /* ============================ CHARTS ============================ */
  const CH_TONES = ['accent', 'success', 'warn', 'danger', 'violet', 'info', 'neutral', 'teal'];
  const chNum = (v, f) => {
    if (v == null || isNaN(v)) return '–';
    const a = Math.abs(v), s = a >= 1e12 ? [1e12, ' T'] : a >= 1e9 ? [1e9, ' M'] : a >= 1e6 ? [1e6, ' jt'] : a >= 1e4 ? [1e3, ' rb'] : [1, ''];
    const n = (v / s[0]).toLocaleString('id-ID', {maximumFractionDigits: s[0] > 1 ? 1 : 2}) + s[1];
    return f === 'money' ? 'Rp ' + n : f === 'percent' ? n + '%' : n;
  };
  const niceMax = m => { if (m <= 0) return 1; const p = 10 ** Math.floor(Math.log10(m)), f = m / p; return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * p; };
  function initChart(el) {
    const type = el.dataset.type, fmt2 = el.dataset.format, labels = JSON.parse(el.dataset.labels || '[]');
    const series = JSON.parse(el.dataset.series || '[]').map((s, i) => ({...s, tone: s.tone || CH_TONES[i % CH_TONES.length], on: true}));
    const plot = $(el, '[data-ch-plot]'), tip = $(el, '[data-ch-tip]');
    let W = 640, H = 300;  // diukur ulang dari kotak plot tiap gambar -> teks tetap ukuran asli
    const P = {l: 56, r: 16, t: 14, b: 40};
    const pieLike = ['pie', 'donut', 'funnel'].includes(type);
    const tipOn = (e, html) => { tip.innerHTML = html; tip.hidden = false; const r = el.getBoundingClientRect(); tip.style.left = Math.min(e.clientX - r.left + 12, r.width - 180) + 'px'; tip.style.top = (e.clientY - r.top + 12) + 'px'; };
    const esc2 = esc;
    function axes(max, horizontal) {
      let g = '';
      const N = horizontal && W < 520 ? 2 : 4;  // sumbu mendatar sempit: lebih sedikit angka supaya tidak berdempet
      for (let i = 0; i <= N; i++) {
        const v = max * i / N;
        if (horizontal) { const x = P.l + (W - P.l - P.r) * i / N; g += `<line x1="${x}" y1="${P.t}" x2="${x}" y2="${H - P.b}" class="dl-chart__grid"/><text x="${x}" y="${H - P.b + 16}" class="dl-chart__ax" text-anchor="middle">${esc2(chNum(v, fmt2))}</text>`; }
        else { const y = H - P.b - (H - P.t - P.b) * i / N; g += `<line x1="${P.l}" y1="${y}" x2="${W - P.r}" y2="${y}" class="dl-chart__grid"/><text x="${P.l - 8}" y="${y + 4}" class="dl-chart__ax" text-anchor="end">${esc2(chNum(v, fmt2))}</text>`; }
      }
      return g;
    }
    const xLabels = (cx) => labels.map((l, i) => `<text x="${cx(i)}" y="${H - P.b + 16}" class="dl-chart__ax" text-anchor="middle">${esc2(l)}</text>`).join('');
    function cartesian() {
      const vis = series.filter(s => s.on), n = labels.length, iw = (W - P.l - P.r) / Math.max(1, n);
      const stacked = type === 'stacked', bars = vis.filter(s => type === 'bar' || stacked || (type === 'combo' && (s.type || 'bar') === 'bar')), lines = vis.filter(s => ['line', 'area'].includes(type) || (type === 'combo' && s.type === 'line'));
      const max = niceMax(Math.max(1, ...labels.map((_, i) => stacked ? bars.reduce((t, s) => t + (+s.data[i] || 0), 0) : Math.max(0, ...vis.map(s => +s.data[i] || 0)))));
      const y = v => H - P.b - (H - P.t - P.b) * (v / max), cx = i => P.l + iw * (i + 0.5);
      let g = axes(max) + xLabels(cx);
      const bw = Math.min(46, iw * 0.7 / (stacked ? 1 : Math.max(1, bars.length)));
      labels.forEach((l, i) => {
        let acc = 0;
        bars.forEach((s, k) => {
          const v = +s.data[i] || 0, x = stacked ? cx(i) - bw / 2 : cx(i) - (bw * bars.length) / 2 + k * bw, top = stacked ? y(acc + v) : y(v), h = stacked ? y(acc) - y(acc + v) : H - P.b - y(v);
          g += `<rect x="${x + 1}" y="${top}" width="${Math.max(1, bw - 2)}" height="${Math.max(0, h)}" rx="3" class="dl-chart--${s.tone} dl-chart__bar" data-tip="${esc2(l)}|${esc2(s.name)}|${v}"/>`;
          acc += v;
        });
      });
      lines.forEach(s => {
        const pts = labels.map((_, i) => [cx(i), y(+s.data[i] || 0)]);
        if (type === 'area') g += `<path d="M${pts[0][0]},${H - P.b} L${pts.map(p => p.join(',')).join(' L')} L${pts[pts.length - 1][0]},${H - P.b} Z" class="dl-chart--${s.tone} dl-chart__area"/>`;
        g += `<polyline points="${pts.map(p => p.join(',')).join(' ')}" class="dl-chart--${s.tone} dl-chart__line"/>` + pts.map((p, i) => `<circle cx="${p[0]}" cy="${p[1]}" r="4" class="dl-chart--${s.tone} dl-chart__dot" data-tip="${esc2(labels[i])}|${esc2(s.name)}|${s.data[i]}"/>`).join('');
      });
      return g;
    }
    function hbar() {
      const vis = series.filter(s => s.on), n = labels.length, ih = (H - P.t - P.b) / Math.max(1, n), L = 120;
      const max = niceMax(Math.max(1, ...vis.flatMap(s => s.data.map(Number))));
      const x = v => L + (W - L - P.r) * v / max, bh = Math.min(26, ih * 0.7 / Math.max(1, vis.length));
      let g = ''; const P0 = P.l; P.l = L; g += axes(max, true); P.l = P0;
      labels.forEach((l, i) => {
        const cy = P.t + ih * (i + 0.5);
        g += `<text x="${L - 8}" y="${cy + 4}" class="dl-chart__ax" text-anchor="end">${esc2(l)}</text>`;
        vis.forEach((s, k) => { const v = +s.data[i] || 0, yy = cy - (bh * vis.length) / 2 + k * bh; g += `<rect x="${L}" y="${yy + 1}" width="${Math.max(0, x(v) - L)}" height="${bh - 2}" rx="3" class="dl-chart--${s.tone} dl-chart__bar" data-tip="${esc2(l)}|${esc2(s.name)}|${v}"/><text x="${x(v) + 5}" y="${yy + bh / 2 + 4}" class="dl-chart__val">${esc2(chNum(v, fmt2))}</text>`; });
      });
      return g;
    }
    function pie() {
      const s = series[0] || {data: []}, vals = labels.map((_, i) => series[0] && hidden.has(i) ? 0 : +s.data[i] || 0), tot = vals.reduce((a, b) => a + b, 0) || 1;
      const R = Math.max(50, Math.min(H / 2 - 12, (W - 190) / 2 - 10)), cx = R + 14, cy = H / 2, r = type === 'donut' ? R * 0.6 : 0, lx0 = cx + R + 22;
      let a0 = -Math.PI / 2, g = '';
      vals.forEach((v, i) => {
        if (!v) return;
        const a1 = a0 + v / tot * Math.PI * 2, large = a1 - a0 > Math.PI ? 1 : 0, p = (a, rr) => [cx + rr * Math.cos(a), cy + rr * Math.sin(a)];
        const [x0, y0] = p(a0, R), [x1, y1] = p(a1, R), [x2, y2] = p(a1, r), [x3, y3] = p(a0, r);
        const d = v / tot > 0.9999 ? `M${cx - R},${cy} a${R},${R} 0 1,0 ${2 * R},0 a${R},${R} 0 1,0 ${-2 * R},0` + (r ? ` M${cx - r},${cy} a${r},${r} 0 1,1 ${2 * r},0 a${r},${r} 0 1,1 ${-2 * r},0` : '')
          : `M${x0},${y0} A${R},${R} 0 ${large} 1 ${x1},${y1} ` + (r ? `L${x2},${y2} A${r},${r} 0 ${large} 0 ${x3},${y3} Z` : `L${cx},${cy} Z`);
        const mid = (a0 + a1) / 2, [lx, ly] = p(mid, r ? (R + r) / 2 : R * 0.62);
        g += `<path d="${d}" fill-rule="evenodd" class="dl-chart--${CH_TONES[i % CH_TONES.length]} dl-chart__slice" data-tip="${esc2(labels[i])}|${esc2(s.name || '')}|${v}|${(v / tot * 100).toFixed(1)}"/>`;
        if (v / tot >= 0.05) g += `<text x="${lx}" y="${ly + 4}" class="dl-chart__pct" text-anchor="middle">${Math.round(v / tot * 100)}%</text>`;
        a0 = a1;
      });
      if (r) g += `<text x="${cx}" y="${cy - 4}" class="dl-chart__total" text-anchor="middle">${esc2(chNum(tot, fmt2))}</text><text x="${cx}" y="${cy + 16}" class="dl-chart__ax" text-anchor="middle">Total</text>`;
      g += labels.map((l, i) => `<g class="dl-chart__pl ${hidden.has(i) ? 'is-off' : ''}" data-ch-slice="${i}"><rect x="${lx0}" y="${40 + i * 24}" width="12" height="12" rx="3" class="dl-chart--${CH_TONES[i % CH_TONES.length]} dl-chart__sw"/><text x="${lx0 + 20}" y="${50 + i * 24}" class="dl-chart__lt"><title>${esc2(l)} · ${esc2(chNum(+s.data[i] || 0, fmt2))}</title>${esc2(l.length > Math.max(8, (W - lx0 - 90) / 7) ? l.slice(0, Math.max(8, (W - lx0 - 90) / 7)) + '…' : l)} · ${esc2(chNum(+s.data[i] || 0, fmt2))}</text></g>`).join('');
      return g;
    }
    function funnel() {
      // Label tahap di kiri, corong di tengah, % dari tahap pertama di kanan (teks tidak pernah terpotong).
      const s = series[0] || {data: []}, vals = s.data.map(Number), max = Math.max(1, ...vals), n = vals.length, h = (H - 16) / Math.max(1, n);
      const L = Math.min(130, W * 0.3), R0 = W - 54, cx = (L + R0) / 2, fw = R0 - L - 8;
      return vals.map((v, i) => {
        const w0 = fw * v / max, w1 = fw * (vals[i + 1] ?? v * 0.85) / max, y0 = 8 + i * h, ym = y0 + (h - 4) / 2 + 4;
        return `<text x="${L - 6}" y="${ym}" class="dl-chart__ax" text-anchor="end">${esc2(labels[i])}</text>`
          + `<path d="M${cx - w0 / 2},${y0} L${cx + w0 / 2},${y0} L${cx + w1 / 2},${y0 + h - 4} L${cx - w1 / 2},${y0 + h - 4} Z" class="dl-chart--${CH_TONES[i % CH_TONES.length]} dl-chart__slice" data-tip="${esc2(labels[i])}|${i ? (v / vals[i - 1] * 100).toFixed(1) + '% dari tahap sebelumnya' : 'tahap awal'}|${v}"/>`
          + (Math.min(w0, w1) > 44 ? `<text x="${cx}" y="${ym}" class="dl-chart__pct" text-anchor="middle">${esc2(chNum(v, fmt2))}</text>` : `<text x="${cx + Math.max(w0, w1) / 2 + 6}" y="${ym}" class="dl-chart__val">${esc2(chNum(v, fmt2))}</text>`)
          + `<text x="${W - 4}" y="${ym}" class="dl-chart__ax" text-anchor="end">${(v / (vals[0] || 1) * 100).toFixed(0)}%</text>`;
      }).join('');
    }
    function heatmap() {
      const rows = series, n = labels.length, L = 110, cw = (W - L - P.r) / Math.max(1, n), rh = (H - P.t - 30) / Math.max(1, rows.length);
      const all = rows.flatMap(s => s.data.map(Number)), mn = Math.min(...all), mx = Math.max(...all);
      let g = labels.map((l, i) => `<text x="${L + cw * (i + 0.5)}" y="${H - 10}" class="dl-chart__ax" text-anchor="middle">${esc2(l)}</text>`).join('');
      rows.forEach((s, r) => {
        g += `<text x="${L - 8}" y="${P.t + rh * (r + 0.5) + 4}" class="dl-chart__ax" text-anchor="end">${esc2(s.name)}</text>`;
        s.data.forEach((v, i) => { const k = mx > mn ? (v - mn) / (mx - mn) : 0.5; g += `<rect x="${L + cw * i + 1}" y="${P.t + rh * r + 1}" width="${cw - 2}" height="${rh - 2}" rx="4" class="dl-chart__heat" style="--k:${(0.08 + k * 0.92).toFixed(2)}" data-tip="${esc2(s.name)}|${esc2(labels[i])}|${v}"/><text x="${L + cw * (i + 0.5)}" y="${P.t + rh * (r + 0.5) + 4}" class="dl-chart__hv ${k > 0.55 ? 'is-dark' : ''}" text-anchor="middle">${esc2(chNum(v, fmt2))}</text>`; });
      });
      return g;
    }
    function radar() {
      const vis = series.filter(s => s.on), n = labels.length, cx = W / 2, cy = H / 2 + 4, R = Math.min(H / 2 - 26, W / 3), max = niceMax(Math.max(1, ...vis.flatMap(s => s.data.map(Number))));
      const pt = (i, v) => { const a = -Math.PI / 2 + i / n * Math.PI * 2; return [cx + R * v / max * Math.cos(a), cy + R * v / max * Math.sin(a)]; };
      let g = '';
      for (let k = 1; k <= 4; k++) g += `<polygon points="${labels.map((_, i) => pt(i, max * k / 4).join(',')).join(' ')}" class="dl-chart__grid dl-chart__web"/>`;
      labels.forEach((l, i) => { const [x, y] = pt(i, max * 1.14); g += `<line x1="${cx}" y1="${cy}" x2="${pt(i, max)[0]}" y2="${pt(i, max)[1]}" class="dl-chart__grid"/><text x="${x}" y="${y + 4}" class="dl-chart__ax" text-anchor="middle">${esc2(l)}</text>`; });
      vis.forEach(s => { g += `<polygon points="${s.data.map((v, i) => pt(i, +v || 0).join(',')).join(' ')}" class="dl-chart--${s.tone} dl-chart__area dl-chart__radar"/>` + s.data.map((v, i) => `<circle cx="${pt(i, +v || 0)[0]}" cy="${pt(i, +v || 0)[1]}" r="4" class="dl-chart--${s.tone} dl-chart__dot" data-tip="${esc2(labels[i])}|${esc2(s.name)}|${v}"/>`).join(''); });
      return g;
    }
    const hidden = new Set();
    function draw() {
      W = Math.max(280, Math.round(plot.clientWidth || 640)); H = Math.max(180, Math.round(plot.clientHeight || 300));
      P.l = type === 'hbar' || type === 'heatmap' ? 56 : Math.min(64, Math.max(44, W * 0.08));
      const body = type === 'hbar' ? hbar() : type === 'pie' || type === 'donut' ? pie() : type === 'funnel' ? funnel() : type === 'heatmap' ? heatmap() : type === 'radar' ? radar() : cartesian();
      plot.innerHTML = `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMid meet" role="img" aria-label="${esc2($(el, '.dl-chart__head b')?.textContent || 'Grafik')}">${body}</svg>`;
      const lg = $(el, '[data-ch-legend]');
      if (lg) lg.innerHTML = pieLike || type === 'heatmap' ? '' : series.map((s, i) => `<button type="button" class="dl-chart__li ${s.on ? '' : 'is-off'}" data-ch-series="${i}"><i class="dl-chart--${s.tone}"></i>${esc2(s.name)}</button>`).join('');
    }
    el.addEventListener('mousemove', e => {
      const t = e.target.closest('[data-tip]'); if (!t) { tip.hidden = true; return; }
      const [a, b, v, pct] = t.dataset.tip.split('|');
      tipOn(e, `<b>${esc2(a)}</b>${b ? `<span>${esc2(b)}</span>` : ''}<em>${esc2(chNum(+v, fmt2))}${pct ? ` · ${pct}%` : ''}</em>`);
    });
    el.addEventListener('mouseleave', () => { tip.hidden = true; });
    el.addEventListener('click', e => {
      const s = e.target.closest('[data-ch-series]'); if (s) { const k = series[+s.dataset.chSeries]; if (series.filter(x => x.on).length > 1 || !k.on) k.on = !k.on; return draw(); }
      const p = e.target.closest('[data-ch-slice]'); if (p) { const i = +p.dataset.chSlice; hidden.has(i) ? hidden.delete(i) : hidden.add(i); draw(); }
    });
    el.dlChart = {series, labels, draw};
    draw();
    let rw = plot.clientWidth; new ResizeObserver(() => { if (Math.abs(plot.clientWidth - rw) > 4) { rw = plot.clientWidth; draw(); } }).observe(plot);
  }


  /* ============================ QR & BARCODE ============================ */
  // Library dimuat sekali dari cdnjs saat komponen pertama muncul (tidak membebani halaman lain).
  const libOnce = {};
  const loadLib = (url, test) => test() ? Promise.resolve() : (libOnce[url] = libOnce[url] || new Promise((ok, no) => { const s = document.createElement('script'); s.src = url; s.onload = ok; s.onerror = no; document.head.appendChild(s); }));
  function initQrcode(el) {
    loadLib('https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', () => window.QRCode).then(() => {
      el.innerHTML = ''; new window.QRCode(el, {text: el.dataset.text || ' ', width: +el.dataset.size, height: +el.dataset.size, colorDark: '#111827', colorLight: '#ffffff', correctLevel: window.QRCode.CorrectLevel.M});
      const img = el.querySelector('img'); if (img) img.alt = 'QR ' + el.dataset.text;
    }).catch(() => { el.textContent = el.dataset.text; el.classList.add('is-fallback'); });
  }
  function initBarcode(el) {
    loadLib('https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js', () => window.JsBarcode).then(() => {
      try { window.JsBarcode(el, el.dataset.value, {format: el.dataset.format, height: +el.dataset.height, width: +el.dataset.width, displayValue: el.dataset.text === '1', margin: 0, fontSize: 13, font: 'monospace', background: 'transparent'}); }
      catch (e) { el.outerHTML = `<span class="dl-barcode__err">Barcode ${esc(el.dataset.format)} tidak sah: ${esc(el.dataset.value)}</span>`; }
    }).catch(() => { el.outerHTML = `<code>${esc(el.dataset.value)}</code>`; });
  }


  /* ============================ FLOW CONFIG ============================ */
  function initFlowConfig(el) {
    const roles = JSON.parse(el.dataset.roles || '{}');
    const num = v => +String(v ?? '').replace(/\D/g, '') || 0;
    function sim(panel) {
      const lines = $(panel, '[data-dl-lines]'); if (!lines || !lines.dlLines) return;
      const v = num($(panel, '[data-fc-value]').value), rows = lines.dlLines.rows;
      const act = {KNOW: 'Mengetahui', APPROVE: 'Menyetujui'};
      $(panel, '[data-fc-path]').innerHTML = `<li class="is-done"><span class="dl-appr__dot"><i class="fa-solid fa-check"></i></span><b>Pembuat mengajukan</b><small>Langkah 0 · selalu</small></li>`
        + rows.map((r, i) => { const on = !+r.min || v >= +r.min; return `<li class="${on ? 'is-current' : 'is-skip'}"><span class="dl-appr__dot">${on ? i + 1 : ''}</span><b>${esc(r.name || 'Langkah ' + (i + 1))} — ${esc(act[r.action] || '')}</b><small>${esc(roles[r.role] || r.role || '—')} · ${+r.min ? (on ? `aktif: nilai ≥ Rp ${(+r.min).toLocaleString('id-ID')}` : `dilewati: nilai < Rp ${(+r.min).toLocaleString('id-ID')}`) : 'selalu aktif'}</small></li>`; }).join('');
    }
    $$(el, '[data-fc-doc]').forEach(panel => {
      const s = () => setTimeout(() => sim(panel));
      panel.addEventListener('input', e => { if (e.target.matches('[data-fc-value]')) { const n = num(e.target.value); e.target.value = n ? n.toLocaleString('id-ID') : ''; } s(); });
      panel.addEventListener('change', s); panel.addEventListener('click', s);
      sim(panel);
    });
    el.addEventListener('click', e => {
      if (!e.target.closest('[data-fc-save]')) return;
      const flows = {}; let bad = 0;
      $$(el, '[data-fc-doc]').forEach(p => { const l = $(p, '[data-dl-lines]'); bad += l.dlLines.validate(); flows[p.dataset.fcDoc] = l.dlLines.rows; });
      if (bad) return toast(`${bad} isian wajib di alur approval belum diisi.`, 'danger');
      el.dispatchEvent(new CustomEvent('dl:flowcfg-save', {bubbles: true, detail: {flows}}));
      toast('Konfigurasi alur approval disimpan.', 'success');
    });
  }


  /* ============================ ROSTER BOARD ============================ */
  function initRoster(el) {
    const shifts = JSON.parse(el.dataset.shifts || '[]'), days = JSON.parse(el.dataset.days || '[]'), people = JSON.parse(el.dataset.people || '[]');
    const assign = JSON.parse(el.dataset.assign || '{}'), picked = new Set(), shut = new Set();
    let q = '', drag = null;
    const ini = n => String(n).trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
    const hue = s => [...String(s)].reduce((h, c) => (h * 31 + c.charCodeAt(0)) % 360, 0);
    const hrs = (a, b) => { const [h1, m1] = a.split(':').map(Number), [h2, m2] = b.split(':').map(Number); let d = (h2 * 60 + m2) - (h1 * 60 + m1); if (d <= 0) d += 1440; return d / 60; };
    const weekHours = s => days.reduce((t, d) => t + (s.pattern[d.key] ? Math.max(0, hrs(...s.pattern[d.key]) - 1) : 0), 0);  // dipotong 1 jam istirahat
    const workDays = s => days.filter(d => s.pattern[d.key]).length;
    const chip = p => `<span class="dl-roster__chip" draggable="true" data-r-person="${esc(p.id)}" title="${esc(p.name)} · ${esc(p.code)}"><i style="--h:${hue(p.name)}">${esc(ini(p.name))}</i><b>${esc(p.name)}</b><small>${esc(p.code)} · ${esc(p.role)}</small><em>${workDays(shifts.find(s => s.key === assign[p.id]))}h</em><button type="button" data-r-remove title="Lepas dari shift">&times;</button></span>`;
    function grid() {
      const head = `<div class="dl-roster__row is-head"><div class="dl-roster__shift">Shift / Pola Jam</div>${days.map(d => `<div class="dl-roster__day ${d.off ? 'is-off' : ''}"><b>${esc(d.key)}</b><small>${esc(d.date || '')}</small></div>`).join('')}</div>`;
      $(el, '[data-r-grid]').innerHTML = head + shifts.map(s => {
        const list = people.filter(p => assign[p.id] === s.key), cap = +s.capacity || 0, pct = cap ? Math.min(100, list.length / cap * 100) : 0;
        const pat = days.filter(d => s.pattern[d.key]).map(d => d.key);
        return `<div class="dl-roster__row dl-roster--${esc(s.tone || 'accent')}" data-r-shift="${esc(s.key)}">
          <div class="dl-roster__shift"><b><i></i>${esc(s.label)}</b><small>${esc(pat.length === days.length ? 'Setiap hari' : pat[0] + '–' + pat[pat.length - 1])} · ${workDays(s)} hari · ${Math.round(weekHours(s) * 10) / 10}j/minggu</small>
            ${cap ? `<span class="dl-roster__cap ${list.length > cap ? 'is-over' : ''}"><span style="width:${pct}%"></span></span><em class="${list.length > cap ? 'is-over' : ''}">${list.length}/${cap}</em>` : ''}</div>
          ${days.map(d => `<div class="dl-roster__time ${s.pattern[d.key] ? '' : 'is-off'}">${s.pattern[d.key] ? `${esc(s.pattern[d.key][0])}<br><small>${esc(s.pattern[d.key][1])}${hrs(...s.pattern[d.key]) && s.pattern[d.key][1] < s.pattern[d.key][0] ? ' +1' : ''}</small>` : 'LIBUR'}</div>`).join('')}
          <div class="dl-roster__drop" data-r-drop="${esc(s.key)}">${list.map(chip).join('') || '<span class="dl-roster__hint">Tarik pegawai ke sini — otomatis mengikuti pola jam shift ini</span>'}</div>
        </div>`;
      }).join('');
    }
    function pool() {
      const free = people.filter(p => !assign[p.id] && (!q || (p.name + ' ' + p.code).toLowerCase().includes(q)));
      const groups = [...new Set(free.map(p => p.role))];
      $(el, '[data-r-poolcount]').textContent = `${free.length} dari ${people.length} pegawai belum terjadwal`;
      $(el, '[data-r-pool]').innerHTML = groups.map(g => { const list = free.filter(p => p.role === g), all = list.every(p => picked.has(String(p.id))); return `<div class="dl-roster__grp"><button type="button" class="dl-roster__grphead" data-r-grp="${esc(g)}"><input type="checkbox" data-r-grpall="${esc(g)}" ${all && list.length ? 'checked' : ''} aria-label="Pilih semua ${esc(g)}"><i class="fa-solid fa-caret-${shut.has(g) ? 'right' : 'down'}"></i><b>${esc(g)}</b><small>${list.length} org</small></button>
        ${shut.has(g) ? '' : list.map(p => `<label class="dl-roster__person ${picked.has(String(p.id)) ? 'is-picked' : ''}" draggable="true" data-r-person="${esc(p.id)}"><input type="checkbox" data-r-pick="${esc(p.id)}" ${picked.has(String(p.id)) ? 'checked' : ''}><i style="--h:${hue(p.name)}">${esc(ini(p.name))}</i><span><b>${esc(p.name)}</b><small>${esc(p.code)} · ${p.skills ?? 0} skill</small></span><em>${esc(p.role)}</em></label>`).join('')}</div>`; }).join('') || '<div class="dl-empty"><b class="dl-empty__title">Semua pegawai sudah terjadwal</b></div>';
      $(el, '[data-r-selbar]').hidden = !picked.size; $(el, '[data-r-selcount]').textContent = picked.size;
      const sched = Object.values(assign).filter(Boolean).length;
      $(el, '[data-r-stats]').innerHTML = `<span><b>${free.length}</b>Pool tampil</span><span class="is-good"><b>${sched}</b>Terjadwal</span><span class="is-warn"><b>${people.length - sched}</b>Belum</span>`;
    }
    const render = () => { grid(); pool(); };
    const put = (ids, key) => {
      const s = shifts.find(x => x.key === key); ids.forEach(id => { assign[id] = key; picked.delete(String(id)); });
      render(); el.dispatchEvent(new CustomEvent('dl:roster', {bubbles: true, detail: {assign: {...assign}}}));
      const n = people.filter(p => assign[p.id] === key).length; if (s.capacity && n > s.capacity) toast(`${s.label} melebihi kapasitas (${n}/${s.capacity}).`, 'warn');
    };
    el.addEventListener('dragstart', e => { const c = e.target.closest('[data-r-person]'); if (!c) return; drag = c.dataset.rPerson; c.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', drag); } catch (x) {} });
    el.addEventListener('dragend', () => { drag = null; $$(el, '.is-drag,.is-over').forEach(x => x.classList.remove('is-drag', 'is-over')); });
    el.addEventListener('dragover', e => { const d = e.target.closest('[data-r-drop],[data-r-pool]'); if (!d || drag == null) return; e.preventDefault(); $$(el, '.is-over').forEach(x => x.classList.remove('is-over')); d.classList.add('is-over'); });
    el.addEventListener('drop', e => {
      const d = e.target.closest('[data-r-drop],[data-r-pool]'); if (!d || drag == null) return; e.preventDefault();
      const id = drag; drag = null;
      if (d.hasAttribute('data-r-pool')) { delete assign[id]; render(); el.dispatchEvent(new CustomEvent('dl:roster', {bubbles: true, detail: {assign: {...assign}}})); }
      else put([id], d.dataset.rDrop);
    });
    el.addEventListener('click', e => {
      const x = e.target;
      const rm = x.closest('[data-r-remove]'); if (rm) { delete assign[rm.closest('[data-r-person]').dataset.rPerson]; render(); el.dispatchEvent(new CustomEvent('dl:roster', {bubbles: true, detail: {assign: {...assign}}})); return; }
      const ga = x.closest('[data-r-grpall]'); if (ga) { e.stopPropagation(); const list = people.filter(p => !assign[p.id] && p.role === ga.dataset.rGrpall); list.forEach(p => ga.checked ? picked.add(String(p.id)) : picked.delete(String(p.id))); return pool(); }
      const g = x.closest('[data-r-grp]'); if (g) { const k = g.dataset.rGrp; shut.has(k) ? shut.delete(k) : shut.add(k); return pool(); }
      if (x.closest('[data-r-selnone]')) { picked.clear(); return pool(); }
      const row = x.closest('[data-r-shift]'); if (row && picked.size && !x.closest('[data-r-person]')) { const n = picked.size; put([...picked], row.dataset.rShift); toast(`${n} pegawai ditempatkan di ${shifts.find(s => s.key === row.dataset.rShift).label}.`, 'success'); return; }
      const wk = x.closest('[data-r-week]'); if (wk) { el.dispatchEvent(new CustomEvent('dl:roster-week', {bubbles: true, detail: {step: +wk.dataset.rWeek}})); toast('Pindah minggu (layar pemakai memuat data minggu itu).'); return; }
      if (x.closest('[data-r-save]')) { el.dispatchEvent(new CustomEvent('dl:roster-save', {bubbles: true, detail: {assign: {...assign}}})); toast('Roster minggu ini disimpan.', 'success'); }
    });
    el.addEventListener('change', e => { const p = e.target.closest('[data-r-pick]'); if (p) { p.checked ? picked.add(p.dataset.rPick) : picked.delete(p.dataset.rPick); pool(); } });
    $(el, '[data-r-search]').addEventListener('input', e => { q = e.target.value.trim().toLowerCase(); pool(); });
    el.dlRoster = {assign, render, put};
    render();
  }


  /* ============================ DRILLDOWN ============================ */
  function initDrill(el) {
    const [sumEl, detEl] = $$(el, '[data-dl-grid]'), sumBox = $(el, '[data-dd-summary]'), detBox = $(el, '[data-dd-detail]'), crumb = $(el, '[data-dd-crumb]');
    if (!sumEl || !detEl) return;
    const all = [...detEl.dlGrid.rows], link = el.dataset.link;
    const home = () => { sumBox.hidden = false; detBox.hidden = true; crumb.innerHTML = `<b><i class="fa-solid fa-layer-group"></i> ${esc(el.dataset.title)}</b><small>Klik 2x baris untuk melihat rinciannya</small>`; };
    sumEl.addEventListener('dl:grid-open', e => {
      e.preventDefault(); e.stopPropagation();
      const g = sumEl.dlGrid, row = g.rows.find(r => String(r[g.cfg.rowKey]) === String(e.detail.id)); if (!row) return;
      const list = all.filter(r => String(r[link]) === String(row[g.cfg.rowKey]));
      detEl.dlGrid.setRows(list);
      sumBox.hidden = true; detBox.hidden = false;
      crumb.innerHTML = `<button type="button" class="dl-btn dl-btn--link dl-btn--sm" data-dd-back><i class="fa-solid fa-arrow-left"></i> ${esc(el.dataset.title)}</button><i class="fa-solid fa-chevron-right"></i><b>${esc(row[el.dataset.label] ?? e.detail.id)}</b><small>${list.length} baris rincian</small>`;
    });
    el.addEventListener('click', e => { if (e.target.closest('[data-dd-back]')) home(); });
    home();
  }


  /* ============================ PIVOT ============================ */
  function initPivot(el) {
    const cfg = JSON.parse(el.dataset.config), data = JSON.parse(el.dataset.rows || '[]');
    const st = {r: [...cfg.rowsBy, ''].slice(0, 2), c: cfg.colBy || '', v: cfg.value, agg: cfg.agg, pct: false, heat: true};
    const F = cfg.fields, M = cfg.measures, $s = s => $(el, s);
    const opt = (o, sel, blank) => (blank ? '<option value="">—</option>' : '') + Object.entries(o).map(([k, l]) => `<option value="${esc(k)}" ${k === sel ? 'selected' : ''}>${esc(l)}</option>`).join('');
    const AGG = {sum: a => a.reduce((t, x) => t + x, 0), count: a => a.length, avg: a => a.length ? a.reduce((t, x) => t + x, 0) / a.length : 0, min: a => a.length ? Math.min(...a) : 0, max: a => a.length ? Math.max(...a) : 0};
    const uniq = k => [...new Set(data.map(r => String(r[k] ?? '')))].sort((a, b) => a.localeCompare(b, 'id', {numeric: true}));
    const fmtV = (v, tot) => { if (st.pct) return tot ? (v / tot * 100).toLocaleString('id-ID', {maximumFractionDigits: 1}) + '%' : '–'; const money = st.agg !== 'count' && cfg.formats[st.v] === 'money'; return money ? 'Rp ' + Math.round(v).toLocaleString('id-ID') : (+v).toLocaleString('id-ID', {maximumFractionDigits: st.agg === 'avg' ? 2 : 0}); };
    let lastTable = [];
    function calc(filter) { const list = data.filter(filter); return {v: AGG[st.agg](list.map(r => +r[st.v] || 0)), n: list.length}; }
    function draw() {
      $s('[data-p-row="0"]').innerHTML = opt(F, st.r[0]); $s('[data-p-row="1"]').innerHTML = opt(F, st.r[1], true); $s('[data-p-col]').innerHTML = opt(F, st.c, true); $s('[data-p-val]').innerHTML = opt(M, st.v);
      $$(el, '[data-p-agg] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b.dataset.value === st.agg));
      const cols = st.c ? uniq(st.c) : [], r0 = uniq(st.r[0]), r1k = st.r[1] && st.r[1] !== st.r[0] ? st.r[1] : '';
      const match = (lv0, lv1, cv) => r => String(r[st.r[0]] ?? '') === lv0 && (lv1 == null || String(r[r1k] ?? '') === lv1) && (cv == null || String(r[st.c] ?? '') === cv);
      const grand = calc(() => true).v;
      const lines = [];
      r0.forEach(a => {
        const subs = r1k ? [...new Set(data.filter(match(a, null, null)).map(r => String(r[r1k] ?? '')))].sort() : [null];
        subs.forEach((b, i) => lines.push({lv: r1k ? 2 : 1, label: b ?? a, parent: a, f0: a, f1: b, first: i === 0, span: subs.length}));
        if (r1k) lines.push({lv: 'sub', label: 'Subtotal ' + a, f0: a, f1: null});
      });
      const cells = [], all = [];
      lines.forEach(l => { const vals = cols.map(c => calc(match(l.f0, l.f1, c)).v); const tot = calc(match(l.f0, l.f1, null)).v; cells.push({vals, tot}); if (l.lv !== 'sub') all.push(...vals); });
      const max = Math.max(1, ...all.map(Math.abs));
      const heat = v => st.heat && cols.length ? ` style="--k:${(Math.abs(v) / max * 0.85).toFixed(2)}"` : '';
      let h = `<thead><tr><th>${esc(F[st.r[0]])}</th>${r1k ? `<th>${esc(F[r1k])}</th>` : ''}${cols.map(c => `<th class="num">${esc(c)}</th>`).join('')}<th class="num is-total">Total</th></tr></thead><tbody>`;
      lastTable = [[F[st.r[0]], ...(r1k ? [F[r1k]] : []), ...cols, 'Total']];
      lines.forEach((l, i) => {
        const {vals, tot} = cells[i], sub = l.lv === 'sub';
        h += `<tr class="${sub ? 'is-sub' : ''}">` + (sub ? `<td colspan="${r1k ? 2 : 1}">${esc(l.label)}</td>` : (r1k ? (l.first ? `<td rowspan="${l.span}" class="dl-pivot__grp">${esc(l.parent)}</td>` : '') + `<td>${esc(l.label)}</td>` : `<td>${esc(l.label)}</td>`))
          + vals.map((v, k) => `<td class="num ${sub ? '' : 'dl-pivot__cell'}"${sub ? '' : heat(v)} data-p-cell="${esc(JSON.stringify([l.f0, l.f1, cols[k]]))}">${v ? esc(fmtV(v, st.pct ? tot : 0)) : '<span class="dl-pivot__zero">–</span>'}</td>`).join('')
          + `<td class="num is-total" data-p-cell="${esc(JSON.stringify([l.f0, l.f1, null]))}">${esc(fmtV(tot, grand))}</td></tr>`;
        lastTable.push([sub ? l.label : (l.f0 ?? ''), ...(r1k ? [sub ? '' : l.f1] : []), ...vals.map(v => v), tot]);
      });
      const colTot = cols.map(c => calc(r => String(r[st.c] ?? '') === c).v);
      h += `</tbody><tfoot><tr><td colspan="${r1k ? 2 : 1}">Grand Total</td>${colTot.map(v => `<td class="num">${esc(fmtV(v, grand))}</td>`).join('')}<td class="num is-total">${esc(fmtV(grand, grand))}</td></tr></tfoot>`;
      lastTable.push(['Grand Total', ...(r1k ? [''] : []), ...colTot, grand]);
      $s('[data-p-table]').innerHTML = h;
    }
    el.addEventListener('change', e => {
      const t = e.target;
      if (t.matches('[data-p-row]')) st.r[+t.dataset.pRow] = t.value;
      else if (t.matches('[data-p-col]')) st.c = t.value;
      else if (t.matches('[data-p-val]')) st.v = t.value;
      else if (t.matches('[data-p-pct]')) st.pct = t.checked;
      else if (t.matches('[data-p-heat]')) st.heat = t.checked;
      else return;
      draw();
    });
    el.addEventListener('click', e => {
      const a = e.target.closest('[data-p-agg] .dl-segment__opt'); if (a) { st.agg = a.dataset.value; return draw(); }
      if (e.target.closest('[data-p-export]')) { const csv = lastTable.map(r => r.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(';')).join('\r\n'); const l = document.createElement('a'); l.href = URL.createObjectURL(new Blob(['\ufeff' + csv], {type: 'text/csv'})); l.download = 'pivot.csv'; l.click(); return; }
      const c = e.target.closest('[data-p-cell]'); if (!c) return;
      const [f0, f1, cv] = JSON.parse(c.dataset.pCell), r1k = st.r[1] && st.r[1] !== st.r[0] ? st.r[1] : '';
      const list = data.filter(r => String(r[st.r[0]] ?? '') === f0 && (f1 == null || !r1k || String(r[r1k] ?? '') === f1) && (cv == null || String(r[st.c] ?? '') === cv));
      if (!el.dispatchEvent(new CustomEvent('dl:pivot-cell', {bubbles: true, cancelable: true, detail: {rows: list, filter: {[st.r[0]]: f0, ...(f1 != null && r1k ? {[r1k]: f1} : {}), ...(cv != null ? {[st.c]: cv} : {})}}}))) return;
      const keys = Object.keys(list[0] || {}).slice(0, 7);
      const ov = document.createElement('div'); ov.className = 'dl-mov is-on';
      ov.innerHTML = `<div class="dl-modal" style="max-width:860px;width:calc(100% - 32px)"><div class="dl-modal__head"><h3>${esc([f0, f1, cv].filter(v => v != null).join(' · '))} — ${list.length} baris sumber</h3><button type="button" class="dl-modal__x">&times;</button></div><div class="dl-modal__body" style="max-height:60vh;overflow:auto"><table class="dl-pivot__table"><thead><tr>${keys.map(k => `<th>${esc(F[k] || M[k] || k)}</th>`).join('')}</tr></thead><tbody>${list.map(r => `<tr>${keys.map(k => `<td>${esc(typeof r[k] === 'number' ? r[k].toLocaleString('id-ID') : r[k])}</td>`).join('')}</tr>`).join('')}</tbody></table></div></div>`;
      ov.addEventListener('click', ev => { if (ev.target === ov || ev.target.closest('.dl-modal__x')) ov.remove(); });
      document.body.appendChild(ov);
    });
    el.dlPivot = {st, draw};
    draw();
  }


  /* ============================ SHEET (spreadsheet) ============================ */
  function initSheet(el) {
    const cfg = JSON.parse(el.dataset.config), cols = cfg.columns, wrap = $(el, '[data-s-wrap]'), table = $(el, '[data-s-table]');
    let data = JSON.parse(el.dataset.rows || '[]').map(r => ({...r}));
    const orig = new Map(data.map(r => [String(r[cfg.rowKey]), {...r}]));
    const undo = [], redo = [];
    let cur = {r: 0, c: firstEditable()}, anchor = null, edit = null, fill = null, drag = false, seq = 0;
    function firstEditable() { const i = cols.findIndex(c => !c.readonly); return i < 0 ? 0 : i; }
    const num = c => c.type === 'number' || c.type === 'money';
    const parse = (c, raw) => {
      const s = String(raw ?? '').trim();
      if (c.type === 'check') return /^(1|true|ya|y|x|✓)$/i.test(s);
      if (num(c)) { if (s === '') return ''; const t = s.replace(/\s|Rp/gi, ''); const n = t.includes(',') ? +t.replace(/\./g, '').replace(',', '.') : (/^-?\d{1,3}(\.\d{3})+$/.test(t) ? +t.replace(/\./g, '') : +t); return isNaN(n) ? s : n; }
      if (c.type === 'select' && c.options) { const hit = Object.entries(c.options).find(([k, l]) => k.toLowerCase() === s.toLowerCase() || String(l).toLowerCase() === s.toLowerCase()); return hit ? hit[0] : s; }
      return s;
    };
    const show = (c, v) => c.type === 'check' ? (v ? '✓' : '') : num(c) ? (v === '' || v == null ? '' : typeof v === 'number' ? (c.type === 'money' ? 'Rp ' : '') + v.toLocaleString('id-ID') : String(v)) : c.type === 'select' ? (c.options?.[v] ?? v ?? '') : c.type === 'date' && v ? fmt.date(v + 'T12:00:00+07:00') : (v ?? '');
    const bad = (c, v) => (c.required && (v === '' || v == null)) || (num(c) && v !== '' && v != null && typeof v !== 'number') || (c.type === 'select' && v && c.options && !(v in c.options));
    const dirty = (r, c) => { const o = orig.get(String(r[cfg.rowKey])); return !o || String(o[c.key] ?? '') !== String(r[c.key] ?? ''); };
    const rng = () => { const a = anchor || cur; return {r0: Math.min(a.r, cur.r), r1: Math.max(a.r, cur.r), c0: Math.min(a.c, cur.c), c1: Math.max(a.c, cur.c)}; };
    function render() {
      const R = rng();
      table.innerHTML = `<thead><tr><th class="dl-sheet__no">#</th>${cols.map(c => `<th class="${num(c) ? 'num' : ''}" ${c.width ? `style="width:${esc(c.width)}"` : ''}>${esc(c.label)}${c.required ? ' <span class="dl-field__req">*</span>' : ''}</th>`).join('')}</tr></thead><tbody>`
        + data.map((r, i) => `<tr class="${orig.has(String(r[cfg.rowKey])) ? '' : 'is-new'}"><td class="dl-sheet__no" data-s-rowsel="${i}">${i + 1}</td>${cols.map((c, j) => { const v = r[c.key], inR = i >= R.r0 && i <= R.r1 && j >= R.c0 && j <= R.c1; return `<td data-r="${i}" data-c="${j}" class="${num(c) ? 'num' : ''} ${c.readonly ? 'is-ro' : ''} ${inR ? 'is-sel' : ''} ${i === cur.r && j === cur.c ? 'is-cur' : ''} ${dirty(r, c) ? 'is-dirty' : ''} ${bad(c, v) ? 'is-bad' : ''}">${esc(show(c, v))}${i === R.r1 && j === R.c1 && !edit ? '<span class="dl-sheet__fill" data-s-fill title="Tarik untuk mengisi ke bawah"></span>' : ''}</td>`; }).join('')}</tr>`).join('') + '</tbody>';
      const changed = changes(); $(el, '[data-s-info]').textContent = `${data.length} baris · ${changed.length} berubah`;
      const sv = $(el, '[data-s-save]'); sv.disabled = !changed.length; sv.lastChild.textContent = changed.length ? `Simpan Semua (${changed.length})` : 'Simpan Semua';
      $(el, '[data-s-undo]').disabled = !undo.length; $(el, '[data-s-redo]').disabled = !redo.length;
      const td = cellEl(cur.r, cur.c); if (td) td.scrollIntoView({block: 'nearest', inline: 'nearest'});
    }
    const changes = () => data.filter(r => !orig.has(String(r[cfg.rowKey])) || cols.some(c => dirty(r, c)));
    const cellEl = (r, c) => $(table, `td[data-r="${r}"][data-c="${c}"]`);
    // Semua perubahan lewat sini supaya Undo/Redo benar.
    function apply(list, noUndo) {
      const back = list.map(({r, k, v}) => ({r, k, v: data[r][k]}));
      list.forEach(({r, k, v}) => { data[r][k] = v; });
      if (!noUndo) { undo.push(back); redo.length = 0; }
      render(); el.dispatchEvent(new CustomEvent('dl:sheet-change', {bubbles: true, detail: {changes: list}}));
      return back;
    }
    const move = (dr, dc, extend) => { if (extend && !anchor) anchor = {...cur}; if (!extend) anchor = null; cur = {r: Math.max(0, Math.min(data.length - 1, cur.r + dr)), c: Math.max(0, Math.min(cols.length - 1, cur.c + dc))}; render(); };
    function startEdit(initial) {
      const c = cols[cur.c]; if (c.readonly) return;
      if (c.type === 'check') { apply([{r: cur.r, k: c.key, v: !data[cur.r][c.key]}]); return; }
      const td = cellEl(cur.r, cur.c); if (!td) return;
      edit = {r: cur.r, c: cur.c};
      const v = data[cur.r][c.key];
      if (c.type === 'select') td.innerHTML = `<select class="dl-sheet__in">${['', ...Object.keys(c.options || {})].map(k => `<option value="${esc(k)}" ${String(k) === String(v ?? '') ? 'selected' : ''}>${esc(k ? c.options[k] : '—')}</option>`).join('')}</select>`;
      else td.innerHTML = `<input class="dl-sheet__in" type="${c.type === 'date' ? 'date' : 'text'}" value="${esc(initial ?? (num(c) && typeof v === 'number' ? String(v).replace('.', ',') : v ?? ''))}">`;
      const inp = $(td, '.dl-sheet__in'); inp.focus(); if (initial == null && inp.select) inp.select();
      if (c.type === 'select') inp.addEventListener('change', () => commit());
    }
    function commit(cancel) {
      if (!edit) return; const {r, c} = edit, inp = $(cellEl(r, c) || document, '.dl-sheet__in'); edit = null;
      if (!cancel && inp) { const col = cols[c], v = parse(col, inp.value); if (String(v) !== String(data[r][col.key] ?? '')) { apply([{r, k: col.key, v}]); wrap.focus(); return; } }
      render(); wrap.focus();
    }
    wrap.addEventListener('keydown', e => {
      if (edit) {
        if (e.key === 'Escape') { e.preventDefault(); commit(true); }
        else if (e.key === 'Enter') { e.preventDefault(); commit(); move(1, 0); }
        else if (e.key === 'Tab') { e.preventDefault(); commit(); move(0, e.shiftKey ? -1 : 1); }
        return;
      }
      const ctrl = e.ctrlKey || e.metaKey;
      if (ctrl && e.key.toLowerCase() === 'z') { e.preventDefault(); const u = undo.pop(); if (u) redo.push(apply(u, true)); return render(); }
      if (ctrl && e.key.toLowerCase() === 'y') { e.preventDefault(); const u = redo.pop(); if (u) undo.push(apply(u, true)); return render(); }
      if (ctrl) return;  // Ctrl+C / Ctrl+V lewat event copy/paste
      const k = e.key;
      if (k === 'ArrowDown') { e.preventDefault(); move(1, 0, e.shiftKey); }
      else if (k === 'ArrowUp') { e.preventDefault(); move(-1, 0, e.shiftKey); }
      else if (k === 'ArrowRight') { e.preventDefault(); move(0, 1, e.shiftKey); }
      else if (k === 'ArrowLeft') { e.preventDefault(); move(0, -1, e.shiftKey); }
      else if (k === 'Tab') { e.preventDefault(); move(0, e.shiftKey ? -1 : 1); }
      else if (k === 'Enter' || k === 'F2') { e.preventDefault(); startEdit(); }
      else if (k === 'Delete' || k === 'Backspace') { e.preventDefault(); const R = rng(), list = []; for (let r = R.r0; r <= R.r1; r++) for (let c = R.c0; c <= R.c1; c++) if (!cols[c].readonly) list.push({r, k: cols[c].key, v: cols[c].type === 'check' ? false : ''}); if (list.length) apply(list); }
      else if (k === ' ' && cols[cur.c].type === 'check') { e.preventDefault(); startEdit(); }
      else if (k.length === 1 && !e.altKey) { e.preventDefault(); anchor = null; startEdit(k); }
    });
    // Salin rentang sebagai TSV (bisa ditempel ke Excel); tempel TSV dari Excel mulai sel aktif (baris baru dibuat bila perlu).
    wrap.addEventListener('copy', e => { if (edit) return; const R = rng(), lines = []; for (let r = R.r0; r <= R.r1; r++) { const row = []; for (let c = R.c0; c <= R.c1; c++) { const col = cols[c], v = data[r][col.key]; row.push(num(col) && typeof v === 'number' ? String(v).replace('.', ',') : col.type === 'select' ? (col.options?.[v] ?? v ?? '') : v ?? ''); } lines.push(row.join('\t')); } e.clipboardData.setData('text/plain', lines.join('\r\n')); e.preventDefault(); toast(`${(R.r1 - R.r0 + 1) * (R.c1 - R.c0 + 1)} sel disalin.`); });
    wrap.addEventListener('paste', e => {
      if (edit) return; e.preventDefault();
      const grid = (e.clipboardData.getData('text/plain') || '').replace(/\r/g, '').replace(/\n$/, '').split('\n').map(l => l.split('\t'));
      const list = [];
      grid.forEach((line, i) => { const r = cur.r + i; while (r >= data.length) data.push(blank()); line.forEach((v, j) => { const c = cur.c + j; if (c < cols.length && !cols[c].readonly) list.push({r, k: cols[c].key, v: parse(cols[c], v)}); }); });
      anchor = {...cur}; cur = {r: Math.min(data.length - 1, cur.r + grid.length - 1), c: Math.min(cols.length - 1, cur.c + (grid[0]?.length || 1) - 1)};
      apply(list); toast(`${list.length} sel ditempel.`, 'success');
    });
    const blank = () => { const r = {[cfg.rowKey]: 'new' + (++seq)}; cols.forEach(c => { r[c.key] = c.type === 'check' ? false : ''; }); return r; };
    // Mouse: klik = sel aktif, drag = rentang, pegangan isi = AutoFill ke bawah.
    table.addEventListener('mousedown', e => {
      const fh = e.target.closest('[data-s-fill]'); if (fh) { e.preventDefault(); fill = {...rng(), to: rng().r1}; return; }
      const rs = e.target.closest('[data-s-rowsel]'); if (rs) { anchor = {r: +rs.dataset.sRowsel, c: 0}; cur = {r: +rs.dataset.sRowsel, c: cols.length - 1}; render(); wrap.focus(); return; }
      const td = e.target.closest('td[data-r]'); if (!td || edit) return;
      if (e.shiftKey) { if (!anchor) anchor = {...cur}; } else anchor = null;
      cur = {r: +td.dataset.r, c: +td.dataset.c}; drag = true; render(); wrap.focus();
    });
    table.addEventListener('mouseover', e => {
      const td = e.target.closest('td[data-r]'); if (!td) return;
      if (fill) { fill.to = Math.max(fill.r1, +td.dataset.r); $$(table, '.is-filltgt').forEach(x => x.classList.remove('is-filltgt')); for (let r = fill.r1 + 1; r <= fill.to; r++) for (let c = fill.c0; c <= fill.c1; c++) cellEl(r, c)?.classList.add('is-filltgt'); return; }
      if (drag) { if (!anchor) anchor = {...cur}; cur = {r: +td.dataset.r, c: +td.dataset.c}; render(); }
    });
    document.addEventListener('mouseup', () => {
      drag = false; if (!fill) return;
      const F = fill; fill = null; if (F.to <= F.r1) return render();
      const list = [];
      for (let c = F.c0; c <= F.c1; c++) {
        const col = cols[c]; if (col.readonly) continue;
        const src = []; for (let r = F.r0; r <= F.r1; r++) src.push(data[r][col.key]);
        const step = num(col) && src.length >= 2 && src.every(v => typeof v === 'number') && src.every((v, i) => !i || v - src[i - 1] === src[1] - src[0]) ? src[1] - src[0] : null;
        for (let r = F.r1 + 1, i = 0; r <= F.to; r++, i++) list.push({r, k: col.key, v: step !== null ? src[src.length - 1] + step * (i + 1) : src[i % src.length]});
      }
      anchor = {r: F.r0, c: F.c0}; cur = {r: F.to, c: F.c1}; if (list.length) { apply(list); toast(`${list.length} sel diisi otomatis.`, 'success'); } else render();
    });
    table.addEventListener('dblclick', e => { const td = e.target.closest('td[data-r]'); if (td) { cur = {r: +td.dataset.r, c: +td.dataset.c}; anchor = null; startEdit(); } });
    wrap.addEventListener('focusout', e => { if (edit && !wrap.contains(e.relatedTarget)) commit(); });
    el.addEventListener('click', e => {
      if (e.target.closest('[data-s-add]')) { data.push(blank()); cur = {r: data.length - 1, c: firstEditable()}; anchor = null; render(); wrap.focus(); return; }
      if (e.target.closest('[data-s-del]')) { const R = rng(), n = R.r1 - R.r0 + 1; confirmBox({message: `Hapus ${n} baris terpilih?`, tone: 'danger'}).then(ok => { if (!ok) return; data.splice(R.r0, n); cur = {r: Math.max(0, Math.min(R.r0, data.length - 1)), c: cur.c}; anchor = null; undo.length = 0; render(); }); return; }
      if (e.target.closest('[data-s-undo]')) { const u = undo.pop(); if (u) redo.push(apply(u, true)); return; }
      if (e.target.closest('[data-s-redo]')) { const u = redo.pop(); if (u) undo.push(apply(u, true)); return; }
      if (e.target.closest('[data-s-save]')) {
        const nBad = data.reduce((t, r) => t + cols.filter(c => bad(c, r[c.key])).length, 0);
        if (nBad) { toast(`${nBad} sel belum benar (wajib / angka / pilihan).`, 'danger'); const b = $(table, 'td.is-bad'); if (b) { cur = {r: +b.dataset.r, c: +b.dataset.c}; anchor = null; render(); } return; }
        const changed = changes();
        el.dispatchEvent(new CustomEvent('dl:sheet-save', {bubbles: true, detail: {rows: data.map(r => ({...r})), changed}}));
        data.forEach(r => orig.set(String(r[cfg.rowKey]), {...r})); undo.length = 0; redo.length = 0;
        toast(`${changed.length} baris disimpan.`, 'success'); render();
      }
    });
    el.dlSheet = {get data() { return data; }, render, apply, get cur() { return cur; }, set cur(v) { cur = v; anchor = null; render(); }};
    render();
  }


  /* ============================ APPROVAL BAR ============================ */
  function initApprovalBar(el) {
    const fieldErr = (inp, msg) => { const f = inp.closest('.dl-field'); f.classList.toggle('is-invalid', !!msg); const x = $(f, '[data-dl-field-error]'); if (x) x.textContent = msg; };
    const form = el.closest('form'), fs = form && $(form, '[data-f-fs]'), note = $(el, '[name=approval_note]');
    // Bilah dipindah ke luar fieldset form supaya tetap bisa diklik saat isian dikunci.
    if (fs && fs.contains(el)) fs.after(el);
    const hist = JSON.parse(el.dataset.history || '[]'), me = el.dataset.me;
    const STEPS = [['DRAFT', 'Draft'], ['SUBMITTED', 'Diajukan'], ['APPROVED', 'Disetujui']];
    const WORD = {submit: 'Ajukan', approve: 'Setujui', reject: 'Tolak', revise: 'Revisi'}, NEXT = {submit: 'SUBMITTED', approve: 'APPROVED', reject: 'REJECTED', revise: 'DRAFT'};
    const ICON = {SUBMITTED: 'fa-paper-plane', APPROVED: 'fa-check', REJECTED: 'fa-ban', DRAFT: 'fa-pen'};
    function draw() {
      const s = el.dataset.status, at = s === 'REJECTED' ? 1 : STEPS.findIndex(x => x[0] === s);
      $(el, '[data-ab-pipe]').innerHTML = STEPS.map(([k, l], i) => `<span class="dl-master__step ${i < at ? 'is-done' : i === at ? (s === 'REJECTED' ? 'is-rejected' : 'is-active') : ''}">${i === at && s === 'REJECTED' ? 'Ditolak' : esc(l)}</span>`).join('');
      const last = hist[hist.length - 1];
      $(el, '[data-ab-banner]').innerHTML = s === 'APPROVED' ? `<i class="fa-solid fa-lock"></i> Disetujui${last ? ` oleh <b>${esc(last.by)}</b> · ${esc(last.at)}` : ''} — dokumen terkunci.` : s === 'SUBMITTED' ? '<i class="fa-solid fa-hourglass-half"></i> Menunggu persetujuan — isian dikunci.' : s === 'REJECTED' ? `<i class="fa-solid fa-circle-xmark"></i> Ditolak${last?.note ? `: “${esc(last.note)}”` : ''} — klik Revisi untuk memperbaiki.` : '';
      el.dataset.tone = s;
      const show = {submit: s === 'DRAFT', approve: s === 'SUBMITTED', reject: s === 'SUBMITTED', revise: s === 'REJECTED'};
      $$(el, '[data-ab-do]').forEach(b => { b.hidden = !show[b.dataset.abDo]; });
      $(el, '[data-ab-act]').hidden = s === 'APPROVED';
      note.closest('.dl-field').hidden = s !== 'SUBMITTED';
      $(el, '[data-ab-hist]').innerHTML = hist.slice().reverse().map(h => `<li><i class="fa-solid ${ICON[h.status] || 'fa-circle'}"></i><div><b>${esc(h.label)}</b><small>Oleh: ${esc(h.by)} · ${esc(h.at)}</small>${h.note ? `<q>${esc(h.note)}</q>` : ''}</div></li>`).join('');
      if (fs) { fs.disabled = s !== 'DRAFT'; form.classList.toggle('is-view', s !== 'DRAFT'); }
    }
    el.addEventListener('click', e => {
      const b = e.target.closest('[data-ab-do]'); if (!b) return;
      e.preventDefault();
      const a = b.dataset.abDo, n = note.value.trim();
      if (a === 'reject' && !n) { fieldErr(note, 'Alasan penolakan wajib diisi.'); note.focus(); return; }
      if (a === 'submit' && form) { const req = $$(form, '.dl-field').filter(f => !el.contains(f) && $(f, '.dl-field__req') && !hasValue(f)); if (req.length) { req.forEach(f => { f.classList.add('is-invalid'); const x = $(f, '[data-dl-field-error]'); if (x) x.textContent = 'Wajib diisi'; }); toast(`${req.length} isian wajib belum diisi sebelum diajukan.`, 'danger'); return; } }
      confirmBox({message: `${WORD[a]} dokumen ini?`, tone: a === 'reject' ? 'danger' : 'warn'}).then(ok => {
        if (!ok) return;
        const status = NEXT[a];
        hist.push({status, label: {submit: 'DIAJUKAN', approve: 'DISETUJUI', reject: 'DITOLAK', revise: 'DIREVISI'}[a], by: a === 'approve' || a === 'reject' ? el.dataset.approver : me, at: `${fmt.date()} ${fmt.time()}`, note: n});
        el.dataset.status = status; note.value = ''; fieldErr(note, ''); draw();
        el.dispatchEvent(new CustomEvent('dl:approval-status', {bubbles: true, detail: {status, action: a, note: n}}));
        toast(`Dokumen ${status === 'DRAFT' ? 'kembali ke Draft' : {SUBMITTED: 'diajukan', APPROVED: 'disetujui', REJECTED: 'ditolak'}[status]}.`, a === 'reject' ? 'danger' : 'success');
      });
    });
    draw();
  }

  /* ============================ FILTER FORM ============================ */
  function initFilterForm(form) {
    const key = 'dl-filter-' + form.dataset.id;
    const load = () => { try { return JSON.parse(localStorage.getItem(key) || '{}'); } catch (e) { return {}; } };
    const presets = () => { const P = load(); $(form, '[data-fl-presets]').innerHTML = '<option value="">Filter tersimpan…</option>' + Object.keys(P).map(n => `<option>${esc(n)}</option>`).join(''); };
    const values = () => { const v = {}, aux = new Set($$(form, '[data-dl-prefix-value]').map(i => i.name)); new FormData(form).forEach((x, k) => { if (!aux.has(k) && String(x).trim() !== '') v[k] = x; }); return v; };
    const labelOf = k => { const f = form.querySelector(`[name="${CSS.escape(k)}"]`)?.closest('.dl-field'); return f ? ($(f, '.dl-field__label')?.textContent || k) : k; };
    function chips() {
      const v = values();
      const seen = new Set();
      $(form, '[data-fl-chips]').innerHTML = Object.keys(v).map(k => { const f = form.querySelector(`[name="${CSS.escape(k)}"]`)?.closest('.dl-field'); if (f && seen.has(f)) return ''; if (f) seen.add(f); return `<span class="dl-tags__chip">${esc(labelOf(k))}: ${esc(f ? fieldText(f) || v[k] : v[k])}<button type="button" data-fl-chip="${esc(k)}" aria-label="Lepas">&times;</button></span>`; }).join('') || '<span class="dl-filter__none">Belum ada kriteria</span>';
    }
    const apply = () => { chips(); form.dispatchEvent(new CustomEvent('dl:filter', {bubbles: true, detail: {values: values()}})); };
    form.addEventListener('submit', e => { e.preventDefault(); apply(); toast(`Filter diterapkan (${Object.keys(values()).length} kriteria).`, 'success'); });
    form.addEventListener('click', e => {
      const x = e.target;
      if (x.closest('[data-fl-toggle]')) { form.classList.toggle('is-closed'); return; }
      if (x.closest('[data-fl-reset]')) { fillForm(form, {}, true); apply(); return; }
      const ch = x.closest('[data-fl-chip]'); if (ch) { setField(form, ch.dataset.flChip, ''); apply(); return; }
      if (x.closest('[data-fl-save]')) { $(form, '[data-fl-savebox]').hidden = false; $(form, '[data-fl-name]').focus(); return; }
      if (x.closest('[data-fl-saveok]')) { const n = $(form, '[data-fl-name]').value.trim(); if (!n) return; const P = load(); P[n] = values(); try { localStorage.setItem(key, JSON.stringify(P)); } catch (er) {} $(form, '[data-fl-savebox]').hidden = true; $(form, '[data-fl-name]').value = ''; presets(); toast(`Filter "${n}" disimpan.`, 'success'); }
    });
    form.addEventListener('change', e => { if (e.target.matches('[data-fl-presets]') && e.target.value) { fillForm(form, load()[e.target.value] || {}, true); apply(); e.target.value = ''; } });
    form.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.matches('[data-fl-name]')) { e.preventDefault(); $(form, '[data-fl-saveok]').click(); } });
    presets(); chips();
  }

  window.Devlib = {toast, setDevMode, fmt, confirm: confirmBox, open: openLayer, close: id => closeLayer(layer(id)), fill: fillForm, setField, words};
  document.addEventListener('DOMContentLoaded', () => {
    initDevMode();
    document.querySelectorAll('[data-dl-themepick]').forEach(initThemePicker);
    document.querySelectorAll('.dl-tokens').forEach(initTokens);
    document.querySelectorAll('[data-dl-fmt-demo]').forEach(initFmtDemo);
    document.querySelectorAll('[data-dl-langs]').forEach(initLangs);
    document.querySelectorAll('[data-dl-clock]').forEach(initClock);
    document.querySelectorAll('[data-dl-quote]').forEach(initQuote);
    document.querySelectorAll('[data-dl-frame]').forEach(initFrame);
    document.querySelectorAll('[data-dl-greeting]').forEach(initGreeting);
    document.querySelectorAll('[data-dl-weather]').forEach(initWeather);
    document.querySelectorAll('[data-dl-announce]').forEach(initAnnounce);
    document.querySelectorAll('[data-dl-uat]').forEach(initUat);
    document.querySelectorAll('[data-dl-split]').forEach(initSplit);
    document.querySelectorAll('[data-dl-device]').forEach(initDevice);
    document.querySelectorAll('[data-dl-form]').forEach(initForm);
    document.querySelectorAll('.dl-field').forEach(initField);
    document.querySelectorAll('[data-dl-lookup]').forEach(initLookup);
    document.querySelectorAll('[data-dl-tabs]').forEach(initTabs);
    document.querySelectorAll('[data-dl-stat]').forEach(initStat);
    document.querySelectorAll('[data-dl-segment]').forEach(initSegment);
    document.querySelectorAll('[data-dl-tags]').forEach(initTags);
    document.querySelectorAll('[data-dl-kv]').forEach(initKv);
    document.querySelectorAll('[data-dl-rich]').forEach(initRich);
    document.querySelectorAll('[data-dl-upload]').forEach(initUpload);
    document.querySelectorAll('[data-dl-counter]').forEach(initCounter);
    document.querySelectorAll('[data-dl-back]').forEach(initBack);
    document.querySelectorAll('[data-dl-notif]').forEach(initNotif);
    document.querySelectorAll('[data-dl-devtoggle]').forEach(initDevToggle);
    document.querySelectorAll('[data-dl-loginbox]').forEach(initLoginBox);
    document.querySelectorAll('[data-dl-code]').forEach(initCode);
    document.querySelectorAll('[data-dl-grid]').forEach(initGrid);
    document.querySelectorAll('[data-dl-apbar]').forEach(initApprovalBar);
    document.querySelectorAll('[data-dl-filter]').forEach(initFilterForm);
    document.querySelectorAll('[data-dl-sheet]').forEach(initSheet);
    document.querySelectorAll('[data-dl-pivot]').forEach(initPivot);
    document.querySelectorAll('[data-dl-drill]').forEach(initDrill);
    document.querySelectorAll('[data-dl-roster]').forEach(initRoster);
    document.querySelectorAll('[data-dl-qrcode]').forEach(initQrcode);
    document.querySelectorAll('[data-dl-barcode]').forEach(initBarcode);
    document.querySelectorAll('[data-dl-chart]').forEach(initChart);
    document.querySelectorAll('[data-dl-impo]').forEach(initImposition);
    document.querySelectorAll('[data-dl-dieline]').forEach(initDieline);
    document.querySelectorAll('[data-dl-dieline-x]').forEach(initDielineX);
    document.querySelectorAll('[data-dl-org]').forEach(initOrgChart);
    document.querySelectorAll('[data-dl-import]').forEach(initImport);
    document.querySelectorAll('[data-dl-print]').forEach(initPrint);
    document.querySelectorAll('[data-dl-calendar]').forEach(initCalendar);
    document.querySelectorAll('[data-dl-gantt]').forEach(initGantt);
    document.querySelectorAll('[data-dl-words]').forEach(initNumberWords);
    document.querySelectorAll('[data-dl-kanban]').forEach(initKanban);
    document.querySelectorAll('[data-dl-wizard]').forEach(initWizard);
    document.querySelectorAll('[data-dl-approval]').forEach(initApproval);
    document.querySelectorAll('[data-dl-lines]').forEach(initLines);
    document.querySelectorAll('[data-dl-flowcfg]').forEach(initFlowConfig);
    document.querySelectorAll('[data-dl-master]').forEach(initMaster);
    document.querySelectorAll('[data-dl-accgroup]').forEach(initAccGroup);
    document.querySelectorAll('[data-dl-slider]').forEach(initSlider);
    document.querySelectorAll('[data-dl-rating]').forEach(initRating);
    document.querySelectorAll('[data-dl-sign]').forEach(initSignature);
    document.querySelectorAll('[data-dl-cc]').forEach(initCreditCard);
    document.querySelectorAll('[data-dl-phonebox]').forEach(initPhone);
    document.addEventListener('click', () => {
      document.querySelectorAll('[data-dl-dd-panel].is-open').forEach(p => p.classList.remove('is-open'));
      document.querySelectorAll('[data-dl-split].is-open').forEach(p => p.classList.remove('is-open'));
    });
    document.querySelectorAll('.dl-shell').forEach(initShell);
  });
})();

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
    form.addEventListener('submit', e => {
      if (!form.getAttribute('action')) { e.preventDefault(); toast('Aksi: ' + (form.querySelector('[data-dl-split-value]')?.value || 'Submit') + ' (pratinjau, tidak dikirim)'); }
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
    const cfg = JSON.parse(root.dataset.config), rows = JSON.parse(root.dataset.rows || '[]'), cols = cfg.columns;
    const saveKey = 'dl-grid-' + cfg.id;
    let saved = {}; try { saved = JSON.parse(localStorage.getItem(saveKey) || '{}'); } catch (e) {}
    const st = Object.assign({search: '', sort: null, dir: 1, stat: null, tab: 0, filters: {}, hidden: cols.filter(c => !c.visible).map(c => c.key),
      group: '', page: 1, perPage: cfg.perPage, density: 'normal', lines: 'fixed', linesN: 1, collapsed: [], open: []}, saved, {page: 1, search: ''});
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
      q('[data-g-head]').innerHTML = '<tr><th class="dl-grid__chk"><input type="checkbox" data-g-all aria-label="Pilih semua di halaman"></th>' + (cfg.expand.length ? '<th class="dl-grid__exp"></th>' : '')
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
      const id = String(r[cfg.rowKey]), open = st.open.includes(id), span = shown().length + 1 + (cfg.expand.length ? 1 : 0) + (cfg.actions !== 'none' ? 1 : 0);
      let h = `<tr class="dl-grid__row ${sel.has(id) ? 'is-selected' : ''} ${cfg.activeKey && r[cfg.activeKey] === false ? 'is-inactive' : ''}" data-id="${esc(id)}" data-idx="${idx}" title="Klik 2x untuk membuka">`
        + `<td class="dl-grid__chk"><input type="checkbox" ${sel.has(id) ? 'checked' : ''} aria-label="Pilih baris"></td>`
        + (cfg.expand.length ? `<td class="dl-grid__exp"><button type="button" class="dl-grid__chev ${open ? 'is-open' : ''}" data-g-exp aria-label="Buka detail"><i class="fa-solid fa-chevron-right"></i></button></td>` : '')
        + shown().map(c => cell(c, r)).join('') + actionsCell(r) + '</tr>';
      if (open) h += `<tr class="dl-grid__detail"><td colspan="${span}"><div class="dl-grid__detailbox">${cfg.expand.map(k => { const c = cols.find(x => x.key === k) || {key: k, label: k}; return `<div><small>${esc(c.label)}</small><b>${esc(fmtCell(c, val(r, k)) || '—')}</b></div>`; }).join('')}</div></td></tr>`;
      return h;
    }

    // ---- badan tabel (+ grup) & kaki (subtotal) ----
    let current = [];
    function drawBody() {
      const list = sorted(filtered()), total = list.length, pages = Math.max(1, Math.ceil(total / st.perPage));
      st.page = Math.min(st.page, pages);
      const page = list.slice((st.page - 1) * st.perPage, st.page * st.perPage);
      current = page;
      const span = shown().length + 1 + (cfg.expand.length ? 1 : 0) + (cfg.actions !== 'none' ? 1 : 0), sums = shown().filter(c => c.sum);
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
      q('[data-g-foot]').innerHTML = sums.length && page.length ? '<tr><td class="dl-grid__chk"></td>' + (cfg.expand.length ? '<td></td>' : '') + shown().map((c, i) => c.sum ? `<td class="num"><b>${esc(fmtCell(c, list.reduce((t, r) => t + (+val(r, c.key) || 0), 0)))}</b></td>` : `<td>${i === 0 ? '<b>Total (semua hasil)</b>' : ''}</td>`).join('') + (cfg.actions !== 'none' ? '<td></td>' : '') + '</tr>' : '';
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
    root.addEventListener('change', e => {
      const f = e.target.closest('[data-g-filter]'); if (f) { st.filters[f.dataset.gFilter] = f.value; st.page = 1; return redraw(); }
      const c = e.target.closest('[data-g-col]'); if (c) { const k = c.dataset.gCol; st.hidden = c.checked ? st.hidden.filter(v => v !== k) : [...st.hidden, k]; if (!shown().length) { st.hidden = st.hidden.filter(v => v !== k); } return redraw(); }
      const g = e.target.closest('[data-g-group]'); if (g) { st.group = g.value; st.collapsed = []; return redraw(); }
      if (e.target.matches('[data-g-lines-n]')) { st.linesN = Math.max(1, Math.min(5, +e.target.value || 1)); return redraw(); }
      if (e.target.matches('[data-g-goto]')) { st.page = Math.max(1, +e.target.value || 1); drawBody(); }
    });
    root.addEventListener('dblclick', e => { const tr = e.target.closest('.dl-grid__row'); if (!tr || e.target.closest('button,input')) return; const r = rows.find(v => String(v[cfg.rowKey]) === tr.dataset.id); if (emit('dl:grid-open', {id: tr.dataset.id, action: 'open'})) toast('Buka: ' + (r.name || tr.dataset.id) + ' (klik 2x)'); });
    document.addEventListener('click', e => { if (!e.target.closest('[data-g-pop]')) qq('.dl-pop2__panel').forEach(p => p.classList.remove('is-open')); });

    // Export = kolom yang sedang tampil, semua baris hasil saringan (bukan hanya halaman ini).
    function exportCsv() {
      const vis = shown(), list = sorted(filtered()), cellTxt = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
      const csv = [vis.map(c => cellTxt(c.label)).join(';'), ...list.map(r => vis.map(c => cellTxt(c.type === 'identity' && c.code ? `${val(r, c.key)} (${val(r, c.code)})` : val(r, c.key))).join(';'))].join('\r\n');
      const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + csv], {type: 'text/csv'})); a.download = cfg.id + '.csv'; a.click();
      toast(`Export ${fmt.number(list.length)} baris × ${vis.length} kolom (kolom yang tampil).`, 'success');
    }

    root.dlGrid = {rows, cfg, redraw};
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
    const show = (c, v) => c.type === 'money' || (c.fn && c.type !== 'number') ? money(v) : c.type === 'number' ? (v === '' ? '' : (+v).toLocaleString('id-ID')) : v;

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
  const hasValue = f => $$(f, '[name]').some(i => (i.type === 'checkbox' || i.type === 'radio') ? i.checked : i.value.trim() !== '');

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
    document.querySelectorAll('[data-dl-words]').forEach(initNumberWords);
    document.querySelectorAll('[data-dl-kanban]').forEach(initKanban);
    document.querySelectorAll('[data-dl-wizard]').forEach(initWizard);
    document.querySelectorAll('[data-dl-approval]').forEach(initApproval);
    document.querySelectorAll('[data-dl-lines]').forEach(initLines);
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

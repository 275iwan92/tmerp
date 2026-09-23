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
      $$(sel, '.dl-dd__item').forEach(it => it.addEventListener('click', () => { $(sel, '[data-dl-value]').value = it.dataset.value; $(sel, '[data-dl-dd-text]').textContent = it.textContent; p.close(); }));
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
      const setVal = d => { hidden.value = d ? iso(d) : ''; text.value = d ? `${d.getDate()} ${MON[d.getMonth()]} ${d.getFullYear()}` : ''; };
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
      if (hidden.value) setVal(new Date(hidden.value));
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
    const a = e.target.closest('[data-dl-back], a[href]:not([href^="#"]):not([target=_blank]):not([href^="javascript"])');
    const live = [...dirtyForms].filter(f => document.contains(f));
    if (!a || !live.length || a.closest('form[data-dl-guard]') === live[0] && !a.hasAttribute('data-dl-back')) return;
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
      const act = x.closest('[data-g-act]'); if (act) { const r = rows.find(v => String(v[cfg.rowKey]) === act.dataset.id); if (act.dataset.gAct === 'toggle' && cfg.activeKey) { r[cfg.activeKey] = !r[cfg.activeKey]; toast((r[cfg.activeKey] ? 'Diaktifkan: ' : 'Dinonaktifkan: ') + (r.name || act.dataset.id), 'success'); redraw(); } else { toast(`${act.title.split(' (')[0]}: ${r.name || r[cfg.rowKey]} (pratinjau)`); root.dispatchEvent(new CustomEvent('dl:grid-open', {bubbles: true, detail: {id: act.dataset.id, action: act.dataset.gAct}})); } return; }
      if (x.closest('[data-g-new]')) { toast('+ New Record (pratinjau)'); root.dispatchEvent(new CustomEvent('dl:grid-new', {bubbles: true})); return; }
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
    root.addEventListener('dblclick', e => { const tr = e.target.closest('.dl-grid__row'); if (!tr || e.target.closest('button,input')) return; const r = rows.find(v => String(v[cfg.rowKey]) === tr.dataset.id); toast('Buka: ' + (r.name || tr.dataset.id) + ' (klik 2x)'); root.dispatchEvent(new CustomEvent('dl:grid-open', {bubbles: true, detail: {id: tr.dataset.id, action: 'open'}})); });
    document.addEventListener('click', e => { if (!e.target.closest('[data-g-pop]')) qq('.dl-pop2__panel').forEach(p => p.classList.remove('is-open')); });

    // Export = kolom yang sedang tampil, semua baris hasil saringan (bukan hanya halaman ini).
    function exportCsv() {
      const vis = shown(), list = sorted(filtered()), cellTxt = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
      const csv = [vis.map(c => cellTxt(c.label)).join(';'), ...list.map(r => vis.map(c => cellTxt(c.type === 'identity' && c.code ? `${val(r, c.key)} (${val(r, c.code)})` : val(r, c.key))).join(';'))].join('\r\n');
      const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + csv], {type: 'text/csv'})); a.download = cfg.id + '.csv'; a.click();
      toast(`Export ${fmt.number(list.length)} baris × ${vis.length} kolom (kolom yang tampil).`, 'success');
    }

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

  window.Devlib = {toast, setDevMode, fmt, confirm: confirmBox, open: openLayer, close: id => closeLayer(layer(id))};
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

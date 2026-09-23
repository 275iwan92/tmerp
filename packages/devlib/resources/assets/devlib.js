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

  /* ========================= PRIMITIVE ========================= */
  let currentLang = new URLSearchParams(location.search).get('lang') || 'id';

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

    const sl = $(f, '[data-dl-slider]');
    if (sl) { const r = $(sl, 'input'), o = $(sl, '[data-dl-slider-value]'); const u = () => { o.textContent = sl.dataset.format.replace('{v}', idNum(r.value)); }; r.addEventListener('input', u); u(); }

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

    const rt = $(f, '[data-dl-rating]');
    if (rt) {
      const stars = $$(rt, '.dl-rating__star'), hidden = $(rt, '[data-dl-value]'), max = rt.dataset.max;
      const paint = (v, cls) => stars.forEach(s => s.classList.toggle(cls, +s.dataset.value <= v));
      stars.forEach(s => {
        s.addEventListener('click', () => { hidden.value = s.dataset.value; paint(+hidden.value, 'is-active'); $(rt, '[data-dl-rating-text]').textContent = `${hidden.value} / ${max} Star${hidden.value > 1 ? 's' : ''}`; });
        s.addEventListener('mouseover', () => paint(+s.dataset.value, 'is-hover'));
        s.addEventListener('mouseout', () => paint(0, 'is-hover'));
      });
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
      const ph = $(pre, '[data-dl-phone]');
      if (ph) ph.addEventListener('input', () => {
        const r = ph.value.replace(/\D/g, '').slice(0, 12);
        ph.value = [r.slice(0, 3), r.slice(3, 7), r.slice(7, 12)].filter(Boolean).join('-');
      });
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

    const up = $(f, '[data-dl-upload]');
    if (up) {
      const file = $(up, 'input[type=file]'), name = $(up, '[data-dl-upload-name]');
      const show = () => { const fl = file.files[0]; up.classList.toggle('has-file', !!fl); name.textContent = fl ? fl.name : ''; };
      up.addEventListener('click', e => { if (!e.target.closest('[data-dl-upload-remove]')) file.click(); });
      file.addEventListener('click', e => e.stopPropagation());
      file.addEventListener('change', show);
      $(up, '[data-dl-upload-remove]').addEventListener('click', e => { e.stopPropagation(); file.value = ''; show(); });
      ['dragover', 'dragenter'].forEach(ev => up.addEventListener(ev, e => { e.preventDefault(); up.classList.add('is-drag'); }));
      ['dragleave', 'drop'].forEach(ev => up.addEventListener(ev, e => { e.preventDefault(); up.classList.remove('is-drag'); }));
      up.addEventListener('drop', e => { if (e.dataTransfer.files.length) { file.files = e.dataTransfer.files; show(); } });
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
    const allowed = !!document.querySelector('[data-dl-developer="1"]') || window.self !== window.top;
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

  window.Devlib = {toast, setDevMode, fmt};
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
    document.querySelectorAll('[data-dl-loginbox]').forEach(initLoginBox);
    document.querySelectorAll('[data-dl-code]').forEach(initCode);
    document.addEventListener('click', () => {
      document.querySelectorAll('[data-dl-dd-panel].is-open').forEach(p => p.classList.remove('is-open'));
      document.querySelectorAll('[data-dl-split].is-open').forEach(p => p.classList.remove('is-open'));
    });
    document.querySelectorAll('.dl-shell').forEach(initShell);
  });
})();

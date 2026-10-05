/* DevLib · Field */
(function (Devlib) {
  'use strict';
  const {$, $$, CASES, CASE_LABEL, applyCase, bindPanel, calcPanel, T, Tf, words, fmt, NUM, dow2, parseNum, DlDate, DlTime, DlDateTime} = Devlib.h;

  // Toggle "00" isian angka (user 2026-10-05, pengganti badge aa yang hanya cocok untuk huruf): klik berpindah
  // 0 desimal -> 2 desimal -> 4 desimal -> kalkulator mini, ikon badge berganti tiap keadaan. Dipakai Decimal dan Money.
  // apply(d) = isian memformat ulang dirinya (d null = keadaan kalkulator: desimal secukupnya, maks 4).
  const NUM_STEPS = [0, 2, 4, null];
  const numRaw = s => { const v = parseNum(s); return isNaN(v) ? s : String(v).replace('.', NUM.dec); };  // nilai mentah untuk diedit: 1234,5
  function numToggle(root, input, start, apply) {
    const btn = $(root, '[data-dl-dec-toggle]'); if (!btn) return;
    const label = d => d === null ? '<i class="fa-solid fa-calculator"></i>' : d === 0 ? '0' : NUM.dec + '0'.repeat(d);
    let i = NUM_STEPS.indexOf(start), panel = null; if (i < 0) i = 1;
    const close = () => { if (panel) { panel.remove(); panel = null; } };
    const open = () => {
      close();
      panel = document.createElement('div'); panel.className = 'dl-pick__panel is-open dl-sheet__calpanel dl-calc'; panel.tabIndex = -1;
      const v = parseNum(input.value);
      calcPanel(panel, {start: isNaN(v) || v === 0 ? '' : numRaw(v), decimals: 4, onResult: r => { input.value = fmt.num(r, 4); apply(null); input.dispatchEvent(new Event('change', {bubbles: true})); }});
      document.body.appendChild(panel);
      const b = (input.closest('.dl-field__box, .dl-prefix') || input).getBoundingClientRect(), up = innerHeight - b.bottom < panel.offsetHeight + 12 && b.top > panel.offsetHeight + 12;
      Object.assign(panel.style, {position: 'fixed', left: Math.max(8, b.right - panel.offsetWidth) + 'px', top: (up ? b.top - panel.offsetHeight - 4 : b.bottom + 4) + 'px'});
      panel.focus();
    };
    btn.innerHTML = label(start); btn.title = T('nt_tip');
    btn.addEventListener('click', () => { i = (i + 1) % NUM_STEPS.length; const d = NUM_STEPS[i]; btn.innerHTML = label(d); close(); apply(d); input.dispatchEvent(new Event('change', {bubbles: true})); if (d === null) open(); });
    document.addEventListener('mousedown', e => { if (panel && !panel.contains(e.target) && !btn.contains(e.target)) close(); });
    document.addEventListener('keydown', e => { if (panel && e.key === 'Escape') { close(); btn.focus(); } });
    addEventListener('scroll', e => { if (panel && !panel.contains(e.target)) close(); }, true);
  }

  class Field extends Devlib.Component {
    static selector = ".dl-field";
    init() {
      this.input = $(this.el, '.dl-input');
      this.caseBtn = $(this.el, '[data-dl-case]');
      if (this.caseBtn && this.input) {
        // raw = teks seperti diketik user, shown = raw setelah mode huruf. aa selalu mengembalikan raw (user 2026-10-05:
        // setelah AA teks harus bisa kembali ke bentuk semula). Ketikan baru disisipkan ke raw lewat awalan / akhiran yang sama.
        let idx = 0, raw = this.input.value, shown = raw;
        const show = () => { shown = applyCase(raw, CASES[idx]); this.input.value = shown; this.input.dispatchEvent(new Event('dl:grow')); };
        this.caseBtn.addEventListener('click', () => { if (this.input.value !== shown) raw = this.input.value; idx = (idx + 1) % 3; this.caseBtn.textContent = CASE_LABEL[CASES[idx]]; show(); });
        this.input.addEventListener('input', () => {
          const v = this.input.value, p = this.input.selectionStart, max = Math.min(shown.length, v.length);
          let a = 0; while (a < max && shown[a] === v[a]) a++;
          let b = 0; while (b < max - a && shown[shown.length - 1 - b] === v[v.length - 1 - b]) b++;
          raw = raw.slice(0, a) + v.slice(a, v.length - b) + raw.slice(raw.length - b);
          show(); try { this.input.setSelectionRange(p, p); } catch (e) {}
        });
      }
      // Tinggi textarea mengikuti isi: saat mengetik, saat mode huruf berganti (huruf besar lebih lebar) dan saat lebar berubah.
      $$(this.el, '[data-dl-autogrow]').forEach(t => { const g = () => { t.style.height = 'auto'; t.style.height = t.scrollHeight + 'px'; }; ['input', 'dl:grow'].forEach(e => t.addEventListener(e, g)); if (window.ResizeObserver) { let w = 0; new ResizeObserver(() => { if (t.clientWidth !== w) { w = t.clientWidth; g(); } }).observe(t); } g(); });

      this.eye = $(this.el, '[data-dl-eye]');
      if (this.eye) this.eye.addEventListener('click', () => { const show = this.input.type === 'password'; this.input.type = show ? 'text' : 'password'; this.eye.innerHTML = `<i class="fa-solid fa-eye${show ? '-slash' : ''}"></i>`; });

      this.sel = $(this.el, '[data-dl-select]');
      if (this.sel) {
        const p = bindPanel(this.sel, () => { if (p.search) { p.search.value = ''; p.search.dispatchEvent(new Event('input')); p.search.focus(); } });
        // Delegasi: pilihan bisa diganti belakangan lewat Devlib.h.setOptions (dropdown bersambung).
        $(this.sel, '[data-dl-dd-list]').addEventListener('click', e => { const it = e.target.closest('.dl-dd__item'); if (!it) return; const h = $(this.sel, '[data-dl-value]'); h.value = it.dataset.value; $(this.sel, '[data-dl-dd-text]').textContent = it.textContent; p.close(); h.dispatchEvent(new Event('change', {bubbles: true})); });
      }

      this.multi = $(this.el, '[data-dl-multi]');
      if (this.multi) {
        bindPanel(this.multi);
        const upd = () => {
          const v = $$(this.multi, 'input:checked').map(c => c.dataset.label);
          $(this.multi, '[data-dl-dd-text]').textContent = !v.length ? this.multi.dataset.empty : v.length === 1 ? v[0] : `${v.length} ${this.multi.dataset.noun} (${v.join(', ')})`;
        };
        $$(this.multi, 'input').forEach(c => c.addEventListener('change', upd)); upd();
      }

      this.color = $(this.el, '[data-dl-color]');
      if (this.color) this.color.addEventListener('input', () => { $(this.el, '[data-dl-color-code]').textContent = this.color.value.toUpperCase(); });

      this.tg = $(this.el, '[data-dl-toggle]');
      if (this.tg) { const cb = $(this.tg, 'input'); cb.addEventListener('change', () => { $(this.tg, '[data-dl-toggle-label]').textContent = cb.checked ? this.tg.dataset.on : this.tg.dataset.off; }); }


      this.dr = $(this.el, '[data-dl-daterange]');
      if (this.dr) {
        const [a, b] = $$(this.dr, 'input'), hint = $(this.el, '[data-dl-duration]');
        const u = () => {
          if (!hint || !a.value || !b.value) return;
          const d1 = new Date(a.value), d2 = new Date(b.value);
          if (d2 < d1) { hint.textContent = T('fd_dur_invalid'); return; }
          const m = (d2.getFullYear() - d1.getFullYear()) * 12 + (d2.getMonth() - d1.getMonth());
          hint.textContent = Tf('fd_duration', {y: Math.floor(m / 12), m: m % 12});
        };
        a.addEventListener('change', u); b.addEventListener('change', u); u();
      }

      this.otp = $(this.el, '[data-dl-otp]');
      if (this.otp) {
        const boxes = $$(this.otp, '.dl-otp__box'), hidden = $(this.otp, '[data-dl-value]');
        boxes.forEach((bx, i) => bx.addEventListener('keyup', e => {
          bx.value = bx.value.replace(/\D/g, '');
          if (e.key >= '0' && e.key <= '9' && i < boxes.length - 1) boxes[i + 1].focus();
          else if (e.key === 'Backspace' && i > 0) boxes[i - 1].focus();
          hidden.value = boxes.map(x => x.value).join('');
        }));
      }

      this.mask = $(this.el, '[data-dl-mask]');
      if (this.mask) {
        const pat = this.mask.dataset.dlMask, digits = (pat.match(/9/g) || []).length, err = $(this.el, '[data-dl-field-error]');
        this.mask.addEventListener('input', () => {
          const raw = this.mask.value.replace(/\D/g, '').slice(0, digits); let out = '', j = 0;
          for (const ch of pat) { if (j >= raw.length) break; out += ch === '9' ? raw[j++] : ch; }
          this.mask.value = out;
        });
        this.mask.addEventListener('blur', () => { const n = this.mask.value.replace(/\D/g, '').length; const bad = n > 0 && n < digits; this.el.classList.toggle('is-invalid', bad); err.textContent = bad ? this.mask.dataset.invalid : ''; });
      }

      this.pre = $(this.el, '[data-dl-prefix]');
      if (this.pre) {
        const p = bindPanel(this.pre, () => { if (p.search) { p.search.value = ''; p.search.dispatchEvent(new Event('input')); } });
        const money = this.pre.hasAttribute('data-dl-money'), mi = $(this.pre, '[data-dl-money-input]'), wt = $(this.el, '[data-dl-money-words]');
        // md = jumlah desimal (toggle "00"; null = keadaan kalkulator). 0 = bawaan lama: angka bulat, ribuan diformat sambil mengetik.
        // Selain 0 = ala sel Excel seperti Decimal (fokus = nilai mentah, keluar = diformat). Terbilang selalu dari bagian bulatnya.
        let md = money ? +(mi.dataset.dlDecimals || 0) : 0;
        const mfmt = v => md === null ? fmt.num(v, 4) : fmt.number(v, md);
        const updMoney = () => {
          let raw;
          if (md === 0) { raw = mi.value.replace(/\D/g, ''); mi.value = raw ? BigInt(raw).toLocaleString('en-US').replace(/,/g, NUM.grp) : ''; }
          else { const v = parseNum(mi.value); raw = isNaN(v) ? '' : String(Math.trunc(Math.abs(v))); }
          // Terbilang mengikuti mata uang yang dipilih (Rp -> rupiah, USD -> dolar, ...).
          if (wt) { const C = JSON.parse(wt.dataset.currencies), code = $(this.pre, '[data-dl-prefix-value]').value; wt.textContent = raw ? words(raw, C[code] || null) : ''; }
        };
        $$(this.pre, '.dl-dd__item').forEach(it => it.addEventListener('click', () => {
          $(this.pre, '[data-dl-prefix-value]').value = it.dataset.value;
          const a = $(this.pre, '[data-dl-prefix-a]'); if (a) a.textContent = it.dataset.a; $(this.pre, '[data-dl-prefix-b]').textContent = it.dataset.b;
          p.close(); if (money) updMoney();
        }));
        if (money) {
          // Nilai mentah dari DB (1250000.00) dibulatkan dulu -- bukan dibuang titiknya (dulu jadi 125000000).
          if (/^-?\d+\.\d+$/.test(mi.value.trim()) && !(NUM.grp === '.' && /^\d{1,3}(\.\d{3})+$/.test(mi.value.trim()))) mi.value = md === 0 ? String(Math.round(+mi.value.trim())) : mfmt(+mi.value.trim());
          else if (md !== 0) { const v = parseNum(mi.value); if (mi.value.trim() !== '' && !isNaN(v)) mi.value = mfmt(v); }
          mi.addEventListener('input', () => { if (md !== 0) mi.value = mi.value.replace(/[^0-9.,-]/g, ''); updMoney(); });
          mi.addEventListener('focus', () => { if (md !== 0 && !mi.readOnly) { mi.value = numRaw(mi.value); mi.select(); } });
          mi.addEventListener('blur', () => { const v = parseNum(mi.value); if (md !== 0 && mi.value.trim() !== '' && !isNaN(v)) mi.value = mfmt(v); });
          numToggle(this.el, mi, md, d => { const v = parseNum(mi.value); md = d; if (!isNaN(v)) mi.value = md === 0 ? String(Math.round(v)) : mfmt(v); updMoney(); });
          updMoney();
        }
      }

      this.dec = $(this.el, '[data-dl-decimal]');
      if (this.dec) {
        let n = +this.dec.dataset.dlDecimal;  // null = keadaan kalkulator (toggle "00"): desimal secukupnya, maks 4
        // Ala sel Excel (user 2026-09-30): fokus = nilai mentah (1234,5), ketik bebas, keluar = diformat bahasa tampilan (1.234,50).
        // Dikirim ke server sebagai kanonik oleh core ('formdata', data-dl-num).
        const out = () => { const v = parseNum(this.dec.value); if (this.dec.value.trim() !== '' && !isNaN(v)) this.dec.value = n === null ? fmt.num(v, 4) : fmt.number(v, n); };
        this.dec.addEventListener('focus', () => { if (!this.dec.readOnly) { this.dec.value = numRaw(this.dec.value); this.dec.select(); } });
        this.dec.addEventListener('input', () => { this.dec.value = this.dec.value.replace(/[^0-9.,-]/g, ''); });
        this.dec.addEventListener('blur', out);
        numToggle(this.el, this.dec, n, d => { n = d; out(); });
        out();
      }

      this.num = $(this.el, '[data-dl-number]');
      if (this.num) {
        const inp = $(this.num, 'input'), min = this.num.dataset.min === '' ? -Infinity : +this.num.dataset.min, max = this.num.dataset.max === '' ? Infinity : +this.num.dataset.max, step = +this.num.dataset.step || 1;
        const clamp = v => Math.min(max, Math.max(min, v));
        $$(this.num, '[data-dl-spin]').forEach(b => b.addEventListener('click', () => { inp.value = clamp((parseInt(inp.value, 10) || 0) + step * b.dataset.dlSpin); }));
        inp.addEventListener('input', () => { inp.value = inp.value.replace(/\D/g, ''); });
        inp.addEventListener('blur', () => { inp.value = clamp(parseInt(inp.value, 10) || 0); });
      }

      this.dt = $(this.el, '[data-dl-date]');
      if (this.dt) {
        const hidden = $(this.dt, '[data-dl-value]'), text = $(this.dt, '[data-dl-date-text]'), days = $(this.dt, '[data-dl-cal-days]'), title = $(this.dt, '[data-dl-cal-title]');
        const MON = NUM.months;
        let view = hidden.value ? new Date(hidden.value) : new Date();
        const iso = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        const setVal = (d, quiet) => { const v = DlDate.of(d); hidden.value = v ? v.iso() : ''; text.value = v ? v.display() : ''; this.el.classList.remove('is-invalid'); if (!quiet) hidden.dispatchEvent(new Event('change', {bubbles: true})); };
        // Boleh diketik (objek DlDate): 10-10-26 / 10 okt / 2026-10-10 -> tampil 10-Okt-2026, kirim ISO. Tidak sah -> ditandai, nilai lama tetap.
        const typed = () => { const s = text.value.trim(), v = DlDate.of(s); if (!s) setVal(null); else if (v) { if (v.iso() !== hidden.value) setVal(v); else text.value = v.display(); } else this.el.classList.add('is-invalid'); };
        text.addEventListener('change', typed);
        text.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); typed(); } });
        const dn = $(this.dt, '.dl-cal--names'); if (dn) dn.innerHTML = dow2().map(d => `<div>${d}</div>`).join('');
        const draw = () => {
          title.textContent = `${MON[view.getMonth()]} ${view.getFullYear()}`;
          const first = new Date(view.getFullYear(), view.getMonth(), 1).getDay(), total = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
          let h = '<div></div>'.repeat(first);
          for (let d = 1; d <= total; d++) { const v = iso(new Date(view.getFullYear(), view.getMonth(), d)); h += `<button type="button" data-d="${v}" class="${v === hidden.value ? 'is-sel' : ''}">${d}</button>`; }
          days.innerHTML = h;
        };
        const p = bindPanel(this.dt, draw);
        days.addEventListener('click', e => { const b = e.target.closest('[data-d]'); if (b) { setVal(b.dataset.d); p.close(); } });
        $(this.dt, '[data-dl-cal-prev]').addEventListener('click', () => { view.setMonth(view.getMonth() - 1); draw(); });
        $(this.dt, '[data-dl-cal-next]').addEventListener('click', () => { view.setMonth(view.getMonth() + 1); draw(); });
        $(this.dt, '[data-dl-cal-clear]').addEventListener('click', () => { setVal(null); p.close(); });
        $(this.dt, '[data-dl-cal-today]').addEventListener('click', () => { setVal(new Date()); p.close(); });
        if (hidden.value) setVal(hidden.value, true);
      }

      this.dtt = $(this.el, '[data-dl-datetime]');
      if (this.dtt) {
        const hidden = $(this.dtt, '[data-dl-value]'), text = $(this.dtt, '[data-dl-date-text]'), days = $(this.dtt, '[data-dl-cal-days]'), title = $(this.dtt, '[data-dl-cal-title]');
        const hh = $(this.dtt, '[data-dl-time-h]'), mm = $(this.dtt, '[data-dl-time-m]'), prev = $(this.dtt, '[data-dl-dt-preview]'), tz = this.dtt.dataset.tzLabel || 'WIB';
        const MON = NUM.months;
        const pad = n => String(n).padStart(2, '0');
        let [d0, t0] = (hidden.value || '').split(/[ T]/);
        let sel = d0 ? d0 : null;
        let view = sel ? new Date(sel) : new Date();
        if (t0) { hh.value = t0.slice(0, 2); const m = Math.round(+t0.slice(3, 5) / +mm.options[1].value) * +mm.options[1].value; mm.value = pad(m % 60); }
        const iso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        const label = (ds, h, m) => `${DlDateTime.of(`${ds} ${h}:${m}`)?.display() ?? ''} ${tz}`;  // objek tanggal+jam DevLib
        const preview = () => { prev.textContent = T('fd_selected') + ': ' + (sel ? label(sel, hh.value, mm.value) : '-'); };
        const draw = () => {
          title.textContent = `${MON[view.getMonth()]} ${view.getFullYear()}`;
          const first = new Date(view.getFullYear(), view.getMonth(), 1).getDay(), total = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
          let h = '<div></div>'.repeat(first);
          for (let d = 1; d <= total; d++) { const v = iso(new Date(view.getFullYear(), view.getMonth(), d)); h += `<button type="button" data-d="${v}" class="${v === sel ? 'is-sel' : ''}">${d}</button>`; }
          days.innerHTML = h; preview();
        };
        const p = bindPanel(this.dtt, draw);
        days.addEventListener('click', e => { const b = e.target.closest('[data-d]'); if (b) { sel = b.dataset.d; draw(); } });
        [hh, mm].forEach(s => s.addEventListener('change', preview));
        $(this.dtt, '[data-dl-cal-prev]').addEventListener('click', () => { view.setMonth(view.getMonth() - 1); draw(); });
        $(this.dtt, '[data-dl-cal-next]').addEventListener('click', () => { view.setMonth(view.getMonth() + 1); draw(); });
        $(this.dtt, '[data-dl-cal-clear]').addEventListener('click', () => { sel = null; hidden.value = ''; text.value = ''; p.close(); });
        $(this.dtt, '[data-dl-dt-apply]').addEventListener('click', () => {
          if (!sel) { sel = iso(new Date()); }
          hidden.value = `${sel} ${hh.value}:${mm.value}`; text.value = label(sel, hh.value, mm.value); p.close();
          hidden.dispatchEvent(new Event('change', {bubbles: true}));
        });
        text.addEventListener('click', () => $(this.dtt, '[data-dl-dd-toggle]').click());
        if (sel) text.value = label(sel, hh.value, mm.value);
      }

      this.tm = $(this.el, '[data-dl-time]');
      if (this.tm) {
        const text = $(this.tm, '[data-dl-time-text]'), h = $(this.tm, '[data-dl-time-h]'), m = $(this.tm, '[data-dl-time-m]');
        const p = bindPanel(this.tm, () => { const [hh, mm] = text.value.split(':'); if (hh) h.value = hh.padStart(2, '0'); if (mm) m.value = String(Math.round(+mm / 5) * 5 % 60).padStart(2, '0'); });
        $(this.tm, '[data-dl-time-apply]').addEventListener('click', () => { text.value = `${h.value}:${m.value}`; p.close(); });
        $(this.tm, '[data-dl-time-clear]').addEventListener('click', () => { text.value = ''; p.close(); });
        text.addEventListener('input', () => { const r = text.value.replace(/\D/g, '').slice(0, 4); text.value = r.length > 2 ? r.slice(0, 2) + ':' + r.slice(2) : r; });
        // Keluar isian: dirapikan objek jam DevLib (8 -> 08:00, 830 -> 08:30); tidak sah -> ditandai.
        text.addEventListener('change', () => { const t = DlTime.of(text.value.replace(':', '')) || DlTime.of(text.value); if (t) text.value = t.display(); this.el.classList.toggle('is-invalid', !!text.value && !t); });
      }
    }
  }
  Devlib.define("Field", Field);
})(window.Devlib);

/* DevLib · Field */
(function (Devlib) {
  'use strict';
  const {$, $$, CASES, CASE_LABEL, applyCase, bindPanel, T, words} = Devlib.h;

  class Field extends Devlib.Component {
    static selector = ".dl-field";
    init() {
      this.input = $(this.el, '.dl-input');
      this.caseBtn = $(this.el, '[data-dl-case]');
      if (this.caseBtn && this.input) {
        let idx = 0;
        this.caseBtn.addEventListener('click', () => { idx = (idx + 1) % 3; this.caseBtn.textContent = CASE_LABEL[CASES[idx]]; this.input.value = applyCase(this.input.value, CASES[idx]); });
        this.input.addEventListener('input', () => { const p = this.input.selectionStart; this.input.value = applyCase(this.input.value, CASES[idx]); try { this.input.setSelectionRange(p, p); } catch (e) {} });
      }
      $$(this.el, '[data-dl-autogrow]').forEach(t => { const g = () => { t.style.height = 'auto'; t.style.height = t.scrollHeight + 'px'; }; t.addEventListener('input', g); g(); });

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
          if (d2 < d1) { hint.textContent = 'Durasi: Tanggal akhir tidak valid.'; return; }
          const m = (d2.getFullYear() - d1.getFullYear()) * 12 + (d2.getMonth() - d1.getMonth());
          hint.textContent = `Durasi: ${Math.floor(m / 12)} Tahun ${m % 12} Bulan`;
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
        const updMoney = () => {
          const raw = mi.value.replace(/\D/g, '');
          mi.value = raw ? BigInt(raw).toLocaleString('id-ID') : '';
          // Terbilang mengikuti mata uang yang dipilih (Rp -> rupiah, USD -> dolar, ...).
          if (wt) { const C = JSON.parse(wt.dataset.currencies), code = $(this.pre, '[data-dl-prefix-value]').value; wt.textContent = raw ? words(raw, C[code] || null) : ''; }
        };
        $$(this.pre, '.dl-dd__item').forEach(it => it.addEventListener('click', () => {
          $(this.pre, '[data-dl-prefix-value]').value = it.dataset.value;
          $(this.pre, '[data-dl-prefix-a]').textContent = it.dataset.a; $(this.pre, '[data-dl-prefix-b]').textContent = it.dataset.b;
          p.close(); if (money) updMoney();
        }));
        if (money) { mi.addEventListener('input', updMoney); updMoney(); }
      }

      this.dec = $(this.el, '[data-dl-decimal]');
      if (this.dec) {
        const n = +this.dec.dataset.dlDecimal;
        this.dec.addEventListener('input', () => { this.dec.value = this.dec.value.replace(/[^0-9.,]/g, '').replace(',', '.'); });
        this.dec.addEventListener('blur', () => { if (this.dec.value !== '' && !isNaN(parseFloat(this.dec.value))) this.dec.value = parseFloat(this.dec.value).toFixed(n); });
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
        const p = bindPanel(this.dt, draw);
        days.addEventListener('click', e => { const b = e.target.closest('[data-d]'); if (b) { setVal(new Date(b.dataset.d)); p.close(); } });
        $(this.dt, '[data-dl-cal-prev]').addEventListener('click', () => { view.setMonth(view.getMonth() - 1); draw(); });
        $(this.dt, '[data-dl-cal-next]').addEventListener('click', () => { view.setMonth(view.getMonth() + 1); draw(); });
        $(this.dt, '[data-dl-cal-clear]').addEventListener('click', () => { setVal(null); p.close(); });
        $(this.dt, '[data-dl-cal-today]').addEventListener('click', () => { setVal(new Date()); p.close(); });
        text.addEventListener('click', () => $(this.dt, '[data-dl-dd-toggle]').click());
        if (hidden.value) setVal(new Date(hidden.value), true);
      }

      this.dtt = $(this.el, '[data-dl-datetime]');
      if (this.dtt) {
        const hidden = $(this.dtt, '[data-dl-value]'), text = $(this.dtt, '[data-dl-date-text]'), days = $(this.dtt, '[data-dl-cal-days]'), title = $(this.dtt, '[data-dl-cal-title]');
        const hh = $(this.dtt, '[data-dl-time-h]'), mm = $(this.dtt, '[data-dl-time-m]'), prev = $(this.dtt, '[data-dl-dt-preview]'), tz = this.dtt.dataset.tzLabel || 'WIB';
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
      }
    }
  }
  Devlib.define("Field", Field);
})(window.Devlib);

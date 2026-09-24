/* DevLib · Field */
(function (Devlib) {
  'use strict';
  const {$, $$, CASES, CASE_LABEL, applyCase, bindPanel, T, words} = Devlib.h;

  class Field extends Devlib.Component {
    static selector = ".dl-field";
    init() {
      const f = this.el;
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
  }
  Devlib.define("Field", Field);
})(window.Devlib);

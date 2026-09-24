/* DevLib · Lines */
(function (Devlib) {
  'use strict';
  const {srcAttr, $, $$, esc, CASES, CASE_LABEL, applyCase, confirmBox} = Devlib.h;
  /* ============================ LINES (detail dokumen) ============================ */
  class Lines extends Devlib.Component {
    static selector = "[data-dl-lines]";
    init() {
      const self = this;
      this.cfg = JSON.parse(this.el.dataset.config);
      this.cols = this.cfg.columns;
      this.name = this.cfg.name;
      this.ro = this.cfg.readonly;
      this.rows = JSON.parse(this.el.dataset.rows || '[]');
      // Rumus aman: hanya nama kolom, angka, dan + - * / ( ).
      this.cols.forEach(c => {
        if (!c.formula) return;
        if (!/^[\w\s+\-*/().]+$/.test(c.formula)) throw new Error('formula tidak sah: ' + c.formula);
        const body = c.formula.replace(/[A-Za-z_]\w*/g, k => { if (!this.cols.some(x => x.key === k)) throw new Error('kolom tidak dikenal: ' + k); return `(+r[${JSON.stringify(k)}]||0)`; });
        c.fn = new Function('r', 'return ' + body);
      });

      this.el.addEventListener('input', e => {
        const t = e.target;
        if (t.matches('[data-l-disc]')) { t.value = t.value.replace(/[^\d]/g, '') ? (+t.value.replace(/[^\d]/g, '')).toLocaleString('id-ID') : ''; return this.refresh(); }
        if (!t.matches('.dl-lines__in') || t.dataset.lk !== undefined) return;
        const c = this.colOf(t.dataset.k), r = this.rowOf(t);
        if (c.type === 'text' && t.dataset.case) { const p = t.selectionStart; t.value = applyCase(t.value, t.dataset.case); try { t.setSelectionRange(p, p); } catch (x) {} }
        r[c.key] = this.num(c) ? this.raw(t.value) : t.value;
        t.closest('td').classList.remove('is-invalid');
        this.refresh();
      });
      this.el.addEventListener('change', e => { if (e.target.matches('select.dl-lines__in, input[type=date].dl-lines__in')) { this.rowOf(e.target)[e.target.dataset.k] = e.target.value; e.target.closest('td').classList.remove('is-invalid'); this.refresh(); } if (e.target.matches('[data-l-ppn]')) this.refresh(); });
      this.el.addEventListener('focusin', e => { const t = e.target; if (t.classList.contains('dl-lines__num')) { t.value = this.rowOf(t)[t.dataset.k] ?? ''; t.select(); } });
      this.el.addEventListener('focusout', e => { const t = e.target; if (t.classList.contains('dl-lines__num')) t.value = this.show(this.colOf(t.dataset.k), this.rowOf(t)[t.dataset.k] ?? ''); });
      this.el.addEventListener('keydown', e => {
        const t = e.target; if (e.key !== 'Enter' || !t.matches('.dl-lines__in') || t.dataset.lk !== undefined) return;
        e.preventDefault();
        const i = +t.closest('tr').dataset.i, next = $(this.el, `tr[data-i="${i + 1}"] [data-k="${t.dataset.k}"]`);
        if (next) next.focus(); else if (!this.ro) { this.add(false); $(this.el, `tr[data-i="${i + 1}"] [data-k="${t.dataset.k}"]`)?.focus(); }
      });
      this.el.addEventListener('click', e => {
        const x = e.target;
        if (x.closest('[data-l-add]')) return this.add();
        if (x.closest('[data-l-dup]')) { const i = +x.closest('tr').dataset.i; this.rows.splice(i + 1, 0, {...this.rows[i]}); this.body(); this.changed(); return; }
        const del = x.closest('[data-l-del]');
        if (del) {
          const i = +del.closest('tr').dataset.i, go = () => { this.rows.splice(i, 1); this.body(); this.changed(); };
          if (this.filled(this.rows[i])) confirmBox({message: `Hapus baris ${i + 1}?`, tone: 'danger'}).then(ok => ok && go()); else go();
          return;
        }
        const cb = x.closest('[data-l-case]');
        if (cb) { const inp = cb.previousElementSibling, idx = (CASES.indexOf(inp.dataset.case || 'none') + 1) % 3; inp.dataset.case = CASES[idx]; cb.textContent = CASE_LABEL[CASES[idx]]; inp.value = applyCase(inp.value, CASES[idx]); inp.dispatchEvent(new Event('input', {bubbles: true})); inp.focus(); }
      });

      this.pop = null;
      this.el.addEventListener('focusin', e => { if (e.target.dataset.lk !== undefined) this.openPop(e.target); });
      this.el.addEventListener('input', e => { if (e.target.dataset.lk !== undefined) this.openPop(e.target); });
      this.el.addEventListener('focusout', e => { if (e.target.dataset.lk !== undefined) { const r = this.rowOf(e.target), c = this.colOf(e.target.dataset.k); e.target.value = r[c.key + '_label'] ?? ''; setTimeout(this.closePop, 120); } });

      this.el.dlLines = {
        get rows() { return self.rows.filter(self.filled).map(r => ({...r})); },
        set(list) { self.rows = (list || []).map(r => ({...r})); while (self.rows.length < Math.max(1, self.cfg.minRows)) self.rows.push(self.blank()); self.body(); $$(self.el, '[data-l-err]').forEach(x => { x.textContent = ''; }); },
        validate: this.validate, total: this.total,
      };
      this.head();
      if (!this.rows.length && !this.ro) this.rows.push(this.blank());
      this.body();
    }

    money(v) {
      return (+v || 0).toLocaleString('id-ID');
    }

    raw(v) {
      return String(v ?? '').replace(/\./g, '').replace(',', '.');
    }

    blank() {
      return Object.fromEntries(this.cols.map(c => [c.key, c.default ?? '']));
    }

    filled(r) {
      return this.cols.some(c => !c.formula && c.type !== 'display' && String(r[c.key] ?? '').trim() !== '');
    }

    calc(r) {
      return this.cols.forEach(c => { if (c.fn) r[c.key] = Math.round(c.fn(r) * 100) / 100; });
    }

    num(c) {
      return ['number', 'money'].includes(c.type) || !!c.fn;
    }

    show(c, v) {
      return (v === '' || v == null) && !c.fn ? '' : c.type === 'money' || (c.fn && c.type !== 'number') ? this.money(v) : c.type === 'number' ? (v === '' ? '' : (+v).toLocaleString('id-ID')) : v;
    }

    cell(c, r, i) {
      const v = r[c.key] ?? '', req = c.required ? ' data-req' : '';
      if (c.fn || c.type === 'display' || this.ro) return `<td class="${this.num(c) ? 'num' : ''} dl-lines__ro" data-k="${esc(c.key)}">${esc(c.type === 'lookup' ? (r[c.key + '_label'] ?? v) : this.show(c, v))}</td>`;
      switch (c.type) {
        case 'select': return `<td${req}><select class="dl-lines__in" data-k="${esc(c.key)}"><option value=""></option>${Object.entries(c.options || {}).map(([k, l]) => `<option value="${esc(k)}" ${String(k) === String(v) ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></td>`;
        case 'date': return `<td${req}><input type="date" class="dl-lines__in" data-k="${esc(c.key)}" value="${esc(v)}"></td>`;
        case 'number': case 'money': return `<td class="num"${req}><input type="text" inputmode="decimal" class="dl-lines__in dl-lines__num" data-k="${esc(c.key)}" value="${esc(this.show(c, v))}"></td>`;
        case 'lookup': return `<td${req} class="dl-lines__lk"><input type="text" class="dl-lines__in" data-k="${esc(c.key)}" data-lk value="${esc(r[c.key + '_label'] ?? '')}" placeholder="Cari..." autocomplete="off"><i class="fa-solid fa-magnifying-glass"></i></td>`;
        default: return `<td${req} class="dl-lines__txt"><input type="text" class="dl-lines__in" data-k="${esc(c.key)}" value="${esc(v)}"><button type="button" class="dl-lines__case" data-l-case tabindex="-1" title="Ganti huruf: kecil / Kapital Tiap Kata / BESAR">aa</button></td>`;
      }
    }

    head() { $(this.el, '[data-l-head]').innerHTML = `<tr><th class="dl-lines__no">#</th>${this.cols.map(c => `<th class="${this.num(c) ? 'num' : ''}"${srcAttr(c)} ${c.width ? `style="width:${esc(c.width)}"` : ''}>${esc(c.label)}${c.required ? ' <span class="dl-field__req">*</span>' : ''}</th>`).join('')}${this.ro ? '' : '<th class="dl-lines__act"></th>'}</tr>`; }

    body() {
      $(this.el, '[data-l-body]').innerHTML = this.rows.map((r, i) => `<tr data-i="${i}"><td class="dl-lines__no">${i + 1}</td>${this.cols.map(c => this.cell(c, r, i)).join('')}${this.ro ? '' : `<td class="dl-lines__act"><button type="button" class="dl-iconbtn dl-iconbtn--plain" data-l-dup title="Duplikat baris"><i class="fa-solid fa-copy"></i></button><button type="button" class="dl-iconbtn dl-iconbtn--plain dl-iconbtn--danger" data-l-del title="Hapus baris"><i class="fa-solid fa-trash-can"></i></button></td>`}</tr>`).join('')
        || `<tr><td colspan="${this.cols.length + 2}" class="dl-lines__empty">Belum ada baris. Klik <b>${esc($(this.el, '[data-l-add]')?.textContent.trim() || '+ Tambah Baris')}</b>.</td></tr>`;
      this.refresh();
    }

    refresh() {
      this.rows.forEach((r, i) => { this.calc(r); this.cols.filter(c => c.fn).forEach(c => { const td = $(this.el, `tr[data-i="${i}"] td[data-k="${c.key}"]`); if (td) td.textContent = this.show(c, r[c.key]); }); });
      const sums = this.cols.filter(c => c.sum);
      $(this.el, '[data-l-foot]').innerHTML = sums.length && this.rows.length ? `<tr><td></td>${this.cols.map((c, i) => c.sum ? `<td class="num"><b>${esc(this.show(c, this.rows.reduce((t, r) => t + (+r[c.key] || 0), 0)))}</b></td>` : `<td>${i === 0 ? '<b>Jumlah</b>' : ''}</td>`).join('')}${this.ro ? '' : '<td></td>'}</tr>` : '';
      $(this.el, '[data-l-count]').textContent = `${this.rows.filter(this.filled).length} baris`;
      const t = this.total();
      if (this.cfg.totals && this.cfg.totals.of) Object.entries(t).forEach(([k, v]) => { const b = $(this.el, `[data-l-t="${k}"]`); if (b) b.textContent = 'Rp ' + this.money(v); });
      $(this.el, '[data-l-inputs]').innerHTML = this.rows.filter(this.filled).map((r, i) => Object.entries(r).map(([k, v]) => `<input type="hidden" name="${esc(this.name)}[${i}][${esc(k)}]" value="${esc(v)}">`).join('')).join('');
    }

    total() {
      const of = this.cfg.totals && this.cfg.totals.of, sub = this.rows.reduce((t, r) => t + (+r[of] || 0), 0);
      const d = $(this.el, '[data-l-disc]'), disc = d ? +this.raw(d.value) || 0 : 0, dpp = sub - disc;
      const p = $(this.el, '[data-l-ppn]'), ppn = p && p.checked ? Math.round(dpp * (+this.cfg.totals.ppn || 0)) / 100 : 0;
      return {sub, dpp, ppn, grand: dpp + ppn};
    }

    changed() {
      return this.el.dispatchEvent(new Event('change', {bubbles: true}));
    }

    rowOf(t) {
      return this.rows[+t.closest('tr').dataset.i];
    }

    colOf(k) {
      return this.cols.find(c => c.key === k);
    }

    add(focus = true) { this.rows.push(this.blank()); this.body(); this.changed(); if (focus) { const f = $(this.el, `tr[data-i="${this.rows.length - 1}"] .dl-lines__in`); if (f) f.focus(); } }
    closePop() { if (this.pop) { this.pop.remove(); this.pop = null; } }

    openPop(inp) {
      const c = this.colOf(inp.dataset.k), L = c.lookup, q = inp.value.trim().toLowerCase();
      const list = L.rows.filter(r => !q || Object.keys(L.columns).some(k => String(r[k] ?? '').toLowerCase().includes(q))).slice(0, 50);
      this.closePop();
      this.pop = document.createElement('div'); this.pop.className = 'dl-lines__pop';
      this.pop.innerHTML = `<table class="dl-lookup__table"><thead><tr>${Object.values(L.columns).map(h => `<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${list.map(r => `<tr class="dl-dd__item" data-key="${esc(r[L.key])}">${Object.keys(L.columns).map(k => `<td>${esc(r[k] ?? '')}</td>`).join('')}</tr>`).join('') || `<tr><td colspan="9" class="dl-lines__empty">Tidak ditemukan</td></tr>`}</tbody></table>`;
      const rc = inp.getBoundingClientRect();
      this.pop.style.cssText = `left:${rc.left + scrollX}px;top:${rc.bottom + scrollY + 2}px;min-width:${Math.max(rc.width, 320)}px`;
      this.pop.addEventListener('mousedown', e => {
        const tr = e.target.closest('tr[data-key]'); if (!tr) return;
        e.preventDefault();
        const src = L.rows.find(r => String(r[L.key]) === tr.dataset.key), row = this.rowOf(inp);
        row[c.key] = src[L.key]; row[c.key + '_label'] = src[L.label];
        Object.entries(L.fill || {}).forEach(([to, from]) => { row[to] = src[from] ?? ''; });
        const i = +inp.closest('tr').dataset.i; this.closePop(); this.body(); this.changed();
        const nextCol = this.cols[this.cols.indexOf(c) + 1]; $(this.el, `tr[data-i="${i}"] [data-k="${nextCol ? nextCol.key : c.key}"]`)?.focus();
      });
      document.body.appendChild(this.pop);
    }

    validate() {
      let bad = 0;
      $$(this.el, 'td.is-invalid').forEach(td => td.classList.remove('is-invalid'));
      this.rows.forEach((r, i) => { if (!this.filled(r)) return; this.cols.filter(c => c.required).forEach(c => { if (String(r[c.key] ?? '').trim() === '') { bad++; $(this.el, `tr[data-i="${i}"] [data-k="${c.key}"]`)?.closest('td').classList.add('is-invalid'); } }); });
      const n = this.rows.filter(this.filled).length, err = $(this.el, '[data-l-err]') || this.el.appendChild(Object.assign(document.createElement('div'), {className: 'dl-lines__err'}));
      err.dataset.lErr = ''; err.textContent = n < this.cfg.minRows ? `Minimal ${this.cfg.minRows} baris terisi.` : bad ? `${bad} sel wajib belum diisi.` : '';
      return bad + (n < this.cfg.minRows ? 1 : 0);
    }
  }
  Devlib.define("Lines", Lines);
})(window.Devlib);

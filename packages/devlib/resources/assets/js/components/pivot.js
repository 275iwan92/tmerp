/* DevLib · Pivot */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, val} = Devlib.h;
  /* ============================ PIVOT ============================ */
  class Pivot extends Devlib.Component {
    static selector = "[data-dl-pivot]";
    init() {
      this.cfg = JSON.parse(this.el.dataset.config);
      this.data = JSON.parse(this.el.dataset.rows || '[]');
      this.st = {r: [...this.cfg.rowsBy, ''].slice(0, 2), c: this.cfg.colBy || '', v: this.cfg.value, agg: this.cfg.agg, pct: false, heat: true};
      this.F = this.cfg.fields;
      this.M = this.cfg.measures;
      this.$s = s => $(this.el, s);
      this.AGG = {sum: a => a.reduce((t, x) => t + x, 0), count: a => a.length, avg: a => a.length ? a.reduce((t, x) => t + x, 0) / a.length : 0, min: a => a.length ? Math.min(...a) : 0, max: a => a.length ? Math.max(...a) : 0};
      this.lastTable = [];
      this.el.addEventListener('change', e => {
        const t = e.target;
        if (t.matches('[data-p-row]')) this.st.r[+t.dataset.pRow] = t.value;
        else if (t.matches('[data-p-col]')) this.st.c = t.value;
        else if (t.matches('[data-p-val]')) this.st.v = t.value;
        else if (t.matches('[data-p-pct]')) this.st.pct = t.checked;
        else if (t.matches('[data-p-heat]')) this.st.heat = t.checked;
        else return;
        this.draw();
      });
      this.el.addEventListener('click', e => {
        const a = e.target.closest('[data-p-agg] .dl-segment__opt'); if (a) { this.st.agg = a.dataset.value; return this.draw(); }
        if (e.target.closest('[data-p-export]')) { const csv = this.lastTable.map(r => r.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(';')).join('\r\n'); const l = document.createElement('a'); l.href = URL.createObjectURL(new Blob(['\ufeff' + csv], {type: 'text/csv'})); l.download = 'pivot.csv'; l.click(); return; }
        const c = e.target.closest('[data-p-cell]'); if (!c) return;
        const [f0, f1, cv] = JSON.parse(c.dataset.pCell), r1k = this.st.r[1] && this.st.r[1] !== this.st.r[0] ? this.st.r[1] : '';
        const list = this.data.filter(r => String(r[this.st.r[0]] ?? '') === f0 && (f1 == null || !r1k || String(r[r1k] ?? '') === f1) && (cv == null || String(r[this.st.c] ?? '') === cv));
        if (!this.el.dispatchEvent(new CustomEvent('dl:pivot-cell', {bubbles: true, cancelable: true, detail: {rows: list, filter: {[this.st.r[0]]: f0, ...(f1 != null && r1k ? {[r1k]: f1} : {}), ...(cv != null ? {[this.st.c]: cv} : {})}}}))) return;
        const keys = Object.keys(list[0] || {}).slice(0, 7);
        const ov = document.createElement('div'); ov.className = 'dl-mov is-on';
        ov.innerHTML = `<div class="dl-modal" style="max-width:860px;width:calc(100% - 32px)"><div class="dl-modal__head"><h3>${esc([f0, f1, cv].filter(v => v != null).join(' · '))} — ${list.length} baris sumber</h3><button type="button" class="dl-modal__x">&times;</button></div><div class="dl-modal__body" style="max-height:60vh;overflow:auto"><table class="dl-pivot__table"><thead><tr>${keys.map(k => `<th>${esc(this.F[k] || this.M[k] || k)}</th>`).join('')}</tr></thead><tbody>${list.map(r => `<tr>${keys.map(k => `<td>${esc(typeof r[k] === 'number' ? r[k].toLocaleString('id-ID') : r[k])}</td>`).join('')}</tr>`).join('')}</tbody></table></div></div>`;
        ov.addEventListener('click', ev => { if (ev.target === ov || ev.target.closest('.dl-modal__x')) ov.remove(); });
        document.body.appendChild(ov);
      });
      this.el.dlPivot = {st: this.st, draw: this.draw};
      this.draw();
    }

    opt(o, sel, blank) {
      return (blank ? '<option value="">—</option>' : '') + Object.entries(o).map(([k, l]) => `<option value="${esc(k)}" ${k === sel ? 'selected' : ''}>${esc(l)}</option>`).join('');
    }

    uniq(k) {
      return [...new Set(this.data.map(r => String(r[k] ?? '')))].sort((a, b) => a.localeCompare(b, 'id', {numeric: true}));
    }

    fmtV(v, tot) { if (this.st.pct) return tot ? (v / tot * 100).toLocaleString('id-ID', {maximumFractionDigits: 1}) + '%' : '–'; const money = this.st.agg !== 'count' && this.cfg.formats[this.st.v] === 'money'; return money ? 'Rp ' + Math.round(v).toLocaleString('id-ID') : (+v).toLocaleString('id-ID', {maximumFractionDigits: this.st.agg === 'avg' ? 2 : 0}); }
    calc(filter) { const list = this.data.filter(filter); return {v: this.AGG[this.st.agg](list.map(r => +r[this.st.v] || 0)), n: list.length}; }

    draw() {
      this.$s('[data-p-row="0"]').innerHTML = this.opt(this.F, this.st.r[0]); this.$s('[data-p-row="1"]').innerHTML = this.opt(this.F, this.st.r[1], true); this.$s('[data-p-col]').innerHTML = this.opt(this.F, this.st.c, true); this.$s('[data-p-val]').innerHTML = this.opt(this.M, this.st.v);
      $$(this.el, '[data-p-agg] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b.dataset.value === this.st.agg));
      const cols = this.st.c ? this.uniq(this.st.c) : [], r0 = this.uniq(this.st.r[0]), r1k = this.st.r[1] && this.st.r[1] !== this.st.r[0] ? this.st.r[1] : '';
      const match = (lv0, lv1, cv) => r => String(r[this.st.r[0]] ?? '') === lv0 && (lv1 == null || String(r[r1k] ?? '') === lv1) && (cv == null || String(r[this.st.c] ?? '') === cv);
      const grand = this.calc(() => true).v;
      const lines = [];
      r0.forEach(a => {
        const subs = r1k ? [...new Set(this.data.filter(match(a, null, null)).map(r => String(r[r1k] ?? '')))].sort() : [null];
        subs.forEach((b, i) => lines.push({lv: r1k ? 2 : 1, label: b ?? a, parent: a, f0: a, f1: b, first: i === 0, span: subs.length}));
        if (r1k) lines.push({lv: 'sub', label: 'Subtotal ' + a, f0: a, f1: null});
      });
      const cells = [], all = [];
      lines.forEach(l => { const vals = cols.map(c => this.calc(match(l.f0, l.f1, c)).v); const tot = this.calc(match(l.f0, l.f1, null)).v; cells.push({vals, tot}); if (l.lv !== 'sub') all.push(...vals); });
      const max = Math.max(1, ...all.map(Math.abs));
      const heat = v => this.st.heat && cols.length ? ` style="--k:${(Math.abs(v) / max * 0.85).toFixed(2)}"` : '';
      let h = `<thead><tr><th>${esc(this.F[this.st.r[0]])}</th>${r1k ? `<th>${esc(this.F[r1k])}</th>` : ''}${cols.map(c => `<th class="num">${esc(c)}</th>`).join('')}<th class="num is-total">Total</th></tr></thead><tbody>`;
      this.lastTable = [[this.F[this.st.r[0]], ...(r1k ? [this.F[r1k]] : []), ...cols, 'Total']];
      lines.forEach((l, i) => {
        const {vals, tot} = cells[i], sub = l.lv === 'sub';
        h += `<tr class="${sub ? 'is-sub' : ''}">` + (sub ? `<td colspan="${r1k ? 2 : 1}">${esc(l.label)}</td>` : (r1k ? (l.first ? `<td rowspan="${l.span}" class="dl-pivot__grp">${esc(l.parent)}</td>` : '') + `<td>${esc(l.label)}</td>` : `<td>${esc(l.label)}</td>`))
          + vals.map((v, k) => `<td class="num ${sub ? '' : 'dl-pivot__cell'}"${sub ? '' : heat(v)} data-p-cell="${esc(JSON.stringify([l.f0, l.f1, cols[k]]))}">${v ? esc(this.fmtV(v, this.st.pct ? tot : 0)) : '<span class="dl-pivot__zero">–</span>'}</td>`).join('')
          + `<td class="num is-total" data-p-cell="${esc(JSON.stringify([l.f0, l.f1, null]))}">${esc(this.fmtV(tot, grand))}</td></tr>`;
        this.lastTable.push([sub ? l.label : (l.f0 ?? ''), ...(r1k ? [sub ? '' : l.f1] : []), ...vals.map(v => v), tot]);
      });
      const colTot = cols.map(c => this.calc(r => String(r[this.st.c] ?? '') === c).v);
      h += `</tbody><tfoot><tr><td colspan="${r1k ? 2 : 1}">Grand Total</td>${colTot.map(v => `<td class="num">${esc(this.fmtV(v, grand))}</td>`).join('')}<td class="num is-total">${esc(this.fmtV(grand, grand))}</td></tr></tfoot>`;
      this.lastTable.push(['Grand Total', ...(r1k ? [''] : []), ...colTot, grand]);
      this.$s('[data-p-table]').innerHTML = h;
    }
  }
  Devlib.define("Pivot", Pivot);
})(window.Devlib);

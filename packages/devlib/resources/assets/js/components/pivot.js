/* DevLib · Pivot */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, val} = Devlib.h;
  /* ============================ PIVOT ============================ */
  class Pivot extends Devlib.Component {
    static selector = "[data-dl-pivot]";
    init() {
      const el = this.el;
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
  }
  Devlib.define("Pivot", Pivot);
})(window.Devlib);

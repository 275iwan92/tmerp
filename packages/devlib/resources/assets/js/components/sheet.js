/* DevLib · Sheet */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, fmt} = Devlib.h;
  /* ============================ SHEET (spreadsheet) ============================ */
  class Sheet extends Devlib.Component {
    static selector = "[data-dl-sheet]";
    init() {
      const el = this.el;
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
  }
  Devlib.define("Sheet", Sheet);
})(window.Devlib);

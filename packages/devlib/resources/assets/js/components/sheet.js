/* DevLib · Sheet */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, fmt, CASES, CASE_LABEL, applyCase} = Devlib.h;
  /* ============================ SHEET (spreadsheet) ============================ */
  class Sheet extends Devlib.Component {
    static selector = "[data-dl-sheet]";
    init() {
      const self = this;
      this.cfg = JSON.parse(this.el.dataset.config);
      this.cols = this.cfg.columns;
      this.wrap = $(this.el, '[data-s-wrap]');
      this.table = $(this.el, '[data-s-table]');
      this.data = JSON.parse(this.el.dataset.rows || '[]').map(r => ({...r}));
      this.orig = new Map(this.data.map(r => [String(r[this.cfg.rowKey]), {...r}]));
      this.undo = [];
      this.redo = [];
      this.cur = {r: 0, c: this.firstEditable()};
      this.anchor = null;
      this.edit = null;
      this.fill = null;
      this.drag = false;
      this.seq = 0;
      this.lookup = !!this.cfg.lookup;
      this.term = '';
      // Huruf aa/Aa/AA per kolom teks (sama dgn x-dl::field.text); kolom boleh set awal lewat 'case'.
      this.caseMode = this.cols.map(c => CASES.includes(c.case) ? c.case : 'none');
      const srch = $(this.el, '[data-s-search]');
      if (srch) srch.addEventListener('input', () => { this.term = srch.value.trim().toLowerCase(); this.render(); });
      this.wrap.addEventListener('keydown', e => {
        if (this.edit) {
          if (e.key === 'Escape') { e.preventDefault(); this.commit(true); }
          else if (e.key === 'Enter') { e.preventDefault(); this.commit(); this.move(1, 0); }
          else if (e.key === 'Tab') { e.preventDefault(); this.commit(); this.move(0, e.shiftKey ? -1 : 1); }
          return;
        }
        const ctrl = e.ctrlKey || e.metaKey;
        if (ctrl && e.key.toLowerCase() === 'z') { e.preventDefault(); const u = this.undo.pop(); if (u) this.redo.push(this.apply(u, true)); return this.render(); }
        if (ctrl && e.key.toLowerCase() === 'y') { e.preventDefault(); const u = this.redo.pop(); if (u) this.undo.push(this.apply(u, true)); return this.render(); }
        if (ctrl) return;  // Ctrl+C / Ctrl+V lewat event copy/paste
        const k = e.key;
        if (k === 'ArrowDown') { e.preventDefault(); this.move(1, 0, e.shiftKey); }
        else if (k === 'ArrowUp') { e.preventDefault(); this.move(-1, 0, e.shiftKey); }
        else if (k === 'ArrowRight') { e.preventDefault(); this.move(0, 1, e.shiftKey); }
        else if (k === 'ArrowLeft') { e.preventDefault(); this.move(0, -1, e.shiftKey); }
        else if (k === 'Tab') { e.preventDefault(); this.move(0, e.shiftKey ? -1 : 1); }
        else if (k === 'Enter' || k === 'F2') { e.preventDefault(); this.startEdit(); }
        else if (k === 'Delete' || k === 'Backspace') { e.preventDefault(); const R = this.rng(), list = []; for (let r = R.r0; r <= R.r1; r++) for (let c = R.c0; c <= R.c1; c++) if (!this.cols[c].readonly) list.push({r, k: this.cols[c].key, v: this.cols[c].type === 'check' ? false : ''}); if (list.length) this.apply(list); }
        else if (k === ' ' && this.cols[this.cur.c].type === 'check') { e.preventDefault(); this.startEdit(); }
        else if (k.length === 1 && !e.altKey) { e.preventDefault(); this.anchor = null; this.startEdit(k); }
      });
      // Salin rentang sebagai TSV (bisa ditempel ke Excel); tempel TSV dari Excel mulai sel aktif (baris baru dibuat bila perlu).
      this.wrap.addEventListener('copy', e => { if (this.edit) return; const R = this.rng(), lines = []; for (let r = R.r0; r <= R.r1; r++) { const row = []; for (let c = R.c0; c <= R.c1; c++) { const col = this.cols[c], v = this.data[r][col.key]; row.push(this.num(col) && typeof v === 'number' ? String(v).replace('.', ',') : col.type === 'select' ? (col.options?.[v] ?? v ?? '') : v ?? ''); } lines.push(row.join('\t')); } e.clipboardData.setData('text/plain', lines.join('\r\n')); e.preventDefault(); toast(`${(R.r1 - R.r0 + 1) * (R.c1 - R.c0 + 1)} sel disalin.`); });
      this.wrap.addEventListener('paste', e => {
        if (this.edit) return; e.preventDefault();
        const grid = (e.clipboardData.getData('text/plain') || '').replace(/\r/g, '').replace(/\n$/, '').split('\n').map(l => l.split('\t'));
        const list = [];
        grid.forEach((line, i) => { const r = this.cur.r + i; while (r >= this.data.length) this.data.push(this.blank()); line.forEach((v, j) => { const c = this.cur.c + j; if (c < this.cols.length && !this.cols[c].readonly) list.push({r, k: this.cols[c].key, v: this.parse(this.cols[c], v)}); }); });
        this.anchor = {...this.cur}; this.cur = {r: Math.min(this.data.length - 1, this.cur.r + grid.length - 1), c: Math.min(this.cols.length - 1, this.cur.c + (grid[0]?.length || 1) - 1)};
        this.apply(list); toast(`${list.length} sel ditempel.`, 'success');
      });
      // Mouse: klik = sel aktif, drag = rentang, pegangan isi = AutoFill ke bawah.
      this.table.addEventListener('mousedown', e => {
        const fh = e.target.closest('[data-s-fill]'); if (fh) { e.preventDefault(); this.fill = {...this.rng(), to: this.rng().r1}; return; }
        const rs = e.target.closest('[data-s-rowsel]'); if (rs) { this.anchor = {r: +rs.dataset.sRowsel, c: 0}; this.cur = {r: +rs.dataset.sRowsel, c: this.cols.length - 1}; this.render(); this.wrap.focus(); return; }
        const td = e.target.closest('td[data-r]'); if (!td || this.edit) return;
        if (e.shiftKey) { if (!this.anchor) this.anchor = {...this.cur}; } else this.anchor = null;
        this.cur = {r: +td.dataset.r, c: +td.dataset.c}; this.drag = true; this.render(); this.wrap.focus();
      });
      this.table.addEventListener('mouseover', e => {
        const td = e.target.closest('td[data-r]'); if (!td) return;
        if (this.fill) { this.fill.to = Math.max(this.fill.r1, +td.dataset.r); $$(this.table, '.is-filltgt').forEach(x => x.classList.remove('is-filltgt')); for (let r = this.fill.r1 + 1; r <= this.fill.to; r++) for (let c = this.fill.c0; c <= this.fill.c1; c++) this.cellEl(r, c)?.classList.add('is-filltgt'); return; }
        if (this.drag) { if (!this.anchor) this.anchor = {...this.cur}; this.cur = {r: +td.dataset.r, c: +td.dataset.c}; this.render(); }
      });
      document.addEventListener('mouseup', () => {
        this.drag = false; if (!this.fill) return;
        const F = this.fill; this.fill = null; if (F.to <= F.r1) return this.render();
        const list = [];
        for (let c = F.c0; c <= F.c1; c++) {
          const col = this.cols[c]; if (col.readonly) continue;
          const src = []; for (let r = F.r0; r <= F.r1; r++) src.push(this.data[r][col.key]);
          const step = this.num(col) && src.length >= 2 && src.every(v => typeof v === 'number') && src.every((v, i) => !i || v - src[i - 1] === src[1] - src[0]) ? src[1] - src[0] : null;
          for (let r = F.r1 + 1, i = 0; r <= F.to; r++, i++) list.push({r, k: col.key, v: step !== null ? src[src.length - 1] + step * (i + 1) : src[i % src.length]});
        }
        this.anchor = {r: F.r0, c: F.c0}; this.cur = {r: F.to, c: F.c1}; if (list.length) { this.apply(list); toast(`${list.length} sel diisi otomatis.`, 'success'); } else this.render();
      });
      this.table.addEventListener('dblclick', e => { const td = e.target.closest('td[data-r]'); if (td) { this.cur = {r: +td.dataset.r, c: +td.dataset.c}; this.anchor = null; this.startEdit(); } });
      this.wrap.addEventListener('focusout', e => { if (this.edit && !this.wrap.contains(e.relatedTarget)) this.commit(); });
      this.el.addEventListener('click', e => {
        if (e.target.closest('[data-s-add]')) { this.data.push(this.blank()); this.cur = {r: this.data.length - 1, c: this.firstEditable()}; this.anchor = null; this.render(); this.wrap.focus(); return; }
        if (e.target.closest('[data-s-revert]')) {
          if (!this.changes().length) return;
          confirmBox({message: 'Buang semua perubahan yang belum disimpan?', tone: 'danger'}).then(ok => { if (!ok) return; this.data = this.data.filter(r => this.orig.has(String(r[this.cfg.rowKey]))).map(r => ({...this.orig.get(String(r[this.cfg.rowKey]))})); this.cur = {r: 0, c: this.firstEditable()}; this.anchor = null; this.undo.length = 0; this.redo.length = 0; this.render(); });
          return;
        }
        if (e.target.closest('[data-s-del]')) { const R = this.rng(), n = R.r1 - R.r0 + 1; confirmBox({message: `Hapus ${n} baris terpilih?`, tone: 'danger'}).then(ok => { if (!ok) return; this.data.splice(R.r0, n); this.cur = {r: Math.max(0, Math.min(R.r0, this.data.length - 1)), c: this.cur.c}; this.anchor = null; this.undo.length = 0; this.render(); }); return; }
        if (e.target.closest('[data-s-undo]')) { const u = this.undo.pop(); if (u) this.redo.push(this.apply(u, true)); return; }
        if (e.target.closest('[data-s-redo]')) { const u = this.redo.pop(); if (u) this.undo.push(this.apply(u, true)); return; }
        if (e.target.closest('[data-s-save]')) {
          const nBad = this.data.reduce((t, r) => t + this.cols.filter(c => this.bad(c, r[c.key], r)).length, 0);
          if (nBad) { toast(`${nBad} sel belum benar (wajib / kembar / angka / pilihan).`, 'danger'); const b = $(this.table, 'td.is-bad'); if (b) { this.cur = {r: +b.dataset.r, c: +b.dataset.c}; this.anchor = null; this.render(); } return; }
          const changed = this.changes();
          this.el.dispatchEvent(new CustomEvent('dl:sheet-save', {bubbles: true, detail: {rows: this.data.map(r => ({...r})), changed}}));
          this.data.forEach(r => this.orig.set(String(r[this.cfg.rowKey]), {...r})); this.undo.length = 0; this.redo.length = 0;
          toast(`${changed.length} baris disimpan.`, 'success'); this.render();
        }
      });
      this.el.dlSheet = {get data() { return self.data; }, render: this.render, apply: this.apply, get cur() { return self.cur; }, set cur(v) { self.cur = v; self.anchor = null; self.render(); }};
      this.render();
    }
    firstEditable() { const i = this.cols.findIndex(c => !c.readonly); return i < 0 ? 0 : i; }

    num(c) {
      return c.type === 'number' || c.type === 'money';
    }

    isText(c) { return c.type === 'text'; }

    // decimals = jumlah digit di belakang koma yang selalu tampil (18.450,00); tanpa decimals = apa adanya.
    numText(c, v) {
      return c.decimals != null ? v.toLocaleString('id-ID', {minimumFractionDigits: c.decimals, maximumFractionDigits: c.decimals}) : v.toLocaleString('id-ID', {maximumFractionDigits: 10});
    }

    parse(c, raw) {
      const s = String(raw ?? '').trim();
      if (c.type === 'check') return /^(1|true|ya|y|x|✓)$/i.test(s);
      if (this.num(c)) { if (s === '') return ''; const t = s.replace(/\s|Rp/gi, ''); const n = t.includes(',') ? +t.replace(/\./g, '').replace(',', '.') : (/^-?\d{1,3}(\.\d{3})+$/.test(t) ? +t.replace(/\./g, '') : +t); return isNaN(n) ? s : c.decimals != null ? Math.round(n * 10 ** c.decimals) / 10 ** c.decimals : n; }
      if (c.type === 'select' && c.options) { const hit = Object.entries(c.options).find(([k, l]) => k.toLowerCase() === s.toLowerCase() || String(l).toLowerCase() === s.toLowerCase()); return hit ? hit[0] : s; }
      return s;
    }

    show(c, v) {
      return c.type === 'check' ? (v ? '✓' : '') : this.num(c) ? (v === '' || v == null ? '' : typeof v === 'number' ? (c.type === 'money' ? 'Rp ' : '') + this.numText(c, v) : String(v)) : c.type === 'select' ? (c.options?.[v] ?? v ?? '') : c.type === 'date' && v ? fmt.date(v + 'T12:00:00+07:00') : (v ?? '');
    }

    // unique: nilai tidak boleh kembar di baris lain (tanpa beda huruf besar/kecil) -- validasi final tetap di server.
    bad(c, v, row) {
      if (c.unique && row && v !== '' && v != null && this.data.some(o => o !== row && String(o[c.key] ?? '').trim().toLowerCase() === String(v).trim().toLowerCase())) return true;
      return (c.required && (v === '' || v == null)) || (this.num(c) && v !== '' && v != null && typeof v !== 'number') || (c.type === 'select' && v && c.options && !(v in c.options));
    }

    dirty(r, c) { const o = this.orig.get(String(r[this.cfg.rowKey])); return !o || String(o[c.key] ?? '') !== String(r[c.key] ?? ''); }
    rng() { const a = this.anchor || this.cur; return {r0: Math.min(a.r, this.cur.r), r1: Math.max(a.r, this.cur.r), c0: Math.min(a.c, this.cur.c), c1: Math.max(a.c, this.cur.c)}; }

    render() {
      const R = this.rng();
      this.table.innerHTML = `<thead><tr><th class="dl-sheet__no">#</th>${this.cols.map(c => `<th class="${this.num(c) ? 'num' : ''}" ${c.width ? `style="width:${esc(c.width)}"` : ''}>${esc(c.label)}${c.required ? ' <span class="dl-field__req">*</span>' : ''}</th>`).join('')}</tr></thead><tbody>`
        + this.data.map((r, i) => this.lookup && this.term && !this.cols.some(c => String(this.show(c, r[c.key])).toLowerCase().includes(this.term)) ? '' : `<tr class=""${this.orig.has(String(r[this.cfg.rowKey])) ? '' : 'is-new'}"><td class="dl-sheet__no" data-s-rowsel="${i}">${i + 1}</td>${this.cols.map((c, j) => { const v = r[c.key], inR = i >= R.r0 && i <= R.r1 && j >= R.c0 && j <= R.c1; return `<td data-r="${i}" data-c="${j}" class="${this.num(c) ? 'num' : ''} ${c.readonly ? 'is-ro' : ''} ${inR ? 'is-sel' : ''} ${i === this.cur.r && j === this.cur.c ? 'is-cur' : ''} ${this.dirty(r, c) ? 'is-dirty' : ''} ${this.bad(c, v, r) ? 'is-bad' : ''}">${this.lookup && c.type === 'check' ? `<span class="dl-sheet__sw ${v ? 'is-on' : ''}"></span>` : esc(this.show(c, v))}${i === R.r1 && j === R.c1 && !this.edit ? '<span class="dl-sheet__fill" data-s-fill title="Tarik untuk mengisi ke bawah"></span>' : ''}</td>`; }).join('')}</tr>`).join('') + '</tbody>';
      const changed = this.changes(), info = $(this.el, '[data-s-info]'), sv = $(this.el, '[data-s-save]');
      sv.disabled = !changed.length;
      if (this.lookup) {
        info.textContent = changed.length ? `${changed.length} perubahan belum disimpan` : `${this.data.length} baris`; info.classList.toggle('is-warn', !!changed.length);
        const rv = $(this.el, '[data-s-revert]'); if (rv) rv.disabled = !changed.length;
      } else {
        info.textContent = `${this.data.length} baris · ${changed.length} berubah`;
        sv.lastChild.textContent = changed.length ? `Simpan Semua (${changed.length})` : 'Simpan Semua';
        $(this.el, '[data-s-undo]').disabled = !this.undo.length; $(this.el, '[data-s-redo]').disabled = !this.redo.length;
      }
      const td = this.cellEl(this.cur.r, this.cur.c); if (td) td.scrollIntoView({block: 'nearest', inline: 'nearest'});
    }

    changes() {
      return this.data.filter(r => !this.orig.has(String(r[this.cfg.rowKey])) || this.cols.some(c => this.dirty(r, c)));
    }

    cellEl(r, c) {
      return $(this.table, `td[data-r="${r}"][data-c="${c}"]`);
    }

    apply(list, noUndo) {
      const back = list.map(({r, k, v}) => ({r, k, v: this.data[r][k]}));
      list.forEach(({r, k, v}) => { this.data[r][k] = v; });
      if (!noUndo) { this.undo.push(back); this.redo.length = 0; }
      this.render(); this.el.dispatchEvent(new CustomEvent('dl:sheet-change', {bubbles: true, detail: {changes: list}}));
      return back;
    }

    move(dr, dc, extend) { if (extend && !this.anchor) this.anchor = {...this.cur}; if (!extend) this.anchor = null; this.cur = {r: Math.max(0, Math.min(this.data.length - 1, this.cur.r + dr)), c: Math.max(0, Math.min(this.cols.length - 1, this.cur.c + dc))}; this.render(); }

    startEdit(initial) {
      const c = this.cols[this.cur.c]; if (c.readonly) return;
      if (c.type === 'check') { this.apply([{r: this.cur.r, k: c.key, v: !this.data[this.cur.r][c.key]}]); return; }
      const td = this.cellEl(this.cur.r, this.cur.c); if (!td) return;
      this.edit = {r: this.cur.r, c: this.cur.c};
      const v = this.data[this.cur.r][c.key];
      if (c.type === 'select') td.innerHTML = `<select class="dl-sheet__in">${['', ...Object.keys(c.options || {})].map(k => `<option value="${esc(k)}" ${String(k) === String(v ?? '') ? 'selected' : ''}>${esc(k ? c.options[k] : '—')}</option>`).join('')}</select>`;
      else {
        const ci = this.cur.c, mode = this.caseMode[ci], start = initial ?? (this.num(c) && typeof v === 'number' ? String(v).replace('.', ',') : v ?? '');
        td.innerHTML = `<input class="dl-sheet__in ${this.isText(c) ? 'has-case' : ''}" type="${c.type === 'date' ? 'date' : 'text'}" ${this.num(c) ? 'inputmode="decimal"' : ''} value="${esc(this.isText(c) ? applyCase(String(start), mode) : start)}">`
          + (this.isText(c) ? `<button type="button" class="dl-case dl-sheet__case" data-s-case title="Ganti huruf: kecil / Kapital Tiap Kata / BESAR">${CASE_LABEL[mode]}</button>` : '');
        const inp = $(td, '.dl-sheet__in');
        if (this.isText(c)) {
          inp.addEventListener('input', () => { const p = inp.selectionStart; inp.value = applyCase(inp.value, this.caseMode[ci]); try { inp.setSelectionRange(p, p); } catch (e) {} });
          const btn = $(td, '[data-s-case]');
          btn.addEventListener('mousedown', e => e.preventDefault());  // input tetap fokus -> tidak commit
          btn.addEventListener('click', () => { const m = CASES[(CASES.indexOf(this.caseMode[ci]) + 1) % 3]; this.caseMode[ci] = m; btn.textContent = CASE_LABEL[m]; inp.value = applyCase(inp.value, m); inp.focus(); });
        }
      }
      const inp = $(td, '.dl-sheet__in'); inp.focus(); if (inp.type === 'text') inp.setSelectionRange(inp.value.length, inp.value.length);  // seperti Excel: kursor di akhir isi
      if (c.type === 'select') inp.addEventListener('change', () => this.commit());
    }

    commit(cancel) {
      if (!this.edit) return; const {r, c} = this.edit, inp = $(this.cellEl(r, c) || document, '.dl-sheet__in'); this.edit = null;
      if (!cancel && inp) { const col = this.cols[c], v = this.parse(col, inp.value); if (String(v) !== String(this.data[r][col.key] ?? '')) { this.apply([{r, k: col.key, v}]); this.wrap.focus(); return; } }
      this.render(); this.wrap.focus();
    }

    blank() { const r = {[this.cfg.rowKey]: 'new' + (++this.seq)}; this.cols.forEach(c => { r[c.key] = c.type === 'check' ? false : ''; }); return r; }
  }
  Devlib.define("Sheet", Sheet);
})(window.Devlib);

/* DevLib · Sheet */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, fmt, CASES, CASE_LABEL, applyCase, numStr, NUM, DlDate, DlTime, T, Tf} = Devlib.h;
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
        if (e.altKey && k === 'ArrowDown' && this.isPick(this.cols[this.cur.c]) && !this.ro(this.cols[this.cur.c], this.data[this.cur.r])) { e.preventDefault(); return this.openPick(); }
        if (k === 'ArrowDown') { e.preventDefault(); this.move(1, 0, e.shiftKey); }
        else if (k === 'ArrowUp') { e.preventDefault(); this.move(-1, 0, e.shiftKey); }
        else if (k === 'ArrowRight') { e.preventDefault(); this.move(0, 1, e.shiftKey); }
        else if (k === 'ArrowLeft') { e.preventDefault(); this.move(0, -1, e.shiftKey); }
        else if (k === 'Tab') { e.preventDefault(); this.move(0, e.shiftKey ? -1 : 1); }
        else if (k === 'Enter' || k === 'F2') { e.preventDefault(); this.startEdit(); }
        else if (k === 'Delete' || k === 'Backspace') { e.preventDefault(); const R = this.rng(), list = []; for (let r = R.r0; r <= R.r1; r++) for (let c = R.c0; c <= R.c1; c++) if (!this.ro(this.cols[c], this.data[r])) list.push({r, k: this.cols[c].key, v: this.cols[c].type === 'check' ? false : ''}); if (list.length) this.apply(list); }
        else if (k === ' ' && this.cols[this.cur.c].type === 'check') { e.preventDefault(); this.startEdit(); }
        else if (k.length === 1 && !e.altKey) { e.preventDefault(); this.anchor = null; this.startEdit(k); }
      });
      // Salin rentang sebagai TSV (bisa ditempel ke Excel); tempel TSV dari Excel mulai sel aktif (baris baru dibuat bila perlu).
      this.wrap.addEventListener('copy', e => { if (this.edit) return; const R = this.rng(), lines = []; for (let r = R.r0; r <= R.r1; r++) { const row = []; for (let c = R.c0; c <= R.c1; c++) { const col = this.cols[c], v = this.data[r][col.key]; row.push(this.num(col) && typeof v === 'number' ? fmt.raw(v) : col.type === 'select' ? (col.options?.[v] ?? v ?? '') : v ?? ''); } lines.push(row.join('\t')); } e.clipboardData.setData('text/plain', lines.join('\r\n')); e.preventDefault(); toast(Tf('s_copied', {n: (R.r1 - R.r0 + 1) * (R.c1 - R.c0 + 1)})); });
      this.wrap.addEventListener('paste', e => {
        if (this.edit) return; e.preventDefault();
        const grid = (e.clipboardData.getData('text/plain') || '').replace(/\r/g, '').replace(/\n$/, '').split('\n').map(l => l.split('\t'));
        const list = [];
        grid.forEach((line, i) => { const r = this.cur.r + i; while (r >= this.data.length) this.data.push(this.blank()); line.forEach((v, j) => { const c = this.cur.c + j; if (c < this.cols.length && !this.ro(this.cols[c], this.data[r])) list.push({r, k: this.cols[c].key, v: this.parse(this.cols[c], v)}); }); });
        this.anchor = {...this.cur}; this.cur = {r: Math.min(this.data.length - 1, this.cur.r + grid.length - 1), c: Math.min(this.cols.length - 1, this.cur.c + (grid[0]?.length || 1) - 1)};
        this.apply(list); toast(Tf('s_pasted', {n: list.length}), 'success');
      });
      // Mouse: klik = sel aktif, drag = rentang, pegangan isi = AutoFill ke bawah.
      this.table.addEventListener('mousedown', e => {
        const cal = e.target.closest('[data-s-cal]'); if (cal) { e.preventDefault(); const td = cal.closest('td'); this.anchor = null; this.cur = {r: +td.dataset.r, c: +td.dataset.c}; this.render(); this.wrap.focus(); this.openPick(); return; }
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
          for (let r = F.r1 + 1, i = 0; r <= F.to; r++, i++) if (!this.ro(col, this.data[r])) list.push({r, k: col.key, v: step !== null ? src[src.length - 1] + step * (i + 1) : src[i % src.length]});
        }
        this.anchor = {r: F.r0, c: F.c0}; this.cur = {r: F.to, c: F.c1}; if (list.length) { this.apply(list); toast(Tf('s_filled', {n: list.length}), 'success'); } else this.render();
      });
      this.table.addEventListener('dblclick', e => { const td = e.target.closest('td[data-r]'); if (td) { this.cur = {r: +td.dataset.r, c: +td.dataset.c}; this.anchor = null; this.startEdit(); } });
      this.wrap.addEventListener('focusout', e => { if (this.edit && !this.wrap.contains(e.relatedTarget)) this.commit(); });
      this.el.addEventListener('click', e => {
        if (e.target.closest('[data-s-add]')) { this.data.push(this.blank()); this.cur = {r: this.data.length - 1, c: this.firstEditable()}; this.anchor = null; this.render(); this.wrap.focus(); return; }
        if (e.target.closest('[data-s-revert]')) {
          if (!this.changes().length) return;
          confirmBox({message: T('f_discard_all'), tone: 'danger'}).then(ok => { if (!ok) return; this.data = this.data.filter(r => this.orig.has(String(r[this.cfg.rowKey]))).map(r => ({...this.orig.get(String(r[this.cfg.rowKey]))})); this.cur = {r: 0, c: this.firstEditable()}; this.anchor = null; this.undo.length = 0; this.redo.length = 0; this.render(); });
          return;
        }
        if (e.target.closest('[data-s-del]')) { const R = this.rng(), n = R.r1 - R.r0 + 1; confirmBox({message: Tf('s_delete_n', {n}), tone: 'danger'}).then(ok => { if (!ok) return; this.data.splice(R.r0, n); this.cur = {r: Math.max(0, Math.min(R.r0, this.data.length - 1)), c: this.cur.c}; this.anchor = null; this.undo.length = 0; this.render(); }); return; }
        if (e.target.closest('[data-s-undo]')) { const u = this.undo.pop(); if (u) this.redo.push(this.apply(u, true)); return; }
        if (e.target.closest('[data-s-redo]')) { const u = this.redo.pop(); if (u) this.undo.push(this.apply(u, true)); return; }
        if (e.target.closest('[data-s-save]')) {
          const nBad = this.data.reduce((t, r) => t + this.cols.filter(c => this.bad(c, r[c.key], r)).length, 0);
          if (nBad) { toast(Tf('s_bad', {n: nBad}), 'danger'); const b = $(this.table, 'td.is-bad'); if (b) { this.cur = {r: +b.dataset.r, c: +b.dataset.c}; this.anchor = null; this.render(); } return; }
          const changed = this.changes();
          if (this.cfg.saveUrl) return this.saveServer(changed);
          this.el.dispatchEvent(new CustomEvent('dl:sheet-save', {bubbles: true, detail: {rows: this.data.map(r => ({...r})), changed}}));
          this.data.forEach(r => this.orig.set(String(r[this.cfg.rowKey]), {...r})); this.undo.length = 0; this.redo.length = 0;
          toast(Tf('s_saved', {n: changed.length}), 'success'); this.render();
        }
      });
      this.el.dlSheet = {get data() { return self.data; }, render: this.render, apply: this.apply, get cur() { return self.cur; }, set cur(v) { self.cur = v; self.anchor = null; self.render(); }};
      this.render();
    }
    isPick(c) { return c.type === 'date' || c.type === 'time' || this.num(c); }

    // Pemilih kecil utk sel aktif (tampilan sama x-dl::field.date / field.time): date = kalender, time = Jam + Menit (5 menit).
    // Panel fixed di body supaya tidak terpotong scroll lembar.
    openPick() {
      this.closePick();
      const {r, c} = this.cur, col = this.cols[c], td = this.cellEl(r, c); if (!td) return;
      const cur = this.data[r][col.key], pad = n => String(n).padStart(2, '0'), p = document.createElement('div');
      const pick = v => { this.closePick(); if (String(v) !== String(cur ?? '')) this.apply([{r, k: col.key, v}]); this.wrap.focus(); };
      if (this.num(col)) {
        // Kalkulator mini: + - x / saja, hitung kiri-ke-kanan dgn prioritas x / ; '=' / Enter = hasil masuk ke sel.
        let ex = typeof cur === 'number' ? fmt.raw(cur) : '';  // desimal ikut bahasa (NUM.dec)
        const calc = () => {
          const t = ex.split(NUM.dec).join('.').match(/(\d+\.?\d*|\.\d+|[-+*/])/g) || []; if (!t.length) return null;
          const nums = [], ops = []; let neg = false;
          for (const x of t) { if ('+-*/'.includes(x) && nums.length === ops.length) { if (x === '-') { neg = !neg; continue; } return null; } if ('+-*/'.includes(x)) ops.push(x); else { nums.push(neg ? -x : +x); neg = false; } }
          if (nums.length === ops.length) ops.pop();  // operator menggantung di akhir diabaikan
          const a = [nums[0]], o = [];
          ops.forEach((op, i) => { const n = nums[i + 1]; if (n == null) return; if (op === '*') a.push(a.pop() * n); else if (op === '/') a.push(a.pop() / n); else { o.push(op); a.push(n); } });
          const v = o.reduce((s, op, i) => op === '+' ? s + a[i + 1] : s - a[i + 1], a[0]);
          return isFinite(v) ? Math.round(v * 1e10) / 1e10 : null;
        };
        const show = () => { $(p, '[data-e]').textContent = ex.replace(/\*/g, '×').replace(/\//g, '÷').replace(/-/g, '−') || '0'; const v = calc(); $(p, '[data-r]').textContent = v == null ? (ex ? '–' : '') : '= ' + fmt.num(v, col.decimals ?? 10); };
        const key = k => {
          if (k === 'C') ex = ''; else if (k === '⌫') ex = ex.slice(0, -1);
          else if (k === '=') { const v = calc(); if (v == null) return toast(T('s_calc_bad'), 'danger'); return pick(col.decimals != null ? Math.round(v * 10 ** col.decimals) / 10 ** col.decimals : v); }
          else if ('+-*/'.includes(k)) ex = /[-+*/]$/.test(ex) && k !== '-' ? ex.slice(0, -1) + k : ex + k;
          else if (k === NUM.dec) { const seg = ex.split(/[-+*/]/).pop(); if (!seg.includes(NUM.dec)) ex += (seg ? '' : '0') + NUM.dec; }
          else ex += k;
          show();
        };
        p.className = 'dl-pick__panel is-open dl-sheet__calpanel dl-calc'; p.tabIndex = -1;
        p.innerHTML = '<div class="dl-calc__screen"><div class="dl-calc__expr" data-e></div><div class="dl-calc__res" data-r></div></div><div class="dl-calc__keys">'
          + ['C', '⌫', '/', '*', '7', '8', '9', '-', '4', '5', '6', '+', '1', '2', '3', '=', '0', NUM.dec].map(k => `<button type="button" data-k="${k}" class="${'+-*/'.includes(k) ? 'is-op' : k === '=' ? 'is-eq' : k === '0' ? 'is-zero' : ''}" ${k === '=' ? 'title="' + esc(T('s_calc_enter')) + '"' : ''}>${{'/': '÷', '*': '×', '-': '−'}[k] ?? k}</button>`).join('') + '</div>';
        p.addEventListener('click', e => { const b = e.target.closest('[data-k]'); if (b) key(b.dataset.k); });
        p.addEventListener('keydown', e => {
          const k = e.key === 'Enter' ? '=' : e.key === 'Backspace' ? '⌫' : e.key === 'Delete' || e.key === 'c' ? 'C' : e.key === '.' || e.key === ',' ? NUM.dec : e.key === 'x' ? '*' : e.key;
          if (/^[0-9,+\-*/=C⌫]$/.test(k)) { e.preventDefault(); e.stopPropagation(); key(k); }
        });
        show();
      } else if (col.type === 'time') {
        const [h0, m0] = /^\d{2}:\d{2}$/.test(cur || '') ? cur.split(':').map(Number) : [8, 0];
        p.className = 'dl-pick__panel dl-pick__panel--time is-open dl-sheet__calpanel';
        p.innerHTML = `<div class="dl-pick__head">${esc(T('s_pick_time'))}</div><div class="dl-pick__time"><label>${esc(T('s_hour'))} <select data-h>${Array.from({length: 24}, (_, i) => `<option ${i === h0 ? 'selected' : ''}>${pad(i)}</option>`).join('')}</select></label><b>:</b><label>${esc(T('s_minute'))} <select data-m>${Array.from({length: 12}, (_, i) => `<option ${i * 5 === Math.round(m0 / 5) * 5 % 60 ? 'selected' : ''}>${pad(i * 5)}</option>`).join('')}</select></label></div><div class="dl-pick__foot"><button type="button" class="dl-pick__sec" data-x>Clear</button><button type="button" class="dl-pick__pri" data-a>Apply</button></div>`;
        p.addEventListener('click', e => { const b = e.target.closest('button'); if (!b) return; if ('x' in b.dataset) pick(''); else if ('a' in b.dataset) pick(`${$(p, '[data-h]').value}:${$(p, '[data-m]').value}`); });
        p.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); $(p, '[data-a]').click(); } });
      } else {
        const MON = NUM.months;
        const iso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        const view = /^\d{4}-\d{2}-\d{2}$/.test(cur || '') ? new Date(cur + 'T00:00') : new Date(); view.setDate(1);
        p.className = 'dl-pick__panel is-open dl-sheet__calpanel';
        p.innerHTML = '<div class="dl-pick__nav"><button type="button" data-p>&lt;</button><span data-t></span><button type="button" data-n>&gt;</button></div><div class="dl-cal dl-cal--names">' + Devlib.h.dow2().map(d => '<div>' + d + '</div>').join('') + '</div><div class="dl-cal" data-d></div><div class="dl-pick__foot"><button type="button" class="dl-pick__sec" data-x>Clear</button><button type="button" class="dl-pick__pri" data-h>' + esc(T('today')) + '</button></div>';
        const draw = () => {
          $(p, '[data-t]').textContent = `${MON[view.getMonth()]} ${view.getFullYear()}`;
          let h = '<div></div>'.repeat(view.getDay()), n = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
          for (let d = 1; d <= n; d++) { const v = iso(new Date(view.getFullYear(), view.getMonth(), d)); h += `<button type="button" data-v="${v}" class="${v === cur ? 'is-sel' : ''}">${d}</button>`; }
          $(p, '[data-d]').innerHTML = h;
        };
        p.addEventListener('click', e => {
          const b = e.target.closest('button'); if (!b) return;
          if (b.dataset.v) pick(b.dataset.v); else if ('p' in b.dataset) { view.setMonth(view.getMonth() - 1); draw(); } else if ('n' in b.dataset) { view.setMonth(view.getMonth() + 1); draw(); } else if ('x' in b.dataset) pick(''); else if ('h' in b.dataset) pick(iso(new Date()));
        });
        draw();
      }
      p.addEventListener('mousedown', e => { if (!e.target.closest('select')) e.preventDefault(); });  // select tetap bisa dibuka
      document.body.appendChild(p);
      const b = td.getBoundingClientRect(), below = b.bottom + 4 + p.offsetHeight <= innerHeight;
      Object.assign(p.style, {position: 'fixed', left: Math.min(b.left, innerWidth - p.offsetWidth - 8) + 'px', top: (below ? b.bottom + 4 : b.top - p.offsetHeight - 4) + 'px'});
      this.pick = p;
      if (col.type === 'time') $(p, '[data-h]').focus(); else if (this.num(col)) p.focus();
      this.pickOff = e => { if (e.type === 'keydown' ? e.key === 'Escape' || !p.contains(e.target) : !p.contains(e.target)) { this.closePick(); if (e.type === 'keydown') this.wrap.focus(); } };
      ['mousedown', 'keydown', 'scroll'].forEach(t => document.addEventListener(t, this.pickOff, true));
    }

    closePick() {
      if (!this.pick) return; this.pick.remove(); this.pick = null;
      ['mousedown', 'keydown', 'scroll'].forEach(t => document.removeEventListener(t, this.pickOff, true));
    }

    // readonly = kolom hanya-baca; lockSaved = kolom kunci (kode) yang hanya bisa diisi di baris BARU -- baris yang sudah
    // tersimpan terkunci, karena kodenya sudah dirujuk data lain.
    ro(c, row) { return !!c.readonly || (!!c.lockSaved && !!row && this.orig.has(String(row[this.cfg.rowKey]))); }

    firstEditable() { const i = this.cols.findIndex(c => !c.readonly); return i < 0 ? 0 : i; }

    num(c) {
      return c.type === 'number' || c.type === 'money';
    }

    isText(c) { return c.type === 'text'; }

    // decimals = jumlah digit di belakang koma yang selalu tampil (18.450,00); tanpa decimals = apa adanya.
    numText(c, v) {
      return c.decimals != null ? fmt.number(v, c.decimals) : fmt.num(v, 10);
    }

    // Tanggal diketik ala sel Excel -> ISO; satu objek tanggal DevLib (DlDate) untuk ketik & tampil.
    pDate(s) {
      return DlDate.of(s)?.iso() ?? null;
    }

    // Jam ala Excel (8 / 830 / 08.30) -> 08:30 lewat objek jam DevLib (DlTime).
    pTime(s) {
      return DlTime.of(s)?.display() ?? null;
    }

    parse(c, raw) {
      const s = String(raw ?? '').trim();
      if (c.type === 'check') return /^(1|true|ya|y|x|✓)$/i.test(s);
      if (c.type === 'date') return s === '' ? '' : this.pDate(s) ?? s;
      if (c.type === 'time') return s === '' ? '' : this.pTime(s) ?? s;
      if (this.num(c)) { if (s === '') return ''; const c0 = numStr(s), n = c0 === '' ? NaN : +c0; return isNaN(n) ? s : c.decimals != null ? Math.round(n * 10 ** c.decimals) / 10 ** c.decimals : n; }
      if (c.type === 'select' && c.options) { const hit = Object.entries(c.options).find(([k, l]) => k.toLowerCase() === s.toLowerCase() || String(l).toLowerCase() === s.toLowerCase()); return hit ? hit[0] : s; }
      return s;
    }

    show(c, v) {
      return c.type === 'check' ? (v ? '✓' : '') : this.num(c) ? (v === '' || v == null ? '' : typeof v === 'number' ? (c.type === 'money' ? 'Rp ' : '') + this.numText(c, v) : String(v)) : c.type === 'select' ? (c.options?.[v] ?? v ?? '') : c.type === 'date' ? fmt.gridDate(v) : (v ?? '');
    }

    // unique: nilai tidak boleh kembar di baris lain (tanpa beda huruf besar/kecil) -- validasi final tetap di server.
    bad(c, v, row) {
      if (c.unique && row && v !== '' && v != null && this.data.some(o => o !== row && String(o[c.key] ?? '').trim().toLowerCase() === String(v).trim().toLowerCase())) return true;
      if (v && ((c.type === 'date' && !/^\d{4}-\d{2}-\d{2}$/.test(v)) || (c.type === 'time' && !/^\d{2}:\d{2}$/.test(v)))) return true;
      return (c.required && (v === '' || v == null)) || (this.num(c) && v !== '' && v != null && typeof v !== 'number') || (c.type === 'select' && v && c.options && !(v in c.options));
    }

    dirty(r, c) { const o = this.orig.get(String(r[this.cfg.rowKey])); return !o || String(o[c.key] ?? '') !== String(r[c.key] ?? ''); }
    rng() { const a = this.anchor || this.cur; return {r0: Math.min(a.r, this.cur.r), r1: Math.max(a.r, this.cur.r), c0: Math.min(a.c, this.cur.c), c1: Math.max(a.c, this.cur.c)}; }

    render() {
      const R = this.rng();
      this.table.innerHTML = `<thead><tr><th class="dl-sheet__no">#</th>${this.cols.map(c => `<th class="${this.num(c) ? 'num' : ''}" ${c.width ? `style="width:${esc(c.width)}"` : ''}>${esc(c.label)}${c.required ? ' <span class="dl-field__req">*</span>' : ''}</th>`).join('')}</tr></thead><tbody>`
        + this.data.map((r, i) => this.lookup && this.term && !this.cols.some(c => String(this.show(c, r[c.key])).toLowerCase().includes(this.term)) ? '' : `<tr class=""${this.orig.has(String(r[this.cfg.rowKey])) ? '' : 'is-new'}"><td class="dl-sheet__no" data-s-rowsel="${i}">${i + 1}</td>${this.cols.map((c, j) => { const v = r[c.key], inR = i >= R.r0 && i <= R.r1 && j >= R.c0 && j <= R.c1; return `<td data-r="${i}" data-c="${j}" class="${this.num(c) ? 'num' : ''} ${this.isPick(c) ? 'is-pick' : ''} ${this.ro(c, r) ? 'is-ro' : ''} ${inR ? 'is-sel' : ''} ${i === this.cur.r && j === this.cur.c ? 'is-cur' : ''} ${this.dirty(r, c) ? 'is-dirty' : ''} ${this.bad(c, v, r) ? 'is-bad' : ''} ${c.emptyWarn && (v === '' || v == null) ? 'is-empty' : ''}">${this.lookup && c.type === 'check' ? `<span class="dl-sheet__sw ${v ? 'is-on' : ''}"></span>` : esc(this.show(c, v))}${this.isPick(c) && !this.ro(c, r) ? `<button type="button" class="dl-sheet__cal" data-s-cal tabindex="-1" title="${esc(T(c.type === 'date' ? 's_tip_date' : c.type === 'time' ? 's_tip_time' : 's_tip_calc'))}"><i class="${c.type === 'date' ? 'fa-regular fa-calendar' : c.type === 'time' ? 'fa-regular fa-clock' : 'fa-solid fa-calculator'}"></i></button>` : ''}${i === R.r1 && j === R.c1 && !this.edit ? '<span class="dl-sheet__fill" data-s-fill title="' + esc(T('s_fill_tip')) + '"></span>' : ''}</td>`; }).join('')}</tr>`).join('') + '</tbody>';
      const changed = this.changes(), info = $(this.el, '[data-s-info]'), sv = $(this.el, '[data-s-save]');
      sv.disabled = !changed.length;
      if (this.lookup) {
        info.textContent = changed.length ? Tf('s_unsaved', {n: changed.length}) : Tf('g_rows_n', {n: this.data.length}); info.classList.toggle('is-warn', !!changed.length);
        const rv = $(this.el, '[data-s-revert]'); if (rv) rv.disabled = !changed.length;
      } else {
        info.textContent = Tf('s_rows_changed', {n: this.data.length, c: changed.length});
        sv.lastChild.textContent = changed.length ? `Save All (${changed.length})` : 'Save All';
        $(this.el, '[data-s-undo]').disabled = !this.undo.length; $(this.el, '[data-s-redo]').disabled = !this.redo.length;
      }
      const td = this.cellEl(this.cur.r, this.cur.c); if (td) td.scrollIntoView({block: 'nearest', inline: 'nearest'});
    }

    // Simpan ke database: server memvalidasi & menulis, lalu mengirim isi terbaru (baris baru mendapat id asli).
    saveServer(changed) {
      if (!changed.length) return toast(T('s_no_changes'));
      fetch(this.cfg.saveUrl, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.cfg.csrf || ''},
        body: JSON.stringify({changed})})
        .then(res => res.json().then(j => ({ok: res.ok, j})))
        .then(({ok, j}) => {
          if (!ok || j.error) return toast(j.error || j.message || T('s_save_failed'), 'danger');
          this.data = j.rows; this.orig = new Map(this.data.map(r => [String(r[this.cfg.rowKey]), {...r}])); this.undo.length = 0; this.redo.length = 0;
          this.el.dispatchEvent(new CustomEvent('dl:sheet-save', {bubbles: true, detail: {rows: this.data.map(r => ({...r})), changed}}));
          toast(Tf('s_saved', {n: changed.length}), 'success'); this.render();
        })
        .catch(() => toast(T('s_save_failed'), 'danger'));
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
      const c = this.cols[this.cur.c]; if (this.ro(c, this.data[this.cur.r])) return;
      if (c.type === 'check') { this.apply([{r: this.cur.r, k: c.key, v: !this.data[this.cur.r][c.key]}]); return; }
      const td = this.cellEl(this.cur.r, this.cur.c); if (!td) return;
      this.edit = {r: this.cur.r, c: this.cur.c};
      const v = this.data[this.cur.r][c.key];
      if (c.type === 'select') td.innerHTML = `<select class="dl-sheet__in">${['', ...Object.keys(c.options || {})].map(k => `<option value="${esc(k)}" ${String(k) === String(v ?? '') ? 'selected' : ''}>${esc(k ? c.options[k] : '—')}</option>`).join('')}</select>`;
      else {
        const ci = this.cur.c, mode = this.caseMode[ci], start = initial ?? (this.num(c) && typeof v === 'number' ? fmt.raw(v) : c.type === 'date' ? fmt.gridDate(v) : v ?? '');
        td.innerHTML = `<input class="dl-sheet__in ${this.isText(c) ? 'has-case' : ''}" type="text" ${this.num(c) ? 'inputmode="decimal"' : ''} ${c.type === 'date' ? 'placeholder="dd-mm-yyyy"' : c.type === 'time' ? 'placeholder="hh:mm" inputmode="numeric"' : ''} value="${esc(this.isText(c) ? applyCase(String(start), mode) : start)}">`
          + (this.isText(c) ? `<button type="button" class="dl-case dl-sheet__case" data-s-case title="${esc(T('case_tip'))}">${CASE_LABEL[mode]}</button>` : '');
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

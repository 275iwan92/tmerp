/* DevLib · Grid */
(function (Devlib) {
  'use strict';
  const {srcAttr, $, $$, esc, toast, fmt, at, val, emit, parseNum, DlDateTime, DlTime, T, Tf} = Devlib.h;
  /* ============================ GRID ============================ */
  // Mesin grid sisi-browser (DevLib, tanpa DB). Di tmerp, versi server (Livewire) memakai
  // markup & kelas yang sama; hanya sumber data & query-nya yang berpindah ke server.
  class Grid extends Devlib.Component {
    static selector = "[data-dl-grid]";
    init() {
      this.cfg = JSON.parse(this.el.dataset.config);
      this.rows = JSON.parse(this.el.dataset.rows || '[]');
      this.cols = this.cfg.columns;
      this.hasExp = this.cfg.expand.length > 0 || !!this.cfg.detail;
      // report = Grid for Reporting: rapat, tanpa centang/Aksi, saring per kolom di kepala tabel.
      this.report = this.cfg.mode === 'report';
      // entry = Grid Pre-Entry (user 2026-09-30): kolom Aksi tanpa judul di paling kiri, ikon tanpa kotak.
      // Transactional selalu rapat; Master (kotak image/inisial di tiap baris) selalu normal.
      // Centang hanya bila layar memasang aksi massal (bulk) -- tanpa bulk centang tak berguna.
      this.entry = !this.report;
      this.chk = !this.report && this.cfg.bulk.length > 0;
      this.saveKey = 'dl-grid-' + this.cfg.id;
      this.saved = {};
      try { this.saved = JSON.parse(localStorage.getItem(this.saveKey) || '{}'); } catch (e) {}

      this.st = Object.assign({search: '', sort: null, dir: 1, stat: null, tab: 0, filters: {}, hidden: this.cols.filter(c => !c.visible).map(c => c.key),
        group: this.cfg.groupBy || '', view: 'table', page: 1, perPage: this.cfg.perPage, density: this.report ? 'compact' : 'normal', lines: 'fixed', linesN: 1, collapsed: [], open: []}, this.saved, {page: 1, search: '', colf: {}}, this.entry ? {density: this.cfg.actions === 'master' ? 'normal' : 'compact', lines: 'fixed', linesN: 1} : {});

      // Saringan awal: stats bertanda 'default' aktif saat grid dibuka (mis. To Do di meja kerja); All tetap bisa dipilih.
      const ds = this.cfg.stats.findIndex(s => s.default); if (ds >= 0) this.st.stat = ds;

      this.sel = new Set();
      this.lastIdx = null;
      this.current = [];
      this.search = this.q('[data-g-search]');
      this.t = undefined;
      this.search.addEventListener('input', () => { clearTimeout(this.t); this.t = setTimeout(() => { this.st.search = this.search.value; this.st.page = 1; this.redraw(); }, 250); });
      window.addEventListener('resize', () => { this.slide('stats'); this.slide('tabs'); });
      this.el.addEventListener('click', e => {
        const x = e.target;
        // Filter tanpa where = "All": memilihnya (atau mengklik lagi yang aktif) = lepas filter.
        const s = x.closest('[data-g-stats] [data-i]'); if (s) { const i = +s.dataset.i; this.st.stat = (this.st.stat === i || !this.cfg.stats[i].where) ? null : i; this.st.page = 1; return this.redraw(); }
        const tb = x.closest('[data-g-tabs] [data-i]'); if (tb) { const i = +tb.dataset.i, all = this.cfg.tabs.findIndex(t => !t.where); this.st.tab = (this.st.tab === i && all >= 0) ? all : i; this.st.page = 1; return this.redraw(); }
        const th = x.closest('th.is-sortable'); if (th) { const k = th.dataset.key; if (this.st.sort === k) { if (this.st.dir > 0) this.st.dir = -1; else { this.st.sort = null; this.st.dir = 1; } } else { this.st.sort = k; this.st.dir = 1; } return this.redraw(); }
        const pg = x.closest('[data-p]'); if (pg && !pg.disabled) { this.st.page = +pg.dataset.p; return this.drawBody(); }
        const pp = x.closest('[data-pp]'); if (pp) { this.st.perPage = +pp.dataset.pp; this.st.page = 1; return this.redraw(); }
        const tg = x.closest('[data-g-toggle]'); if (tg) { e.stopPropagation(); const pan = this.q(`[data-g-panel="${tg.dataset.gToggle}"]`), was = pan.classList.contains('is-open'); this.qq('.dl-pop2__panel').forEach(p => p.classList.remove('is-open')); pan.classList.toggle('is-open', !was); return; }
        const d = x.closest('[data-g-density] .dl-segment__opt'); if (d) { this.st.density = d.dataset.value; return this.redraw(); }
        const l = x.closest('[data-g-lines] .dl-segment__opt'); if (l) { this.st.lines = l.dataset.value; return this.redraw(); }
        if (x.closest('[data-g-density-reset]')) { this.st.density = 'normal'; this.st.lines = 'fixed'; this.st.linesN = 1; return this.redraw(); }
        if (x.closest('[data-g-filter-reset]')) { this.st.filters = {}; this.st.page = 1; return this.redraw(); }
        const ch = x.closest('[data-g-chip]'); if (ch) { const k = ch.dataset.gChip; if (k === 'all') { this.st.filters = {}; this.st.colf = {}; this.st.stat = null; this.st.search = ''; this.search.value = ''; } else if (k === 'stat') this.st.stat = null; else if (k === 'search') { this.st.search = ''; this.search.value = ''; } else if (k.startsWith('c:')) delete this.st.colf[k.slice(2)]; else this.st.filters[k.slice(2)] = ''; this.st.page = 1; return this.redraw(); }
        const gr = x.closest('[data-g-grp]'); if (gr) { const g = gr.dataset.gGrp; this.st.collapsed = this.st.collapsed.includes(g) ? this.st.collapsed.filter(v => v !== g) : [...this.st.collapsed, g]; return this.drawBody(); }
        const vw = x.closest('[data-g-view] .dl-segment__opt'); if (vw) { this.st.view = vw.dataset.value; this.qq('[data-g-view] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b === vw)); return this.drawBody(); }
        const im = x.closest('[data-g-img]'); if (im) { const ov = document.createElement('div'); ov.className = 'dl-mov is-on dl-grid__lightbox'; ov.innerHTML = `<img src="${esc(im.dataset.gImg)}" alt=""><button type="button" class="dl-modal__x">&times;</button>`; ov.addEventListener('click', () => ov.remove()); document.body.appendChild(ov); return; }
        const gl = x.closest('[data-g-link]'); if (gl && gl.getAttribute('href') === '#') { e.preventDefault(); const id = gl.closest('tr').dataset.id; if (this._emit('dl:grid-link', {id, key: gl.dataset.gLink})) toast(`${gl.closest('td') && this.cols.find(c => c.key === gl.dataset.gLink)?.label}: ${id} (pratinjau)`); return; }
        const ga = x.closest('[data-g-action]'); if (ga) { const id = ga.closest('tr').dataset.id; if (this._emit('dl:grid-action', {id, action: ga.dataset.gAction})) toast(`${ga.textContent}: ${this.rows.find(v => String(v[this.cfg.rowKey]) === id)?.name || id} (pratinjau)`); return; }
        if (x.closest('[data-g-qa-add]')) return this.quickAdd();
        const ex = x.closest('[data-g-exp]'); if (ex) { const id = ex.closest('tr').dataset.id; this.st.open = this.st.open.includes(id) ? this.st.open.filter(v => v !== id) : [...this.st.open, id]; return this.drawBody(); }
        if (x.closest('[data-g-select-all]')) { this.filtered().forEach(r => this.sel.add(String(r[this.cfg.rowKey]))); this.drawBody(); return this.drawBulk(); }
        if (x.closest('[data-g-select-none]')) { this.sel.clear(); this.drawBody(); return this.drawBulk(); }
        const ba = x.closest('[data-g-bulk-action]'); if (ba) { toast(`${ba.textContent.trim()}: ${Tf('g_rows_preview', {n: fmt.number(this.sel.size)})}`, 'success'); this.el.dispatchEvent(new CustomEvent('dl:grid-bulk', {bubbles: true, detail: {action: ba.dataset.gBulkAction, ids: [...this.sel]}})); return; }
        const act = x.closest('[data-g-act]'); if (act) { const r = this.rows.find(v => String(v[this.cfg.rowKey]) === act.dataset.id); if (act.dataset.gAct === 'toggle' && this.cfg.activeKey && this.cfg.toggleUrl) { this.saveToggle(r, act.dataset.id); } else if (act.dataset.gAct === 'toggle' && this.cfg.activeKey) { r[this.cfg.activeKey] = !r[this.cfg.activeKey]; toast((r[this.cfg.activeKey] ? 'Diaktifkan: ' : 'Dinonaktifkan: ') + (r.name || act.dataset.id), 'success'); this.redraw(); } else if (this._emit('dl:grid-open', {id: act.dataset.id, action: act.dataset.gAct})) toast(`${act.title.split(' (')[0]}: ${r.name || r[this.cfg.rowKey]} (pratinjau)`); return; }
        if (x.closest('[data-g-new]')) { if (this._emit('dl:grid-new', {})) toast('+ New Record (pratinjau)'); return; }
        if (x.closest('[data-g-export]')) return this.exportCsv();
        if (x.closest('[data-g-fopen]')) return this.openFilter();
        if (x.closest('[data-g-fclose]') || x.matches('[data-g-fmodal]')) return this.closeFilter();
        if (x.closest('[data-g-fapply]')) return this.applyFilter();
        if (x.closest('[data-g-freset]')) { this.st.filters = {}; this.st.colf = {}; this.st.page = 1; this.closeFilter(); return this.redraw(); }
        const cb = x.matches('.dl-grid__row input[type=checkbox]') ? x : null;
        if (cb) {
          const tr = cb.closest('tr'), idx = +tr.dataset.idx;
          if (e.shiftKey && this.lastIdx !== null) { const [a, b] = [Math.min(this.lastIdx, idx), Math.max(this.lastIdx, idx)]; this.current.slice(a, b + 1).forEach(r => cb.checked ? this.sel.add(String(r[this.cfg.rowKey])) : this.sel.delete(String(r[this.cfg.rowKey]))); }
          else cb.checked ? this.sel.add(tr.dataset.id) : this.sel.delete(tr.dataset.id);
          this.lastIdx = idx; this.drawBody(); this.drawBulk(); return;
        }
        if (x.matches('[data-g-all]')) { this.current.forEach(r => x.checked ? this.sel.add(String(r[this.cfg.rowKey])) : this.sel.delete(String(r[this.cfg.rowKey]))); this.drawBody(); this.drawBulk(); }
      });
      this.el.addEventListener('keydown', e => { if (e.target.closest('[data-g-fmodal]')) { if (e.key === 'Escape') this.closeFilter(); else if (e.key === 'Enter' && e.target.matches('input')) { e.preventDefault(); this.applyFilter(); } return; } if (e.key === 'Enter' && e.target.matches('[data-g-qa]')) { e.preventDefault(); this.quickAdd(); } });
      this.el.addEventListener('change', e => {
        const tc = e.target.closest('[data-g-cell]'); if (tc) { const id = tc.closest('tr').dataset.id, r = this.rows.find(v => String(v[this.cfg.rowKey]) === id); r[tc.dataset.gCell] = tc.checked; this._emit('dl:grid-cell', {id, key: tc.dataset.gCell, value: tc.checked}); return; }
        const f = e.target.closest('[data-g-filter]'); if (f) { this.st.filters[f.dataset.gFilter] = f.value; this.st.page = 1; return this.redraw(); }
        const c = e.target.closest('[data-g-col]'); if (c) { const k = c.dataset.gCol; this.st.hidden = c.checked ? this.st.hidden.filter(v => v !== k) : [...this.st.hidden, k]; if (!this.shown().length) { this.st.hidden = this.st.hidden.filter(v => v !== k); } return this.redraw(); }
        const g = e.target.closest('[data-g-group]'); if (g) { this.st.group = g.value; this.st.collapsed = []; return this.redraw(); }
        if (e.target.matches('[data-g-lines-n]')) { this.st.linesN = Math.max(1, Math.min(5, +e.target.value || 1)); return this.redraw(); }
        if (e.target.matches('[data-g-goto]')) { this.st.page = Math.max(1, +e.target.value || 1); this.drawBody(); }
      });
      this.el.addEventListener('dblclick', e => { const tr = e.target.closest('.dl-grid__row, .dl-grid__card'); if (!tr || e.target.closest('button,input,label')) return; const r = this.rows.find(v => String(v[this.cfg.rowKey]) === tr.dataset.id); if (this._emit('dl:grid-open', {id: tr.dataset.id, action: 'open'})) toast(Tf('g_open_dbl', {name: r.name || tr.dataset.id})); });
      document.addEventListener('click', e => { if (!e.target.closest('[data-g-pop]')) this.qq('.dl-pop2__panel').forEach(p => p.classList.remove('is-open')); });

      this.el.dlGrid = {rows: this.rows, cfg: this.cfg, redraw: this.redraw, setRows: list => { this.rows.splice(0, this.rows.length, ...list); this.st.page = 1; this.sel.clear(); this.redraw(); }};
      this.redraw();
    }
    persist() { try { localStorage.setItem(this.saveKey, JSON.stringify({sort: this.st.sort, dir: this.st.dir, hidden: this.st.hidden, group: this.st.group, perPage: this.st.perPage, density: this.st.density, lines: this.st.lines, linesN: this.st.linesN, filters: this.st.filters, tab: this.st.tab})); } catch (e) {} }

    q(s) {
      return $(this.el, s);
    }

    qq(s) {
      return $$(this.el, s);
    }

    shown() {
      return this.cols.filter(c => !this.st.hidden.includes(c.key));
    }

    // Aktif/Nonaktif Grid Master tersimpan ke database: status akhir = jawaban server, bukan tebakan layar.
    saveToggle(r, id) {
      fetch(this.cfg.toggleUrl.replace('{id}', encodeURIComponent(id)), {method: 'POST', credentials: 'same-origin', headers: {'X-CSRF-TOKEN': this.cfg.csrf || '', 'Accept': 'application/json'}})
        .then(res => res.ok ? res.json() : Promise.reject(res))
        .then(j => { r[this.cfg.activeKey] = !!j.active; toast((j.active ? 'Diaktifkan: ' : 'Dinonaktifkan: ') + (r.name || id), 'success'); this.redraw(); })
        .catch(() => toast(T('g_toggle_failed'), 'danger'));
    }

    _emit(name, detail) {
      const go = this.el.dispatchEvent(new CustomEvent(name, {bubbles: true, cancelable: true, detail}));
      if (go && name === 'dl:grid-new' && this.cfg.newModal) { document.querySelector(`[data-dl-modal-id="${this.cfg.newModal}"]`)?.classList.add('is-on'); return false; }
      if (go && name === 'dl:grid-new' && this.cfg.newUrl) { location.href = this.cfg.newUrl; return false; }
      if (go && name === 'dl:grid-open' && this.cfg.openUrl && ['open', 'edit'].includes(detail.action)) {
        const r = this.rows.find(v => String(v[this.cfg.rowKey]) === String(detail.id)) || {};
        location.href = this.cfg.openUrl.replace(/\{(\w+)\}/g, (m, k) => k === 'id' ? encodeURIComponent(detail.id) : (r[k] ?? ''));
        return false;
      }
      return go;
    }

    val(r, k) {
      return r[k] ?? '';
    }

    match(r, where) {
      return !where || Object.entries(where).every(([k, v]) => Array.isArray(v) ? v.map(String).includes(String(this.val(r, k))) : String(this.val(r, k)) === String(v));
    }

    fmtCell(c, v) {
      if (c.type === 'link') return this.fmtCell({...c, type: c.format || 'number'}, v);
      return c.type === 'money' ? fmt.money(v) : c.type === 'number' ? fmt.number(v, String(v).includes('.') ? 2 : 0) : c.type === 'date' ? fmt.gridDate(v) : c.type === 'datetime' ? (DlDateTime.of(v)?.display() ?? v) : c.type === 'time' ? (DlTime.of(v)?.display() ?? v) : v;
    }

    filtered(opts = {}) {
      const term = this.st.search.trim().toLowerCase(), vis = this.shown();
      return this.rows.filter(r =>
        (opts.noStat || this.st.stat === null || this.match(r, this.cfg.stats[this.st.stat].where)) &&
        (opts.noTab || !this.cfg.tabs.length || this.match(r, this.cfg.tabs[this.st.tab].where)) &&
        Object.entries(this.st.filters).every(([k, v]) => v === '' || String(this.val(r, k)) === v) &&
        Object.entries(this.st.colf).every(([k, t]) => this.colMatch(k, t, r)) &&
        (!term || vis.some(c => String(this.fmtCell(c, this.val(r, c.key))).toLowerCase().includes(term) || ((c.code || c.party) && String(this.val(r, c.code || c.party)).toLowerCase().includes(term)))));
    }

    // Angka: ">1000", "<=5", "=12"; selain itu teks mengandung (tanpa beda huruf besar/kecil).
    colMatch(k, t, r) {
      t = String(t || '').trim(); if (!t) return true;
      const c = this.cols.find(x => x.key === k) || {key: k}, v = this.val(r, k);
      const m = ['number', 'money'].includes(c.type) && t.match(/^(>=|<=|>|<|=)\s*(-?[\d.,]+)$/);
      if (m) { const n = parseNum(m[2]), x = +v; return {'>': x > n, '<': x < n, '>=': x >= n, '<=': x <= n, '=': x === n}[m[1]]; }
      return String(this.fmtCell(c, v)).toLowerCase().includes(t.toLowerCase()) || ((c.code || c.party) && String(this.val(r, c.code || c.party)).toLowerCase().includes(t.toLowerCase()));
    }

    // Pop-up Filter (Grid for Reporting): pilihan tetap (filters) + kriteria per kolom; berlaku setelah Terapkan.
    openFilter() {
      const sel = Object.entries(this.cfg.filters).map(([k, label]) => { const opts = [...new Set(this.rows.map(r => String(this.val(r, k))))].sort(); return `<label class="dl-pop2__field"><span>${esc(label)}</span><select data-gf-sel="${esc(k)}"><option value="">${esc(T('g_all'))}</option>${opts.map(o => `<option ${this.st.filters[k] === o ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select></label>`; }).join('');
      const cols = this.cols.filter(c => !['image', 'toggle', 'spark', 'button', 'steps', 'progress', 'rating'].includes(c.type)).map(c => { const num = ['number', 'money'].includes(c.type); return `<label class="dl-pop2__field"><span>${esc(c.label)}</span><input type="search" data-gf-col="${esc(c.key)}" value="${esc(this.st.colf[c.key] || '')}" placeholder="${num ? 'mis. >1000, <=5, =12' : 'mengandung...'}"></label>`; }).join('');
      this.q('[data-g-fform]').innerHTML = (sel ? `<div class="dl-pop2__title">${esc(T('g_selection'))}</div><div class="dl-grid__fgrid">${sel}</div>` : '') + `<div class="dl-pop2__title">${esc(T('g_per_column'))}</div><div class="dl-grid__fgrid">${cols}</div>`;
      this.q('[data-g-fmodal]').classList.add('is-on');
      const first = this.q('[data-g-fform] select, [data-g-fform] input'); if (first) first.focus();
    }

    closeFilter() {
      this.q('[data-g-fmodal]').classList.remove('is-on');
    }

    applyFilter() {
      this.qq('[data-gf-sel]').forEach(el => { this.st.filters[el.dataset.gfSel] = el.value; });
      this.st.colf = {}; this.qq('[data-gf-col]').forEach(el => { if (el.value.trim()) this.st.colf[el.dataset.gfCol] = el.value.trim(); });
      this.st.page = 1; this.closeFilter(); this.redraw();
    }

    span() {
      return this.shown().length + (this.chk ? 1 : 0) + (this.hasExp ? 1 : 0) + (this.cfg.actions !== 'none' ? 1 : 0);
    }

    actFirst() {
      return this.entry && this.cfg.actions !== 'none';
    }

    actLast() {
      return !this.entry && this.cfg.actions !== 'none';
    }

    sorted(list) {
      if (!this.st.sort) return list;
      const c = this.cols.find(x => x.key === this.st.sort), num = ['number', 'money', 'link'].includes(c && c.type);
      return [...list].sort((a, b) => { const x = this.val(a, this.st.sort), y = this.val(b, this.st.sort); return (num ? (+x - +y) : String(x).localeCompare(String(y), 'id', {numeric: true})) * this.st.dir; });
    }

    drawStats() {
      const box = this.q('[data-g-stats]'); if (!box) return;
      const base = this.filtered({noStat: true}), none = this.st.stat === null;
      box.innerHTML = this.cfg.stats.map((s, i) => `<button type="button" class="dl-fb dl-fb--${esc(s.tone || 'accent')} ${this.st.stat === i || (none && !s.where) ? 'is-active' : ''}" data-i="${i}">${esc(s.label)} <span class="dl-grid__tabcount">${fmt.number(base.filter(r => this.match(r, s.where)).length)}</span></button>`).join('');
      this.slide('stats');
    }

    // Tab tanpa where ("All") disembunyikan bila baris status sudah punya "All"; tab tanpa tone mendapat warna bergilir.
    drawTabs() {
      const bar = this.q('[data-g-tabs]'); if (!bar) return;
      const base = this.filtered({noTab: true, noStat: true}), hideAll = this.cfg.stats.some(s => !s.where), tones = ['orange', 'pink', 'lime', 'violet', 'success', 'warn'];
      let n = 0;
      bar.innerHTML = this.cfg.tabs.map((t, i) => !t.where && hideAll ? '' : `<button type="button" class="dl-fb dl-fb--${esc(t.tone || (t.where ? tones[n++ % tones.length] : 'accent'))} ${this.st.tab === i ? 'is-active' : ''}" data-i="${i}">${esc(t.label)} <span class="dl-grid__tabcount">${base.filter(r => this.match(r, t.where)).length}</span></button>`).join('');
      this.q('[data-g-fsep]').hidden = !(this.cfg.stats.length && this.cfg.tabs.length);
      this.slide('tabs');
    }

    // Garis bawah berjalan: pindah ke pilihan aktif dan memakai warnanya (--fb).
    slide(kind) {
      const ind = this.q(`[data-g-find="${kind}"]`), a = this.q(`[data-g-${kind}] .is-active`);
      if (!ind) return;
      if (!a || !a.offsetWidth) { ind.style.opacity = 0; return; }
      Object.assign(ind.style, {opacity: 1, left: a.offsetLeft + 'px', width: a.offsetWidth + 'px', top: (a.offsetTop + a.offsetHeight - 3) + 'px', background: getComputedStyle(a).getPropertyValue('--fb')});
    }

    drawHead() {
      const vis = this.shown();
      this.q('[data-g-head]').innerHTML = '<tr>' + (this.actFirst() ? '<th class="dl-grid__act"></th>' : '') + (this.chk ? '<th class="dl-grid__chk"><input type="checkbox" data-g-all aria-label="' + esc(T('g_select_page')) + '"></th>' : '') + (this.hasExp ? '<th class="dl-grid__exp"></th>' : '')
        + vis.map(c => {
          const on = this.st.sort === c.key, arrow = on ? (this.st.dir > 0 ? 'fa-arrow-up-short-wide' : 'fa-arrow-down-wide-short') : 'fa-sort';
          return `<th class="${['number', 'money', 'link'].includes(c.type) ? 'num' : ''} ${c.sortable ? 'is-sortable' : ''} ${on ? 'is-sorted' : ''}" data-key="${esc(c.key)}"${srcAttr(c)} ${c.width ? `style="width:${esc(c.width)}"` : ''}>${esc(c.label)}${c.sortable ? ` <i class="fa-solid ${arrow}"></i>` : ''}</th>`;
        }).join('') + (this.actLast() ? '<th class="dl-grid__act">Aksi</th>' : '') + '</tr>';
    }

    cell(c, r) {
      const v = this.val(r, c.key);
      switch (c.type) {
        case 'identity': {
          const name = String(v || '?'), w = name.trim().split(/\s+/), ini = (w[0][0] + (w[1] ? w[1][0] : '')).toUpperCase();
          let h = 0; for (const ch of name) h = (h * 31 + ch.charCodeAt(0)) % 360;
          return `<td><span class="dl-avatar-id">${c.avatar === false ? '' : `<span class="dl-avatar-id__pic" style="--h:${h}">${esc(ini)}</span>`}<span class="dl-avatar-id__txt"><b>${esc(name)}</b>${c.code ? `<small>${esc(this.val(r, c.code))}</small>` : ''}</span></span></td>`;
        }
        // Persegi dokumen (Grid Transactional, user 2026-10-02): nomor dokumen di atas, nama pihak ('party') di bawah.
        // Warna = satu dari 8 gradasi pilihan, dihitung dari nama pihak supaya pihak yang sama selalu berwarna sama.
        case 'doc': {
          const party = c.party ? String(this.val(r, c.party)) : '';
          let h = 0; for (const ch of (party || String(v))) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
          return `<td><span class="dl-doctag dl-doctag--${(Math.imul(h ^ (h >>> 15), 0x9E3779B1) >>> 29) + 1}"><b>${esc(v)}</b>${party ? `<small>${esc(party)}</small>` : ''}</span></td>`;
        }
        case 'badge': return `<td><span class="dl-badge dl-badge--pill dl-badge--${esc((c.tones || {})[v] || 'neutral')}">${esc(v)}</span></td>`;
        case 'money': case 'number': return `<td class="num">${esc(this.fmtCell(c, v))}</td>`;
        case 'date': return `<td class="dl-grid__date">${esc(this.fmtCell(c, v))}</td>`;
        // Nilai dari transaksi (Grid Master, user 2026-09-30): hanya-baca, klik = buka transaksi baris ini (url {id}).
        case 'link': { const u = c.url ? c.url.replace('{id}', encodeURIComponent(r[this.cfg.rowKey])) : '#'; return `<td class="num"><a class="dl-grid__link" href="${esc(u)}" data-g-link="${esc(c.key)}" title="${esc(T('g_open_tx'))}">${esc(this.fmtCell(c, v))} <i class="fa-solid fa-arrow-up-right-from-square"></i></a></td>`; }
        case 'image': return `<td class="dl-grid__imgcell">${v ? `<button type="button" class="dl-grid__thumb" data-g-img="${esc(v)}" title="Perbesar"><img src="${esc(v)}" alt="" loading="lazy"></button>` : (c.fallback ? `<span class="dl-grid__noimg dl-grid__noimg--ini">${esc(String(this.val(r, c.fallback) || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase())}</span>` : '<span class="dl-grid__noimg"><i class="fa-regular fa-image"></i></span>')}</td>`;
        case 'progress': { const n = Math.max(0, Math.min(100, +v || 0)), t = n >= 100 ? 'success' : n >= 60 ? 'accent' : n >= 30 ? 'warn' : 'danger'; return `<td><span class="dl-grid__prog dl-chart--${t}"><span style="width:${n}%"></span></span><small class="dl-grid__progv">${n}%</small></td>`; }
        case 'steps': { const at = (c.steps || []).indexOf(v); return `<td><span class="dl-grid__steps" title="${esc(v)}">${(c.steps || []).map((s2, i) => `<i class="${i < at ? 'is-done' : i === at ? 'is-now' : ''}" title="${esc(s2)}"></i>`).join('')}</span><small class="dl-grid__stepv">${esc(v)}</small></td>`; }
        case 'toggle': return `<td><label class="dl-switch dl-switch--sm"><input type="checkbox" data-g-cell="${esc(c.key)}" ${v ? 'checked' : ''}><span class="dl-switch__slider"></span></label></td>`;
        case 'rating': return `<td class="dl-grid__stars" title="${esc(v)} / 5">${[1, 2, 3, 4, 5].map(i => `<i class="fa-solid fa-star ${i <= Math.round(+v || 0) ? 'is-on' : ''}"></i>`).join('')}</td>`;
        case 'spark': { const d = (Array.isArray(v) ? v : []).map(Number); if (d.length < 2) return '<td></td>'; const mn = Math.min(...d), mx = Math.max(...d); const pts = d.map((y, i) => `${(i / (d.length - 1) * 80).toFixed(1)},${(22 - (mx > mn ? (y - mn) / (mx - mn) : .5) * 20).toFixed(1)}`).join(' '); return `<td><svg class="dl-grid__spark ${d[d.length - 1] < d[0] ? 'is-down' : ''}" viewBox="0 0 80 24"><polyline points="${pts}"/></svg></td>`; }
        case 'button': return `<td><button type="button" class="dl-btn dl-btn--secondary dl-btn--sm" data-g-action="${esc(c.action || c.key)}">${esc(c.text || c.label)}</button></td>`;
        default: return `<td><div class="dl-grid__txt">${esc(this.fmtCell(c, v))}</div></td>`;
      }
    }

    actionsCell(r) {
      if (this.cfg.actions === 'none') return '';
      const id = esc(r[this.cfg.rowKey]);
      // Tanpa Confirm/Approve di grid (user 2026-09-30): Confirm hanya di dalam form dokumen.
      if (this.cfg.actions === 'worklist') return `<td class="dl-grid__act"><button type="button" class="dl-iconbtn" data-g-act="open" data-id="${id}" title="${esc(T('g_open_hint'))}"><i class="fa-solid fa-up-right-from-square"></i></button></td>`;
      const on = !!r[this.cfg.activeKey];
      return `<td class="dl-grid__act"><button type="button" class="dl-iconbtn" data-g-act="edit" data-id="${id}" title="${esc(T('g_edit_hint'))}"><i class="fa-solid fa-pen"></i></button><button type="button" class="dl-iconbtn dl-iconbtn--${on ? 'danger' : 'success'}" data-g-act="toggle" data-id="${id}" title="${on ? 'Nonaktifkan' : 'Aktifkan'}" data-dl-confirm="${on ? 'Nonaktifkan' : 'Aktifkan'} data ini?"><i class="fa-solid ${on ? 'fa-ban' : 'fa-circle-check'}"></i></button></td>`;
    }

    rowHtml(r, idx) {
      const id = String(r[this.cfg.rowKey]), open = this.st.open.includes(id), span = this.span();
      let h = `<tr class="dl-grid__row ${this.sel.has(id) ? 'is-selected' : ''} ${this.cfg.activeKey && r[this.cfg.activeKey] === false ? 'is-inactive' : ''}" data-id="${esc(id)}" data-idx="${idx}" title="Klik 2x untuk membuka">`
        + (this.actFirst() ? this.actionsCell(r) : '')
        + (this.chk ? `<td class="dl-grid__chk"><input type="checkbox" ${this.sel.has(id) ? 'checked' : ''} aria-label="${esc(T('g_select_row'))}"></td>` : '')
        + (this.hasExp ? `<td class="dl-grid__exp"><button type="button" class="dl-grid__chev ${open ? 'is-open' : ''}" data-g-exp aria-label="${esc(T('g_open_detail'))}"><i class="fa-solid fa-chevron-right"></i></button></td>` : '')
        + this.shown().map(c => this.cell(c, r)).join('') + (this.actLast() ? this.actionsCell(r) : '') + '</tr>';
      if (open && this.cfg.detail) { const D = this.cfg.detail, kids = r[D.key] || []; h += `<tr class="dl-grid__detail"><td colspan="${span}"><div class="dl-grid__nest"><b>${esc(String(D.title || 'Detail').replace(/\{(\w+)\}/g, (m, k) => r[k] ?? ''))}</b><table><thead><tr>${D.columns.map(c => `<th class="${['number', 'money'].includes(c.type) ? 'num' : ''}">${esc(c.label)}</th>`).join('')}</tr></thead><tbody>${kids.map(k => `<tr>${D.columns.map(c => `<td class="${['number', 'money'].includes(c.type) ? 'num' : ''}">${c.strong ? '<b>' : ''}${esc(this.fmtCell(c, k[c.key] ?? ''))}${c.strong ? '</b>' : ''}</td>`).join('')}</tr>`).join('') || `<tr><td colspan="${D.columns.length}">${esc(T('g_no_detail'))}</td></tr>`}</tbody></table></div></td></tr>`; }
      else if (open) h += `<tr class="dl-grid__detail"><td colspan="${span}"><div class="dl-grid__detailbox">${this.cfg.expand.map(k => { const c = this.cols.find(x => x.key === k) || {key: k, label: k}; return `<div><small>${esc(c.label)}</small><b>${esc(this.fmtCell(c, this.val(r, k)) || '—')}</b></div>`; }).join('')}</div></td></tr>`;
      return h;
    }

    drawBody() {
      const list = this.sorted(this.filtered()), total = list.length, pages = Math.max(1, Math.ceil(total / this.st.perPage));
      this.st.page = Math.min(this.st.page, pages);
      const page = list.slice((this.st.page - 1) * this.st.perPage, this.st.page * this.st.perPage);
      this.current = page;
      const span = this.span(), sums = this.shown().filter(c => c.sum);
      let html = '';
      if (!page.length) html = `<tr><td colspan="${span}"><div class="dl-empty"><span class="dl-empty__icon"><i class="fa-solid fa-magnifying-glass"></i></span><b class="dl-empty__title">${esc(T('g_no_match'))}</b><p class="dl-empty__msg">${esc(T('g_no_match_hint'))}</p></div></td></tr>`;
      else if (this.st.group) {
        const gcol = this.cols.find(c => c.key === this.st.group), buckets = new Map();
        page.forEach((r, i) => { const g = String(this.val(r, this.st.group)) || T('g_empty_group'); if (!buckets.has(g)) buckets.set(g, []); buckets.get(g).push([r, i]); });
        buckets.forEach((items, g) => {
          const shut = this.st.collapsed.includes(g);
          html += `<tr class="dl-grid__group"><td colspan="${span}"><button type="button" data-g-grp="${esc(g)}"><i class="fa-solid fa-chevron-${shut ? 'right' : 'down'}"></i> ${esc(gcol ? gcol.label : this.st.group)}: <b>${esc(g)}</b> <span>${esc(Tf('g_n_rows', {n: items.length}))}</span>${sums.map(c => ` · ${esc(c.label)} <b>${esc(this.fmtCell(c, items.reduce((t, [r]) => t + (+this.val(r, c.key) || 0), 0)))}</b>`).join('')}</button></td></tr>`;
          if (!shut) items.forEach(([r, i]) => { html += this.rowHtml(r, i); });
        });
      } else page.forEach((r, i) => { html += this.rowHtml(r, i); });
      this.q('[data-g-body]').innerHTML = html;
      if (this.cfg.quickAdd.length) {
        const qa = this.cfg.quickAdd.map(k => this.cols.find(c => c.key === k) || {key: k, label: k});
        this.q('[data-g-body]').insertAdjacentHTML('afterbegin', `<tr class="dl-grid__quick"><td class="dl-grid__chk"><i class="fa-solid fa-plus"></i></td><td colspan="${span - 1}"><div class="dl-grid__qa">${qa.map(c => `<input type="text" data-g-qa="${esc(c.key)}" placeholder="${esc(c.label)}${['number', 'money'].includes(c.type) ? ' (angka)' : ''}" aria-label="${esc(c.label)}">`).join('')}<button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-g-qa-add>${esc(T('g_add'))}</button><small>${esc(T('g_enter_add'))}</small></div></td></tr>`);
      }
      const cardsOn = this.cfg.cards && this.st.view === 'cards';
      this.q('[data-g-cards]').hidden = !cardsOn; this.q('.dl-grid__wrap').hidden = !!cardsOn;
      if (cardsOn) { const C = this.cfg.cards; this.q('[data-g-cards]').innerHTML = page.map(r => `<article class="dl-grid__card" data-id="${esc(r[this.cfg.rowKey])}"><div class="dl-grid__cardimg">${r[C.image] ? `<img src="${esc(r[C.image])}" alt="" loading="lazy">` : '<i class="fa-regular fa-image"></i>'}${C.badge && r[C.badge] ? `<span class="dl-badge dl-badge--pill dl-badge--${esc((C.tones || {})[r[C.badge]] || 'neutral')}">${esc(r[C.badge])}</span>` : ''}</div><div class="dl-grid__cardbody"><b>${esc(r[C.title])}</b><small>${esc(r[C.sub] ?? '')}</small>${(C.meta || []).map(k => { const c = this.cols.find(x => x.key === k) || {key: k}; return `<span><em>${esc(c.label || k)}</em>${esc(this.fmtCell(c, r[k] ?? ''))}</span>`; }).join('')}</div></article>`).join('') || '<div class="dl-empty"><b class="dl-empty__title">' + esc(T('g_no_match')) + '</b></div>'; }
      const cnt = this.q('[data-g-count]'); if (cnt) cnt.textContent = Tf('g_matched', {n: fmt.number(total), total: fmt.number(this.rows.length)});
      this.q('[data-g-foot]').innerHTML = sums.length && page.length ? '<tr>' + (this.actFirst() ? '<td></td>' : '') + (this.chk ? '<td class="dl-grid__chk"></td>' : '') + (this.hasExp ? '<td></td>' : '') + this.shown().map((c, i) => c.sum ? `<td class="num"><b>${esc(this.fmtCell(c, list.reduce((t, r) => t + (+this.val(r, c.key) || 0), 0)))}</b></td>` : `<td>${i === 0 ? '<b>Total (semua hasil)</b>' : ''}</td>`).join('') + (this.actLast() ? '<td></td>' : '') + '</tr>' : '';
      this.drawPager(total, pages);
      const all = this.q('[data-g-all]'); if (all) { const ids = page.map(r => String(r[this.cfg.rowKey])); all.checked = ids.length > 0 && ids.every(i => this.sel.has(i)); all.indeterminate = !all.checked && ids.some(i => this.sel.has(i)); }
    }

    drawPager(total, pages) {
      const from = total ? (this.st.page - 1) * this.st.perPage + 1 : 0, to = Math.min(total, this.st.page * this.st.perPage), start = Math.max(1, Math.min(this.st.page - 2, pages - 4));
      let nums = ''; for (let p = start; p <= Math.min(pages, start + 4); p++) nums += `<button type="button" class="${p === this.st.page ? 'is-active' : ''}" data-p="${p}">${p}</button>`;
      this.q('[data-g-pager]').innerHTML = `<div class="dl-pager"><div class="dl-pager__left">Go to <input type="number" min="1" max="${pages}" value="${this.st.page}" class="dl-pager__goto" data-g-goto> View ${this.cfg.pageSizes.map(n => `<button type="button" class="dl-pager__size ${n === this.st.perPage ? 'is-active' : ''}" data-pp="${n}">${n}</button>`).join('')}</div>`
        + `<div class="dl-pager__nav"><button type="button" data-p="1" ${this.st.page <= 1 ? 'disabled' : ''} title="${esc(T('g_first'))}">⏮</button><button type="button" data-p="${this.st.page - 1}" ${this.st.page <= 1 ? 'disabled' : ''} title="${esc(T('g_prev'))}">◀</button>${nums}<button type="button" data-p="${this.st.page + 1}" ${this.st.page >= pages ? 'disabled' : ''} title="${esc(T('g_next'))}">▶</button><button type="button" data-p="${pages}" ${this.st.page >= pages ? 'disabled' : ''} title="${esc(T('g_last'))}">⏭</button></div>`
        + `<div class="dl-pager__info">${esc(Tf('g_range', {from: fmt.number(from), to: fmt.number(to), total: fmt.number(total)}))}</div></div>`;
    }

    drawTools() {
      const ff = this.q('[data-g-filter-fields]');
      if (ff) ff.innerHTML = Object.entries(this.cfg.filters).map(([k, label]) => { const opts = [...new Set(this.rows.map(r => String(this.val(r, k))))].sort(); return `<label class="dl-pop2__field"><span>${esc(label)}</span><select data-g-filter="${esc(k)}"><option value="">${esc(T('g_all'))}</option>${opts.map(o => `<option ${this.st.filters[k] === o ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select></label>`; }).join('');
      const n = Object.values(this.st.filters).filter(v => v !== '').length + Object.values(this.st.colf).filter(v => String(v).trim()).length, badge = this.q('[data-g-filter-count]');
      if (badge) { badge.hidden = !n; badge.textContent = n; }
      this.q('[data-g-col-list]').innerHTML = this.cols.map(c => `<label class="dl-choice"><input type="checkbox" data-g-col="${esc(c.key)}" ${this.st.hidden.includes(c.key) ? '' : 'checked'}> ${esc(c.label)}</label>`).join('');
      const gl = this.q('[data-g-group-list]');
      if (gl) gl.innerHTML = [['', T('g_no_group')], ...Object.entries(this.cfg.groups)].map(([k, l]) => `<label class="dl-choice"><input type="radio" name="g-${esc(this.cfg.id)}" value="${esc(k)}" data-g-group ${this.st.group === k ? 'checked' : ''}> ${esc(l)}</label>`).join('');
      this.qq('[data-g-density] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b.dataset.value === this.st.density));
      this.qq('[data-g-lines] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b.dataset.value === this.st.lines));
      const ln = this.q('[data-g-lines-n]'); if (ln) { ln.value = this.st.linesN; ln.disabled = this.st.lines === 'auto'; }
      this.el.dataset.density = this.st.density; this.el.style.setProperty('--dl-grid-lines', this.st.lines === 'auto' ? 'unset' : this.st.linesN); this.el.classList.toggle('is-clamped', this.st.lines !== 'auto');
      const chips = [
        ...(this.st.stat !== null ? [['stat', 'Filter: ' + this.cfg.stats[this.st.stat].label]] : []),
        ...Object.entries(this.st.filters).filter(([, v]) => v !== '').map(([k, v]) => ['f:' + k, `${this.cfg.filters[k]}: ${v}`]),
        ...Object.entries(this.st.colf).filter(([, v]) => String(v).trim()).map(([k, v]) => ['c:' + k, `${(this.cols.find(c => c.key === k) || {label: k}).label}: ${v}`]),
        ...(this.st.search ? [['search', Tf('g_search_chip', {q: this.st.search})]] : []),
      ];
      const cbox = this.q('[data-g-chips]'); cbox.hidden = !chips.length;
      cbox.innerHTML = chips.map(([k, l]) => `<span class="dl-tags__chip">${esc(l)}<button type="button" data-g-chip="${esc(k)}" aria-label="${esc(T('g_clear'))}">&times;</button></span>`).join('') + (chips.length > 1 ? '<button type="button" class="dl-btn dl-btn--link" data-g-chip="all">' + esc(T('g_clear_all')) + '</button>' : '');
    }

    drawBulk() {
      const b = this.q('[data-g-bulk]'); b.hidden = !this.sel.size; this.q('[data-g-bulk-count]').textContent = fmt.number(this.sel.size);
      const all = this.q('[data-g-select-all]'); const n = this.filtered().length; all.textContent = this.sel.size < n ? Tf('g_select_all_n', {n: fmt.number(n)}) : T('g_all_selected'); all.disabled = this.sel.size >= n;
    }

    redraw() { this.drawStats(); this.drawTabs(); this.drawTools(); this.drawHead(); this.drawBody(); this.drawBulk(); this.persist(); }

    quickAdd() {
      const inputs = this.qq('[data-g-qa]'), first = inputs[0];
      if (!first || !first.value.trim()) { first && first.focus(); toast(Tf('g_required_x', {x: this.cols.find(c => c.key === this.cfg.quickAdd[0])?.label || T('g_first_field')}), 'warn'); return; }
      const r = {[this.cfg.rowKey]: 'n' + Date.now()}; if (this.cfg.activeKey) r[this.cfg.activeKey] = true;
      inputs.forEach(i => { const c = this.cols.find(x => x.key === i.dataset.gQa) || {}; r[i.dataset.gQa] = ['number', 'money'].includes(c.type) ? parseNum(i.value) || 0 : i.value.trim(); });
      this.rows.unshift(r); this.redraw(); this._emit('dl:grid-add', {row: r}); toast(T('g_row_added'), 'success'); const f = this.q('[data-g-qa]'); if (f) f.focus();
    }

    exportCsv() {
      const vis = this.shown(), list = this.sorted(this.filtered()), cellTxt = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
      const csv = [vis.map(c => cellTxt(c.label)).join(';'), ...list.map(r => vis.map(c => cellTxt(c.type === 'identity' && c.code ? `${this.val(r, c.key)} (${this.val(r, c.code)})` : c.type === 'doc' && c.party ? `${this.val(r, c.key)} (${this.val(r, c.party)})` : this.val(r, c.key))).join(';'))].join('\r\n');
      const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + csv], {type: 'text/csv'})); a.download = this.cfg.id + '.csv'; a.click();
      toast(Tf('g_export_done', {n: fmt.number(list.length), c: vis.length}), 'success');
    }
  }
  Devlib.define("Grid", Grid);
})(window.Devlib);

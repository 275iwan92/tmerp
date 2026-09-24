/* DevLib · Formation */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, emit} = Devlib.h;
  /* ============================ FORMASI HARIAN ============================ */
  class Formation extends Devlib.Component {
    static selector = "[data-dl-formation]";
    init() {
      const self = this;
      this.POS = this.J('positions') || [];
      this.SH = this.J('shifts') || {};
      this.DAYS = this.J('days') || [];
      this.PEOPLE = this.J('people') || [];
      this.asg = (this.J('assign') || []).map(a => ({...a, days: [...a.days]}));
      this.who = Object.fromEntries(this.PEOPLE.map(p => [String(p.id), p]));
      this.N = this.DAYS.length;
      this.ALL = [...Array(this.N).keys()];
      this.st = {mode: 'week', day: +this.el.dataset.today || 0, filter: null, q: '', edit: null};

      this.el.addEventListener('click', e => {
        const x = e.target;
        const f = x.closest('[data-fm-f]'); if (f) { const k = f.dataset.fmF; this.st.filter = k === 'all' || this.st.filter === k ? null : k; if (k !== 'all' && k !== 'unsched' && k !== 'empty' && this.st.mode === 'week') { this.st.mode = 'day'; } return this.draw(); }
        const m = x.closest('[data-fm-mode] [data-m]'); if (m) { this.st.mode = m.dataset.m; return this.draw(); }
        const dy = x.closest('[data-fm-day]'); if (dy) { this.st.mode = 'day'; this.st.day = +dy.dataset.fmDay; return this.draw(); }
        const ed = x.closest('[data-fm-edit]'); if (ed) {
          if (this.locked()) return toast('Formasi sudah dipublish — tidak bisa diubah.', 'warn');
          const [pos, sh] = ed.dataset.fmEdit.split('|');
          const shift = sh || (this.POS.find(p => p.code === pos)?.shifts || [])[0] || '';
          this.st.edit = {pos, sh: shift, rows: this.slotOf(pos, shift).map(a => ({id: a.id, days: [...a.days]}))}; this.drawBody(); this.drawSide();
          $(this.el, '[data-fm-side]').scrollIntoView({block: 'nearest'}); return;
        }
        const rm = x.closest('[data-fm-rm]'); if (rm) { this.st.edit.rows.splice(+rm.dataset.fmRm, 1); return this.drawSide(); }
        const pk = x.closest('[data-fm-pick]'); if (pk) { const p = this.PEOPLE.find(q => String(q.id) === pk.dataset.fmPick); if (p && !this.certOk(p, this.st.edit.pos)) toast(`${p.name} belum bersertifikat ${this.posName(this.st.edit.pos)}.`, 'warn'); return this.addPerson(pk.dataset.fmPick); }
        if (x.closest('[data-fm-cancel]')) { this.st.edit = null; this.drawBody(); return this.drawSide(); }
        if (x.closest('[data-fm-apply]')) {
          const e2 = this.st.edit;
          this.asg = this.asg.filter(a => !(a.pos === e2.pos && a.shift === e2.sh)).concat(e2.rows.filter(r => r.days.length).map(r => ({pos: e2.pos, shift: e2.sh, id: r.id, days: [...r.days].sort((a, b) => a - b)})));
          this.st.edit = null; this.el.dataset.dirty = '1'; this.draw();
          this._emit('change', {assign: this.asg}); toast(`Formasi ${this.posName(e2.pos)} diperbarui.`, 'success'); return;
        }
        const wk = x.closest('[data-fm-week]'); if (wk) { this._emit('week', {dir: +wk.dataset.fmWeek}); return toast(`Pindah ke minggu ${+wk.dataset.fmWeek < 0 ? 'sebelumnya' : 'berikutnya'} (pratinjau).`); }
        if (x.closest('[data-fm-save]')) { delete this.el.dataset.dirty; this._emit('save', {assign: this.asg}); return toast('Formasi disimpan (Draft).', 'success'); }
        if (x.closest('[data-fm-print]')) return window.print();
        if (x.closest('[data-fm-copy]')) return confirmBox({message: 'Salin formasi minggu lalu? Isi minggu ini akan diganti.'}).then(ok => { if (ok) { this._emit('copy', {}); toast('Formasi minggu lalu disalin (pratinjau).', 'success'); } });
        if (x.closest('[data-fm-publish]')) return confirmBox({message: 'Publish formasi minggu ini? Setelah publish, formasi terkunci.', tone: 'warn'}).then(ok => { if (!ok) return; this.el.dataset.status = 'PUBLISHED'; this.st.edit = null; this.draw(); this._emit('publish', {assign: this.asg}); toast('Formasi dipublish.', 'success'); });
      });
      this.el.addEventListener('change', e => {
        const cb = e.target.closest('[data-fm-cb]'); if (!cb) return;
        const [i, d] = cb.dataset.fmCb.split('|').map(Number), r = this.st.edit.rows[i];
        r.days = cb.checked ? [...new Set([...r.days, d])] : r.days.filter(x => x !== d);
      });
      this.el.addEventListener('input', e => { if (e.target.matches('[data-fm-search]')) { this.st.q = e.target.value.trim().toLowerCase(); this.drawBody(); } });
      this.el.addEventListener('keydown', e => {
        if (e.key !== 'Enter' || !e.target.matches('[data-fm-add]')) return;
        e.preventDefault(); const v = e.target.value.trim().toLowerCase(), p = this.PEOPLE.find(q => q.name.toLowerCase() === v) || this.PEOPLE.find(q => q.name.toLowerCase().includes(v));
        if (!p) return toast('Nama tidak ditemukan.', 'warn');
        if (!this.certOk(p, this.st.edit.pos)) toast(`${p.name} belum bersertifikat ${this.posName(this.st.edit.pos)}.`, 'warn');
        this.addPerson(p.id);
      });
      this.el.addEventListener('change', e => { if (e.target.matches('[data-fm-add]') && e.target.value) { const p = this.PEOPLE.find(q => q.name === e.target.value); if (p) this.addPerson(p.id); } });
      this.el.dataset.uid = Math.random().toString(36).slice(2, 8);
      $(this.el, '[data-fm-legend]').innerHTML = Object.values(this.SH).map(s => `<span><i class="dl-fm__swatch is-${esc(s.tone || 'slate')}"></i> ${esc(s.label)}</span>`).join('')
        + '<span><span class="dl-fm__chip is-bor">borongan</span> dipanggil saat kerja berlebih</span><span><span class="dl-fm__chip is-multi">⇄</span> di 2 titik hari itu</span><span><span class="dl-fm__chip is-warn">⚠</span> belum bersertifikat unit ini</span>';
      this.el.dlFormation = {get assign() { return self.asg.map(a => ({...a, days: [...a.days]})); }, redraw: this.draw};
      this.draw();
    }

    J(k) {
      return JSON.parse(this.el.dataset[k] || 'null');
    }

    locked() {
      return this.el.dataset.status === 'PUBLISHED';
    }

    certOk(p, pos) {
      return !p.certs || p.certs.includes(pos);
    }

    dayName(i) {
      return this.DAYS[i]?.key || '';
    }

    rangeLabel(ds) { const d = [...ds].sort((a, b) => a - b); if (d.length === this.N) return `${this.dayName(0)}–${this.dayName(this.N - 1)}`; if (d.length === 1) return `${this.dayName(d[0])} saja`; return d.every((x, i) => !i || x === d[i - 1] + 1) ? `${this.dayName(d[0])}–${this.dayName(d[d.length - 1])}` : d.map(this.dayName).join(', '); }

    slotOf(pos, sh) {
      return this.asg.filter(a => a.pos === pos && a.shift === sh);
    }

    elsewhere(a, days) {
      return this.asg.find(b => b !== a && String(b.id) === String(a.id) && b.days.some(d => days.includes(d)));
    }

    posName(code) {
      return this.POS.find(p => p.code === code)?.name || code;
    }

    onDay(a, d) {
      return a.days.includes(d);
    }

    chip(a, days) {
      const p = this.who[a.id] || {name: a.id}, other = this.elsewhere(a, days), warn = !this.certOk(p, a.pos);
      const hit = this.st.q && p.name.toLowerCase().includes(this.st.q);
      const cls = ['dl-fm__chip', p.borongan ? 'is-bor' : '', other ? 'is-multi' : '', warn ? 'is-warn' : '', hit ? 'is-hit' : ''].join(' ');
      const tip = [warn ? 'Belum bersertifikat unit ini' : '', other ? `Juga di ${this.posName(other.pos)} · ${this.SH[other.shift]?.label || other.shift}` : ''].filter(Boolean).join(' · ');
      return `<span class="${cls}" ${tip ? `title="${esc(tip)}"` : ''}>${warn ? '⚠ ' : ''}${esc(p.name)}${p.borongan ? ' · borongan' : ''}${other ? ` ⇄ ${esc(this.posName(other.pos))}` : ''}</span>`;
    }

    weekCell(list) {
      const full = list.filter(a => a.days.length === this.N), part = list.filter(a => a.days.length && a.days.length < this.N);
      const groups = [];
      part.forEach(a => { const k = [...a.days].sort((x, y) => x - y).join(','); let g = groups.find(x => x.k === k); if (!g) groups.push(g = {k, days: a.days.slice().sort((x, y) => x - y), items: []}); g.items.push(a); });
      groups.sort((a, b) => a.days[0] - b.days[0]);
      const contiguous = g => g.days.every((x, i) => !i || x === g.days[i - 1] + 1);
      const swap = !full.length && groups.length >= 2 && groups.every(g => contiguous(g) && g.items.every(a => !this.who[a.id]?.borongan))
        && groups.every((g, i) => !i || g.days[0] === groups[i - 1].days[groups[i - 1].days.length - 1] + 1);
      if (swap) return {swap: true, html: groups.map(g => `<span class="dl-fm__when">${esc(this.rangeLabel(g.days))}</span> ${g.items.map(a => this.chip(a, g.days)).join('')}`).join('<i class="fa-solid fa-arrow-right dl-fm__arrow"></i>') + '<span class="dl-fm__badge">BERGANTI</span>'};
      return {html: full.map(a => this.chip(a, this.ALL)).join('') + groups.map(g => `<span class="dl-fm__when is-plus">+ ${esc(this.rangeLabel(g.days))}:</span> ${g.items.map(a => this.chip(a, g.days)).join('')}`).join('')};
    }

    rows() {
      const out = [];
      this.POS.forEach(p => (p.shifts?.length ? p.shifts : ['']).forEach(sh => {
        const list = this.slotOf(p.code, sh);
        const shown = this.st.mode === 'day' ? list.filter(a => this.onDay(a, this.st.day)) : list.filter(a => a.days.length);
        out.push({p, sh, list, shown});
      }));
      return out;
    }

    posEmpty(code, d) {
      return !this.asg.some(a => a.pos === code && (d == null ? a.days.length : this.onDay(a, d)));
    }

    unscheduled() {
      return this.PEOPLE.filter(p => !p.borongan && !this.asg.some(a => String(a.id) === String(p.id) && a.days.length));
    }

    drawStats() {
      const d = this.st.day, today = this.asg.filter(a => this.onDay(a, d));
      const empty = this.POS.filter(p => this.posEmpty(p.code, d));
      const cards = [['all', `Terjadwal · ${this.dayName(d)}`, new Set(today.map(a => String(a.id))).size, this.DAYS[d]?.date || '', '']]
        .concat(Object.entries(this.SH).map(([k, s]) => [k, `${s.label} · ${String(s.time || '').split('–')[0]}`, new Set(today.filter(a => a.shift === k).map(a => String(a.id))).size, '', {blue: '', amber: 'warn', slate: 'neutral'}[s.tone] ?? '']))
        .concat([['bor', 'Borongan dipanggil', new Set(today.filter(a => this.who[a.id]?.borongan).map(a => String(a.id))).size, '', 'violet'],
          ['empty', 'Unit kosong', empty.length, empty.map(p => p.name).join(', '), 'danger'],
          ['unsched', 'Mingguan belum terjadwal', this.unscheduled().length, '', 'success']]);
      $(this.el, '[data-fm-stats]').innerHTML = cards.map(([k, l, v, sub, tone]) => `<button type="button" class="dl-stat is-filter ${tone ? 'dl-stat--' + tone : ''} ${(this.st.filter || 'all') === k ? 'is-active' : ''}" data-fm-f="${k}"><span class="dl-stat__label">${esc(l)}</span><span class="dl-stat__value">${v}</span>${sub ? `<span class="dl-stat__sub">${esc(sub)}</span>` : ''}</button>`).join('');
    }

    drawBody() {
      let list = this.rows();
      const f = this.st.filter, d = this.st.mode === 'day' ? this.st.day : null;
      if (f && this.SH[f]) list = list.filter(r => r.sh === f);
      if (f === 'bor') list = list.filter(r => r.shown.some(a => this.who[a.id]?.borongan));
      if (f === 'empty') list = list.filter(r => this.posEmpty(r.p.code, this.st.day));
      if (this.st.q) list = list.filter(r => r.shown.some(a => (this.who[a.id]?.name || '').toLowerCase().includes(this.st.q)));
      const byPos = []; list.forEach(r => { const g = byPos[byPos.length - 1]; if (g && g.p === r.p) g.rows.push(r); else byPos.push({p: r.p, rows: [r]}); });
      $(this.el, '[data-fm-body]').innerHTML = byPos.map((g, gi) => {
        const noIdx = this.POS.indexOf(g.p) + 1, wholeEmpty = this.posEmpty(g.p.code, d);
        if (wholeEmpty && g.rows.every(r => !r.shown.length) && g.rows.length > 0 && (!g.p.shifts?.length)) {
          return `<tr class="is-empty"><td class="dl-fm__no">${noIdx}</td><td class="dl-fm__pos">${esc(g.p.name)}</td><td class="dl-fm__sh is-reg">—</td><td class="dl-fm__empty">Kosong ${d == null ? 'minggu ini' : 'hari ini'} <small>· klik ✎ untuk isi</small></td><td class="dl-fm__ed"><button type="button" data-fm-edit="${esc(g.p.code)}|" title="Ubah" aria-label="Ubah"><i class="fa-solid fa-pen"></i></button></td></tr>`;
        }
        return g.rows.map((r, i) => {
          const s = this.SH[r.sh] || {}, cell = this.st.mode === 'day' ? {html: r.shown.map(a => this.chip(a, [this.st.day])).join('')} : this.weekCell(r.shown);
          const sel = this.st.edit && this.st.edit.pos === g.p.code && this.st.edit.sh === r.sh;
          return `<tr class="${cell.swap ? 'is-swap' : ''} ${!r.shown.length ? 'is-empty' : ''} ${sel ? 'is-sel' : ''}">${i ? '' : `<td class="dl-fm__no" rowspan="${g.rows.length}">${noIdx}</td><td class="dl-fm__pos" rowspan="${g.rows.length}">${esc(g.p.name)}</td>`}<td class="dl-fm__sh is-${esc(s.tone || 'slate')}"><b>${esc(s.label || '—')}</b> <small>${esc(s.time || '')}</small></td><td class="dl-fm__who">${r.shown.length ? cell.html : `<span class="dl-fm__empty">Kosong ${d == null ? 'minggu ini' : 'hari ini'} <small>· klik ✎ untuk isi</small></span>`}</td><td class="dl-fm__ed"><button type="button" data-fm-edit="${esc(g.p.code)}|${esc(r.sh)}" title="Ubah" aria-label="Ubah"><i class="fa-solid fa-pen"></i></button></td></tr>`;
        }).join('');
      }).join('') || `<tr><td colspan="5" class="dl-fm__none">Tidak ada baris yang cocok.</td></tr>`;
      $(this.el, '[data-fm-colhead]').textContent = this.st.mode === 'day' ? `Yang bertugas (${this.dayName(this.st.day)}, ${this.DAYS[this.st.day]?.date || ''})` : `Yang bertugas (${this.dayName(0)}–${this.dayName(this.N - 1)}, ${this.DAYS[0]?.date || ''} – ${this.DAYS[this.N - 1]?.date || ''})`;
      $$(this.el, '[data-fm-mode] [data-m]').forEach(b => b.classList.toggle('is-active', b.dataset.m === this.st.mode));
      $(this.el, '[data-fm-days]').innerHTML = this.DAYS.map((x, i) => `<button type="button" class="dl-fm__day ${this.st.mode === 'day' && this.st.day === i ? 'is-active' : ''} ${i === +this.el.dataset.today ? 'is-today' : ''}" data-fm-day="${i}">${esc(x.key)} ${esc(String(x.date).split(' ')[0])}${i === +this.el.dataset.today ? ' · hari ini' : ''}</button>`).join('');
    }

    drawSide() {
      const side = $(this.el, '[data-fm-side]');
      if (!this.st.edit) {
        const un = this.unscheduled(), groups = {};
        un.forEach(p => { (groups[p.group || 'Lainnya'] ||= []).push(p); });
        side.innerHTML = `<div class="dl-fm__sidehead"><b>Belum terjadwal minggu ini</b><small>${un.length} orang · klik ✎ di baris untuk menempatkan</small></div><div class="dl-fm__sidebody">${Object.entries(groups).map(([g, ps]) => `<div class="dl-fm__grp">${esc(g)}</div><div>${ps.map(p => `<span class="dl-fm__chip">${esc(p.name)}</span>`).join('')}</div>`).join('') || '<p class="dl-fm__none">Semua sudah terjadwal.</p>'}</div>`;
        side.classList.toggle('is-flash', this.st.filter === 'unsched');
        return;
      }
      const e = this.st.edit, p = this.POS.find(x => x.code === e.pos), s = this.SH[e.sh];
      const taken = new Set(this.asg.filter(a => a.days.length).map(a => String(a.id))), inRow = new Set(e.rows.map(r => String(r.id)));
      const cand = this.PEOPLE.filter(x => !inRow.has(String(x.id)) && !taken.has(String(x.id)) && (!p.group || x.group === p.group || x.borongan));
      side.classList.remove('is-flash');
      side.innerHTML = `<div class="dl-fm__sidehead"><b>${esc(p.name)}${s ? ' · ' + esc(s.label) : ''}</b><small>Centang hari masuknya tiap orang</small></div>
        <table class="dl-fm__ed-t"><thead><tr><th>Nama</th>${this.DAYS.map(x => `<th>${esc(x.key[0] + (x.key[2] || ''))}</th>`).join('')}<th></th></tr></thead><tbody>${e.rows.map((r, i) => { const pp = this.who[r.id] || {name: r.id}; return `<tr><td>${this.certOk(pp, e.pos) ? '' : '<span class="dl-fm__warn" title="Belum bersertifikat unit ini">⚠</span> '}${esc(pp.name)}</td>${this.ALL.map(d => `<td><input type="checkbox" data-fm-cb="${i}|${d}" ${r.days.includes(d) ? 'checked' : ''} aria-label="${esc(pp.name)} ${esc(this.dayName(d))}"></td>`).join('')}<td><button type="button" class="dl-fm__x" data-fm-rm="${i}" aria-label="Lepas">✕</button></td></tr>`; }).join('') || `<tr><td colspan="${this.N + 2}" class="dl-fm__none">Belum ada orang.</td></tr>`}</tbody></table>
        <div class="dl-fm__sidebody">
          <div class="dl-grid__search"><i class="fa-solid fa-user-plus"></i><input type="search" list="dl-fm-dl-${this.el.dataset.uid}" placeholder="+ Tambah orang (cari nama)…" data-fm-add aria-label="Tambah orang"></div>
          <datalist id="dl-fm-dl-${this.el.dataset.uid}">${this.PEOPLE.filter(x => !inRow.has(String(x.id))).map(x => `<option value="${esc(x.name)}">`).join('')}</datalist>
          <div class="dl-fm__grp">Belum terjadwal · ${esc(p.group || 'semua')}</div>
          <div>${cand.map(x => `<button type="button" class="dl-fm__chip ${x.borongan ? 'is-bor' : ''} ${this.certOk(x, e.pos) ? '' : 'is-warn'}" data-fm-pick="${esc(x.id)}">${this.certOk(x, e.pos) ? '' : '⚠ '}${esc(x.name)}</button>`).join('') || '<span class="dl-fm__none">Tidak ada.</span>'}</div>
          <div class="dl-fm__sideact"><button type="button" class="dl-btn dl-btn--secondary dl-btn--sm" data-fm-cancel>Batal</button><button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-fm-apply>Simpan</button></div>
        </div>`;
    }

    draw() { this.drawStats(); this.drawBody(); this.drawSide(); this.drawStatus(); }

    drawStatus() {
      const pub = this.locked();
      $(this.el, '[data-fm-status]').textContent = pub ? 'PUBLISHED' : 'DRAFT';
      $(this.el, '[data-fm-status]').classList.toggle('is-pub', pub);
      $$(this.el, '[data-fm-publish],[data-fm-save],[data-fm-copy]').forEach(b => { b.disabled = pub; });
      this.el.classList.toggle('is-locked', pub);
    }

    _emit(n, detail) {
      return this.el.dispatchEvent(new CustomEvent('dl:formation-' + n, {bubbles: true, detail}));
    }

    addPerson(id) { if (!this.st.edit || this.st.edit.rows.some(r => String(r.id) === String(id))) return; this.st.edit.rows.push({id, days: [...this.ALL]}); this.drawSide(); }
  }
  Devlib.define("Formation", Formation);
})(window.Devlib);

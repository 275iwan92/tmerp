/* DevLib · Gantt */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, TZ, fmt, setField} = Devlib.h;
  /* ============================ GANTT ============================ */
  class Gantt extends Devlib.Component {
    static selector = "[data-dl-gantt]";
    init() {
      this.cfg = JSON.parse(this.el.dataset.config);
      this.rows = this.cfg.rows;
      this.tasks = this.cfg.tasks;
      this.cats = this.cfg.categories;
      this.PX = this.cfg.px;
      this.S = this.cfg.start;
      this.E = this.cfg.end;
      this.SNAP = this.cfg.snap / 60;
      this.board = $(this.el, '[data-g2-board]');
      this.changed = new Set();
      this.hi = null;
      this.fRes = '';
      this.fProc = '';
      this.pop = null;
      this.tasks.forEach(t => { t.s = this.h(t.start); t.e = t.end ? this.h(t.end) : t.s + (+t.duration || 1); });
      this.W = (this.E - this.S) * this.PX;
      this.op = null;
      this.board.addEventListener('pointerdown', e => {
        const b = e.target.closest('[data-g2-task]'); if (!b || e.button !== 0) return;
        const t = this.task(b.dataset.g2Task);
        this.closePop();
        if (this.live()) return;
        if (t.state === 'qchold') { this.op = {t, b, x0: e.clientX, locked: true}; return; }
        this.op = {t, b, x0: e.clientX, s0: t.s, e0: t.e, rz: !!e.target.closest('[data-g2-rz]'), moved: false};
        b.setPointerCapture(e.pointerId);
      });
      this.board.addEventListener('pointermove', e => {
        if (!this.op || this.op.locked) return;
        const dh = (e.clientX - this.op.x0) / this.PX; if (Math.abs(e.clientX - this.op.x0) > 3) this.op.moved = true; if (!this.op.moved) return;
        const sn = v => Math.round(v / this.SNAP) * this.SNAP;
        if (this.op.rz) this.op.t.e = Math.min(this.E, Math.max(this.op.t.s + this.SNAP, this.op.s0 + sn(this.op.e0 - this.op.s0 + dh)));
        else { const d = this.op.e0 - this.op.s0; this.op.t.s = Math.min(this.E - d, Math.max(this.S, sn(this.op.s0 + dh))); this.op.t.e = this.op.t.s + d; }
        this.op.b.style.left = (this.op.t.s - this.S) * this.PX + 'px'; this.op.b.style.width = (this.op.t.e - this.op.t.s) * this.PX + 'px';
        $(this.op.b, 'small').textContent = `${this.hm(this.op.t.s)}–${this.hm(this.op.t.e)}`;
      });
      this.board.addEventListener('pointerup', e => {
        if (!this.op) { const b = e.target.closest('[data-g2-task]'); if (b && this.live()) this.showPop(this.task(b.dataset.g2Task), b); return; }
        const o = this.op; this.op = null;
        if (o.locked) { if (Math.abs(e.clientX - o.x0) > 3) toast('QC Hold terkunci sampai QA memutuskan.', 'warn'); else this.showPop(o.t, o.b); return; }
        if (!o.moved) return this.showPop(o.t, o.b);
        if (o.t.s !== o.s0 || o.t.e !== o.e0) this.touch(o.t, o.rz ? 'resize' : 'move');
        this.render();
      });
      this.board.addEventListener('dblclick', e => {
        const b = e.target.closest('[data-g2-task]'); if (!b || this.live()) return;
        this.closePop();
        const t = this.task(b.dataset.g2Task);
        if (t.state === 'qchold') { toast('QC Hold terkunci sampai QA memutuskan.', 'warn'); return; }
        const ov = document.createElement('div'); ov.className = 'dl-mov is-on';
        ov.innerHTML = `<div class="dl-modal dl-modal--sm" role="dialog" aria-modal="true"><div class="dl-modal__head"><h3>Ubah Task</h3><button type="button" class="dl-modal__x" data-x>&times;</button></div>
          <div class="dl-modal__body dl-gantt__edit"><label><span>Kode</span><input value="${esc(t.label)}" disabled></label>
          <label><span>Resource</span><select data-e-row>${this.rows.map(r => `<option value="${esc(r.key)}" ${r.key === t.row ? 'selected' : ''}>${esc(r.label)}${r.section ? ' (' + esc(r.section.split(' ')[0].toLowerCase()) + ')' : ''}</option>`).join('')}</select></label>
          <label><span>Proses</span><select data-e-proc>${Object.entries(this.cats).map(([k, c]) => `<option value="${esc(k)}" ${k === t.process ? 'selected' : ''}>${esc(c.label)}</option>`).join('')}</select></label></div>
          <div class="dl-modal__foot"><button type="button" class="dl-btn dl-btn--ghost dl-btn--md" data-x>Batal</button><button type="button" class="dl-btn dl-btn--primary dl-btn--md" data-e-save>Simpan</button></div></div>`;
        ov.addEventListener('click', ev => {
          if (ev.target === ov || ev.target.closest('[data-x]')) return ov.remove();
          if (ev.target.closest('[data-e-save]')) { const nr = $(ov, '[data-e-row]').value, np = $(ov, '[data-e-proc]').value; if (nr !== t.row || np !== t.process) { t.row = nr; t.process = np; this.touch(t, 'reassign'); toast(`${t.label} dipindah ke ${this.rows.find(r => r.key === nr).label}.`, 'success'); } ov.remove(); this.render(); }
        });
        document.body.appendChild(ov); $(ov, '[data-e-row]').focus();
      });
      this.el.addEventListener('click', e => {
        const s = e.target.closest('[data-g2-hi]'); if (s) { const k = s.dataset.g2Hi; this.hi = k === 'all' || k === 'none' || this.hi === k ? null : k; return this.render(); }
        const c = e.target.closest('[data-g2-chip]'); if (c) { if (c.dataset.g2Chip !== 'proc') { this.fRes = ''; this.setSel('gantt_resource', ''); } if (c.dataset.g2Chip !== 'res') { this.fProc = ''; this.setSel('gantt_process', ''); } return this.render(); }
        const m = e.target.closest('[data-g2-mode] .dl-segment__opt'); if (m) { this.el.dataset.mode = m.dataset.value; $$(this.el, '[data-g2-mode] .dl-segment__opt').forEach(x => x.classList.toggle('is-active', x === m)); this.closePop(); return this.render(); }
        if (e.target.closest('[data-g2-submit]')) confirmBox({message: `Kirim ${this.changed.size} perubahan simulasi ke Master Schedule?`, tone: 'warn'}).then(ok => { if (!ok) return; this.el.dispatchEvent(new CustomEvent('dl:gantt-submit', {bubbles: true, detail: {tasks: this.tasks.filter(t => this.changed.has(t.id)).map(t => ({...t, start: this.hm(t.s), end: this.hm(t.e)}))}})); toast(`${this.changed.size} perubahan dikirim ke Master Schedule.`, 'success'); this.changed.clear(); this.render(); });
      });
      this.el.addEventListener('change', e => {
        if (e.target.name === 'gantt_resource') { this.fRes = e.target.value; this.render(); }
        if (e.target.name === 'gantt_process') { this.fProc = e.target.value; this.render(); }
      });
      document.addEventListener('pointerdown', e => { if (this.pop && !e.target.closest('.dl-gantt__pop,[data-g2-task]')) this.closePop(); });
      this.legend();this.render();
      this.el.dlGantt = {tasks: this.tasks, rows: this.rows, render: this.render, changed: this.changed};
    }
    h(t) { const [a, b] = String(t).split(':'); return +a + (+b || 0) / 60; }

    hm(x) {
      return `${String(Math.floor(x + 1e-9)).padStart(2, '0')}:${String(Math.round((x % 1) * 60) % 60).padStart(2, '0')}`;
    }

    live() {
      return this.el.dataset.mode === 'live';
    }

    catOf(t) {
      return this.cats[t.process] || {label: t.process || '', tone: 'accent'};
    }

    rowTasks(k) {
      return this.tasks.filter(t => t.row === k).sort((a, b) => a.s - b.s);
    }

    gaps(k) { const out = [], list = this.rowTasks(k); for (let i = 1; i < list.length; i++) { const a = Math.max(...list.slice(0, i).map(t => t.e)), b = list[i].s; if (b - a >= 1 - 1e-9) out.push([a, b]); } return out; }

    bigIdle(k) {
      return this.gaps(k).some(([a, b]) => b - a >= this.cfg.idle);
    }

    conflict(t) {
      return this.tasks.some(o => o !== t && o.row === t.row && o.s < t.e - 1e-9 && o.e > t.s + 1e-9);
    }

    stats() {
      const risk = this.tasks.filter(t => t.risk).length, idle = this.rows.filter(r => this.bigIdle(r.key)).length;
      $(this.el, '[data-g2-stats]').innerHTML = [
        ['all', 'Total Task Aktif', this.tasks.length, 'accent', 'klik untuk reset sorotan'],
        ['none', 'Belum Terjadwal', this.cfg.unscheduled, 'danger', '—'],
        ['risk', 'Berisiko Telat', risk, 'warn', 'klik untuk sorot di board'],
        ['idle', `Resource Kosong >${this.cfg.idle} jam`, idle, 'info', 'klik untuk sorot di board'],
      ].map(([k, l, v, tone, sub]) => `<button type="button" class="dl-stat dl-stat--${tone} is-filter ${this.hi === k ? 'is-active' : ''}" data-g2-hi="${k}"><span class="dl-stat__label">${esc(l)}</span><span class="dl-stat__value">${v}</span><span class="dl-stat__sub">${esc(sub)}</span></button>`).join('');
    }

    legend() {
      $(this.el, '[data-g2-legend]').innerHTML = Object.entries(this.cats).map(([k, c]) => `<span><i class="dl-gantt__sw dl-gantt--${esc(c.tone)}"></i>${esc(c.label)}</span>`).join('')
        + '<span class="dl-gantt__lsep"></span><span><i class="dl-gantt__sw dl-gantt--breakdown"></i>Breakdown</span><span><i class="dl-gantt__sw is-qchold"></i>QC Hold</span><span><i class="dl-gantt__sw is-hot"></i>🔥 Hot Job</span><span class="dl-gantt__lsep"></span><span>▬ garis dalam blok = progress</span>';
    }

    block(t) {
      const c = this.catOf(t), cls = t.state === 'breakdown' ? 'breakdown' : c.tone;
      const dim = this.hi && this.hi !== 'all' && !(this.hi === 'risk' ? t.risk : this.hi === 'idle' ? this.bigIdle(t.row) : false);
      return `<div class="dl-gantt__blk dl-gantt--${esc(cls)} ${t.state === 'hot' ? 'is-hot' : ''} ${t.state === 'qchold' ? 'is-qchold' : ''} ${this.conflict(t) ? 'is-conflict' : ''} ${dim ? 'is-dim' : ''} ${this.hi && !dim && this.hi !== 'all' ? 'is-match' : ''} ${this.changed.has(t.id) ? 'is-changed' : ''}"
        style="left:${(t.s - this.S) * this.PX}px;width:${Math.max(0.25, t.e - t.s) * this.PX}px" data-g2-task="${esc(t.id)}" title="${esc(t.label)} · ${this.hm(t.s)}–${this.hm(t.e)}">
        <b>${t.state === 'hot' ? '🔥 ' : t.state === 'breakdown' ? '⚠ ' : t.state === 'qchold' ? '🔍 ' : ''}${esc(t.label)}${t.state !== 'breakdown' && c.label ? ' · ' + esc(c.label) : ''}</b>
        <small>${this.hm(t.s)}–${this.hm(t.e)}</small><span class="dl-gantt__prog"><span style="width:${+t.progress || 0}%"></span></span>
        ${t.state === 'qchold' || this.live() ? '' : '<span class="dl-gantt__rz" data-g2-rz></span>'}</div>`;
    }

    render() {
      const vis = this.rows.filter(r => (!this.fRes || r.key === this.fRes) && (!this.fProc || this.rowTasks(r.key).some(t => t.process === this.fProc)));
      let html = `<div class="dl-gantt__row dl-gantt__head"><div class="dl-gantt__res">${esc(this.cfg.resourceLabel)}</div><div class="dl-gantt__lane" style="width:${this.W}px">${Array.from({length: this.E - this.S + 1}, (_, i) => `<span style="left:${i * this.PX}px">${String(this.S + i).padStart(2, '0')}:00</span>`).join('')}</div></div>`;
      let sec = null;
      vis.forEach(r => {
        if (r.section && r.section !== sec) { sec = r.section; html += `<div class="dl-gantt__sec">${esc(sec)}</div>`; }
        const list = this.rowTasks(r.key).filter(t => !this.fProc || t.process === this.fProc);
        html += `<div class="dl-gantt__row" data-g2-row="${esc(r.key)}"><div class="dl-gantt__res"><b>${esc(r.label)}</b>${r.sub ? `<small><i class="fa-solid fa-user"></i> ${esc(r.sub)}</small>` : ''}</div>
          <div class="dl-gantt__lane" style="width:${this.W}px;background-size:${this.PX}px 100%">${this.fProc ? '' : this.gaps(r.key).map(([a, b]) => `<div class="dl-gantt__idle" style="left:${(a - this.S) * this.PX}px;width:${(b - a) * this.PX}px">idle</div>`).join('')}${list.map(this.block).join('')}</div></div>`;
      });
      if (!vis.length) html += '<div class="dl-empty"><b class="dl-empty__title">Tidak ada resource yang cocok dengan filter</b></div>';
      const now = new Date(new Date().toLocaleString('en-US', {timeZone: TZ()})), nh = now.getHours() + now.getMinutes() / 60;
      if (nh >= this.S && nh <= this.E) html += `<div class="dl-gantt__now" style="left:calc(var(--dl-gantt-res) + ${(nh - this.S) * this.PX}px)" title="Sekarang ${fmt.time()}"></div>`;
      this.board.innerHTML = html;
      this.stats();
      const chips = [...(this.fRes ? [['res', 'Resource: ' + this.rows.find(r => r.key === this.fRes).label]] : []), ...(this.fProc ? [['proc', 'Proses: ' + this.catOf({process: this.fProc}).label]] : [])];
      const cb = $(this.el, '[data-g2-chips]'); cb.hidden = !chips.length;
      cb.innerHTML = chips.map(([k, l]) => `<span class="dl-tags__chip">${esc(l)}<button type="button" data-g2-chip="${k}">&times;</button></span>`).join('') + (chips.length ? '<button type="button" class="dl-btn dl-btn--link" data-g2-chip="all">Reset semua filter</button>' : '');
      const sb = $(this.el, '[data-g2-submit]'); if (sb) { sb.disabled = !this.changed.size || this.live(); sb.dataset.count = this.changed.size; }
      $(this.el, '[data-g2-modehelp]').textContent = this.live() ? 'Mode Live: jadwal hanya bisa dilihat.' : `Mode Simulasi: ${this.changed.size} perubahan belum dikirim ke jadwal Live sampai Submit. QC Hold terkunci sampai QA memutuskan.`;
    }

    setSel(name, v) { const f = $(this.el, `[name="${name}"]`); if (f) setField(this.el, name, v); }

    task(id) {
      return this.tasks.find(t => String(t.id) === String(id));
    }

    touch(t, type) { this.changed.add(t.id); this.el.dispatchEvent(new CustomEvent('dl:gantt', {bubbles: true, detail: {type, task: {...t, start: this.hm(t.s), end: this.hm(t.e)}}})); if (this.conflict(t)) toast(`${t.label} bertabrakan dengan task lain di resource yang sama.`, 'warn'); }
    closePop() { if (this.pop) { this.pop.remove(); this.pop = null; } }

    showPop(t, b) {
      this.closePop();
      this.pop = document.createElement('div'); this.pop.className = 'dl-gantt__pop';
      this.pop.innerHTML = `<button type="button" class="dl-gantt__popx" aria-label="Tutup">&times;</button><h3>${esc(t.label)}</h3><dl><dt>Resource</dt><dd>${esc(this.rows.find(r => r.key === t.row)?.label || '')}</dd><dt>Proses</dt><dd>${esc(this.catOf(t).label)}</dd><dt>Waktu</dt><dd>${this.hm(t.s)}–${this.hm(t.e)}</dd><dt>Progress</dt><dd>${+t.progress || 0}%</dd><dt>Status</dt><dd>${esc(t.status || {hot: 'Hot Job', qchold: 'QC Hold', breakdown: 'Breakdown'}[t.state] || 'Terjadwal')}</dd></dl>`;
      const r = b.getBoundingClientRect(); this.pop.style.cssText = `left:${Math.min(r.left + scrollX, innerWidth - 260)}px;top:${r.bottom + scrollY + 6}px`;
      this.pop.querySelector('.dl-gantt__popx').addEventListener('click', this.closePop);
      document.body.appendChild(this.pop);
    }
  }
  Devlib.define("Gantt", Gantt);
})(window.Devlib);

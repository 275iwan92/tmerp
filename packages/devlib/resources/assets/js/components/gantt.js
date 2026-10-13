/*
 * REMARK
 * Tujuan       : Perilaku papan Gantt: seret/resize/klik ganda, panel dampak, filter, mode linked (Process lalu Machine, sumbu dipas).
 * Sumber hulu  : config JSON dari Gantt.php (rows, tasks, linked).
 * Input        : Event pointer/dblclick, perubahan dropdown gantt_process dan gantt_machine_*.
 * Output       : Render board, POST impact/move, event dl:gantt, event dl:plan-sync (posisi bar pratinjau/berubah untuk tabel di halaman).
 * Dipakai oleh : Semua x-dl::gantt.
 * Status       : Aktif (DevLib).
 */
/* DevLib · Gantt */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, TZ, fmt, setField, T, Tf, NUM} = Devlib.h;
  const GATES = [['material', 'gt_gate_material'], ['tooling', 'gt_gate_tooling'], ['artwork', 'gt_gate_artwork']];
  const GATE_STATE = {ok: 'gt_gate_ok', wait: 'gt_gate_wait', block: 'gt_gate_block'};
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
      this.L = this.cfg.linked || null;   // cascading Process -> Machine filters
      if (this.L) { this.fProc = this.L.process || ''; this.fRes = this.L.machine || ''; }
      this.pop = null;
      this.origin = this.cfg.originDate ? this.cfg.originDate.split('-').map(Number) : null;  // [y, m, d] = the date hour 0 means
      this.LK = this.cfg.lockUntil != null && this.cfg.lockUntil !== '' ? this.h(this.cfg.lockUntil) : null;  // lock window end (hour)
      this.pending = null;  // a moved block waiting for the server check / the user's decision
      this.impact = null;
      this.tasks.forEach(t => {
        t.s = this.h(t.start); t.e = t.end ? this.h(t.end) : t.s + (+t.duration || 1);
        t.over = t.forecastEnd != null && t.forecastEnd !== '' ? Math.max(0, this.h(t.forecastEnd) - t.e) : 0;  // overrun past the planned end (hours)
      });
      this.W = (this.E - this.S) * this.PX;
      this.op = null;
      this.board.addEventListener('pointerdown', e => {
        const b = e.target.closest('[data-g2-task]'); if (!b || e.button !== 0) return;
        const t = this.task(b.dataset.g2Task);
        this.closePop();
        if (this.live() || this.pending) return;
        if (t.state === 'qchold') { this.op = {t, b, x0: e.clientX, locked: true, msg: 'gt_qc_locked'}; return; }
        if (this.lockedFor(t)) { this.op = {t, b, x0: e.clientX, locked: true, msg: 'gt_task_locked'}; return; }
        this.op = {t, b, x0: e.clientX, y0: e.clientY, s0: t.s, e0: t.e, row0: t.row, target: t.row, rz: !!e.target.closest('[data-g2-rz]'), moved: false};
        b.setPointerCapture(e.pointerId);
      });
      this.board.addEventListener('pointermove', e => {
        if (!this.op || this.op.locked) return;
        const o = this.op, dh = (e.clientX - o.x0) / this.PX, dy = e.clientY - o.y0;
        if (Math.abs(e.clientX - o.x0) > 3 || Math.abs(dy) > 3) o.moved = true;
        if (!o.moved) return;
        const sn = v => Math.round(v / this.SNAP) * this.SNAP;
        if (o.rz) o.t.e = Math.min(this.E, Math.max(o.t.s + this.SNAP, o.s0 + sn(o.e0 - o.s0 + dh)));
        else { const d = o.e0 - o.s0; o.t.s = Math.min(this.E - d, Math.max(this.minStart(o.t), sn(o.s0 + dh))); o.t.e = o.t.s + d; }
        this.place(o.t, o.b);
        if (!o.rz) { o.b.style.transform = `translateY(${dy}px)`; o.b.style.zIndex = 6; o.target = this.rowAt(e.clientY) ?? o.row0; this.markRows(o.t, o.target); }
      });
      this.board.addEventListener('pointerup', e => {
        if (!this.op) { const b = e.target.closest('[data-g2-task]'); if (b && this.live()) this.showPop(this.task(b.dataset.g2Task), b); return; }
        const o = this.op; this.op = null;
        if (o.locked) { if (Math.abs(e.clientX - o.x0) > 3) toast(T(o.msg), 'warn'); else this.showPop(o.t, o.b); return; }
        if (!o.moved) return this.showPop(o.t, o.b);
        if (o.target !== o.row0 && !this.canRun(o.t, o.target)) { o.t.s = o.s0; o.t.e = o.e0; toast(T('gt_not_eligible'), 'warn'); return this.render(); }
        o.t.row = o.target;
        if (o.t.s === o.s0 && o.t.e === o.e0 && o.t.row === o.row0) return this.render();
        this.commit(o.t, {row: o.row0, s: o.s0, e: o.e0, process: o.t.process}, o.t.row !== o.row0 ? 'reassign' : o.rz ? 'resize' : 'move');
      });
      this.board.addEventListener('dblclick', e => {
        const b = e.target.closest('[data-g2-task]'); if (!b || this.live() || this.pending) return;
        this.closePop();
        const t = this.task(b.dataset.g2Task);
        if (t.state === 'qchold') { toast(T('gt_qc_locked'), 'warn'); return; }
        if (this.lockedFor(t)) { toast(T('gt_task_locked'), 'warn'); return; }
        const ov = document.createElement('div'); ov.className = 'dl-mov is-on';
        ov.innerHTML = `<div class="dl-modal dl-modal--sm" role="dialog" aria-modal="true"><div class="dl-modal__head"><h3>${esc(T('gt_edit_task'))}</h3><button type="button" class="dl-modal__x" data-x>&times;</button></div>
          <div class="dl-modal__body dl-gantt__edit"><label><span>${esc(T('gt_code'))}</span><input value="${esc(t.label)}" disabled></label>
          <label><span>Resource</span><select data-e-row>${this.rows.filter(r => !this.L || r.key === t.row || this.canRun(t, r.key)).map(r => `<option value="${esc(r.key)}" ${r.key === t.row ? 'selected' : ''}>${esc(r.label)}${r.section ? ' (' + esc(r.section.split(' ')[0].toLowerCase()) + ')' : ''}</option>`).join('')}</select></label>
          <label ${this.L ? 'hidden' : ''}><span>${esc(T('gt_process'))}</span><select data-e-proc>${Object.entries(this.cats).map(([k, c]) => `<option value="${esc(k)}" ${k === t.process ? 'selected' : ''}>${esc(c.label)}</option>`).join('')}</select></label></div>
          <div class="dl-modal__foot"><button type="button" class="dl-btn dl-btn--ghost dl-btn--md" data-x>Cancel</button><button type="button" class="dl-btn dl-btn--primary dl-btn--md" data-e-save>Save</button></div></div>`;
        ov.addEventListener('click', ev => {
          if (ev.target === ov || ev.target.closest('[data-x]')) return ov.remove();
          if (ev.target.closest('[data-e-save]')) {
            const nr = $(ov, '[data-e-row]').value, np = $(ov, '[data-e-proc]').value;
            if (nr !== t.row && !this.canRun(t, nr)) { toast(T('gt_not_eligible'), 'warn'); return; }
            if (nr !== t.row || np !== t.process) {
              const orig = {row: t.row, s: t.s, e: t.e, process: t.process}; t.row = nr; t.process = np;
              this.commit(t, orig, 'reassign'); toast(Tf('gt_moved', {task: t.label, res: this.rows.find(r => r.key === nr).label}), 'success');
            }
            ov.remove(); this.render();
          }
        });
        document.body.appendChild(ov); $(ov, '[data-e-row]').focus();
      });
      this.el.addEventListener('click', e => {
        const s = e.target.closest('[data-g2-hi]'); if (s) { const k = s.dataset.g2Hi; this.hi = k === 'all' || k === 'none' || this.hi === k ? null : k; return this.render(); }
        const c = e.target.closest('[data-g2-chip]'); if (c) { if (c.dataset.g2Chip !== 'proc') { this.fRes = ''; this.setSel('gantt_resource', ''); } if (c.dataset.g2Chip !== 'res') { this.fProc = ''; this.setSel('gantt_process', ''); } return this.render(); }
        const m = e.target.closest('[data-g2-mode] .dl-segment__opt'); if (m) { this.el.dataset.mode = m.dataset.value; $$(this.el, '[data-g2-mode] .dl-segment__opt').forEach(x => x.classList.toggle('is-active', x === m)); this.closePop(); return this.render(); }
        if (e.target.closest('[data-g2-bulk]')) return this.bulkApply();
        if (e.target.closest('[data-g2-submit]')) confirmBox({message: Tf('gt_ask_submit', {n: this.changed.size}), tone: 'warn'}).then(ok => { if (!ok) return; this.el.dispatchEvent(new CustomEvent('dl:gantt-submit', {bubbles: true, detail: {tasks: this.tasks.filter(t => this.changed.has(t.id)).map(t => ({...t, start: this.hm(t.s), end: this.hm(t.e)}))}})); toast(Tf('gt_submitted', {n: this.changed.size}), 'success'); this.changed.clear(); this.render(); });
      });
      this.el.addEventListener('change', e => {
        if (e.target.name === 'gantt_resource') { this.fRes = e.target.value; this.render(); }
        if (e.target.name === 'gantt_process' && this.L) {
          // the machine list follows the process: show the select of this process, preset its default machine
          this.fProc = e.target.value;
          $$(this.el, '[data-g2-mach]').forEach(w => { w.hidden = w.dataset.g2Mach !== this.fProc; });
          this.fRes = (this.L.defaults || {})[this.fProc] || '';
          const sel = $(this.el, `[data-g2-mach="${this.fProc}"] select`); if (sel) setField(this.el, sel.name, this.fRes);
          this.render(); return;
        }
        if (e.target.name && e.target.name.startsWith('gantt_machine_')) { this.fRes = e.target.value; this.render(); return; }
        if (e.target.name === 'gantt_process') { this.fProc = e.target.value; this.render(); }
      });
      document.addEventListener('pointerdown', e => { if (this.pop && !e.target.closest('.dl-gantt__pop,[data-g2-task]')) this.closePop(); });
      document.addEventListener('keydown', e => { if (e.key === 'Escape' && this.impact) this.revert(); });
      this.legend();this.render();
      this.el.dlGantt = {tasks: this.tasks, rows: this.rows, render: this.render, changed: this.changed, setDraft: this.setDraft};
    }
    h(t) { const [a, b] = String(t).split(':'); return +a + (+b || 0) / 60; }

    hm(x) {
      return `${String(Math.floor(x + 1e-9)).padStart(2, '0')}:${String(Math.round((x % 1) * 60) % 60).padStart(2, '0')}`;
    }

    // Time label for people: with an origin date "Thu 08:30" (hour modulo 24), otherwise plain HH:MM.
    lbl(x) {
      if (!this.origin) return this.hm(x);
      const day = Math.floor((x + 1e-9) / 24);
      return `${this.dayName(day)} ${this.hm(x - day * 24)}`;
    }

    // Date of day n after the origin (UTC math, so no time zone drift).
    dayDate(n) {
      return new Date(Date.UTC(this.origin[0], this.origin[1] - 1, this.origin[2] + n));
    }

    dayName(n, full = false) {
      return this.dayDate(n).toLocaleDateString(NUM.lang === 'id' ? 'id-ID' : 'en-GB', full ? {weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC'} : {weekday: 'short', timeZone: 'UTC'});
    }

    live() {
      return this.el.dataset.mode === 'live';
    }

    // Locked tasks cannot be dragged: explicit locked:true, or starting before the lock window ends (hot / movable tasks excepted).
    lockedFor(t) {
      if (t.locked === true) return true;
      return this.LK != null && !t.movable && t.state !== 'hot' && t.s < this.LK - 1e-9;
    }

    minStart(t) {
      return this.LK != null && !t.movable && t.state !== 'hot' ? Math.max(this.S, this.LK) : this.S;
    }

    canRun(t, key) {
      return !Array.isArray(t.eligible) || t.eligible.includes(key);
    }

    // Row under the pointer, found by geometry (the dragged block covers the lane, so hit testing would return the old row).
    rowAt(y) {
      const el = $$(this.board, '[data-g2-row]').find(r => { const b = r.getBoundingClientRect(); return y >= b.top && y < b.bottom; });
      return el ? el.dataset.g2Row : null;
    }

    markRows(t, over) {
      $$(this.board, '[data-g2-row]').forEach(r => {
        const ok = this.canRun(t, r.dataset.g2Row) || r.dataset.g2Row === t.row;
        r.classList.toggle('is-elig', ok); r.classList.toggle('is-nelig', !ok); r.classList.toggle('is-over', r.dataset.g2Row === over);
      });
    }

    // Position a block (and its forecast tail) from the task's current times.
    place(t, b) {
      b.style.left = (t.s - this.S) * this.PX + 'px'; b.style.width = Math.max(0.25, t.e - t.s) * this.PX + 'px';
      $(b, 'small').textContent = `${this.lbl(t.s)}–${this.lbl(t.e)}`;
      const fc = $(this.board, `[data-g2-fc="${t.id}"]`); if (fc) { fc.style.left = (t.e - this.S) * this.PX + 'px'; fc.style.width = this.fcWidth(t) + 'px'; }
    }

    fcWidth(t) {
      return Math.max(0, Math.min(t.over, this.E - t.e)) * this.PX;
    }

    fcText(t) {
      return t.over < 1 ? Tf('gt_over_min', {n: Math.round(t.over * 60)}) : Tf('gt_over_h', {n: t.over.toFixed(1)});
    }

    setDraft(text) {
      const d = $(this.el, '[data-g2-draft]'); if (d) d.textContent = text;
    }

    /* ---------- server check before commit (impactUrl / moveUrl) ---------- */
    // Without impactUrl the change is committed at once (old behaviour); with it the server judges the move first.
    // A block dropped on another one in the same row slides to the next free place (never before the lock).
    snapToGap(t) {
      const d = t.e - t.s, rest = this.tasks.filter(o => o !== t && o.row === t.row).sort((a, b) => a.s - b.s);
      let s = t.s;
      for (const o of rest) { if (s < o.e - 1e-9 && s + d > o.s + 1e-9) s = o.e; }
      s = Math.min(Math.max(s, this.minStart(t)), this.E - d);
      t.s = s; t.e = s + d;
    }

    commit(t, orig, type) {
      if (type !== 'resize') this.snapToGap(t);
      if (!this.cfg.impactUrl) { this.touch(t, type); return this.render(); }
      return this.check(t, orig, type);
    }

    async post(url, t, reason) {
      const r = await fetch(url, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.DL_CSRF || document.querySelector('meta[name="csrf-token"]')?.content || ''},
        body: JSON.stringify({task: t.id, row: t.row, start: this.hm(t.s), end: this.hm(t.e), ...(reason ? {reason} : {})})});
      const body = await r.json().catch(() => ({}));
      if (body.ok === undefined) body.ok = r.ok;
      if (!body.ok && !body.reason) body.reason = body.message || r.statusText;
      return body;
    }

    async check(t, orig, type) {
      this.pending = {t, orig, type, checking: true}; this.render();
      let res;
      try { res = await this.post(this.cfg.impactUrl, t); } catch (e) { this.revert(); return toast(String(e.message || T('gt_check_failed')), 'danger'); }
      if (!this.pending || this.pending.t !== t) return;
      if (!res.ok) { this.revert(); return toast(res.reason || T('gt_check_failed'), 'warn'); }
      if (res.row != null) t.row = res.row;
      if (res.start != null) t.s = this.h(res.start);
      if (res.end != null) t.e = this.h(res.end);
      this.pending.checking = false; this.pending.res = res; this.previewMoves(res.moves || []); this.syncSheet(t.id, res.sheet); this.render(); this.showImpact();
    }

    // Connected preview: operations the server says would move are placed at their new time/row (outlined), the old place stays as a ghost.
    previewMoves(moves) {
      this.clearPreview();
      this.ghosts = []; this.previewed = [];
      moves.forEach(m => {
        const t = this.task(m.id); if (!t || t.locked === true) return;
        this.previewed.push({t, row: t.row, s: t.s, e: t.e});
        this.ghosts.push({row: t.row, s: t.s, e: t.e});
        t.row = m.row; t.s = this.h(m.start); t.e = this.h(m.end); t.previewed = true;
        this.syncSheet(m.id, m.sheet);
      });
    }

    // Tell tables on the page (BOP sheet) where an operation sits now; null = back to what the page was loaded with.
    syncSheet(id, cells) {
      this.el.dispatchEvent(new CustomEvent('dl:plan-sync', {bubbles: true, detail: {id, cells: cells || null}}));
    }

    clearPreview() {
      (this.previewed || []).forEach(o => { this.syncSheet(o.t.id, null); o.t.row = o.row; o.t.s = o.s; o.t.e = o.e; delete o.t.previewed; });
      this.previewed = []; this.ghosts = [];
    }

    // Bulk button (e.g. APPLY ALL WOs): the Work Orders that have a Draft bar on the process / machine on show, asked first, then done one by one on the server.
    async bulkApply() {
      const keys = this.L ? ((this.L.machines || {})[this.fProc] || []).filter(k => !this.fRes || k === this.fRes) : this.rows.map(r => r.key);
      const wos = [...new Set(this.tasks.filter(t => keys.includes(t.row) && (!this.fProc || t.process === this.fProc) && !t.locked && !t.live).map(t => t.short || t.label))].sort();
      if (!wos.length) return toast(T('s_no_changes'), 'warn');
      const ok = await confirmBox({message: wos.length + ' · ' + wos.join(', '), tone: 'warn'}); if (!ok) return;
      const r = await fetch(this.cfg.bulkUrl, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.DL_CSRF || document.querySelector('meta[name="csrf-token"]')?.content || ''},
        body: JSON.stringify({process: this.fProc, machine: this.fRes})});
      const j = await r.json().catch(() => ({}));
      if (!r.ok || j.error) return toast(j.error || T('s_save_failed'), 'danger');
      await confirmBox({message: (j.results || []).map(x => x.wo + ': ' + (x.ok ? 'OK' : x.message)).join('\n'), tone: (j.results || []).every(x => x.ok) ? 'info' : 'warn'});
      location.reload();
    }

    revert() {
      this.clearPreview();
      const p = this.pending; this.pending = null; this.closeImpact();
      if (p) { this.syncSheet(p.t.id, null); p.t.row = p.orig.row; p.t.s = p.orig.s; p.t.e = p.orig.e; p.t.process = p.orig.process; }
      this.render();
    }

    async accept() {
      const p = this.pending; if (!p || p.checking) return;
      if (this.cfg.moveUrl) {
        const reason = ($(this.impact, '[data-g2-imp-reason]')?.value || '').trim();
        if (this.cfg.reasonRequired && !reason) { $(this.impact, '[data-g2-imp-reason]')?.focus(); return toast(T('gt_reason_needed'), 'warn'); }
        p.checking = true; $(this.impact, '[data-g2-imp-ok]').disabled = true;
        let res;
        try { res = await this.post(this.cfg.moveUrl, p.t, reason); } catch (e) { this.revert(); return toast(String(e.message || T('gt_check_failed')), 'danger'); }
        if (!res.ok) { this.revert(); return toast(res.reason || T('gt_check_failed'), 'warn'); }
        if (res.draft) this.setDraft(res.draft);
        if (res.reload) { toast(T('gt_replanned'), 'success'); setTimeout(() => location.reload(), 600); }
      }
      this.pending = null; this.closeImpact(); this.clearPreview();
      this.touch(p.t, p.type); this.render();
    }

    closeImpact() { if (this.impact) { this.impact.remove(); this.impact = null; } }

    showImpact() {
      this.closeImpact();
      const p = this.pending, b = p && $(this.board, `[data-g2-task="${p.t.id}"]`); if (!b) return;
      const lines = p.res.lines || [];
      this.impact = document.createElement('div'); this.impact.className = 'dl-gantt__imp';
      this.impact.innerHTML = `<h3>${esc(T('gt_impact_title'))}</h3><p>${esc(p.t.label)} · ${esc(this.lbl(p.t.s))}–${esc(this.lbl(p.t.e))}</p>${lines.length ? `<ul>${lines.map(l => `<li class="is-${esc(l.tone || 'ok')}"><span>${esc(l.label)}</span><b>${esc(l.value)}</b></li>`).join('')}</ul>` : ''}${this.cfg.reasonRequired ? `<input type="text" class="dl-gantt__reason" data-g2-imp-reason maxlength="190" placeholder="${esc(T('gt_reason'))}">` : ''}
        <div class="dl-gantt__impf"><button type="button" class="dl-btn dl-btn--ghost dl-btn--md" data-g2-imp-x>${esc(T('gt_cancel'))}</button><button type="button" class="dl-btn dl-btn--primary dl-btn--md" data-g2-imp-ok>${esc(this.cfg.moveLabel || T('gt_move_draft'))}</button></div>`;
      $(this.impact, '[data-g2-imp-x]').addEventListener('click', this.revert);
      $(this.impact, '[data-g2-imp-ok]').addEventListener('click', this.accept);
      if (p.res.no_change) { $(this.impact, '[data-g2-imp-ok]').disabled = true; const rs = $(this.impact, '[data-g2-imp-reason]'); if (rs) rs.hidden = true; }
      const r = b.getBoundingClientRect(); this.impact.style.cssText = `left:${Math.max(8, Math.min(r.left + scrollX, innerWidth - 300))}px;top:${r.bottom + scrollY + 6}px`;
      document.body.appendChild(this.impact);
    }

    /* ---------- board ---------- */
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
        ['all', T('gt_stat_all'), this.tasks.length, 'accent', T('gt_sub_reset')],
        ['none', T('gt_stat_none'), this.cfg.unscheduled, 'danger', '—'],
        ['risk', T('gt_stat_risk'), risk, 'warn', T('gt_sub_hi')],
        ['idle', Tf('gt_stat_idle', {n: this.cfg.idle}), idle, 'info', T('gt_sub_hi')],
      ].map(([k, l, v, tone, sub]) => `<button type="button" class="dl-stat dl-stat--${tone} is-filter ${this.hi === k ? 'is-active' : ''}" data-g2-hi="${k}"><span class="dl-stat__label">${esc(l)}</span><span class="dl-stat__value">${v}</span><span class="dl-stat__sub">${esc(sub)}</span></button>`).join('');
    }

    legend() {
      $(this.el, '[data-g2-legend]').innerHTML = Object.entries(this.cats).map(([k, c]) => `<span><i class="dl-gantt__sw dl-gantt--${esc(c.tone)}"></i>${esc(c.label)}</span>`).join('')
        + '<span class="dl-gantt__lsep"></span><span><i class="dl-gantt__sw dl-gantt--breakdown"></i>Breakdown</span><span><i class="dl-gantt__sw is-qchold"></i>QC Hold</span><span><i class="dl-gantt__sw is-hot"></i>🔥 Hot Job</span><span class="dl-gantt__lsep"></span><span>▬ ' + esc(T('gt_legend_progress')) + '</span>';
    }

    gatesHtml(t) {
      if (!Array.isArray(t.gates) || !t.gates.length) return '';
      return `<span class="dl-gantt__gates">${GATES.map(([k, key], i) => t.gates[i] ? `<i class="is-${esc(t.gates[i])}" title="${esc(T(key))} · ${esc(T(GATE_STATE[t.gates[i]] || 'gt_gate_ok'))}"></i>` : '').join('')}</span>`;
    }

    block(t) {
      const c = this.catOf(t), cls = t.state === 'breakdown' ? 'breakdown' : c.tone;
      const dim = t.muted === true || this.hi && this.hi !== 'all' && !(this.hi === 'risk' ? t.risk : this.hi === 'idle' ? this.bigIdle(t.row) : false);
      const gates = Array.isArray(t.gates) ? t.gates : [], p = this.pending && this.pending.t === t ? this.pending : null;
      const setupPx = Math.min((t.e - t.s) * this.PX, (+t.setup || 0) / 60 * this.PX);
      const wide = (t.e - t.s) * this.PX >= 150;   // a narrow bar shows only its short identity (the Work Order number), the full detail is in the tooltip
      const flags = [t.state === 'hot' ? 'is-hot' : '', t.state === 'qchold' ? 'is-qchold' : '', this.conflict(t) ? 'is-conflict' : '', dim ? 'is-dim' : '', this.hi && !dim && this.hi !== 'all' ? 'is-match' : '',
        this.changed.has(t.id) ? 'is-changed' : '', this.lockedFor(t) && t.state !== 'qchold' ? 'is-locked' : '', gates.includes('block') ? 'has-gate-block' : gates.includes('wait') ? 'has-gate-wait' : '',
        gates.length ? 'has-gates' : '', t.clash ? 'is-clash' : '', t.previewed ? 'is-previewed' : '', t.queued ? 'is-queued' : '', t.mine ? 'is-mine' : '', t.live ? 'is-live' : '', p ? (p.checking ? 'is-checking' : 'is-pending') : ''].filter(Boolean).join(' ');
      return `<div class="dl-gantt__blk dl-gantt--${esc(cls)} ${flags}"
        style="left:${(t.s - this.S) * this.PX}px;width:${Math.max(0.25, t.e - t.s) * this.PX}px" data-g2-task="${esc(t.id)}" title="${esc(t.detail || t.label)} · ${esc(this.lbl(t.s))}–${esc(this.lbl(t.e))}">
        ${setupPx > 0 ? `<span class="dl-gantt__setup" style="width:${setupPx}px">${setupPx >= 40 ? 'S ' + (+t.setup) : ''}</span>` : ''}
        <b>${t.state === 'hot' ? '🔥 ' : t.state === 'breakdown' ? '⚠ ' : t.state === 'qchold' ? '🔍 ' : ''}${this.lockedFor(t) && t.state !== 'qchold' ? '<i class="fa-solid fa-lock dl-gantt__lk"></i> ' : ''}${wide ? esc(t.label) + (t.state !== 'breakdown' && c.label ? ' · ' + esc(c.label) : '') : esc(t.short || t.label)}</b>
        <small ${wide || !t.short ? '' : 'hidden'}>${esc(this.lbl(t.s))}–${esc(this.lbl(t.e))}</small>${this.gatesHtml(t)}<span class="dl-gantt__prog"><span style="width:${+t.progress || 0}%"></span></span>
        ${t.state === 'qchold' || this.live() || this.lockedFor(t) ? '' : '<span class="dl-gantt__rz" data-g2-rz></span>'}</div>`;
    }

    forecast(t) {
      return t.over > 1e-9 ? `<div class="dl-gantt__fc" data-g2-fc="${esc(t.id)}" style="left:${(t.e - this.S) * this.PX}px;width:${this.fcWidth(t)}px"><em>${esc(this.fcText(t))}</em></div>` : '';
    }

    // Vertical day separators (multi-day axis only), shared by header and lanes.
    seps() {
      if (!this.origin) return '';
      let out = '';
      for (let m = Math.floor(this.S / 24 + 1) * 24; m < this.E; m += 24) out += `<i class="dl-gantt__dsep" style="left:${(m - this.S) * this.PX}px"></i>`;
      return out;
    }

    headHtml() {
      const step = Math.max(1, Math.ceil(36 / this.PX));  // label every N hours so the labels never overlap
      const ticks = Array.from({length: this.E - this.S + 1}, (_, i) => { const hr = this.S + i; if (hr % step) return ''; return `<span style="left:${i * this.PX}px">${String(this.origin ? hr % 24 : hr).padStart(2, '0')}:00</span>`; }).join('');
      let days = '';
      if (this.origin) for (let d = Math.floor(this.S / 24); d * 24 < this.E; d++) {
        const a = Math.max(this.S, d * 24), b = Math.min(this.E, (d + 1) * 24);
        days += `<em class="dl-gantt__day" style="left:${(a - this.S) * this.PX}px;width:${(b - a) * this.PX}px">${esc(this.dayName(d, true))}</em>`;
      }
      return `<div class="dl-gantt__row dl-gantt__head ${this.origin ? 'is-days' : ''}"><div class="dl-gantt__res">${esc(this.cfg.resourceLabel)}</div><div class="dl-gantt__lane" style="width:${this.W}px">${days}${this.seps()}${ticks}</div></div>`;
    }

    resHtml(r) {
      const load = r.load != null && r.load !== '' ? Math.max(0, Math.min(100, +r.load)) : null;
      return `<div class="dl-gantt__res"><b>${esc(r.label)}</b>${r.bottleneck ? `<span class="dl-gantt__bn">${esc(T('gt_bottleneck'))}</span>` : ''}`
        + (load != null ? `<span class="dl-gantt__load" title="${esc(T('gt_load'))} ${load}%"><span class="dl-gantt__bar2 ${load >= 90 ? 'is-bad' : load >= 75 ? 'is-warn' : ''}"><i style="width:${load}%"></i></span><small>${load}%</small></span>` : '') + '</div>';
    }

    laneExtras(r) {
      let out = this.seps();
      if (this.LK != null && this.LK > this.S) out += `<div class="dl-gantt__lock" style="width:${(Math.min(this.LK, this.E) - this.S) * this.PX}px"></div>`;
      (r.down || []).forEach(d => {
        const a = Math.max(this.S, this.h(d.from)), b = Math.min(this.E, this.h(d.to)); if (b <= a) return;
        out += `<div class="dl-gantt__down" style="left:${(a - this.S) * this.PX}px;width:${(b - a) * this.PX}px" title="${esc(d.label || '')}"><span>${esc(d.label || '')}</span></div>`;
      });
      return out;
    }

    // Linked mode: the time axis is fitted to every bar of the machines on show, so no bar sits outside the board and short operations stay readable.
    fit() {
      const keys = ((this.L.machines || {})[this.fProc] || []).filter(k => !this.fRes || k === this.fRes);
      const ts = this.tasks.filter(t => keys.includes(t.row)); if (!ts.length) return;
      const lo = Math.min(...ts.map(t => t.s)), hi = Math.max(...ts.map(t => t.e));
      this.S = Math.max(0, Math.floor((lo - 2) / 6) * 6); this.E = Math.max(this.S + 24, Math.ceil((hi + 4) / 6) * 6);
      const avail = Math.max(600, (this.board.clientWidth || 1100) - 190);
      this.PX = Math.max(14, Math.min(60, avail / (this.E - this.S))); this.W = (this.E - this.S) * this.PX;
    }

    render() {
      if (this.L && !this.pending) this.fit();
      const vis = this.L ? this.rows.filter(r => ((this.L.machines || {})[this.fProc] || []).includes(r.key) && (!this.fRes || r.key === this.fRes))
        : this.rows.filter(r => (!this.fRes || r.key === this.fRes) && (!this.fProc || this.rowTasks(r.key).some(t => t.process === this.fProc)));
      let html = this.headHtml();
      let sec = null;
      vis.forEach(r => {
        if (r.section && r.section !== sec) { sec = r.section; html += `<div class="dl-gantt__sec">${esc(sec)}</div>`; }
        const list = this.rowTasks(r.key).filter(t => this.L || !this.fProc || t.process === this.fProc);   // linked: the whole schedule of the machine
        html += `<div class="dl-gantt__row" data-g2-row="${esc(r.key)}">${this.resHtml(r)}
          <div class="dl-gantt__lane" style="width:${this.W}px;background-size:${this.PX}px 100%">${this.laneExtras(r)}${(this.ghosts || []).filter(g => g.row === r.key).map(g => `<div class="dl-gantt__ghost" style="left:${(g.s - this.S) * this.PX}px;width:${Math.max(0.25, g.e - g.s) * this.PX}px"></div>`).join('')}${this.fProc && !this.L ? '' : this.gaps(r.key).map(([a, b]) => `<div class="dl-gantt__idle" style="left:${(a - this.S) * this.PX}px;width:${(b - a) * this.PX}px">idle</div>`).join('')}${list.map(this.forecast).join('')}${list.map(this.block).join('')}</div></div>`;
      });
      if (!vis.length) html += '<div class="dl-empty"><b class="dl-empty__title">' + esc(!this.tasks.length && this.cfg.emptyText ? this.cfg.emptyText : T('gt_no_resource')) + '</b></div>';
      let nh, nowTitle;
      if (this.cfg.nowAt != null && this.cfg.nowAt !== '') { nh = +this.cfg.nowAt; nowTitle = this.lbl(nh); } else {
        const now = new Date(new Date().toLocaleString('en-US', {timeZone: TZ()}));
        nh = now.getHours() + now.getMinutes() / 60; nowTitle = fmt.time();
        if (this.origin) nh += (Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()) - Date.UTC(this.origin[0], this.origin[1] - 1, this.origin[2])) / 3600000;
      }
      if (nh >= this.S && nh <= this.E) html += `<div class="dl-gantt__now" style="left:calc(var(--dl-gantt-res) + ${(nh - this.S) * this.PX}px)" title="${esc(T('gt_now'))} ${esc(nowTitle)}"></div>`;
      if (this.LK != null && this.LK > this.S && this.LK <= this.E) html += `<div class="dl-gantt__lockline" style="left:calc(var(--dl-gantt-res) + ${(this.LK - this.S) * this.PX}px)"><span>${esc(Tf('gt_locked_until', {t: this.lbl(this.LK)}))}</span></div>`;
      this.board.innerHTML = html;
      this.stats();
      const chips = [...(this.fRes ? [['res', 'Resource: ' + this.rows.find(r => r.key === this.fRes).label]] : []), ...(this.fProc ? [['proc', T('gt_process') + ': ' + this.catOf({process: this.fProc}).label]] : [])];
      const cb = $(this.el, '[data-g2-chips]'); cb.hidden = !chips.length || this.el.dataset.extras === '0';
      cb.innerHTML = chips.map(([k, l]) => `<span class="dl-tags__chip">${esc(l)}<button type="button" data-g2-chip="${k}">&times;</button></span>`).join('') + (chips.length ? '<button type="button" class="dl-btn dl-btn--link" data-g2-chip="all">' + esc(T('gt_reset_filters')) + '</button>' : '');
      const sb = $(this.el, '[data-g2-submit]'); if (sb) { sb.disabled = !this.changed.size || this.live(); sb.dataset.count = this.changed.size; }
      $(this.el, '[data-g2-modehelp]').textContent = this.live() ? T('gt_mode_live') : Tf('gt_mode_sim', {n: this.changed.size});
    }

    setSel(name, v) { const f = $(this.el, `[name="${name}"]`); if (f) setField(this.el, name, v); }

    task(id) {
      return this.tasks.find(t => String(t.id) === String(id));
    }

    touch(t, type) { this.changed.add(t.id); this.el.dispatchEvent(new CustomEvent('dl:gantt', {bubbles: true, detail: {type, task: {...t, start: this.hm(t.s), end: this.hm(t.e)}}})); if (this.conflict(t)) toast(Tf('gt_conflict', {task: t.label}), 'warn'); }
    closePop() { if (this.pop) { this.pop.remove(); this.pop = null; } }

    showPop(t, b) {
      this.closePop();
      this.pop = document.createElement('div'); this.pop.className = 'dl-gantt__pop';
      const extra = (+t.setup ? `<dt>${esc(T('gt_setup'))}</dt><dd>${+t.setup} min</dd>` : '') + (t.over > 1e-9 ? `<dt>${esc(T('gt_forecast'))}</dt><dd>${esc(this.lbl(t.e + t.over))} (${esc(this.fcText(t))})</dd>` : '');
      this.pop.innerHTML = `<button type="button" class="dl-gantt__popx" aria-label="${esc(T('close'))}">&times;</button><h3>${esc(t.label)}</h3><dl><dt>Resource</dt><dd>${esc(this.rows.find(r => r.key === t.row)?.label || '')}</dd><dt>${esc(T('gt_process'))}</dt><dd>${esc(this.catOf(t).label)}</dd><dt>${esc(T('gt_time'))}</dt><dd>${esc(this.lbl(t.s))}–${esc(this.lbl(t.e))}</dd>${extra}<dt>Progress</dt><dd>${+t.progress || 0}%</dd><dt>Status</dt><dd>${esc(t.status || {hot: 'Hot Job', qchold: 'QC Hold', breakdown: 'Breakdown'}[t.state] || T('gt_scheduled'))}</dd></dl>`;
      const r = b.getBoundingClientRect(); this.pop.style.cssText = `left:${Math.min(r.left + scrollX, innerWidth - 260)}px;top:${r.bottom + scrollY + 6}px`;
      this.pop.querySelector('.dl-gantt__popx').addEventListener('click', this.closePop);
      document.body.appendChild(this.pop);
    }
  }
  Devlib.define("Gantt", Gantt);
})(window.Devlib);
